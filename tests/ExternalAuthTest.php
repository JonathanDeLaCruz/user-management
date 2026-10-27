<?php
use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use webvimark\modules\UserManagement\components\{ExternalAuth, ExternalIdentityToken, AccountSecurity as Security};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;
use yii\db\Query;

class FakeExternalAuth extends ExternalAuth
{
    public string $token = '';
    public array $jwks = [];
    public array $requests = [];
    protected function request(string $url, ?array $form = null): array
    {
        $this->requests[] = [$url, $form];
        return $form === null ? $this->jwks : ['id_token' => $this->token];
    }
}

final class ExternalAuthTest extends TestCase
{
    private $tx;
    private $module;
    private array $settings;
    private $identity;
    private $key;
    private FakeExternalAuth $client;
    private User $user;

    protected function setUp(): void
    {
        $this->tx = Yii::$app->db->beginTransaction();
        $this->identity = Yii::$app->user->identity;
        $this->module = UM::instance();
        $this->settings = [$this->module->externalAuth, $this->module->mfaPolicy, $this->module->useEmailAsLogin, $this->module->maxAttempts];
        $this->module->mfaPolicy = 'optional'; $this->module->maxAttempts = 100;
        foreach (['google', 'microsoft'] as $provider) $this->module->externalAuth[$provider] = [
            'enabled' => true, 'clientId' => 'test-' . $provider, 'clientSecret' => 'secret-not-for-logs',
            'redirectUri' => 'http://localhost/user-management/external-auth/callback?provider=' . $provider,
        ];
        Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll();
        $this->key = openssl_pkey_new(['config' => __DIR__ . '/fixtures/openssl.cnf', 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        self::assertNotFalse($this->key);
        $public = openssl_pkey_get_details($this->key)['rsa'];
        $b64 = fn($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $this->client = new FakeExternalAuth();
        $this->client->jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig', 'n' => $b64($public['n']), 'e' => $b64($public['e'])]]];
        Yii::$app->cache->delete(['um-oidc-jwks', 'https://www.googleapis.com/oauth2/v3/certs']);
        Yii::$app->cache->delete(['um-oidc-jwks', 'https://login.microsoftonline.com/common/discovery/v2.0/keys']);
        $this->user = new User(['scenario' => 'newUser', 'username' => 'external-test', 'password' => 'A local password phrase', 'repeat_password' => 'A local password phrase']);
        self::assertTrue($this->user->save());
    }

    protected function tearDown(): void
    {
        [$this->module->externalAuth, $this->module->mfaPolicy, $this->module->useEmailAsLogin, $this->module->maxAttempts] = $this->settings;
        Yii::$app->user->setIdentity($this->identity); Yii::$app->session->removeAll();
        $this->tx->rollBack(); Yii::$app->authManager->invalidateCache();
    }

    private function claims(string $provider = 'google', array $replace = []): array
    {
        $flow = Yii::$app->session->get('um_external_flow', []);
        $claims = ['iss' => 'https://accounts.google.com', 'sub' => 'subject-ABC', 'aud' => 'test-' . $provider,
            'nonce' => $flow['nonce'] ?? 'test-nonce', 'iat' => time(), 'exp' => time() + 300, 'auth_time' => time(),
            'email' => 'new-google@example.test', 'email_verified' => true];
        if ($provider === 'microsoft') $claims = array_replace($claims, ['tid' => '11111111-2222-3333-4444-555555555555',
            'iss' => 'https://login.microsoftonline.com/11111111-2222-3333-4444-555555555555/v2.0', 'ver' => '2.0']);
        return array_replace($claims, $replace);
    }

    private function finishCallback(string $provider = 'google', array $replace = []): string
    {
        $flow = Yii::$app->session->get('um_external_flow');
        $this->client->token = JWT::encode($this->claims($provider, $replace), $this->key, 'RS256', 'test-key');
        return $this->client->callback($provider, ['state' => $flow['state'], 'code' => 'code-not-for-logs']);
    }

    private function linked(string $provider = 'google'): void
    {
        Yii::$app->user->setIdentity($this->user); Security::markRecent($this->user, 'password');
        $this->client->start($provider, 'link'); self::assertSame('security', $this->finishCallback($provider));
    }

    private function rejects(callable $call): void
    {
        try { $call(); self::fail('The untrusted authentication attempt was accepted.'); }
        catch (yii\web\HttpException|UnexpectedValueException $e) { self::assertTrue(true); }
    }

    public function testProvidersAreOffByDefaultAndDisabledCallbacksDoNotCallTheProvider(): void
    {
        $defaults = (new ReflectionClass(UM::class))->getDefaultProperties()['externalAuth'];
        self::assertFalse($defaults['google']['enabled']); self::assertFalse($defaults['microsoft']['enabled']);
        $this->client->start('google', 'login');
        $this->module->externalAuth = $defaults;
        $this->rejects(fn() => $this->finishCallback());
        $this->rejects(fn() => $this->client->start('google', 'login'));
        self::assertSame([], $this->client->requests);
        self::assertSame([], ExternalAuth::enabled());
        $markup = Yii::$app->view->renderFile(dirname(__DIR__) . '/views/auth/_externalButtons.php');
        self::assertStringNotContainsString('<form', $markup);
    }

    public function testLinkAndLoginUseSignedIdentityNotEmailAndPreserveMfa(): void
    {
        $this->linked(); self::assertCount(1, ExternalAuth::links($this->user));
        Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll();
        $url = $this->client->start('google', 'login'); parse_str(parse_url($url, PHP_URL_QUERY), $params);
        self::assertSame('S256', $params['code_challenge_method']);
        self::assertSame('openid profile email', $params['scope']);
        self::assertSame('dashboard', $this->finishCallback()); self::assertSame($this->user->id, Yii::$app->user->id);
        self::assertFalse(Yii::$app->user->isGuest);
        self::assertSame(Yii::$app->session->get('um_external_flow'), null);
        Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll();
        $this->module->mfaPolicy = 'admins'; $this->user->superadmin = 1; $this->user->save(false);
        $this->client->start('google', 'login'); self::assertSame('mfa', $this->finishCallback());
        self::assertTrue(Yii::$app->user->isGuest); self::assertSame($this->user->id, Security::pending()->id);
        self::assertFalse(Yii::$app->user->completeExternalLogin($this->user, 'google'));
        $audit = json_encode((new Query())->from('{{%um_audit_log}}')->all());
        foreach (['secret-not-for-logs', 'code-not-for-logs', $this->client->token] as $secret) self::assertStringNotContainsString($secret, $audit);
    }

    public function testStateExpiryConfigChangesAndReplayAreRejected(): void
    {
        $this->client->start('google', 'login');
        $this->rejects(fn() => $this->client->callback('google', ['state' => 'wrong', 'code' => 'code']));
        self::assertSame([], $this->client->requests);
        $flow = Yii::$app->session->get('um_external_flow'); $flow['expires'] = time() - 1; Yii::$app->session->set('um_external_flow', $flow);
        $this->rejects(fn() => $this->finishCallback()); self::assertSame([], $this->client->requests);
        $this->client->start('google', 'login'); $this->module->externalAuth['google']['clientSecret'] = 'rotated';
        $this->rejects(fn() => $this->finishCallback()); self::assertSame([], $this->client->requests);
        $this->linked(); $flow = ['state' => 'old', 'code' => 'code'];
        $this->rejects(fn() => $this->client->callback('google', $flow));
    }

    public function testTokenSignatureNonceIssuerAudienceExpiryAndTenantAreValidated(): void
    {
        foreach (['google', 'microsoft'] as $provider) {
            $config = ExternalAuth::config($provider); $claims = $this->claims($provider);
            $jwt = fn($data) => JWT::encode($data, $this->key, 'RS256', 'test-key');
            self::assertSame('subject-ABC', ExternalIdentityToken::validate($jwt($claims), $this->client->jwks, $provider, $config, 'test-nonce')['sub']);
            foreach ([['nonce' => 'wrong'], ['iss' => 'https://attacker.test'], ['aud' => 'another-app'], ['exp' => time() - 1],
                ['iat' => time() + 300], ['sub' => ''], ['azp' => 'attacker'], ['aud' => [$config['clientId'], 'other']]] as $change) {
                $this->rejects(fn() => ExternalIdentityToken::validate($jwt(array_replace($claims, $change)), $this->client->jwks, $provider, $config, 'test-nonce'));
            }
            $badKey = openssl_pkey_new(['config' => __DIR__ . '/fixtures/openssl.cnf', 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
            $this->rejects(fn() => ExternalIdentityToken::validate(JWT::encode($claims, $badKey, 'RS256', 'test-key'), $this->client->jwks, $provider, $config, 'test-nonce'));
        }
        $config = ExternalAuth::config('microsoft'); $config['tenant'] = 'consumers';
        $this->rejects(fn() => ExternalIdentityToken::validate($jwt($this->claims('microsoft')), $this->client->jwks, 'microsoft', $config, 'test-nonce'));
        $config['tenant'] = 'common'; $jwks = $this->client->jwks; $jwks['keys'][0]['issuer'] = 'https://attacker.test';
        $this->rejects(fn() => ExternalIdentityToken::validate($jwt($this->claims('microsoft')), $jwks, 'microsoft', $config, 'test-nonce'));
    }

    public function testRegistrationIsOptInAndDoesNotMergeMatchingEmails(): void
    {
        $this->client->start('google', 'login'); $this->rejects(fn() => $this->finishCallback());
        $this->module->externalAuth['google']['allowRegistration'] = true;
        $this->user->email = 'new-google@example.test'; $this->user->save(false);
        $this->client->start('google', 'login'); $this->rejects(fn() => $this->finishCallback());
        self::assertSame([], ExternalAuth::links($this->user));
        $this->client->start('google', 'login'); self::assertSame('dashboard', $this->finishCallback('google', ['email' => 'different@example.test']));
        $created = Yii::$app->user->identity;
        self::assertNotSame($this->user->id, $created->id); self::assertSame(0, (int)$created->superadmin);
        self::assertSame('different@example.test', $created->email); self::assertSame(1, (int)$created->email_confirmed);
        self::assertSame([], Yii::$app->authManager->getRolesByUser($created->id));
    }

    public function testUnlinkRequiresRecentProofAndLeavesAnEnabledWayToSignIn(): void
    {
        $this->linked(); $this->rejects(fn() => $this->client->unlink($this->user, 'google', ''));
        $this->linked('microsoft');
        $this->module->externalAuth['microsoft']['enabled'] = false;
        $this->rejects(fn() => $this->client->unlink($this->user, 'google', ''));
        $this->module->externalAuth['microsoft']['enabled'] = true;
        $this->client->unlink($this->user, 'google', ''); self::assertCount(1, ExternalAuth::links($this->user));
        Yii::$app->session->remove('um_security_verified');
        $this->rejects(fn() => $this->client->unlink($this->user, 'microsoft', 'A local password phrase'));
        Security::markRecent($this->user, 'password');
        $this->client->unlink($this->user, 'microsoft', 'A local password phrase'); self::assertSame([], ExternalAuth::links($this->user));
    }

    public function testReauthenticationMustUseTheAlreadyLinkedIdentity(): void
    {
        $this->linked(); Yii::$app->session->remove('um_security_verified');
        $this->client->start('google', 'reauth'); $this->rejects(fn() => $this->finishCallback('google', ['sub' => 'another-user']));
        $this->client->start('google', 'reauth'); $this->rejects(fn() => $this->finishCallback('google', ['auth_time' => time() - 600]));
        $this->client->start('google', 'reauth'); self::assertSame('security', $this->finishCallback());
        self::assertTrue(ExternalAuth::proof($this->user)); self::assertTrue(Security::recent($this->user));
    }

    public function testExternalReauthenticationStillRequiresTheLocalSecondFactor(): void
    {
        $this->linked();
        $previousKey = $this->module->mfaEncryptionKey;
        $previousController = Yii::$app->controller;
        try {
            $this->module->mfaEncryptionKey = base64_encode(random_bytes(32));
            $otp = Security::totpSetup($this->user); self::assertTrue(Security::confirmTotp($this->user, $otp->now()));
            Yii::$app->session->remove('um_security_verified');
            $this->client->start('google', 'reauth'); self::assertSame('security', $this->finishCallback());
            self::assertTrue(ExternalAuth::proof($this->user)); self::assertFalse(Security::recent($this->user));
            $controller = new webvimark\modules\UserManagement\controllers\SecurityController('security', $this->module);
            Yii::$app->controller = $controller;
            Yii::$app->request->setBodyParams([]);
            $this->rejects(fn() => $controller->actionReauthenticate());
            Yii::$app->request->setBodyParams(['code' => $otp->at(time() + 30)]);
            $controller->actionReauthenticate(); self::assertTrue(Security::recent($this->user));
            self::assertFalse(ExternalAuth::proof($this->user));
        } finally {
            Yii::$app->request->setBodyParams([]); Yii::$app->controller = $previousController;
            $this->module->mfaEncryptionKey = $previousKey;
        }
    }
}

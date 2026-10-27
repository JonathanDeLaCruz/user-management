<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\components\{AccountSecurity as Security, Passkeys, SecuritySessions};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\forms\LoginForm;
use yii\db\Query;

class AccountSecurityTest extends TestCase
{
    private $tx;
    private $module;
    private $settings;
    private $user;
    private $privateKey;
    private $credentialId;

    protected function setUp(): void
    {
        $this->tx = Yii::$app->db->beginTransaction();
        Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll();
        $this->module = Yii::$app->getModule('user-management');
        $this->settings = [$this->module->mfaPolicy, $this->module->webauthnOrigin, $this->module->mfaEncryptionKey, $this->module->maxAttempts];
        $this->module->mfaPolicy = 'optional'; $this->module->webauthnOrigin = 'https://example.test';
        $this->module->mfaEncryptionKey = base64_encode(random_bytes(32)); $this->module->maxAttempts = 100;
        $this->user = new User(['scenario' => 'newUser', 'username' => 'security-test', 'password' => 'A security test phrase', 'repeat_password' => 'A security test phrase']);
        self::assertTrue($this->user->save());
        $this->privateKey = openssl_pkey_new(['config' => __DIR__ . '/fixtures/openssl.cnf', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(32);
    }

    protected function tearDown(): void
    {
        [$this->module->mfaPolicy, $this->module->webauthnOrigin, $this->module->mfaEncryptionKey, $this->module->maxAttempts] = $this->settings;
        Yii::$app->user->setIdentity(null); Yii::$app->user->enableSession = false; Yii::$app->session->removeAll();
        $this->tx->rollBack();
    }

    private function b64(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }

    private function enroll(int $flags = 69, ?string $origin = null): void
    {
        Security::begin($this->user);
        $options = Passkeys::options($this->user, 'register');
        $ec = openssl_pkey_get_details($this->privateKey)['ec'];
        $encoder = new CBOR\Encoder();
        $key = $encoder->encode([1 => 2, 3 => -7, -1 => 1, -2 => CBOR\ByteStringObject::create(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)), -3 => CBOR\ByteStringObject::create(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT))]);
        $authData = hash('sha256', 'example.test', true) . chr($flags) . pack('N', 0) . str_repeat("\0", 16)
            . pack('n', strlen($this->credentialId)) . $this->credentialId . $key;
        $attestation = $encoder->encode(['fmt' => 'none', 'attStmt' => new CBOR\MapObject(), 'authData' => CBOR\ByteStringObject::create($authData)]);
        $client = json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge'], 'origin' => $origin ?? 'https://example.test']);
        Passkeys::verify($this->user, 'register', ['id' => $this->b64($this->credentialId), 'rawId' => $this->b64($this->credentialId), 'type' => 'public-key',
            'response' => ['clientDataJSON' => $this->b64($client), 'attestationObject' => $this->b64($attestation), 'transports' => ['internal']]], 'Test device');
    }

    private function assertion(array $changes = []): array
    {
        $options = Passkeys::options($this->user, 'login');
        $client = json_encode(['type' => 'webauthn.get', 'challenge' => $changes['challenge'] ?? $options['challenge'],
            'origin' => $changes['origin'] ?? 'https://example.test', 'crossOrigin' => $changes['crossOrigin'] ?? false]);
        $auth = hash('sha256', $changes['rp'] ?? 'example.test', true) . chr($changes['flags'] ?? 5) . pack('N', $changes['counter'] ?? 1);
        openssl_sign($auth . hash('sha256', $client, true), $signature, $changes['privateKey'] ?? $this->privateKey, OPENSSL_ALGO_SHA256);
        return ['id' => $this->b64($this->credentialId), 'rawId' => $this->b64($this->credentialId), 'type' => 'public-key', 'response' => [
            'clientDataJSON' => $this->b64($client), 'authenticatorData' => $this->b64($auth), 'signature' => $this->b64($signature),
            'userHandle' => $changes['userHandle'] ?? $this->b64(hex2bin(Security::profile($this->user)['user_handle'])),
        ]];
    }

    private function reject(callable $operation): void
    {
        $rejected = false;
        try { $operation(); } catch (Throwable $e) { $rejected = true; }
        self::assertTrue($rejected, 'Unsafe authentication was accepted.');
    }

    public function testRealPasskeyEnrollmentAndSignedAssertion(): void
    {
        $this->enroll();
        self::assertCount(1, Security::passkeys($this->user));
        $data = $this->assertion();
        Passkeys::verify($this->user, 'login', $data);
        self::assertTrue(Security::recent($this->user));
        self::assertSame(1, (int)Security::passkeys($this->user)[0]['version']);
        $this->reject(fn() => Passkeys::verify($this->user, 'login', $data));
    }

    public function testOriginChallengeRpSignatureUserAndVerificationAreEnforced(): void
    {
        $this->enroll();
        $otherKey = openssl_pkey_new(['config' => __DIR__ . '/fixtures/openssl.cnf', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        foreach ([['origin' => 'https://evil.example.test'], ['origin' => 'https://example.test.evil.test'],
            ['challenge' => $this->b64(random_bytes(32))], ['rp' => 'evil.test'], ['flags' => 1],
            ['crossOrigin' => true], ['privateKey' => $otherKey], ['userHandle' => $this->b64(random_bytes(32))]] as $change) {
            $data = $this->assertion($change);
            $this->reject(fn() => Passkeys::verify($this->user, 'login', $data));
        }
    }

    public function testRegistrationRequiresUserVerificationAndExactOrigin(): void
    {
        $this->reject(fn() => $this->enroll(65));
        $this->reject(fn() => $this->enroll(69, 'https://fake.example.test'));
        self::assertSame([], Security::passkeys($this->user));
    }

    public function testCounterAndChallengeExpiryAreEnforced(): void
    {
        $this->enroll(); Passkeys::verify($this->user, 'login', $this->assertion());
        $data = $this->assertion(['counter' => 1]);
        $this->reject(fn() => Passkeys::verify($this->user, 'login', $data));
        $data = $this->assertion(['counter' => 2]);
        $state = Yii::$app->session->get('um_ceremony'); $state['expires'] = time() - 1; Yii::$app->session->set('um_ceremony', $state);
        $this->reject(fn() => Passkeys::verify($this->user, 'login', $data));
    }

    public function testAdminPasswordCannotCreateSessionAndCannotDowngrade(): void
    {
        $this->module->mfaPolicy = 'admins'; $this->user->superadmin = 1; $this->user->save(false);
        $form = new LoginForm(['username' => $this->user->username, 'password' => 'A security test phrase']);
        self::assertFalse($form->login()); self::assertTrue($form->requiresMfa); self::assertTrue(Yii::$app->user->isGuest);
        self::assertSame($this->user->id, Security::pending()->id);
        self::assertFalse(Yii::$app->user->login($this->user));
        self::assertFalse(Security::verifyTotp($this->user, '123456'));
        self::assertFalse(Security::recover($this->user, str_repeat('a', 32)));
    }

    public function testTotpIsEncryptedBoundToUserAndCannotBeReplayed(): void
    {
        Security::markRecent($this->user, 'password');
        $otp = Security::totpSetup($this->user);
        $state = Yii::$app->session->get('um_totp_setup');
        self::assertStringNotContainsString($otp->getSecret(), $state['secret']);
        $code = $otp->now();
        self::assertTrue(Security::confirmTotp($this->user, $code));
        self::assertFalse(Security::verifyTotp($this->user, $code));
        Yii::$app->db->createCommand()->update('{{%um_mfa}}', ['totp_step' => -1], ['user_id' => $this->user->id])->execute();
        self::assertTrue(Security::verifyTotp($this->user, $code));
        self::assertFalse(Security::verifyTotp($this->user, $code));
        $codes = Security::recoveryCodes($this->user);
        Security::begin($this->user);
        self::assertTrue(Security::recover($this->user, $codes[0]));
        self::assertFalse(Security::recover($this->user, $codes[0]));
        self::assertTrue(Yii::$app->user->isGuest);
        self::assertTrue(Security::mayEnroll($this->user));
        $stored = (new Query())->from('{{%um_recovery_code}}')->column();
        self::assertNotContains(str_replace('-', '', $codes[1]), $stored);
    }

    public function testSessionsAreRevokedExpiredAndInvalidatedByPasswordChanges(): void
    {
        Yii::$app->user->enableSession = true;
        SecuritySessions::start($this->user, 'password'); self::assertTrue(SecuritySessions::valid($this->user));
        SecuritySessions::revoke($this->user->id, SecuritySessions::currentId()); self::assertFalse(SecuritySessions::valid($this->user));
        SecuritySessions::start($this->user, 'password');
        $this->user->generateAuthKey(); self::assertFalse(SecuritySessions::valid($this->user));
        SecuritySessions::start($this->user, 'password');
        Yii::$app->db->createCommand()->update('{{%um_session}}', ['expires_at' => time() - 1], ['id' => SecuritySessions::currentId()])->execute();
        self::assertFalse(SecuritySessions::valid($this->user));
    }

    public function testInheritedAdministrativeRolesRequireMfaAndPendingStateExpires(): void
    {
        $this->module->mfaPolicy = 'admins';
        $manager = Yii::$app->authManager;
        $parent = $manager->createRole('SecuritySupervisor');
        $manager->add($parent); $manager->addChild($parent, $manager->getRole('Admin'));
        $manager->assign($parent, $this->user->id);
        self::assertTrue(Security::policyRequires($this->user));
        Security::begin($this->user);
        $state = Yii::$app->session->get('um_pending_login'); $state['expires'] = time() - 1;
        Yii::$app->session->set('um_pending_login', $state);
        self::assertNull(Security::pending());
        Security::begin($this->user); $this->user->generateAuthKey(); $this->user->save(false);
        self::assertNull(Security::pending());
    }

    public function testAuditDoesNotStoreAuthenticationSecrets(): void
    {
        $this->user->password = 'A changed confidential password';
        $this->user->save(false);
        $rows = (new Query())->from('{{%um_audit_log}}')->all();
        $text = json_encode($rows);
        self::assertStringNotContainsString($this->user->password, $text);
        self::assertStringNotContainsString($this->user->password_hash, $text);
        self::assertStringNotContainsString($this->user->auth_key, $text);
        self::assertNotEmpty(array_filter($rows, fn($row) => $row['event'] === 'security.Password changed'));
    }

    public function testFactorManagementRequiresRecentIdentityAndPreservesLastRequiredPasskey(): void
    {
        $this->enroll();
        Yii::$app->user->setIdentity($this->user);
        $controller = new \webvimark\modules\UserManagement\controllers\SecurityController('security', $this->module);
        Yii::$app->session->remove('um_security_verified');
        $this->reject(fn() => $controller->actionRecoveryCodes());
        $this->reject(fn() => $controller->actionRemoveFactor());
        Security::markRecent($this->user, 'passkey');
        $this->module->mfaPolicy = 'all';
        Yii::$app->request->setBodyParams(['kind' => 'passkey', 'id' => Security::passkeys($this->user)[0]['id']]);
        $this->reject(fn() => $controller->actionRemoveFactor());
        self::assertCount(1, Security::passkeys($this->user));
        Yii::$app->request->setBodyParams([]);
    }
}

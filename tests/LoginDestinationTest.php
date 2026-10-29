<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\UserManagementModule as UM;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\components\{AccountSecurity as Security, ExternalAuth};
use webvimark\modules\UserManagement\controllers\{AuthController, SecurityController, ExternalAuthController};

final class DestinationExternalClient extends ExternalAuth
{
    public string $outcome = 'dashboard';
    public function callback(string $provider, array $query): string { return $this->outcome; }
}
final class DestinationExternalController extends ExternalAuthController
{
    public DestinationExternalClient $client;
    protected function authClient(): ExternalAuth { return $this->client; }
}

final class LoginDestinationTest extends TestCase
{
    private $tx;
    private $module;
    private $settings;
    private $identity;
    private $controller;
    private $manager;
    private $method;
    private $body;
    private $user;
    private $sessionValues;
    private $query;

    protected function setUp(): void
    {
        $this->tx = Yii::$app->db->beginTransaction();
        $this->module = UM::instance();
        $this->settings = [$this->module->afterLoginRoute, $this->module->respectReturnUrl, $this->module->mfaPolicy,
            $this->module->mfaEncryptionKey, $this->module->requirePhishingResistant];
        $this->identity = Yii::$app->user->identity;
        $this->controller = Yii::$app->controller;
        $this->manager = Yii::$app->urlManager;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->body = Yii::$app->request->bodyParams;
        $this->query = Yii::$app->request->queryParams;
        $this->sessionValues = Yii::$app->session->get(Yii::$app->user->returnUrlParam);
        Yii::$app->session->removeAll();
        $this->module->mfaPolicy = 'optional';
        $this->module->mfaEncryptionKey = base64_encode(random_bytes(32));
        $this->module->requirePhishingResistant = false;
        $this->user = new User(['username' => 'destination-test', 'status' => 1, 'superadmin' => 1,
            'password_hash' => Yii::$app->security->generatePasswordHash('Destination test password!')]);
        $this->user->save(false);
        Yii::$app->user->setIdentity($this->user);
        Yii::$app->controller = new AuthController('auth', $this->module);
        Yii::$app->response->headers->removeAll();
    }

    protected function tearDown(): void
    {
        $this->tx->rollBack();
        [$this->module->afterLoginRoute, $this->module->respectReturnUrl, $this->module->mfaPolicy,
            $this->module->mfaEncryptionKey, $this->module->requirePhishingResistant] = $this->settings;
        Yii::$app->user->setIdentity($this->identity);
        Yii::$app->controller = $this->controller;
        Yii::$app->set('urlManager', $this->manager);
        if ($this->method === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD'] = $this->method;
        Yii::$app->request->setBodyParams($this->body);
        Yii::$app->request->setQueryParams($this->query);
        Yii::$app->session->removeAll();
        if ($this->sessionValues !== null) Yii::$app->user->setReturnUrl($this->sessionValues);
        Yii::$app->response->headers->removeAll();
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = yii\web\Response::FORMAT_HTML;
    }

    private function configured(bool $respect): void
    {
        $this->module->afterLoginRoute = ['/site/index', 'category' => 1];
        $this->module->respectReturnUrl = $respect;
    }

    public function testDefaultsPreserveEachExistingLoginFlow(): void
    {
        Yii::$app->user->setReturnUrl('/user-management/user/index?sort=username');
        self::assertSame('/user-management/user/index?sort=username', $this->module->resolveAfterLoginUrl(true));
        self::assertSame('/user-management/dashboard/index', $this->module->resolveAfterLoginUrl());
        Yii::$app->user->setReturnUrl(Yii::$app->homeUrl);
        self::assertSame('/user-management/dashboard/index', $this->module->resolveAfterLoginUrl(true));
    }

    public function testExplicitPolicyIgnoresOrRespectsOnlyStoredLocalReturnUrls(): void
    {
        $this->configured(false);
        foreach (['/user-management/security/audit', '/user-management/security/index', '/user-management/user/index'] as $return) {
            Yii::$app->user->setReturnUrl($return);
            self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl(true));
            self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl());
        }
        $this->configured(true);
        Yii::$app->user->setReturnUrl('/user-management/user/index?UserSearch[username]=Jane%20Doe&sort=username');
        self::assertSame('/user-management/user/index?UserSearch[username]=Jane%20Doe&sort=username', $this->module->resolveAfterLoginUrl());
        Yii::$app->user->setReturnUrl(['/user-management/role/index', 'sort' => '-description']);
        self::assertSame('/user-management/role/index?sort=-description', $this->module->resolveAfterLoginUrl());
        Yii::$app->session->remove(Yii::$app->user->returnUrlParam);
        self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl());
        Yii::$app->request->setQueryParams(['returnUrl' => '//evil.test', 'redirect' => '/user-management/security/index']);
        self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl());
        Yii::$app->request->setQueryParams([]);
    }

    public function testUnsafeReturnUrlsAndAuthenticationLoopsFallBack(): void
    {
        $this->configured(true);
        foreach (['https://evil.test', '//evil.test', '/\\evil.test', '/%5cevil.test', '/%2f%2fevil.test',
            '/%252f%252fevil.test', "\r\nLocation: https://evil.test", '/path%0d%0aLocation:evil',
            '/user-management/auth/login', '/user-management/auth/login?returnUrl=/site/index',
            '/user-management/security/challenge', '/user-management/security/passkey-finish',
            '/user-management/security/totp-confirm', '/user-management/external-auth/callback?provider=google',
            '/user-management/auth/logout', '/other/../user-management/auth/login', '/%75ser-management/auth/login',
            'relative/path', [], 42, false] as $return) {
            Yii::$app->user->setReturnUrl($return);
            self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl(), json_encode($return));
        }
        Yii::$app->set('urlManager', new yii\web\UrlManager(['enablePrettyUrl' => true, 'showScriptName' => false,
            'rules' => ['sign-in' => 'user-management/auth/login', 'verify-identity' => 'user-management/security/challenge']]));
        foreach (['/sign-in', '/verify-identity'] as $return) {
            Yii::$app->user->setReturnUrl($return);
            self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl());
        }
        Yii::$app->set('urlManager', new yii\web\UrlManager(['enablePrettyUrl' => false, 'showScriptName' => true]));
        Yii::$app->user->setReturnUrl('/index.php?r=user-management%2Fauth%2Flogin');
        self::assertSame('/index.php?r=site%2Findex&category=1', $this->module->resolveAfterLoginUrl());
        Yii::$app->user->setReturnUrl('/index.php?r=user-management%2Fuser%2Findex&page=2');
        self::assertSame('/index.php?r=user-management%2Fuser%2Findex&page=2', $this->module->resolveAfterLoginUrl());
    }

    public function testInvalidConfiguredDestinationFailsInsteadOfRedirectingExternally(): void
    {
        foreach ([['https://evil.test'], ['//evil.test'], ['/user-management/auth/login'], ['/user-management/security/challenge'], ['/user-management/external-auth/start']] as $route) {
            $this->module->afterLoginRoute = $route;
            try { $this->module->resolveAfterLoginUrl(); self::fail('Unsafe destination accepted.'); }
            catch (yii\base\InvalidConfigException $error) { self::assertNotEmpty($error->getMessage()); }
        }
    }

    public function testLocalReturnsWorkWhenTheApplicationIsInstalledInASubdirectory(): void
    {
        $this->configured(true);
        $original = Yii::$app->request;
        $request = new yii\web\Request(['scriptFile' => $original->scriptFile, 'scriptUrl' => '/portal/index.php', 'baseUrl' => '/portal',
            'hostInfo' => 'http://localhost', 'url' => '/portal/user-management/auth/login', 'cookieValidationKey' => 'fake-key']);
        Yii::$app->set('request', $request);
        Yii::$app->set('urlManager', new yii\web\UrlManager(['enablePrettyUrl' => true, 'showScriptName' => false]));
        try {
            Yii::$app->user->setReturnUrl('/portal/user-management/user/index?page=2');
            self::assertSame('/portal/user-management/user/index?page=2', $this->module->resolveAfterLoginUrl());
            Yii::$app->user->setReturnUrl('/portal/user-management/security/challenge');
            self::assertSame('/portal/site/index?category=1', $this->module->resolveAfterLoginUrl());
            Yii::$app->set('urlManager', new yii\web\UrlManager(['enablePrettyUrl' => false, 'showScriptName' => true]));
            Yii::$app->user->setReturnUrl('/portal/index.php?r=user-management%2Fauth%2Flogin');
            self::assertSame('/portal/index.php?r=site%2Findex&category=1', $this->module->resolveAfterLoginUrl());
        } finally { Yii::$app->set('request', $original); }
    }

    public function testDefaultRoutesCannotTurnAReturnIntoALoginLoop(): void
    {
        $this->configured(true);
        $appDefault = Yii::$app->defaultRoute;
        $moduleDefault = $this->module->defaultRoute;
        try {
            Yii::$app->defaultRoute = 'user-management/auth/login';
            Yii::$app->user->setReturnUrl('/');
            self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl());
            $this->module->defaultRoute = 'security/challenge';
            Yii::$app->user->setReturnUrl('/user-management');
            self::assertSame('/site/index?category=1', $this->module->resolveAfterLoginUrl());
        } finally { Yii::$app->defaultRoute = $appDefault; $this->module->defaultRoute = $moduleDefault; }
    }

    public function testPasswordLoginAndAlreadyAuthenticatedLoginUseTheConfiguredDestination(): void
    {
        $this->configured(false);
        Yii::$app->user->setReturnUrl('/user-management/security/audit');
        self::assertSame('http://localhost/site/index?category=1', Yii::$app->controller->actionLogin()->headers->get('Location'));
        Yii::$app->user->setIdentity(null);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yii::$app->request->setBodyParams(['LoginForm' => ['username' => $this->user->username, 'password' => 'Destination test password!']]);
        self::assertSame('http://localhost/site/index?category=1', Yii::$app->controller->actionLogin()->headers->get('Location'));
        self::assertFalse(Yii::$app->user->isGuest);
    }

    public function testTotpAndRecoveryStayPendingUntilVerificationCompletes(): void
    {
        $this->configured(false);
        Security::markRecent($this->user, 'password');
        $otp = Security::totpSetup($this->user);
        self::assertTrue(Security::confirmTotp($this->user, $otp->now()));
        Security::markRecent($this->user, 'totp');
        $codes = Security::recoveryCodes($this->user);
        Yii::$app->user->setIdentity(null);
        Security::begin($this->user);
        try { $this->module->resolveAfterLoginUrl(); self::fail('Pending MFA was treated as completed login.'); }
        catch (yii\web\ForbiddenHttpException $error) { self::assertTrue(Yii::$app->user->isGuest); }
        $controller = new SecurityController('security', $this->module);
        Yii::$app->controller = $controller;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yii::$app->request->setBodyParams(['method' => 'totp', 'code' => $otp->at(time() + 30)]);
        self::assertSame('http://localhost/site/index?category=1', $controller->actionChallenge()->headers->get('Location'));
        self::assertFalse(Yii::$app->user->isGuest);
        Yii::$app->user->setIdentity(null);
        Security::begin($this->user);
        self::assertTrue(Security::recover($this->user, $codes[0]));
        self::assertTrue(Yii::$app->user->isGuest);
        $replacement = Security::totpSetup($this->user);
        Yii::$app->request->setBodyParams(['code' => $replacement->now()]);
        self::assertSame('http://localhost/site/index?category=1', $controller->actionTotpConfirm()->headers->get('Location'));
        self::assertFalse(Yii::$app->user->isGuest);
    }

    public function testExternalCallbackKeepsLinkAndChallengeDestinationsSeparate(): void
    {
        $this->configured(false);
        $controller = new DestinationExternalController('external-auth', $this->module);
        $controller->client = new DestinationExternalClient();
        self::assertSame('http://localhost/site/index?category=1', $controller->actionCallback('google')->headers->get('Location'));
        $this->configured(true);
        Yii::$app->user->setReturnUrl('/user-management/role/index');
        self::assertSame('http://localhost/user-management/role/index', $controller->actionCallback('microsoft')->headers->get('Location'));
        $controller->client->outcome = 'security';
        self::assertSame('http://localhost/user-management/security/index', $controller->actionCallback('google')->headers->get('Location'));
        Yii::$app->user->setIdentity(null);
        $controller->client->outcome = 'mfa';
        self::assertSame('http://localhost/user-management/security/challenge', $controller->actionCallback('google')->headers->get('Location'));
        self::assertTrue(Yii::$app->user->isGuest);
    }

    public function testMfaRespectsReturnUrlAndReauthenticationStillReturnsToSecurity(): void
    {
        $this->configured(true);
        Security::markRecent($this->user, 'password');
        $otp = Security::totpSetup($this->user);
        self::assertTrue(Security::confirmTotp($this->user, $otp->now()));
        Yii::$app->user->setIdentity(null);
        Security::begin($this->user);
        Yii::$app->user->setReturnUrl('/user-management/user/index?sort=-username');
        $controller = new SecurityController('security', $this->module);
        Yii::$app->controller = $controller;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Yii::$app->request->setBodyParams(['method' => 'totp', 'code' => $otp->at(time() + 30)]);
        self::assertSame('http://localhost/user-management/user/index?sort=-username', $controller->actionChallenge()->headers->get('Location'));
        self::assertFalse(Yii::$app->user->isGuest);
        // Reauthentication without a second factor remains at its own destination.
        Yii::$app->db->createCommand()->delete('{{%um_mfa}}', ['user_id' => $this->user->id])->execute();
        $this->configured(false);
        Yii::$app->request->setBodyParams(['password' => 'Destination test password!', 'code' => '']);
        self::assertSame('http://localhost/user-management/security/index', $controller->actionReauthenticate()->headers->get('Location'));
    }
}

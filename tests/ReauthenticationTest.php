<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\components\AccountSecurity as Security;
use webvimark\modules\UserManagement\controllers\SecurityController;
use webvimark\modules\UserManagement\models\User;

final class ReauthenticationTest extends TestCase
{
    private $tx;
    private $module;
    private $settings;
    private $identity;
    private $controller;
    private $user;
    private $otp;

    protected function setUp(): void
    {
        $this->tx = Yii::$app->db->beginTransaction();
        $this->identity = Yii::$app->user->identity; $this->controller = Yii::$app->controller;
        $this->module = Yii::$app->getModule('user-management');
        $this->settings = [$this->module->mfaPolicy, $this->module->requirePhishingResistant, $this->module->mfaEncryptionKey, $this->module->maxAttempts];
        $this->module->mfaPolicy = 'optional'; $this->module->requirePhishingResistant = false;
        $this->module->mfaEncryptionKey = base64_encode(random_bytes(32)); $this->module->maxAttempts = 100;
        Yii::$app->session->removeAll();
        $this->user = new User(['scenario' => 'newUser', 'username' => 'reauth-test', 'password' => 'A reauthentication phrase', 'repeat_password' => 'A reauthentication phrase']);
        self::assertTrue($this->user->save()); Yii::$app->user->setIdentity($this->user);
        Security::markRecent($this->user, 'password');
        $this->otp = Security::totpSetup($this->user); self::assertTrue(Security::confirmTotp($this->user, $this->otp->now()));
        Yii::$app->session->remove('um_security_verified');
    }

    protected function tearDown(): void
    {
        [$this->module->mfaPolicy, $this->module->requirePhishingResistant, $this->module->mfaEncryptionKey, $this->module->maxAttempts] = $this->settings;
        Yii::$app->controller = $this->controller; Yii::$app->user->setIdentity($this->identity);
        Yii::$app->request->setBodyParams([]); Yii::$app->session->removeAll(); $this->tx->rollBack();
    }

    private function attempt(array $data)
    {
        $controller = new SecurityController('security', $this->module);
        $controller->action = new yii\base\Action('reauthenticate', $controller);
        $controller->setView(new yii\web\View()); Yii::$app->controller = $controller;
        Yii::$app->request->setBodyParams($data);
        return $controller->actionReauthenticate();
    }

    public function testInvalidValuesRenderFieldErrorsWithoutGrantingVerification(): void
    {
        foreach ([[], ['password' => 'wrong phrase', 'code' => 'Samsung'], ['password' => ['invalid'], 'code' => '123456'],
            ['password' => 'A reauthentication phrase', 'code' => ['invalid']]] as $data) {
            $html = $this->attempt($data);
            self::assertIsString($html); self::assertStringContainsString('alert alert-danger', $html);
            self::assertStringContainsString('is-invalid', $html); self::assertFalse(Security::recent($this->user));
            self::assertStringNotContainsString('value="Samsung"', $html);
            self::assertStringNotContainsString('value="A reauthentication phrase"', $html);
        }
        try { Yii::$app->controller->actionRecoveryCodes(); self::fail('A failed form must not unlock sensitive operations.'); }
        catch (yii\web\ForbiddenHttpException $e) { self::assertTrue(true); }
    }

    public function testOnlyCorrectPasswordAndUnusedCodeGrantVerification(): void
    {
        $code = $this->otp->at(time() + 30);
        $html = $this->attempt(['password' => 'wrong phrase', 'code' => $code]);
        self::assertStringContainsString('La contraseña no es correcta.', $html); self::assertFalse(Security::recent($this->user));
        $response = $this->attempt(['password' => 'A reauthentication phrase', 'code' => $code]);
        self::assertInstanceOf(yii\web\Response::class, $response); self::assertTrue(Security::recent($this->user));
        Yii::$app->session->remove('um_security_verified');
        $html = $this->attempt(['password' => 'A reauthentication phrase', 'code' => $code]);
        self::assertStringContainsString('caducó o ya se utilizó', $html); self::assertFalse(Security::recent($this->user));
    }

    public function testRateLimitIsShownInTheFormAndDoesNotUnlockTheAccount(): void
    {
        $this->module->maxAttempts = 1;
        $this->attempt(['password' => 'wrong phrase', 'code' => $this->otp->at(time() + 30)]);
        $html = $this->attempt(['password' => 'A reauthentication phrase', 'code' => $this->otp->at(time() + 30)]);
        self::assertIsString($html); self::assertStringContainsString('Demasiados intentos', $html);
        self::assertFalse(Security::recent($this->user));
    }
}

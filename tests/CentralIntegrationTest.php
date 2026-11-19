<?php
use PHPUnit\Framework\TestCase;
use yii\base\Event;
use yii\db\Query;
use webvimark\modules\UserManagement\components\{AccountSecurity, ExternalAuth, Passkeys, SecuritySessions, SessionsRevokedEvent};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\forms\{LoginForm, ChangeOwnPasswordForm, PasswordRecoveryForm};
use webvimark\modules\UserManagement\UserManagementModule as UM;

final class CentralIntegrationTest extends TestCase
{
    private array $users = [];
    private array $events = [];
    private array $observations = [];
    private $listener;
    private array $settings;

    protected function setUp(): void
    {
        $m = UM::instance();
        $this->settings = [$m->localAuthentication, $m->mfaPolicy, $m->requirePhishingResistant, $m->externalAuth];
        $m->localAuthentication = true; $m->mfaPolicy = 'optional'; $m->requirePhishingResistant = false;
        Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll();
        Yii::$app->user->enableSession = true;
        $this->listener = function (SessionsRevokedEvent $e): void {
            $this->events[] = [$e->userId, $e->sessionIds, $e->reason];
            $this->observations[] = [Yii::$app->db->getTransaction() === null,
                array_map(fn($id) => SecuritySessions::isActive($e->userId, $id), $e->sessionIds)];
        };
        Event::on(SecuritySessions::class, SecuritySessions::EVENT_SESSIONS_REVOKED, $this->listener);
    }

    protected function tearDown(): void
    {
        Event::off(SecuritySessions::class, SecuritySessions::EVENT_SESSIONS_REVOKED, $this->listener);
        [$local, $policy, $strict, $external] = $this->settings;
        $m = UM::instance(); $m->localAuthentication = $local; $m->mfaPolicy = $policy;
        $m->requirePhishingResistant = $strict; $m->externalAuth = $external;
        Yii::$app->user->setIdentity(null); Yii::$app->user->enableSession = false; Yii::$app->session->removeAll();
        foreach ($this->users as $id) {
            foreach (['um_session', 'um_mfa', 'um_passkey', 'um_recovery_code', 'um_external_identity', 'um_session_revocation'] as $table)
                Yii::$app->db->createCommand()->delete('{{%' . $table . '}}', ['user_id' => $id])->execute();
            Yii::$app->db->createCommand()->delete($m->auth_assignment_table, ['user_id' => $id])->execute();
            Yii::$app->db->createCommand()->delete('{{%um_audit_log}}', ['or', ['target' => (string)$id], ['actor_id' => $id]])->execute();
            Yii::$app->db->createCommand()->delete(User::tableName(), ['id' => $id])->execute();
        }
        Yii::$app->authManager->invalidateCache();
        foreach ($this->observations as [$committed, $active]) {
            self::assertTrue($committed, 'Notify only after the outer transaction commits.');
            self::assertNotContains(true, $active, 'A notified session must already be closed.');
        }
    }

    private function account(bool $external = false): User
    {
        $name = 'central-test-' . bin2hex(random_bytes(5));
        $u = $external ? User::createExternal($name, $name . '@example.test') : new User([
            'username' => $name, 'status' => 1, 'password_hash' => Yii::$app->security->generatePasswordHash('Central test phrase!')]);
        if (!$external) self::assertTrue($u->save());
        $this->users[] = (int)$u->id;
        $u->refresh();
        return $u;
    }

    private function session(User $u, string $method = 'password'): string
    {
        SecuritySessions::start($u, $method);
        $id = SecuritySessions::currentId();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $id);
        return $id;
    }

    private function rejected(callable $action): void
    {
        try { $action(); self::fail('Local credential operation was accepted.'); }
        catch (yii\base\InvalidArgumentException|yii\web\NotFoundHttpException $e) { self::assertTrue(true); }
    }

    public function testSessionStatusChecksOwnershipExpiryAccountAndCredentialChanges(): void
    {
        $u = $this->account(); $other = $this->account();
        $id = $this->session($u);
        self::assertTrue(SecuritySessions::isActive((int)$u->id, $id));
        foreach (['', 'invalid', str_repeat('A', 64), str_repeat('a', 63), $id . "\n"] as $invalid)
            self::assertFalse(SecuritySessions::isActive((int)$u->id, $invalid));
        self::assertFalse(SecuritySessions::isActive((int)$other->id, $id));
        self::assertFalse(SecuritySessions::isActive(0, $id));
        foreach (['expires_at' => time() - 1, 'last_seen_at' => time() - UM::instance()->sessionIdleTimeout] as $field => $value) {
            Yii::$app->db->createCommand()->update('{{%um_session}}', [$field => $value], ['id' => $id])->execute();
            self::assertFalse(SecuritySessions::isActive((int)$u->id, $id));
            Yii::$app->db->createCommand()->update('{{%um_session}}', [$field => time() + 100], ['id' => $id])->execute();
        }
        $original = $u->password_hash;
        // Even an integration which forgot to rotate auth_key cannot retain a session after a hash change.
        Yii::$app->db->createCommand()->update(User::tableName(), ['password_hash' => '!changed'], ['id' => $u->id])->execute();
        self::assertFalse(SecuritySessions::isActive((int)$u->id, $id));
        Yii::$app->db->createCommand()->update(User::tableName(), ['password_hash' => $original, 'auth_key' => 'changed'], ['id' => $u->id])->execute();
        self::assertFalse(SecuritySessions::isActive((int)$u->id, $id));
        $u->refresh(); $id = $this->session($u);
        $u->status = 0; self::assertTrue($u->save(false));
        self::assertFalse(SecuritySessions::isActive((int)$u->id, $id));
    }

    public function testRevocationsNotifyOnceWithEveryReasonAndNoSecretFields(): void
    {
        foreach (['logout', 'revoked', 'revoked-others', 'password-changed', 'account-disabled', 'account-deleted', 'mfa-reset'] as $reason) {
            $u = $this->account(); $first = $this->session($u); $second = $this->session($u);
            $tx = Yii::$app->db->beginTransaction();
            SecuritySessions::revoke((int)$u->id, null, false, $reason);
            $count = count($this->events);
            self::assertFalse(SecuritySessions::isActive((int)$u->id, $first));
            $tx->commit();
            self::assertCount($count + 1, $this->events);
            [$userId, $ids, $actual] = $this->events[$count];
            self::assertSame((int)$u->id, $userId); self::assertSame($reason, $actual);
            self::assertEqualsCanonicalizing([$first, $second], $ids);
            $json = json_encode($this->events[$count]);
            foreach ([$u->auth_key, $u->password_hash, Yii::$app->session->get('um_session_token')] as $secret)
                self::assertStringNotContainsString($secret, $json);
            SecuritySessions::revoke((int)$u->id);
            self::assertCount($count + 1, $this->events, 'No duplicate event for sessions already closed.');
        }
    }

    public function testRollbackAndSavepointRollbackNeverNotifyAndListenerFailureCannotUndoRevocation(): void
    {
        $u = $this->account(); $id = $this->session($u);
        $tx = Yii::$app->db->beginTransaction(); SecuritySessions::revoke((int)$u->id); $tx->rollBack();
        self::assertSame([], $this->events); self::assertTrue(SecuritySessions::isActive((int)$u->id, $id));
        $outer = Yii::$app->db->beginTransaction(); $inner = Yii::$app->db->beginTransaction();
        SecuritySessions::revoke((int)$u->id); $inner->rollBack(); $outer->commit();
        self::assertSame([], $this->events); self::assertTrue(SecuritySessions::isActive((int)$u->id, $id));
        $failing = static function (): void { throw new RuntimeException('Listener deliberately failed'); };
        Event::on(SecuritySessions::class, SecuritySessions::EVENT_SESSIONS_REVOKED, $failing);
        try { SecuritySessions::revoke((int)$u->id); }
        finally { Event::off(SecuritySessions::class, SecuritySessions::EVENT_SESSIONS_REVOKED, $failing); }
        self::assertFalse(SecuritySessions::isActive((int)$u->id, $id)); self::assertCount(1, $this->events);
    }

    public function testAccountOperationsEmitTheirActualReasonAfterCommit(): void
    {
        foreach (['password-changed', 'account-disabled', 'account-deleted', 'logout', 'revoked-others', 'mfa-reset'] as $reason) {
            $u = $this->account(); $id = $this->session($u); $count = count($this->events);
            if ($reason === 'password-changed') { $u->password = 'Another central test phrase!'; self::assertTrue($u->save(false)); }
            elseif ($reason === 'account-disabled') { $u->status = 0; self::assertTrue($u->save(false)); }
            elseif ($reason === 'account-deleted') self::assertSame(1, $u->delete());
            elseif ($reason === 'logout') { Yii::$app->user->setIdentity($u); self::assertTrue(Yii::$app->user->logout(false)); }
            elseif ($reason === 'revoked-others') {
                $current = $this->session($u); SecuritySessions::revoke((int)$u->id, null, true);
                self::assertTrue(SecuritySessions::isActive((int)$u->id, $current));
            } else {
                $command = new webvimark\modules\UserManagement\commands\SecurityController('security', UM::instance());
                $command->confirmRecovery = true;
                self::assertSame(0, $command->actionResetMfa($u->username));
            }
            self::assertCount($count + 1, $this->events); self::assertSame($reason, $this->events[$count][2]);
            self::assertContains($id, $this->events[$count][1]);
        }
    }

    public function testCentralAccountsCannotAcquireLocalCredentialsEvenWhenLocalLoginIsEnabled(): void
    {
        $u = $this->account(true);
        self::assertTrue($u->isExternal()); self::assertSame('central', $u->auth_source);
        foreach (['', 'anything', '!'] as $password) {
            self::assertFalse($u->validatePassword($password));
            self::assertFalse((new LoginForm(['username' => $u->username, 'password' => $password]))->login());
        }
        self::assertFalse(Yii::$app->user->login($u));
        $this->rejected(fn() => $u->setPassword('Attempt to set a password'));
        $this->rejected(fn() => $u->generateConfirmationToken());
        $this->rejected(fn() => User::updateAll(['password_hash' => 'hash'], ['id' => $u->id]));
        $this->rejected(fn() => $u->updateAttributes(['auth_source' => 'local'])); $u->refresh();
        $u->password_hash = 'hash'; $this->rejected(fn() => $u->save(false)); $u->refresh();
        $u->auth_source = 'local'; $this->rejected(fn() => $u->save(false)); $u->refresh();
        $form = new ChangeOwnPasswordForm(['user' => $u, 'scenario' => 'restoreViaEmail', 'password' => 'Attempt a reset phrase']);
        self::assertFalse($form->changePassword(false));
        $recovery = new PasswordRecoveryForm(['email' => $u->email]);
        $u->email_confirmed = 1; self::assertTrue($u->save(false));
        self::assertTrue($recovery->validateEmailConfirmedAndUserActive()); self::assertTrue($recovery->sendEmail(false));
        $u->refresh(); self::assertNull($u->confirmation_token);
        $this->rejected(fn() => AccountSecurity::totpSetup($u));
        $this->rejected(fn() => Passkeys::options($u, 'register'));
        $this->rejected(fn() => Passkeys::verify($u, 'register', []));
        self::assertFalse(AccountSecurity::confirmTotp($u, '123456'));
        self::assertFalse(AccountSecurity::verifyTotp($u, '123456'));
        self::assertFalse(AccountSecurity::recover($u, str_repeat('a', 32)));
        Yii::$app->user->setIdentity($u);
        UM::instance()->externalAuth['google'] = ['enabled' => true, 'clientId' => 'test', 'clientSecret' => 'test', 'redirectUri' => 'https://example.test/callback'];
        $this->rejected(fn() => (new ExternalAuth())->start('google', 'link'));
        self::assertTrue(User::assignRole($u->id, 'Admin'));
        $u->status = 0; self::assertTrue($u->save(false)); self::assertNull(User::findIdentity($u->id));
    }

    public function testChildModeRecordsTrustedApplicationSessionsWithoutLocalMfaAndKeepsCentralDefaults(): void
    {
        self::assertTrue((new ReflectionClass(UM::class))->getDefaultProperties()['localAuthentication']);
        $u = $this->account(true); $u->superadmin = 1; self::assertTrue($u->save(false));
        UM::instance()->mfaPolicy = 'all'; UM::instance()->requirePhishingResistant = true;
        UM::instance()->localAuthentication = false;
        self::assertFalse(AccountSecurity::requiresMfa($u)); self::assertSame([], ExternalAuth::enabled());
        self::assertTrue(Yii::$app->user->login($u));
        $id = SecuritySessions::currentId(); self::assertTrue(SecuritySessions::isActive((int)$u->id, $id));
        self::assertTrue(SecuritySessions::valid($u)); self::assertTrue(AccountSecurity::recent($u));
        self::assertSame('central', (new Query())->select('method')->from('{{%um_session}}')->where(['id' => $id])->scalar());
        $controller = new webvimark\modules\UserManagement\controllers\SecurityController('security', UM::instance());
        $previous = Yii::$app->controller; Yii::$app->controller = $controller;
        try { $html = $controller->actionIndex(); } finally { Yii::$app->controller = $previous; }
        self::assertStringNotContainsString('totp-setup', $html); self::assertStringNotContainsString('passkey-options', $html);
        self::assertStringNotContainsString('reauthenticate', $html); self::assertStringContainsString('revoke-session', $html);
        SecuritySessions::revoke((int)$u->id, $id); self::assertFalse(SecuritySessions::isActive((int)$u->id, $id));
        UM::instance()->localAuthentication = true;
        Yii::$app->user->setIdentity(null);
        $local = $this->account(); $local->superadmin = 1; self::assertTrue($local->save(false));
        self::assertTrue(AccountSecurity::requiresMfa($local)); self::assertFalse(Yii::$app->user->login($local));
    }

    public function testModuleGuardDeniesEveryAuthenticationFamilyIncludingFutureActions(): void
    {
        UM::instance()->localAuthentication = false;
        foreach (['auth' => webvimark\modules\UserManagement\controllers\AuthController::class,
            'security' => webvimark\modules\UserManagement\controllers\SecurityController::class,
            'external-auth' => webvimark\modules\UserManagement\controllers\ExternalAuthController::class] as $id => $class) {
            $controller = new $class($id, UM::instance());
            $allowed = $id === 'auth' ? ['logout'] : ($id === 'security' ? ['index', 'audit', 'revoke-session'] : []);
            $actions = array_unique(array_merge(array_keys($controller->actions()), ['future-login-method']));
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (str_starts_with($method->name, 'action') && $method->name !== 'actions')
                    $actions[] = yii\helpers\Inflector::camel2id(substr($method->name, 6));
            }
            foreach ($actions as $action) {
                $instance = new yii\base\InlineAction($action, $controller, 'actionIndex');
                if (in_array($action, $allowed, true)) self::assertTrue(UM::instance()->beforeAction($instance));
                else $this->rejected(fn() => UM::instance()->beforeAction($instance));
            }
        }
        $u = $this->account(true); $u->superadmin = 1; $u->save(false); Yii::$app->user->setIdentity($u);
        foreach (['user', 'role', 'permission', 'auth-item-group'] as $id) {
            [$controller] = UM::instance()->createController($id . '/index');
            self::assertTrue(UM::instance()->beforeAction($controller->createAction('index')));
        }
        $newController = new yii\web\Controller('future-auth', UM::instance());
        $this->rejected(fn() => UM::instance()->beforeAction(new yii\base\Action('login', $newController)));
    }

    public function testLoginRequiredStoresOnlyRelativeReturnUrlWithoutTrustingHost(): void
    {
        $request = Yii::$app->request;
        $url = $request->url; $host = $request->hostInfo;
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        try {
            $request->setHostInfo('https://attacker.example');
            foreach (['/user-management/role/index?sort=name' => '/user-management/role/index?sort=name',
                '//attacker.example/path' => null, '/user-management/auth/login' => null] as $input => $expected) {
                $request->setUrl($input);
                Yii::$app->user->loginRequired(false, false);
                self::assertSame($expected, Yii::$app->session->get(Yii::$app->user->returnUrlParam));
            }
        } finally {
            $request->setUrl($url); $request->setHostInfo($host);
            if ($method === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD'] = $method;
            Yii::$app->response->headers->removeAll(); Yii::$app->response->statusCode = 200;
        }
    }
}

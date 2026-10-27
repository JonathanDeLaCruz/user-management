<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\forms\LoginForm;
use webvimark\modules\UserManagement\models\forms\ChangeOwnPasswordForm;
use webvimark\modules\UserManagement\components\AuthHelper;

class ModuleTest extends TestCase
{
    private $transaction;
    protected function setUp(): void { $this->transaction=Yii::$app->db->beginTransaction(); Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll(); Yii::$app->authManager->invalidateCache(); }
    protected function tearDown(): void { $this->transaction->rollBack(); Yii::$app->user->setIdentity(null); }
    private function user($name='tester'): User
    {
        $user=new User(['scenario'=>'newUser','username'=>$name,'password'=>'A secure phrase 123','repeat_password'=>'A secure phrase 123']);
        self::assertTrue($user->save(),json_encode($user->errors)); return $user;
    }
    public function testWildcardDoesNotAuthorizeOtherRoutes(): void
    {
        foreach (['/admin/users/index','/admin/users/nested/view'] as $route) self::assertTrue(Route::isRouteAllowed($route,['/admin/users/*']));
        foreach (['/users/admin/index','/admin/admin/index','/admin/users2/index','/admin/index','/other/index'] as $route) self::assertFalse(Route::isRouteAllowed($route,['/admin/users/*']));
        self::assertTrue(Route::isRouteAllowed('/anything/index',['/*']));
    }
    public function testMigrationDoesNotCreateDefaultAdmin(): void { self::assertNull(User::findByUsername('superadmin')); }
    public function testLoginAndPasswordValidation(): void
    {
        $user=$this->user(); $form=new LoginForm(['username'=>$user->username,'password'=>'wrong']); self::assertFalse($form->validate());
        $form=new LoginForm(['username'=>$user->username,'password'=>'A secure phrase 123']); self::assertTrue($form->validate());
    }
    public function testShortPasswordRejectedAndWhitespacePreserved(): void
    {
        $user=new User(['scenario'=>'newUser','username'=>'short','password'=>'short','repeat_password'=>'short']); self::assertFalse($user->save());
        $user=$this->user(); $user->password='  twelve characters  '; $user->repeat_password=$user->password; self::assertTrue($user->save());
        self::assertTrue($user->validatePassword('  twelve characters  ')); self::assertFalse($user->validatePassword('twelve characters'));
    }
    public function testResetTokenIsHashedPurposeBoundAndSingleUse(): void
    {
        $user=$this->user(); $oldKey=$user->auth_key; $user->generateConfirmationToken('password-reset'); $token=$user->confirmationToken; self::assertTrue($user->save(false));
        self::assertNotSame($token,$user->confirmation_token); self::assertNull(User::findByConfirmationToken($token,'email')); self::assertNull(User::findByConfirmationToken('invalid'));
        $first=User::findByConfirmationToken($token); $second=User::findByConfirmationToken($token);
        self::assertNotNull($first); self::assertNotNull($second);
        $form=new ChangeOwnPasswordForm(['scenario'=>'restoreViaEmail','user'=>$first,'password'=>'Another secure phrase','repeat_password'=>'Another secure phrase']); self::assertTrue($form->changePassword());
        $replay=new ChangeOwnPasswordForm(['scenario'=>'restoreViaEmail','user'=>$second,'password'=>'Replay secure phrase','repeat_password'=>'Replay secure phrase']); self::assertFalse($replay->changePassword());
        self::assertNull(User::findByConfirmationToken($token)); $first->refresh(); self::assertNotSame($oldKey,$first->auth_key); self::assertTrue($first->validatePassword('Another secure phrase'));
    }
    public function testEmailTokenCannotResetPassword(): void
    {
        $user=$this->user(); $user->generateConfirmationToken('email'); self::assertTrue($user->save(false)); self::assertNull(User::findByConfirmationToken($user->confirmationToken)); self::assertNotNull(User::findByConfirmationToken($user->confirmationToken,'email'));
    }
    public function testInactiveSuperadminCannotRestoreIdentity(): void
    {
        $user=$this->user(); $user->superadmin=1; $user->status=User::STATUS_BANNED; self::assertTrue($user->save(false)); self::assertNull(User::findIdentity($user->id)); Yii::$app->user->setIdentity($user); self::assertFalse(Yii::$app->user->isSuperadmin);
    }
    public function testRateLimitSurvivesNewSession(): void
    {
        $m=Yii::$app->getModule('user-management'); for($i=0;$i<5;$i++) self::assertTrue($m->checkAttempts('target','test')); Yii::$app->session->removeAll(); self::assertFalse($m->checkAttempts('target','test')); self::assertTrue($m->checkAttempts('target','other-purpose'));
    }
    public function testPermissionRevocationAndVersionRefresh(): void
    {
        $user=$this->user(); $role=Role::create('Review'); $permission=Permission::create('reviewUsers'); Route::create('/review/*'); Role::addChildren($role->name,[$permission->name],true); Permission::addChildren($permission->name,['/review/*'],true); self::assertTrue(User::assignRole($user->id,'Review')); Yii::$app->user->setIdentity($user); AuthHelper::updatePermissions($user); self::assertTrue(User::hasPermission('reviewUsers')); self::assertTrue(User::canRoute('/review/index'));
        $version=AuthHelper::getPermissionsVersion(); self::assertTrue(User::revokeRole($user->id,'Review')); self::assertNotSame($version,AuthHelper::getPermissionsVersion()); self::assertFalse(User::hasPermission('reviewUsers')); self::assertFalse(User::canRoute('/review/index'));
    }
    public function testRoleCyclesAndPermissionToRoleEdgesAreRejected(): void
    {
        Role::create('Parent'); Role::create('Child'); Permission::create('Permission'); Role::addChildren('Parent',['Child'],true);
        try { Role::addChildren('Child',['Parent'],true); self::fail('Cycle was accepted'); } catch (yii\base\InvalidCallException $e) { self::assertStringContainsString('loop',$e->getMessage()); }
        $this->expectException(yii\base\InvalidArgumentException::class); Permission::addChildren('Permission',['Parent'],true);
    }
    public function testRememberMeKeyCannotBeUsedAsApiToken(): void { $this->expectException(yii\base\NotSupportedException::class); User::findIdentityByAccessToken('anything'); }
    public function testEmailRegistrationAndFailedMailAreTransactional(): void
    {
        $module=Yii::$app->getModule('user-management'); $module->useEmailAsLogin=true; $module->emailConfirmationRequired=true;
        $form=new class extends \webvimark\modules\UserManagement\models\forms\RegistrationForm {
            protected function sendConfirmationEmail($user) { return false; }
        };
        $form->username='unconfirmed@example.test'; $form->password='A secure phrase 123';
        try { self::assertFalse($form->registerUser(false)); self::assertFalse(User::find()->where(['username'=>$form->username])->exists()); }
        finally { $module->useEmailAsLogin=false; $module->emailConfirmationRequired=false; }
    }
}

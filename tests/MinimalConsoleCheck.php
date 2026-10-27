<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@webvimark/modules/UserManagement', dirname(__DIR__));
$app = new yii\console\Application([
    'id' => 'minimal-migration-regression',
    'basePath' => __DIR__ . '/fixtures/app',
    'components' => ['db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:', 'tablePrefix' => 'app_']],
]);
if ($app->hasModule('user-management') || $app->has('authManager') || isset($app->params['adminEmail'])) {
    throw new RuntimeException('The regression fixture is not minimal.');
}
$args = ['migrationPath' => dirname(__DIR__) . '/migrations', 'interactive' => false];
if ($app->runAction('migrate/up', $args) !== 0) throw new RuntimeException('Minimal-console migration failed.');
if (!$app->db->getTableSchema('{{%um_user}}', true)) throw new RuntimeException('User table missing.');
if ($app->authManager->itemTable !== '{{%um_auth_item}}') throw new RuntimeException('RBAC table mismatch.');
if (!(new yii\db\Query())->from('{{%um_auth_item_child}}')->where(['parent'=>'Admin','child'=>'viewUsers'])->exists()) throw new RuntimeException('RBAC seed missing.');
if ($app->runAction('migrate/up', $args) !== 0) throw new RuntimeException('Repeat migration failed.');
echo "OK: minimal console without module, authManager, cache or adminEmail; all migrations and repeat passed.\n";

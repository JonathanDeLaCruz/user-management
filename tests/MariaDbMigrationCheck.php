<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', __DIR__ . '/fixtures/app');
Yii::setAlias('@webvimark/modules/UserManagement', dirname(__DIR__));
$connectionConfig = ['class'=>yii\db\Connection::class, 'dsn'=>getenv('UM_TEST_DB_DSN'), 'username'=>getenv('UM_TEST_DB_USER'), 'password'=>getenv('UM_TEST_DB_PASSWORD')];
$admin = Yii::createObject($connectionConfig);
$database = 'um_migrationcheck_' . bin2hex(random_bytes(6));
$created = false;
try {
    $admin->createCommand('CREATE DATABASE ' . $admin->quoteTableName($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')->execute();
    $created = true;
    $dsn = preg_replace('/dbname=[^;]+/', 'dbname=' . $database, $connectionConfig['dsn']);
    if ($dsn === $connectionConfig['dsn']) throw new RuntimeException('No se pudo aislar el DSN de prueba.');
    $connectionConfig['dsn'] = $dsn;
    $connectionConfig['tablePrefix'] = 'app_';
    $config = require __DIR__ . '/fixtures/app/config/console.php';
    $config['components']['db'] = $connectionConfig;
    new yii\console\Application($config);
    $files = glob(dirname(__DIR__) . '/migrations/m*.php');
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        require $file;
        $class = basename($file, '.php');
        ob_start();
        try { $result = (new $class(['db'=>Yii::$app->db]))->up(); if ($result === false) throw new RuntimeException('Migración rechazada: '.$class); }
        finally { ob_end_clean(); }
        echo 'OK ', $class, PHP_EOL;
    }
    $state = Yii::$app->db->createCommand('SELECT value FROM {{%um_state}} WHERE name=:name', [':name'=>'permissions'])->queryScalar();
    Yii::$app->db->createCommand()->dropTable('{{%um_attempt}}')->execute();
    Yii::$app->db->schema->refresh();
    ob_start();
    try { (new m140601_000000_create_security_state(['db'=>Yii::$app->db]))->up(); }
    finally { ob_end_clean(); }
    $after = Yii::$app->db->createCommand('SELECT value FROM {{%um_state}} WHERE name=:name', [':name'=>'permissions'])->queryScalar();
    if ($state !== $after || !Yii::$app->db->schema->getTableSchema('{{%um_attempt}}',true)) throw new RuntimeException('La recuperación parcial falló.');
    echo 'OK recuperación con um_state existente', PHP_EOL;
    $statistics = webvimark\modules\UserManagement\components\VisitLogStatistics::daily();
    if ($statistics['total'] !== 0 || count($statistics['series']) !== 30) throw new RuntimeException('Estadísticas incorrectas.');
    echo 'OK estadísticas en MariaDB', PHP_EOL;
    $module = Yii::$app->getModule('user-management');
    $token = str_repeat('a', 64) . '_' . time();
    Yii::$app->db->createCommand()->insert($module->user_table, [
        'id'=>990, 'username'=>'migration-preserved', 'password_hash'=>'existing-hash',
        'auth_key'=>'existing-key', 'created_at'=>1, 'updated_at'=>1, 'confirmation_token'=>$token,
    ])->execute();
    Yii::$app->db->createCommand()->insert($module->auth_assignment_table, ['item_name'=>'Admin','user_id'=>990])->execute();
    $tables = ['user', 'user_visit_log', 'auth_rule', 'auth_item', 'auth_item_group', 'auth_item_child', 'auth_assignment'];
    foreach ($tables as $table) Yii::$app->db->createCommand()->renameTable('{{%um_' . $table . '}}', '{{%' . $table . '}}')->execute();
    Yii::$app->db->schema->refresh();
    Yii::$app->db->createCommand()->createTable('{{%migration}}', ['version'=>'varchar(180) PRIMARY KEY', 'apply_time'=>'integer'])->execute();
    foreach ($files as $file) {
        $version = basename($file, '.php');
        if ($version !== 'm140531_000000_normalize_table_names') Yii::$app->db->createCommand()->insert('{{%migration}}', ['version'=>$version, 'apply_time'=>time()])->execute();
    }
    ob_start();
    try {
        $result = Yii::$app->runAction('migrate/up', ['migrationPath'=>dirname(__DIR__) . '/migrations', 'interactive'=>false]);
    } finally { $migrationOutput = ob_get_clean(); }
    if ($result !== 0) throw new RuntimeException('Actualización por historial rechazada: ' . $migrationOutput);
    $user = (new yii\db\Query())->from($module->user_table)->where(['id'=>990])->one();
    if ($user['password_hash'] !== 'existing-hash' || $user['confirmation_token'] !== $token) throw new RuntimeException('Datos modificados al renombrar.');
    if (!(new yii\db\Query())->from($module->auth_assignment_table)->where(['user_id'=>990, 'item_name'=>'Admin'])->exists()) throw new RuntimeException('Asignación perdida.');
    echo 'OK actualización real con historial anterior, renombrado y conservación de cuentas/RBAC', PHP_EOL;
    foreach ($files as $file) {
        $class = basename($file, '.php');
        ob_start();
        try { if ((new $class(['db'=>Yii::$app->db]))->up() === false) throw new RuntimeException('Repetición rechazada: '.$class); }
        finally { ob_end_clean(); }
    }
    $after = (new yii\db\Query())->from($module->user_table)->where(['id'=>990])->one();
    if ($after['confirmation_token'] !== $token) throw new RuntimeException('Token modificado al repetir.');
    echo 'OK repetición de todas las migraciones sobre tablas existentes', PHP_EOL;
} finally {
    if (Yii::$app && Yii::$app->has('db')) Yii::$app->db->close();
    if ($created && preg_match('/^um_migrationcheck_[a-f0-9]{12}$/D', $database)) {
        $admin->createCommand('DROP DATABASE ' . $admin->quoteTableName($database))->execute();
        echo 'Esquema aislado de prueba eliminado.', PHP_EOL;
    }
}

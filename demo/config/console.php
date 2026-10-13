<?php
$config = require __DIR__ . '/common.php';
unset($config['components']['user']);
$config['modules']['user-management']['controllerNamespace'] = 'webvimark\modules\UserManagement\commands';
$config['controllerMap']['migrate'] = ['class'=>yii\console\controllers\MigrateController::class,'migrationPath'=>'@webvimark/modules/UserManagement/migrations'];
$config['controllerMap']['um-user'] = 'webvimark\modules\UserManagement\commands\UserController';
return $config;

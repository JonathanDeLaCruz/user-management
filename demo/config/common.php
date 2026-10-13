<?php
use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\components\UserConfig;
return [
    'id'=>'user-management-demo', 'name'=>'Control de acceso', 'basePath'=>dirname(__DIR__),
    'language'=>'es', 'timeZone'=>'America/Mexico_City', 'vendorPath'=>dirname(__DIR__) . '/vendor',
    'runtimePath'=>getenv('UM_RUNTIME_PATH') ?: dirname(__DIR__) . '/runtime',
    'aliases'=>['@bower'=>'@vendor/bower-asset', '@npm'=>'@vendor/npm-asset'],
    'modules'=>['user-management'=>[
        'class'=>UserManagementModule::class, 'enableRegistration'=>true,
        'on beforeAction'=>function($event) { if ($event->action->controller->id === 'auth') $event->action->controller->layout = '@app/views/layouts/auth'; },
    ]],
    'components'=>[
        'db'=>['class'=>yii\db\Connection::class,'dsn'=>getenv('UM_DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/runtime/demo.sqlite','username'=>getenv('UM_DB_USER') ?: null,'password'=>getenv('UM_DB_PASSWORD') ?: null,'charset'=>'utf8mb4'],
        'cache'=>['class'=>yii\caching\FileCache::class],
        'authManager'=>['class'=>yii\rbac\DbManager::class],
        'mailer'=>['class'=>yii\symfonymailer\Mailer::class, 'useFileTransport'=>true, 'viewPath'=>'@webvimark/modules/UserManagement/views'],
        'user'=>['class'=>UserConfig::class, 'identityCookie'=>['name'=>'_um_identity','httpOnly'=>true,'sameSite'=>'Lax'],
            'on afterLogin'=>function($event) { webvimark\modules\UserManagement\models\UserVisitLog::newVisitor($event->identity->id); }],
    ],
    'params'=>['adminEmail'=>'demo@example.test'],
];

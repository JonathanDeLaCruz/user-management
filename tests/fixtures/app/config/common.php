<?php
return [
    'id' => 'module-test-application', 'name' => 'Control de acceso', 'language' => 'es',
    'basePath' => dirname(__DIR__), 'vendorPath' => dirname(__DIR__, 4) . '/vendor',
    'runtimePath' => getenv('UM_RUNTIME_PATH') ?: dirname(__DIR__) . '/runtime',
    'aliases' => ['@bower' => '@vendor/bower-asset', '@npm' => '@vendor/npm-asset'],
    'modules' => ['user-management' => [
        'class' => webvimark\modules\UserManagement\UserManagementModule::class,
        'mfaPolicy' => getenv('UM_TEST_MFA_POLICY') ?: 'optional', 'enableRegistration' => true,
        'webauthnOrigin' => getenv('UM_WEBAUTHN_ORIGIN') ?: 'https://example.test',
    ]],
    'components' => [
        'db' => ['class' => yii\db\Connection::class, 'dsn' => getenv('UM_DB_DSN') ?: 'sqlite::memory:'],
        'cache' => ['class' => yii\caching\FileCache::class],
        'authManager' => ['class' => yii\rbac\DbManager::class],
        'user' => ['class' => webvimark\modules\UserManagement\components\UserConfig::class],
        'mailer' => ['class' => yii\symfonymailer\Mailer::class, 'useFileTransport' => true, 'viewPath' => '@webvimark/modules/UserManagement/views'],
    ],
    'params' => ['adminEmail' => 'security-tests@example.test'],
];

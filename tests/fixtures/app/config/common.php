<?php
$config = [
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
if (getenv('UM_TEST_EXTERNAL_PROVIDERS') === '1') {
    foreach (['google', 'microsoft'] as $provider) $config['modules']['user-management']['externalAuth'][$provider] = [
        'enabled' => true, 'clientId' => 'test-' . $provider, 'clientSecret' => 'test-only-secret',
        'redirectUri' => (getenv('UM_WEBAUTHN_ORIGIN') ?: 'http://localhost') . '/user-management/external-auth/callback?provider=' . $provider,
    ];
}
if (getenv('UM_TEST_LOGIN_DESTINATION') === '1') {
    $config['modules']['user-management']['afterLoginRoute'] = ['/site/index', 'category' => 1];
    $config['modules']['user-management']['respectReturnUrl'] = false;
}
if (getenv('UM_TEST_EXTERNAL_LAYOUT') === '1') {
    $config['modules']['user-management']['on beforeAction'] = static function ($event) {
        if ($event->action->controller->id === 'dashboard') $event->action->controller->layout = '@app/views/layouts/external';
    };
}
return $config;

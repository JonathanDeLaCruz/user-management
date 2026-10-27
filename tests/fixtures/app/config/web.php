<?php
$config = require __DIR__ . '/common.php';
$config['components']['request'] = ['cookieValidationKey' => require $config['runtimePath'] . '/cookie-key.php'];
$config['components']['session'] = ['savePath' => '@runtime/sessions', 'cookieParams' => ['httpOnly' => true, 'sameSite' => 'Lax']];
$config['components']['urlManager'] = ['enablePrettyUrl' => true, 'showScriptName' => false];
$config['components']['errorHandler'] = ['errorAction' => 'site/error'];
$config['components']['assetManager'] = ['forceCopy' => true];
return $config;

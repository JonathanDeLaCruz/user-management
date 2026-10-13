<?php
$config = require __DIR__ . '/common.php';
$config['bootstrap'] = ['log'];
$config['components']['request'] = ['cookieValidationKey'=>require $config['runtimePath'] . '/cookie-key.php'];
$config['components']['session'] = ['savePath'=>'@runtime/sessions', 'cookieParams'=>['httpOnly'=>true, 'sameSite'=>'Lax']];
$config['components']['urlManager'] = ['enablePrettyUrl'=>true,'showScriptName'=>false,'rules'=>[]];
$config['components']['errorHandler'] = ['errorAction'=>'site/error'];
$config['components']['log'] = ['targets'=>[['class'=>yii\log\FileTarget::class,'levels'=>['error','warning']]]];
return $config;

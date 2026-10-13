<?php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'dev');
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', dirname(__DIR__));
(new yii\web\Application(require dirname(__DIR__) . '/config/web.php'))->run();

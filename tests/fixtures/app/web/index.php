<?php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'test');
require dirname(__DIR__, 4) . '/vendor/autoload.php';
require dirname(__DIR__, 4) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', dirname(__DIR__));
Yii::setAlias('@webvimark/modules/UserManagement', dirname(__DIR__, 4));
(new yii\web\Application(require dirname(__DIR__) . '/config/web.php'))->run();

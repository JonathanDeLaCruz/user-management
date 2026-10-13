<?php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', dirname(__DIR__) . '/demo');
Yii::setAlias('@webvimark/modules/UserManagement', dirname(__DIR__));
foreach (['demo/runtime','demo/web/assets'] as $directory) {
    if (!is_dir(dirname(__DIR__) . '/' . $directory)) mkdir(dirname(__DIR__) . '/' . $directory,0755,true);
}
class TestSession extends yii\web\Session
{
    private array $values = [];
    public function get($key,$defaultValue=null) { return $this->values[$key] ?? $defaultValue; }
    public function set($key,$value) { $this->values[$key]=$value; }
    public function remove($key) { $value=$this->values[$key]??null;unset($this->values[$key]);return $value; }
    public function removeAll() { $this->values=[]; }
}
$config = require dirname(__DIR__) . '/demo/config/common.php';
$config['vendorPath'] = dirname(__DIR__) . '/vendor';
$config['components']['db']['dsn'] = 'sqlite::memory:';
$config['components']['request'] = ['class'=>yii\web\Request::class,'cookieValidationKey'=>'test-key','scriptFile'=>dirname(__DIR__).'/demo/web/index.php','scriptUrl'=>'/index.php','hostInfo'=>'http://localhost','baseUrl'=>'','url'=>'/'];
$config['components']['urlManager'] = ['enablePrettyUrl'=>true,'showScriptName'=>false];
$config['components']['session'] = TestSession::class;
$config['components']['user']['enableSession'] = false;
$config['components']['user']['enableAutoLogin'] = false;
unset($config['components']['user']['on afterLogin']);
$config['components']['errorHandler']['errorAction'] = 'site/error';
new yii\web\Application($config);
ob_start();
foreach (glob(dirname(__DIR__) . '/migrations/m*.php') as $file) { require $file; $class=basename($file,'.php'); (new $class(['db'=>Yii::$app->db]))->up(); }
ob_end_clean();

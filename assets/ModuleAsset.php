<?php
namespace webvimark\modules\UserManagement\assets;
class ModuleAsset extends \yii\web\AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $css = ['module.css'];
    public $depends = ['yii\web\YiiAsset', 'yii\bootstrap5\BootstrapAsset', 'yii\bootstrap5\BootstrapPluginAsset'];
}

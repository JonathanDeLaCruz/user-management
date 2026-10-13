<?php
namespace webvimark\modules\UserManagement\assets;

class AuthAsset extends \yii\web\AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $css = ['auth.css'];
    public $depends = [ModuleAsset::class];
}

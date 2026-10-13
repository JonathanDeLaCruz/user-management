<?php
namespace webvimark\modules\UserManagement\assets;

class LayoutAsset extends \yii\web\AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $css = ['layout.css'];
    public $depends = [ModuleAsset::class];
}

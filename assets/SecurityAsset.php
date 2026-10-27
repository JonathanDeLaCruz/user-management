<?php
namespace webvimark\modules\UserManagement\assets;
class SecurityAsset extends \yii\web\AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $js = ['security.js'];
    public $depends = [ModuleAsset::class];
}

<?php
namespace webvimark\modules\UserManagement\assets;

/** Resources used by the dashboard itself, independently of the application's layout. */
class DashboardAsset extends \yii\web\AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';
    public $css = ['dashboard.css'];
    public $depends = [ModuleAsset::class];
}

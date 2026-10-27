<?php
namespace app\controllers;
class SiteController extends \webvimark\modules\UserManagement\controllers\DashboardController
{
    public $freeAccessActions = ['index', 'error'];
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticated']['only'] = ['index'];
        return $behaviors;
    }
    public function actions() { return ['error' => ['class' => \yii\web\ErrorAction::class]]; }
}

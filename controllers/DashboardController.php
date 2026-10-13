<?php
namespace webvimark\modules\UserManagement\controllers;

use webvimark\modules\UserManagement\components\BaseController;
use webvimark\modules\UserManagement\components\VisitLogStatistics;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\UserVisitLog;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\rbacDB\Permission;

class DashboardController extends BaseController
{
    public $freeAccessActions = ['index'];

    public function behaviors()
    {
        return ['authenticated' => [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ]] + parent::behaviors();
    }

    public function actionIndex()
    {
        return $this->render('index', [
            'stats' => ['Users'=>User::find()->count(), 'Roles'=>Role::find()->count(), 'Permissions'=>Permission::find()->count(), 'Visit log'=>UserVisitLog::find()->count()],
            'activity' => User::hasPermission('viewVisitLog') ? VisitLogStatistics::daily() : null,
        ]);
    }
}

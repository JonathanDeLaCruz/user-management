<?php
namespace app\controllers;
use Yii;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\UserVisitLog;
class SiteController extends \webvimark\modules\UserManagement\components\BaseController
{
    public $freeAccessActions = ['error'];
    public function actions() { return ['error'=>['class'=>\yii\web\ErrorAction::class]]; }
    public function actionIndex()
    {
        return $this->render('index', ['stats'=>['Usuarios'=>User::find()->count(),'Roles'=>Role::find()->count(),'Permisos'=>Permission::find()->count(),'Accesos'=>UserVisitLog::find()->count()], 'users'=>User::find()->orderBy(['id'=>SORT_DESC])->limit(5)->all()]);
    }
}

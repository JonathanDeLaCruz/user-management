<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use webvimark\modules\UserManagement\assets\ModuleAsset;

class BaseController extends Controller
{
    public $freeAccess = false;
    public $freeAccessActions = [];
    public $enableOnlyActions = [];

    public function behaviors()
    {
        return [
            'verbs' => ['class' => VerbFilter::class, 'actions' => array_fill_keys([
                'logout', 'delete', 'bulk-delete', 'bulk-activate', 'bulk-deactivate',
                'set-roles', 'set-child-roles', 'set-child-permissions', 'set-child-routes',
                'refresh-routes', 'grid-page-size',
            ], ['POST'])],
            'access' => ['class' => GhostAccessControl::class],
        ];
    }

    public function beforeAction($action)
    {
        if ($this->enableOnlyActions && !in_array($action->id, $this->enableOnlyActions, true)) {
            throw new \yii\web\NotFoundHttpException();
        }
        if (!parent::beforeAction($action)) return false;
        ModuleAsset::register($this->view);
        return true;
    }

    public function renderIsAjax($view, $params = [])
    {
        return Yii::$app->request->isAjax ? $this->renderAjax($view, $params) : $this->render($view, $params);
    }
}

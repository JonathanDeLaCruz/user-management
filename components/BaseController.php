<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use webvimark\modules\UserManagement\assets\ModuleAsset;

class BaseController extends Controller
{
    public $layout = '@webvimark/modules/UserManagement/views/layouts/main';
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
        $sensitive = $this->id !== 'security' && ($this->id !== 'auth' || in_array($action->id, ['change-own-password', 'confirm-email'], true));
        if (Yii::$app->request->isPost && $sensitive
            && !Yii::$app->user->isGuest && !AccountSecurity::recent(Yii::$app->user->identity)) {
            Yii::$app->session->setFlash('warning', \webvimark\modules\UserManagement\UserManagementModule::t('front', 'Verify your identity before making changes.'));
            Yii::$app->response->redirect(['/user-management/security/index']);
            return false;
        }
        ModuleAsset::register($this->view);
        return true;
    }

    public function afterAction($action, $result)
    {
        $result = parent::afterAction($action, $result);
        if (Yii::$app->request->isPost && !Yii::$app->user->isGuest && $this->id !== 'security' && $this->id !== 'auth') {
            Audit::record('action.completed', '/' . $action->uniqueId, ['id' => mb_substr((string)Yii::$app->request->get('id', ''), 0, 100)]);
        }
        return $result;
    }

    public function renderIsAjax($view, $params = [])
    {
        return Yii::$app->request->isAjax ? $this->renderAjax($view, $params) : $this->render($view, $params);
    }
}

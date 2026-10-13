<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use webvimark\modules\UserManagement\models\User;

class AdminDefaultController extends BaseController
{
    public $modelClass;
    public $modelSearchClass;

    protected function findModel($id)
    {
        $model = ($this->modelClass)::findOne($id);
        if (!$model) throw new NotFoundHttpException('Registro no encontrado.');
        if (($model instanceof User && (int)$model->superadmin === 1 && !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin)
            || ($model instanceof \webvimark\modules\UserManagement\models\UserVisitLog && $model->user?->superadmin && !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin)) {
            throw new ForbiddenHttpException();
        }
        return $model;
    }

    public function actionIndex()
    {
        $searchModel = new $this->modelSearchClass();
        return $this->renderIsAjax('index', ['searchModel' => $searchModel, 'dataProvider' => $searchModel->search(Yii::$app->request->queryParams)]);
    }

    public function actionView($id) { return $this->renderIsAjax('view', ['model' => $this->findModel($id)]); }
    public function actionCreate()
    {
        $model = new $this->modelClass();
        if ($model->load(Yii::$app->request->post()) && $model->save()) return $this->redirect($this->getRedirectPage('create', $model));
        return $this->renderIsAjax('create', compact('model'));
    }
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
		$data = Yii::$app->request->post();
		if ($model instanceof User && !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin) {
			if (!User::hasPermission('editUserEmail')) unset($data[$model->formName()]['email'], $data[$model->formName()]['email_confirmed']);
			if (!User::hasPermission('bindUserToIp')) unset($data[$model->formName()]['bind_to_ip']);
		}
		if ($model->load($data) && $model->save()) return $this->redirect($this->getRedirectPage('update', $model));
        return $this->renderIsAjax('update', compact('model'));
    }
    public function actionDelete($id)
    {
        if ($this->findModel($id)->delete() === false) throw new ForbiddenHttpException('No se puede eliminar este registro.');
        return $this->redirect(['index']);
    }
    protected function getRedirectPage($action, $model = null) { return ['view', 'id' => $model->getPrimaryKey()]; }

    public function actionGridPageSize()
    {
        $size = (int)Yii::$app->request->post('pageSize', 20);
        if (!in_array($size, [10,20,50,100], true)) throw new \yii\web\BadRequestHttpException();
        Yii::$app->response->cookies->add(new \yii\web\Cookie(['name' => '_grid_page_size', 'value' => $size, 'httpOnly' => true, 'sameSite' => 'Lax']));
        return $this->redirect(['index']);
    }

    private function bulk($operation)
    {
        $ids = Yii::$app->request->post('selection', []);
        if (!is_array($ids) || count($ids) > 100) throw new \yii\web\BadRequestHttpException();
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($ids as $id) {
                $model = $this->findModel($id);
                if ($operation === 'delete') {
                    if ($model->delete() === false) throw new ForbiddenHttpException();
                } else {
                    if (!$model instanceof User || (string)$model->id === (string)\webvimark\modules\UserManagement\models\User::component()->id) throw new ForbiddenHttpException();
                    $model->status = $operation === 'activate' ? User::STATUS_ACTIVE : User::STATUS_INACTIVE;
                    if (!$model->save()) throw new \yii\web\ServerErrorHttpException();
                }
            }
            $transaction->commit();
        } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
        return $this->redirect(['index']);
    }
    public function actionBulkDelete() { return $this->bulk('delete'); }
    public function actionBulkActivate() { return $this->bulk('activate'); }
    public function actionBulkDeactivate() { return $this->bulk('deactivate'); }
}

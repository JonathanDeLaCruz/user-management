<?php
namespace app\controllers;

/** Isolated HTTP test fixture. Never included in the Composer distribution. */
class CentralTestController extends \yii\web\Controller
{
    public function beforeAction($action)
    {
        if (getenv('UM_TEST_CHILD_MODE') !== '1') throw new \yii\web\NotFoundHttpException();
        return parent::beforeAction($action);
    }

    public function actionEntry()
    {
        if (!\Yii::$app->request->isPost) {
            return \yii\helpers\Html::beginForm(['/central-test/entry'], 'post') . \yii\helpers\Html::submitButton('Fixture login') . \yii\helpers\Html::endForm();
        }
        $user = \webvimark\modules\UserManagement\models\User::findByUsername('http-central-account');
        if (!$user || !\Yii::$app->user->login($user)) throw new \yii\web\ForbiddenHttpException();
        return $this->redirect(['/user-management/security/index']);
    }
}

<?php
namespace routefixtures\controllers;

abstract class AbstractBaseController extends \yii\web\Controller
{
    public function actionInherited() { throw new \RuntimeException('Discovery executed an action.'); }
    protected function actionPrivateRoute() { throw new \RuntimeException('Discovery executed an action.'); }
    public static function actionStaticRoute() { throw new \RuntimeException('Discovery executed an action.'); }
}

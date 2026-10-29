<?php
namespace routefixtures\controllers;

final class AdminController extends AbstractBaseController
{
    public function actionRoot() { throw new \RuntimeException('Discovery executed an action.'); }
}

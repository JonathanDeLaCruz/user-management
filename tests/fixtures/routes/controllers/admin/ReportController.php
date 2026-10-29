<?php
namespace routefixtures\controllers\admin;

final class ReportController extends \routefixtures\controllers\AbstractBaseController
{
    public function actionDownload() { throw new \RuntimeException('Discovery executed an action.'); }
}

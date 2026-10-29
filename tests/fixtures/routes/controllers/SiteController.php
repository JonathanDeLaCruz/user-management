<?php
namespace routefixtures\controllers;

use yii\mail\MailerInterface;
use yii\base\{Module, Security};

/** Same constructor dependencies as Yii Basic 2.0.55. All actions must remain unexecuted. */
final class SiteController extends AbstractBaseController
{
    public static array $dependencies = [];

    public function __construct($id, Module $module, MailerInterface $mailer, Security $security, $config = [])
    {
        self::$dependencies[] = [$id, $mailer, $security];
        parent::__construct($id, $module, $config);
    }

    public function actions() { return ['error' => \yii\web\ErrorAction::class]; }
    public function beforeAction($action) { throw new \RuntimeException('Discovery invoked beforeAction.'); }
    public function actionIndex() { throw new \RuntimeException('Discovery executed index.'); }
    public function actionContact() { throw new \RuntimeException('Discovery executed contact.'); }
}

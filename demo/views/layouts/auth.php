<?php
use yii\helpers\Html;
webvimark\modules\UserManagement\assets\ModuleAsset::register($this);
$this->registerCssFile('@web/app.css'); $this->beginPage(); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?= Html::csrfMetaTags() ?><title><?= Html::encode($this->title ?: 'Acceso · User Manager') ?></title><?php $this->head() ?></head><body class="auth-page"><?php $this->beginBody() ?><main class="auth-shell"><div class="auth-intro"><span class="brand-mark">U</span><h1>Un lugar para<br>gestionar cada acceso.</h1><p>Usuarios, roles y permisos.<br>Una interfaz clara para tu equipo.</p><span class="auth-version">Yii 2 + Bootstrap 5 · PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span></div><div class="auth-content"><?= $content ?></div></main><?php $this->endBody() ?></body></html><?php $this->endPage() ?>

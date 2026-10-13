<?php
use yii\helpers\Html;
use yii\helpers\Url;
use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\assets\ModuleAsset;
ModuleAsset::register($this);
$this->registerCssFile('@web/app.css');
$this->beginPage(); ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?= Html::csrfMetaTags() ?><title><?= Html::encode($this->title ?: Yii::$app->name) ?></title><?php $this->head() ?></head>
<body><?php $this->beginBody() ?>
<div class="app-shell">
<aside class="app-sidebar"><a class="app-brand" href="<?= Url::to(['/site/index']) ?>"><span class="brand-mark">U</span> User Manager</a><span class="sidebar-label">ADMINISTRACIÓN</span>
<nav aria-label="Principal">
<?php foreach ([['Resumen','/site/index'],['Usuarios','/user-management/user/index'],['Roles','/user-management/role/index'],['Permisos','/user-management/permission/index'],['Grupos','/user-management/auth-item-group/index'],['Historial de accesos','/user-management/user-visit-log/index']] as [$label,$route]): ?>
<?= GhostHtml::a(Html::encode($label),[$route],['class'=>'sidebar-link' . (str_starts_with('/'.Yii::$app->controller->route, substr($route,0,strrpos($route,'/'))) ? ' active' : '')]) ?>
<?php endforeach ?></nav><div class="sidebar-footer"><span class="status-dot"></span> Entorno de revisión<br><small>Yii 2 · Bootstrap 5 · PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></small></div></aside>
<div class="app-workspace"><header class="app-topbar"><span>Administración / <strong><?= Html::encode($this->title ?: 'Resumen') ?></strong></span><div class="d-flex gap-3 align-items-center"><span class="user-avatar"><?= Html::encode(mb_strtoupper(mb_substr(Yii::$app->user->username ?? 'U',0,1))) ?></span><span><?= Html::encode(Yii::$app->user->username) ?></span><?= Html::a('Salir',['/user-management/auth/logout'],['class'=>'btn btn-sm btn-outline-secondary','data-method'=>'post']) ?></div></header>
<main class="app-content"><?php foreach (Yii::$app->session->getAllFlashes() as $type=>$message): ?><div class="alert alert-<?= in_array($type,['success','warning','danger'],true) ? $type : 'info' ?>"><?= Html::encode($message) ?></div><?php endforeach ?><?= $content ?></main>
<footer class="app-footer">User Manager · Tu versión modernizada <span>Bootstrap 5.3 · PHP <?= PHP_VERSION ?></span></footer></div></div>
<?php $this->endBody() ?></body></html><?php $this->endPage() ?>

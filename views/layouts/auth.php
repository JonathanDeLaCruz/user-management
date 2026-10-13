<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule as UM;
webvimark\modules\UserManagement\assets\AuthAsset::register($this);
$this->beginPage();
?>
<!doctype html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head>
<meta charset="<?= Yii::$app->charset ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= Html::csrfMetaTags() ?>
<title><?= Html::encode($this->title) ?></title>
<?php $this->head() ?>
</head>
<body class="um-auth-page"><?php $this->beginBody() ?>
<main class="um-auth-shell">
<section class="um-auth-intro">
<div class="um-auth-brand"><span class="um-auth-logo" aria-hidden="true">U</span><?= Html::encode(UM::t('front', 'User management')) ?></div>
<h2><?= Html::encode(UM::t('front', 'One place to manage every access.')) ?></h2>
<div class="um-auth-illustration" aria-hidden="true"><svg viewBox="0 0 240 150" fill="none" stroke="currentColor" stroke-width="2"><rect x="28" y="34" width="184" height="104" rx="14"/><path d="M28 60h184 M48 80h65 M48 94h95 M48 108h80"/><circle cx="182" cy="103" r="16"/><path d="m174 103 5 5 10-11"/><path d="M104 34V24a16 16 0 0 1 32 0v10"/></svg></div>
</section>
<section class="um-auth-content"><?= $content ?></section>
</main>
<?php $this->endBody() ?></body></html><?php $this->endPage() ?>

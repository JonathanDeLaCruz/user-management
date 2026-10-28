<?php
use yii\helpers\Html;
$this->beginPage();
?>
<!doctype html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><?= Html::csrfMetaTags() ?>
<title><?= Html::encode($this->title) ?></title>
<style>.host-layout { padding: 1rem; } .activity-bars { display: block; } .stat-link { text-decoration: underline; }</style>
<?php $this->head() ?></head>
<body class="host-layout"><?php $this->beginBody() ?>
<header class="mb-4">Layout externo de la aplicación</header>
<main><?= $content ?></main>
<?php $this->endBody() ?></body></html>
<?php $this->endPage() ?>

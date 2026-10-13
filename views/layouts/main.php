<?php
use yii\bootstrap5\Breadcrumbs;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\UserManagementModule as UM;
webvimark\modules\UserManagement\assets\LayoutAsset::register($this);

$collapseLabel = UM::t('back', 'Collapse menu');
$expandLabel = UM::t('back', 'Expand menu');
$this->registerJs("const shell = document.querySelector('.app-shell'); const toggle = document.getElementById('sidebar-toggle'); if (shell && toggle) { const apply = collapsed => { shell.classList.toggle('sidebar-collapsed', collapsed); toggle.setAttribute('aria-expanded', String(!collapsed)); toggle.setAttribute('aria-label', collapsed ? " . Json::htmlEncode($expandLabel) . " : " . Json::htmlEncode($collapseLabel) . "); toggle.title = toggle.getAttribute('aria-label'); }; let collapsed = window.matchMedia('(max-width: 767px)').matches; try { const saved = localStorage.getItem('um-sidebar-collapsed'); if (saved !== null) collapsed = saved === 'true'; } catch (e) {} apply(collapsed); toggle.addEventListener('click', () => { collapsed = !shell.classList.contains('sidebar-collapsed'); apply(collapsed); try { localStorage.setItem('um-sidebar-collapsed', String(collapsed)); } catch (e) {} }); }");
$this->beginPage();
?>
<!doctype html>
<html lang="<?= Html::encode(Yii::$app->language) ?>">
<head><meta charset="<?= Yii::$app->charset ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?= Html::csrfMetaTags() ?><title><?= Html::encode($this->title ?: UM::t('back', 'Access management')) ?></title><?php $this->head() ?></head>
<body class="um-app-page"><?php $this->beginBody() ?>
<div class="app-shell">
<aside class="app-sidebar" id="app-sidebar">
<a class="app-brand" href="<?= Url::to(['/user-management/dashboard/index']) ?>"><span class="brand-mark" aria-hidden="true">U</span><span class="sidebar-caption"><?= Html::encode(UM::t('back', 'Access management')) ?></span></a>
<button id="sidebar-toggle" type="button" class="sidebar-toggle" aria-controls="sidebar-navigation" aria-expanded="true" aria-label="<?= Html::encode($collapseLabel) ?>" title="<?= Html::encode($collapseLabel) ?>"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m14 6-6 6 6 6"/></svg></button>
<nav id="sidebar-navigation" aria-label="<?= Html::encode(UM::t('back', 'Main navigation')) ?>">
<?php
$items = array_merge([['label' => UM::t('back', 'Overview'), 'url' => ['/user-management/dashboard/index']]], UM::menuItems());
$icons = ['M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M18 8h4 M20 6v4', 'M12 3 3 7v6c0 5 9 9 9 9s9-4 9-9V7Z', 'M14 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M10 11H2 M5 11v4 M8 11v3', 'M3 5h7l2 2h9v13H3Z', 'M3 12a9 9 0 1 0 3-7 M3 3v6h6 M12 7v5l3 2'];
foreach ($items as $index => $item) {
    $route = $item['url'][0];
    $active = Yii::$app->controller->id === 'site' ? $index === 0 : str_starts_with('/' . Yii::$app->controller->route, substr($route, 0, strrpos($route, '/') + 1));
    $icon = '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="' . $icons[$index] . '"/></svg>';
    echo GhostHtml::a($icon . '<span class="sidebar-caption">' . Html::encode($item['label']) . '</span>', $item['url'], ['class' => 'sidebar-link' . ($active ? ' active' : ''), 'title' => $item['label'], 'aria-label' => $item['label']] + ($active ? ['aria-current' => 'page'] : []));
}
?>
</nav>
</aside>
<div class="app-workspace">
<header class="app-topbar"><h1 class="app-page-title"><?= Html::encode($this->title ?: UM::t('back', 'Access management')) ?></h1>
<?php if (!Yii::$app->user->isGuest): ?>
<?= Html::beginForm(['/user-management/auth/logout'], 'post', ['class' => 'd-flex align-items-center gap-3']) ?>
<span class="text-body-secondary"><?= Html::encode(Yii::$app->user->username) ?></span>
<?= Html::submitButton(Html::encode(UM::t('back', 'Logout')), ['class' => 'btn btn-secondary btn-sm']) ?>
<?= Html::endForm() ?>
<?php endif ?>
</header>
<main class="app-content">
<?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs'] ?? []]) ?>
<?php foreach (Yii::$app->session->getAllFlashes() as $type => $message): ?>
<div role="alert" class="alert alert-<?= in_array($type, ['success', 'warning', 'danger'], true) ? $type : 'info' ?>"><?= Html::encode($message) ?></div>
<?php endforeach ?>
<?= $content ?>
</main>
<footer class="app-footer"><?= Html::encode(UM::t('back', 'Access management')) ?></footer>
</div></div>
<?php $this->endBody() ?></body></html><?php $this->endPage() ?>

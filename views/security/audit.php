<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\widgets\GridView;
use yii\widgets\Pjax;
use yii\bootstrap5\ActiveForm;
use webvimark\modules\UserManagement\components\SecurityPresentation as Presentation;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('front', 'Security audit');
$this->params['breadcrumbs'][] = $this->title;
$t = fn($label) => Html::encode(UM::t('front', $label));
$grid = new GridView(['id' => 'security-audit-grid', 'dataProvider' => $provider, 'filterModel' => $searchModel]);
?>
<?php Pjax::begin(['id' => 'security-audit-pjax', 'formSelector' => '#security-audit-search']); ?>
<div class="card um-card mb-4"><div class="card-body">
<p class="text-body-secondary"><?= $t('Review sign-ins and account changes. Times use the application time zone.') ?></p>
<?php $form = ActiveForm::begin(['id' => 'security-audit-search', 'method' => 'get', 'action' => $grid->queryUrl(), 'options' => ['data-pjax' => 1]]); ?>
<div class="row g-3">
<div class="col-12 col-lg-6"><?= $form->field($searchModel, 'search')->label(UM::t('front', 'Search activity'))->textInput(['placeholder' => UM::t('front', 'Username, action, IP or record'), 'maxlength' => 100]) ?></div>
<div class="col-12 col-sm-6 col-lg-3"><?= $form->field($searchModel, 'from')->label(UM::t('front', 'From date'))->input('date') ?></div>
<div class="col-12 col-sm-6 col-lg-3"><?= $form->field($searchModel, 'to')->label(UM::t('front', 'To date'))->input('date') ?></div>
<div class="col-12 col-sm-6 col-lg-3 d-flex align-items-end"><div class="mb-3 w-100"><?= Html::submitButton($t('Search'), ['class' => 'btn btn-primary w-100']) ?></div></div>
<div class="col-12 col-sm-6 col-lg-3 d-flex align-items-end"><div class="mb-3 w-100"><?= Html::a($t('Clear filters'), $grid->queryUrl(), ['class' => 'btn btn-secondary w-100']) ?></div></div>
</div>
<?php foreach (['created_at', 'username', 'event', 'target', 'ip', 'details'] as $attribute) echo Html::activeHiddenInput($searchModel, $attribute); ?>
<?php ActiveForm::end(); ?>
</div></div>
<div class="card um-card"><div class="card-body table-responsive um-audit-grid">
<?= GridView::widget(['id' => 'security-audit-grid', 'dataProvider' => $provider, 'filterModel' => $searchModel,
    'filterSelector' => '#security-audit-search input:not([type=hidden])',
    'tableOptions' => ['class' => 'table table-striped align-middle'], 'columns' => [
    ['class' => \webvimark\modules\UserManagement\widgets\SerialColumn::class],
    ['attribute' => 'created_at', 'label' => UM::t('front', 'Date'), 'format' => 'datetime', 'filter' => Html::activeInput('date', $searchModel, 'created_at', ['class' => 'form-control', 'aria-label' => $searchModel->getAttributeLabel('created_at')])],
    ['attribute' => 'username', 'label' => UM::t('front', 'User'), 'value' => 'display_username'],
    ['attribute' => 'event', 'label' => UM::t('front', 'Event'), 'value' => 'display_event', 'filter' => Presentation::events(), 'filterInputOptions' => ['class' => 'form-select', 'prompt' => UM::t('front', 'All events')]],
    ['attribute' => 'target', 'label' => UM::t('front', 'Target'), 'value' => 'display_target'],
    ['attribute' => 'ip', 'label' => 'IP'],
    ['attribute' => 'details', 'label' => UM::t('front', 'Details'), 'value' => 'display_details'],
]]) ?>
</div></div>
<?php Pjax::end(); ?>

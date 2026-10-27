<?php
use yii\helpers\Html;
use yii\grid\GridView;
use yii\bootstrap5\ActiveForm;
use webvimark\modules\UserManagement\components\SecurityPresentation as Presentation;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('front', 'Security audit');
$this->params['breadcrumbs'][] = $this->title;
$t = fn($label) => Html::encode(UM::t('front', $label));
?>
<div class="card um-card mb-4"><div class="card-body">
<p class="text-body-secondary"><?= $t('Review sign-ins and account changes. Times use the application time zone.') ?></p>
<?php $form = ActiveForm::begin(['method' => 'get', 'action' => ['audit']]); ?>
<div class="row g-3">
<div class="col-12 col-lg-6"><?= $form->field($searchModel, 'search')->label(UM::t('front', 'Search activity'))->textInput(['placeholder' => UM::t('front', 'Username, action, IP or record'), 'maxlength' => 100]) ?></div>
<div class="col-12 col-sm-6 col-lg-3"><?= $form->field($searchModel, 'username')->label(UM::t('front', 'Username'))->textInput(['maxlength' => 100]) ?></div>
<div class="col-12 col-sm-6 col-lg-3"><?= $form->field($searchModel, 'event')->label(UM::t('front', 'Event'))->dropDownList(Presentation::events(), ['prompt' => UM::t('front', 'All events')]) ?></div>
<div class="col-12 col-sm-6 col-lg-3"><?= $form->field($searchModel, 'from')->label(UM::t('front', 'From date'))->input('date') ?></div>
<div class="col-12 col-sm-6 col-lg-3"><?= $form->field($searchModel, 'to')->label(UM::t('front', 'To date'))->input('date') ?></div>
<div class="col-12 col-sm-6 col-lg-3 d-flex align-items-end"><div class="mb-3 w-100"><?= Html::submitButton($t('Search'), ['class' => 'btn btn-primary w-100']) ?></div></div>
<div class="col-12 col-sm-6 col-lg-3 d-flex align-items-end"><div class="mb-3 w-100"><?= Html::a($t('Clear filters'), ['audit'], ['class' => 'btn btn-secondary w-100']) ?></div></div>
</div>
<?php ActiveForm::end(); ?>
</div></div>
<div class="card um-card"><div class="card-body table-responsive um-audit-grid">
<?= GridView::widget(['dataProvider' => $provider, 'tableOptions' => ['class' => 'table table-striped align-middle'], 'columns' => [
    ['attribute' => 'created_at', 'label' => UM::t('front', 'Date'), 'format' => 'datetime'],
    ['label' => UM::t('front', 'User'), 'value' => [Presentation::class, 'actor']],
    ['label' => UM::t('front', 'Event'), 'value' => fn($row) => Presentation::events()[$row['event']] ?? UM::t('front', 'Other security event')],
    ['label' => UM::t('front', 'Target'), 'value' => [Presentation::class, 'target']],
    ['attribute' => 'ip', 'label' => 'IP', 'enableSorting' => false],
    ['label' => UM::t('front', 'Details'), 'value' => [Presentation::class, 'details']],
]]) ?>
</div></div>

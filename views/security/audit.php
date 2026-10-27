<?php
use yii\helpers\Html;
use yii\grid\GridView;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('front', 'Security audit');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card um-card"><div class="card-body table-responsive">
<?= GridView::widget(['dataProvider' => $provider, 'tableOptions' => ['class' => 'table table-striped align-middle'], 'columns' => [
    ['attribute' => 'created_at', 'label' => UM::t('front', 'Date'), 'format' => 'datetime'],
    ['attribute' => 'actor_id', 'label' => UM::t('front', 'User')],
    ['attribute' => 'event', 'label' => UM::t('front', 'Event'), 'format' => 'text'],
    ['attribute' => 'target', 'label' => UM::t('front', 'Target'), 'format' => 'text'], 'ip:text',
    ['attribute' => 'details', 'label' => UM::t('front', 'Details'), 'format' => 'text'],
]]) ?>
</div></div>

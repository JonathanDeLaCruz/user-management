<?php

use webvimark\modules\UserManagement\UserManagementModule;
use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\UserVisitLog $model
 */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Visit log'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-visit-log-view">


	<div class="card um-card">
		<div class="card-body">

			<?= DetailView::widget([
				'model' => $model,
				'attributes' => [
					[
						'attribute'=>'user_id',
						'value'=>($model->user?->username ?? 'Usuario eliminado'),
					],
					'ip',
					'language',
					'os',
					'browser',
					'user_agent',

					'visit_time:datetime',
				],
			]) ?>

		</div>
	</div>
</div>

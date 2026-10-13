<?php


use webvimark\modules\UserManagement\UserManagementModule;
use yii\helpers\Html;
use yii\widgets\Pjax;
use webvimark\modules\UserManagement\widgets\GridPageSize;
use yii\grid\GridView;

/**
 * @var yii\web\View $this
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var webvimark\modules\UserManagement\models\search\UserVisitLogSearch $searchModel
 */

$this->title = UserManagementModule::t('back', 'Visit log');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-visit-log-index">

	<?php // echo $this->render('_search', ['model' => $searchModel]); ?>

	<div class="card um-card">

		<div class="card-body">

			<div class="row">
				<div class="col-sm-12 text-end">
					<?= GridPageSize::widget(['pjaxId'=>'user-visit-log-grid-pjax']) ?>
				</div>
			</div>

			<?php Pjax::begin([
				'id'=>'user-visit-log-grid-pjax',
			]) ?>

			<?= GridView::widget([
				'id'=>'user-visit-log-grid',
				'dataProvider' => $dataProvider,
				'pager'=>['class' => 'yii\bootstrap5\LinkPager',
					'options'=>['class'=>'pagination pagination-sm'],
					'hideOnSinglePage'=>true,
					'lastPageLabel'=>'>>',
					'firstPageLabel'=>'<<',
				],
				'layout'=>'{items}<div class="row"><div class="col-sm-8">{pager}</div><div class="col-sm-4 text-end">{summary}</div></div>',
				'filterModel' => $searchModel,
				'columns' => [
					['class' => 'webvimark\modules\UserManagement\widgets\SerialColumn', 'options'=>['style'=>'width:10px'] ],

					[
						'attribute'=>'user_id',
						'value'=>function($model){
								return Html::a(Html::encode($model->user?->username ?? UserManagementModule::t('back', 'Deleted user')), ['view', 'id'=>$model->id], ['data-pjax'=>0]);
							},
						'format'=>'raw',
					],
					'language',
					'os',
					'browser',
					array(
						'attribute'=>'ip',
						'value'=>function($model){
								return Html::a(Html::encode($model->ip), "https://ipinfo.io/" . $model->ip, ["target"=>"_blank"]);
							},
						'format'=>'raw',
					),
					'visit_time:datetime',
					[
						'class' => 'webvimark\modules\UserManagement\widgets\ActionColumn',
						'template'=>'{view}',
						'contentOptions'=>['style'=>'width:1%; white-space:nowrap; text-align:center;'],
					],
				],
			]); ?>

			<?php Pjax::end() ?>
		</div>
	</div>
</div>

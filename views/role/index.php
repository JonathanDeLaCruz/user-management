<?php
use webvimark\modules\UserManagement\widgets\GridBulkActions;
use webvimark\modules\UserManagement\widgets\GridPageSize;
use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\models\rbacDB\AuthItemGroup;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\widgets\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

/**
 * @var yii\data\ActiveDataProvider $dataProvider
 * @var webvimark\modules\UserManagement\models\rbacDB\search\RoleSearch $searchModel
 * @var yii\web\View $this
 */
$this->title = UserManagementModule::t('back', 'Roles');
$this->params['breadcrumbs'][] = $this->title;

?>

<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

<div class="card um-card">
	<div class="card-body">
		<div class="row">
			<div class="col-sm-6">
				<p>
					<?= GhostHtml::a(
						'' . UserManagementModule::t('back', 'Create'),
						['create'],
						['class' => 'btn btn-success']
					) ?>
				</p>
			</div>

			<div class="col-sm-6 text-end">
				<?= GridPageSize::widget(['pjaxId'=>'role-grid-pjax']) ?>
			</div>
		</div>

		<?php Pjax::begin([
			'id'=>'role-grid-pjax',
		]) ?>

		<?= GridView::widget([
			'id'=>'role-grid',
			'dataProvider' => $dataProvider,
			'pager'=>['class' => 'yii\bootstrap5\LinkPager',
				'options'=>['class'=>'pagination pagination-sm'],
				'hideOnSinglePage'=>true,
				'lastPageLabel'=>'>>',
				'firstPageLabel'=>'<<',
			],
			'filterModel' => $searchModel,
			'layout'=>'{items}<div class="row"><div class="col-sm-8">{pager}</div><div class="col-sm-4 text-end">{summary}'.GridBulkActions::widget([
						'gridId'=>'role-grid',
						'actions'=>[ Url::to(['bulk-delete'])=>GridBulkActions::t('app', 'Delete'),],
					]).'</div></div>',
			'columns' => [
				['class' => 'webvimark\modules\UserManagement\widgets\SerialColumn', 'options'=>['style'=>'width:10px'] ],

				[
					'attribute'=>'description',
					'value'=>function(Role $model){
							return Html::a(Html::encode($model->description), ['view', 'id'=>$model->name], ['data-pjax'=>0]);
						},
					'format'=>'raw',
				],
				'name',
				['class' => 'yii\grid\CheckboxColumn', 'options'=>['style'=>'width:10px'] ],
				[
					'class' => 'webvimark\modules\UserManagement\widgets\ActionColumn',
					'contentOptions'=>['style'=>'width:1%; white-space:nowrap; text-align:center;'],
				],
			],
		]); ?>

		<?php Pjax::end() ?>
	</div>
</div>

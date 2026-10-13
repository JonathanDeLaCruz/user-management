<?php

use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\DetailView;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\User $model
 */

$this->title = $model->username;
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-view">

	<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

	<div class="card um-card">
		<div class="card-body">

		    <p>
			<?= GhostHtml::a(UserManagementModule::t('back', 'Edit'), ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']) ?>
			<?= GhostHtml::a(UserManagementModule::t('back', 'Create'), ['create'], ['class' => 'btn btn-sm btn-success']) ?>
			<?= GhostHtml::a(
				UserManagementModule::t('back', 'Roles and permissions'),
				['/user-management/user-permission/set', 'id'=>$model->id],
				['class' => 'btn btn-sm btn-secondary']
			) ?>

			<?= GhostHtml::a(UserManagementModule::t('back', 'Delete'), ['delete', 'id' => $model->id], [
			    'class' => 'btn btn-sm btn-danger float-end',
			    'data' => [
				'confirm' => UserManagementModule::t('back', 'Are you sure you want to delete this user?'),
				'method' => 'post',
			    ],
			]) ?>
		    </p>

			<?= DetailView::widget([
				'model'      => $model,
				'attributes' => [
					'id',
					[
						'attribute'=>'status',
						'value'=>User::getStatusValue($model->status),
					],
					'username',
					[
						'attribute'=>'email',
						'value'=>$model->email,
						'format'=>'email',
						'visible'=>User::hasPermission('viewUserEmail'),
					],
					[
						'attribute'=>'email_confirmed',
						'value'=>$model->email_confirmed,
						'format'=>'boolean',
						'visible'=>User::hasPermission('viewUserEmail'),
					],
					[
						'label'=>UserManagementModule::t('back', 'Roles'),
						'value'=>implode('<br>', array_map([Html::class, 'encode'], ArrayHelper::map(Role::getUserRoles($model->id), 'name', 'description'))),
						'visible'=>User::hasPermission('viewUserRoles'),
						'format'=>'raw',
					],
					[
						'attribute'=>'bind_to_ip',
						'visible'=>User::hasPermission('bindUserToIp'),
					],
					array(
						'attribute'=>'registration_ip',
						'value'=>Html::a(Html::encode($model->registration_ip), "https://ipinfo.io/" . $model->registration_ip, ["target"=>"_blank"]),
						'format'=>'raw',
						'visible'=>User::hasPermission('viewRegistrationIp'),
					),
					'created_at:datetime',
					'updated_at:datetime',
				],
			]) ?>

		</div>
	</div>
</div>

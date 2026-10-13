<?php
/**
 * @var yii\widgets\ActiveForm $form
 * @var array $childRoles
 * @var array $allRoles
 * @var array $routes
 * @var array $currentRoutes
 * @var array $permissionsByGroup
 * @var array $currentPermissions
 * @var yii\rbac\Role $role
 */

use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

$this->title = UserManagementModule::t('back', 'Permissions for role:') . ' '. $role->description;
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Roles'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

<?php if ( Yii::$app->session->hasFlash('success') ): ?>
	<div class="alert alert-success text-center">
		<?= Yii::$app->session->getFlash('success') ?>
	</div>
<?php endif; ?>

<p>
	<?= GhostHtml::a(UserManagementModule::t('back', 'Edit'), ['update', 'id' => $role->name], ['class' => 'btn btn-sm btn-primary']) ?>
	<?= GhostHtml::a(UserManagementModule::t('back', 'Create'), ['create'], ['class' => 'btn btn-sm btn-success']) ?>
</p>

<div class="row">
	<div class="col-sm-4">
		<div class="card um-card">
			<div class="card-header">
				<strong>
					<?= UserManagementModule::t('back', 'Child roles') ?>
				</strong>
			</div>
			<div class="card-body">
				<?= Html::beginForm(['set-child-roles', 'id'=>$role->name]) ?>

				<?php foreach ($allRoles as $aRole): ?>
					<label>
						<?php $isChecked = in_array($aRole['name'], ArrayHelper::map($childRoles, 'name', 'name')) ? 'checked' : '' ?>
						<input type="checkbox" <?= $isChecked ?> name="child_roles[]" value="<?= Html::encode($aRole['name']) ?>">
						<?= Html::encode($aRole['description']) ?>
					</label>

					<?= GhostHtml::a(
						'',
						['/user-management/role/view', 'id'=>$aRole['name']],
						['target'=>'_blank']
					) ?>
					<br/>
				<?php endforeach ?>


				<hr/>
				<?= Html::submitButton(
					'' . UserManagementModule::t('back', 'Save'),
					['class'=>'btn btn-primary btn-sm']
				) ?>

				<?= Html::endForm() ?>
			</div>
		</div>
	</div>

	<div class="col-sm-8">
		<div class="card um-card">
			<div class="card-header">
				<strong>
					<?= UserManagementModule::t('back', 'Permissions') ?>
				</strong>
			</div>
			<div class="card-body">
				<?= Html::beginForm(['set-child-permissions', 'id'=>$role->name]) ?>

				<div class="row">
					<?php foreach ($permissionsByGroup as $groupName => $permissions): ?>
						<div class="col-sm-6">
							<fieldset>
								<legend><?= Html::encode($groupName) ?></legend>

								<?php foreach ($permissions as $permission): ?>
									<div class="um-permission-row">

									<label>
										<?php $isChecked = in_array($permission->name, ArrayHelper::map($currentPermissions, 'name', 'name')) ? 'checked' : '' ?>
										<input type="checkbox" <?= $isChecked ?> name="child_permissions[]" value="<?= Html::encode($permission->name) ?>">
										<?= Html::encode($permission->description) ?>
									</label>
<?= GhostHtml::a(
										Html::encode(Yii::t('yii', 'View')),
										['/user-management/permission/view', 'id'=>$permission->name],
										['class'=>'btn btn-success btn-sm', 'target'=>'_blank', 'rel'=>'noopener']
									) ?>

									</div>
								<?php endforeach ?>

							</fieldset>
							<br/>
						</div>


					<?php endforeach ?>
				</div>

				<hr/>
				<?= Html::submitButton(
					'' . UserManagementModule::t('back', 'Save'),
					['class'=>'btn btn-primary btn-sm']
				) ?>

				<?= Html::endForm() ?>

			</div>
		</div>
	</div>
</div>

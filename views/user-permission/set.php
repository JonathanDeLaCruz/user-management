<?php
/**
 * @var yii\web\View $this
 * @var array $permissionsByGroup
 * @var webvimark\modules\UserManagement\models\User $user
 */

use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\BootstrapPluginAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

BootstrapPluginAsset::register($this);
$this->title = UserManagementModule::t('back', 'Roles and permissions for user:') . ' ' . $user->username;

$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Users'), 'url' => ['/user-management/user/index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

<?php if ( Yii::$app->session->hasFlash('success') ): ?>
	<div class="alert alert-success text-center">
		<?= Yii::$app->session->getFlash('success') ?>
	</div>
<?php endif; ?>

<div class="row">
	<div class="col-sm-4">
		<div class="card um-card">
			<div class="card-header">
				<strong>
					<?= UserManagementModule::t('back', 'Roles') ?>
				</strong>
			</div>
			<div class="card-body">

				<?= Html::beginForm(['set-roles', 'id'=>$user->id]) ?>

				<?php foreach (Role::getAvailableRoles() as $aRole): ?>
					<label>
						<?php $isChecked = in_array($aRole['name'], ArrayHelper::map(Role::getUserRoles($user->id), 'name', 'name')) ? 'checked' : '' ?>

						<?php if ( Yii::$app->getModule('user-management')->userCanHaveMultipleRoles ): ?>
							<input type="checkbox" <?= $isChecked ?> name="roles[]" value="<?= Html::encode($aRole['name']) ?>">

						<?php else: ?>
							<input type="radio" <?= $isChecked ?> name="roles" value="<?= Html::encode($aRole['name']) ?>">

						<?php endif; ?>

						<?= Html::encode($aRole['description']) ?>
					</label>

					<?= GhostHtml::a(
						'',
						['/user-management/role/view', 'id'=>$aRole['name']],
						['target'=>'_blank']
					) ?>
					<br/>
				<?php endforeach ?>

				<br/>

				<?php if ( Yii::$app->user->isSuperadmin OR Yii::$app->user->id != $user->id ): ?>

					<?= Html::submitButton(
						'' . UserManagementModule::t('back', 'Save'),
						['class'=>'btn btn-primary btn-sm']
					) ?>
				<?php else: ?>
					<div class="alert alert-warning p-2 text-center">
						<?= UserManagementModule::t('back', 'You can not change own permissions') ?>
					</div>
				<?php endif; ?>


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

				<div class="row">
					<?php foreach ($permissionsByGroup as $groupName => $permissions): ?>

						<div class="col-sm-6">
							<fieldset>
								<legend><?= Html::encode($groupName) ?></legend>

								<ul>
									<?php foreach ($permissions as $permission): ?>
										<li>
											<?= Html::encode($permission->description) ?>

											<?= GhostHtml::a(
												'',
												['/user-management/permission/view', 'id'=>$permission->name],
												['target'=>'_blank']
											) ?>
										</li>
									<?php endforeach ?>
								</ul>
							</fieldset>

							<br/>
						</div>

					<?php endforeach ?>

				</div>

			</div>
		</div>
	</div>
</div>

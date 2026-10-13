<?php
use yii\helpers\Html;
/**
 *
 * @var yii\web\View $this
 * @var yii\widgets\ActiveForm $form
 * @var webvimark\modules\UserManagement\models\rbacDB\Role $model
 */
use webvimark\modules\UserManagement\UserManagementModule;

$this->title = UserManagementModule::t('back', 'Role creation');
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Roles'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

<div class="card um-card">
	<div class="card-body">
		<?= $this->render('_form', [
			'model'=>$model,
		]) ?>
	</div>
</div>

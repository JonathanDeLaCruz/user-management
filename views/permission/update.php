<?php
use yii\helpers\Html;
/**
 * @var yii\widgets\ActiveForm $form
 * @var webvimark\modules\UserManagement\models\rbacDB\Permission $model
 */

use webvimark\modules\UserManagement\UserManagementModule;

$this->title = UserManagementModule::t('back', 'Editing permission: ') . ' ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Permissions'), 'url' => ['index']];
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

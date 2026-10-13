<?php

use webvimark\modules\UserManagement\UserManagementModule;
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\rbacDB\AuthItemGroup $model
 * @var yii\bootstrap5\ActiveForm $form
 */
?>
<div class="auth-item-group-form">

	<?php $form = ActiveForm::begin([
		'id'=>'auth-item-group-form',
		'layout'=>'horizontal',
		'validateOnBlur' => false,

	]); ?>

	<?= $form->field($model, 'name')->textInput(['maxlength' => 255, 'autofocus'=>$model->isNewRecord ? true:false]) ?>

	<?= $form->field($model, 'code')->textInput(['maxlength' => 64]) ?>

	<div class="mb-3">
		<div class="offset-sm-3 col-sm-9">
			<?php if ( $model->isNewRecord ): ?>
				<?= Html::submitButton(
					'' . UserManagementModule::t('back', 'Create'),
					['class' => 'btn btn-success']
				) ?>
			<?php else: ?>
				<?= Html::submitButton(
					'' . UserManagementModule::t('back', 'Save'),
					['class' => 'btn btn-primary']
				) ?>
			<?php endif; ?>
		</div>
	</div>

	<?php ActiveForm::end(); ?>

</div>

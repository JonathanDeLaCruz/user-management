<?php
/**
 * @var yii\widgets\ActiveForm $form
 * @var webvimark\modules\UserManagement\models\rbacDB\Permission $model
 */

use webvimark\modules\UserManagement\models\rbacDB\AuthItemGroup;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
?>

<?php $form = ActiveForm::begin([
	'id'      => 'role-form',
	'layout'=>'horizontal',
	'validateOnBlur' => false,
]) ?>

	<?= $form->field($model, 'description')->textInput(['maxlength' => 255, 'autofocus'=>$model->isNewRecord ? true:false]) ?>

	<?= $form->field($model, 'name')->textInput(['maxlength' => 64]) ?>

	<?= $form->field($model, 'group_code')
		->dropDownList(ArrayHelper::map(AuthItemGroup::find()->asArray()->all(), 'code', 'name'), ['prompt'=>'']) ?>

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
<?php ActiveForm::end() ?>

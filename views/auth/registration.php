<?php

use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\ActiveForm;
use webvimark\modules\UserManagement\widgets\Captcha;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\forms\RegistrationForm $model
 */

$this->title = UserManagementModule::t('front', 'Registration');
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="user-registration auth-login card um-card"><div class="card-body p-4 p-md-5">

	<h1 class="h3 mb-4"><?= Html::encode($this->title) ?></h1>

	<?php $form = ActiveForm::begin([
		'id'=>'user',
		'layout'=>'default',
		'validateOnBlur'=>false,
	]); ?>

	<?= $form->field($model, 'username')->textInput(['maxlength' => 50, 'autocomplete'=>'off', 'autofocus'=>true]) ?>

	<?= $form->field($model, 'password')->passwordInput(['maxlength' => 255, 'autocomplete'=>'off']) ?>

	<?= $form->field($model, 'repeat_password')->passwordInput(['maxlength' => 255, 'autocomplete'=>'off']) ?>

	<?= $form->field($model, 'captcha')->widget(Captcha::className(), [
		'captchaAction'=>['/user-management/auth/captcha']
	]) ?>

	<div class="mb-3">
		<div class="d-grid">
			<?= Html::submitButton(
				'' . UserManagementModule::t('front', 'Register'),
				['class' => 'btn btn-primary w-100']
			) ?>
		</div>
	</div>

	<?php ActiveForm::end(); ?>
<div class="mt-4 small">
<?= Html::a(Html::encode(UserManagementModule::t('front', 'Login')), ['/user-management/auth/login'], ['class'=>'btn btn-secondary btn-sm w-100']) ?>
</div></div>
</div>

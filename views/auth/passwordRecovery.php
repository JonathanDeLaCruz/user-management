<?php

use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\ActiveForm;
use webvimark\modules\UserManagement\widgets\Captcha;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\forms\PasswordRecoveryForm $model
 */

$this->title = UserManagementModule::t('front', 'Password recovery');
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="password-recovery auth-login card um-card"><div class="card-body p-4 p-md-5">

	<h1 class="h3 mb-4"><?= Html::encode($this->title) ?></h1>

	<?php if ( Yii::$app->session->hasFlash('error') ): ?>
		<div class="alert alert-warning text-center">
			<?= Yii::$app->session->getFlash('error') ?>
		</div>
	<?php endif; ?>

	<?php $form = ActiveForm::begin([
		'id'=>'user',
		'layout'=>'default',
		'validateOnBlur'=>false,
	]); ?>

	<?= $form->field($model, 'email')->textInput(['maxlength' => 255, 'autofocus'=>true]) ?>

	<?= $form->field($model, 'captcha')->widget(Captcha::className(), [
		'captchaAction'=>['/user-management/auth/captcha']
	]) ?>

	<div class="mb-3">
		<div class="d-grid">
			<?= Html::submitButton(
				'' . UserManagementModule::t('front', 'Recover'),
				['class' => 'btn btn-primary w-100']
			) ?>
		</div>
	</div>

	<?php ActiveForm::end(); ?>
<div class="mt-4 small">
<?= Html::a(Html::encode(UserManagementModule::t('front', 'Login')), ['/user-management/auth/login'], ['class'=>'btn btn-secondary btn-sm w-100']) ?>
</div></div>
</div>

<?php
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\UserManagementModule;
$this->title = UserManagementModule::t('front', 'Login');
?>
<div class="auth-login"><h1 class="h3 mb-4"><?= Html::encode($this->title) ?></h1>
<?php $form=ActiveForm::begin(['id'=>'login-form','enableAjaxValidation'=>false,'validateOnBlur'=>false]); ?>
<?= $form->field($model,'username')->textInput(['autocomplete'=>'username','autofocus'=>true]) ?>
<?= $form->field($model,'password')->passwordInput(['autocomplete'=>'current-password']) ?>
<?= $form->field($model,'rememberMe')->checkbox() ?>
<?= Html::submitButton(UserManagementModule::t('front', 'Login'),['class'=>'btn btn-primary w-100 mt-2']) ?>
<?php ActiveForm::end(); ?>
<div class="d-flex flex-wrap justify-content-between gap-3 mt-4 small"><?= GhostHtml::a(Html::encode(UserManagementModule::t('front', 'Registration')),['/user-management/auth/registration']) ?><?= GhostHtml::a(Html::encode(UserManagementModule::t('front', 'Password recovery')),['/user-management/auth/password-recovery']) ?></div></div>

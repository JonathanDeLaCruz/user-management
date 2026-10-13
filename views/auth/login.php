<?php
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\GhostHtml;
use webvimark\modules\UserManagement\UserManagementModule;
$this->title = UserManagementModule::t('front', 'Login');
?>
<div class="auth-login card um-card"><div class="card-body p-4 p-md-5">
<p class="text-primary small fw-semibold mb-2"><?= Html::encode(UserManagementModule::t('front', 'Welcome back')) ?></p>
<h1 class="h3 mb-2"><?= Html::encode($this->title) ?></h1>
<p class="text-body-secondary mb-4"><?= Html::encode(UserManagementModule::t('front', 'Sign in to manage users and permissions.')) ?></p>
<?php $form=ActiveForm::begin(['id'=>'login-form','enableAjaxValidation'=>false,'validateOnBlur'=>false]); ?>
<?= $form->field($model,'username')->textInput(['autocomplete'=>'username','autofocus'=>true]) ?>
<?= $form->field($model,'password')->passwordInput(['autocomplete'=>'current-password']) ?>
<?= $form->field($model,'rememberMe')->checkbox() ?>
<?= Html::submitButton(UserManagementModule::t('front', 'Login'),['class'=>'btn btn-primary w-100 mt-2']) ?>
<?php ActiveForm::end(); ?>
<div class="auth-links d-flex flex-wrap gap-2 mt-4"><?= GhostHtml::a(Html::encode(UserManagementModule::t('front', 'Registration')),['/user-management/auth/registration'], ['class'=>'btn btn-secondary btn-sm flex-grow-1']) ?><?= GhostHtml::a(Html::encode(UserManagementModule::t('front', 'Password recovery')),['/user-management/auth/password-recovery'], ['class'=>'btn btn-secondary btn-sm flex-grow-1']) ?></div></div></div>

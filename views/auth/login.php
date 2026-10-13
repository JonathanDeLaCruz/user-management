<?php
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\GhostHtml;
$this->title = 'Iniciar sesión';
?>
<div class="auth-login"><span class="eyebrow">BIENVENIDO DE NUEVO</span><h2 class="mt-3 mb-2">Inicia sesión.</h2><p class="text-secondary mb-4">Accede al panel de gestión de usuarios.</p>
<?php $form=ActiveForm::begin(['id'=>'login-form','enableAjaxValidation'=>false,'validateOnBlur'=>false]); ?>
<?= $form->field($model,'username')->textInput(['autocomplete'=>'username','autofocus'=>true])->label('Usuario') ?>
<?= $form->field($model,'password')->passwordInput(['autocomplete'=>'current-password'])->label('Contraseña') ?>
<?= $form->field($model,'rememberMe')->checkbox()->label('Mantener sesión') ?>
<?= Html::submitButton('Entrar al panel →',['class'=>'btn btn-primary w-100 mt-2']) ?>
<?php ActiveForm::end(); ?>
<div class="d-flex justify-content-between gap-3 mt-4 small"><?= GhostHtml::a('Crear una cuenta',['/user-management/auth/registration']) ?><?= GhostHtml::a('Recuperar contraseña',['/user-management/auth/password-recovery']) ?></div></div>

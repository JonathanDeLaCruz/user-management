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
<?php
$passwordId = Html::getInputId($model, 'password');
$toggle = Html::button(
    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
    [
        'type' => 'button',
        'class' => 'btn btn-secondary um-password-toggle',
        'aria-controls' => $passwordId,
        'aria-pressed' => 'false',
        'aria-label' => UserManagementModule::t('front', 'Show password'),
        'title' => UserManagementModule::t('front', 'Show password'),
        'data-show-label' => UserManagementModule::t('front', 'Show password'),
        'data-hide-label' => UserManagementModule::t('front', 'Hide password'),
    ]
);
?>
<?= $form->field($model, 'password', [
    'template' => '{label}<div class="input-group">{input}' . $toggle . '</div>{error}{hint}',
])->passwordInput(['autocomplete' => 'current-password']) ?>
<?= Html::submitButton(UserManagementModule::t('front', 'Login'),['class'=>'btn btn-primary w-100 mt-2']) ?>
<?php ActiveForm::end(); ?>
<?= $this->render('_externalButtons') ?>
<div class="auth-links d-flex flex-wrap gap-2 mt-4"><?= GhostHtml::a(Html::encode(UserManagementModule::t('front', 'Registration')),['/user-management/auth/registration'], ['class'=>'btn btn-secondary btn-sm flex-grow-1']) ?><?= GhostHtml::a(Html::encode(UserManagementModule::t('front', 'Password recovery')),['/user-management/auth/password-recovery'], ['class'=>'btn btn-secondary btn-sm flex-grow-1']) ?></div></div></div>
<?php
$this->registerJs(<<<'JS'
document.addEventListener('click', function (event) {
    var button = event.target.closest('.um-password-toggle');
    if (!button) return;
    var input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    var visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    var label = visible ? button.dataset.hideLabel : button.dataset.showLabel;
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
});
document.getElementById('login-form').addEventListener('submit', function () {
    var button = this.querySelector('.um-password-toggle');
    document.getElementById(button.getAttribute('aria-controls')).type = 'password';
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', button.dataset.showLabel);
    button.setAttribute('title', button.dataset.showLabel);
});
JS, \yii\web\View::POS_READY, 'um-login-password-toggle');
?>

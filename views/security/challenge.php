<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\AccountSecurity as Security;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('front', 'Verify your identity');
$setup = Security::mayEnroll($user);
?>
<div class="auth-login card um-card"><div class="card-body p-4 p-md-5">
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p><?= Html::encode(UM::t('front', 'Your password was verified. Complete the security check to continue.')) ?></p>
<?php if ($strict): ?><p class="alert alert-info"><?= Html::encode(UM::t('front', 'This account requires a passkey. Authenticator and recovery codes cannot replace it.')) ?></p><?php endif ?>
<p id="um-security-feedback" role="status" aria-live="polite" class="text-danger"></p>
<?php if ($error): ?><p class="alert alert-danger" role="alert"><?= Html::encode($error) ?></p><?php endif ?>
<?php if ($keys && !Security::recovered($user)): ?>
<?= $this->render('_passkeyButton', ['operation' => 'login', 'label' => 'Continue with a passkey']) ?>
<?php endif ?>
<?php if ($setup): ?>
<h2 class="h5 mt-4"><?= Html::encode(UM::t('front', 'Register a passkey')) ?></h2>
<p><?= Html::encode(UM::t('front', 'Use your device PIN, biometrics or a security key. Register a backup device after signing in.')) ?></p>
<?= Html::label(Html::encode(UM::t('front', 'Device name')), 'um-passkey-name', ['class' => 'form-label']) ?>
<?= Html::textInput('name', '', ['id' => 'um-passkey-name', 'class' => 'form-control mb-3', 'maxlength' => 100]) ?>
<?= $this->render('_passkeyButton', ['operation' => 'register', 'label' => 'Register a passkey']) ?>
<?php if (!$strict): ?>
<?= Html::beginForm(['totp-setup'], 'post', ['class' => 'mt-3']) ?>
<?= Html::submitButton(Html::encode(UM::t('front', 'Set up an authenticator')), ['class' => 'btn btn-secondary']) ?><?= Html::endForm() ?>
<?php endif ?>
<?php elseif (!$strict): ?>
<?= Html::beginForm(['challenge'], 'post', ['class' => 'mt-4']) ?>
<?= Html::label(Html::encode(UM::t('front', 'Verification method')), 'um-method', ['class' => 'form-label']) ?>
<?= Html::dropDownList('method', 'totp', ['totp' => UM::t('front', 'Authenticator code'), 'recovery' => UM::t('front', 'Recovery code')], ['id' => 'um-method', 'class' => 'form-select mb-3']) ?>
<?= Html::label(Html::encode(UM::t('front', 'Verification code')), 'um-code', ['class' => 'form-label']) ?>
<?= Html::textInput('code', '', ['id' => 'um-code', 'class' => 'form-control mb-3', 'autocomplete' => 'one-time-code', 'maxlength' => 40, 'required' => true]) ?>
<?= Html::submitButton(Html::encode(UM::t('front', 'Continue')), ['class' => 'btn btn-primary']) ?><?= Html::endForm() ?>
<?php endif ?>
<?php if (isset($otp)): ?><?= $this->render('_totpSetup', compact('otp')) ?><?php endif ?>
<?= Html::a(Html::encode(UM::t('front', 'Back to login')), ['/user-management/auth/login'], ['class' => 'btn btn-secondary mt-4']) ?>
</div></div>

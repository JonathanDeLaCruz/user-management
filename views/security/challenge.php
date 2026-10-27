<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\AccountSecurity as Security;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('front', 'Verify your identity');
$setup = Security::mayEnroll($user);
$t = fn($label) => Html::encode(UM::t('front', $label));
?>
<div class="auth-login card um-card um-security-page"><div class="card-body p-4 p-md-5">
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p><?= $t('The first sign-in step was verified. Complete the security check to continue.') ?></p>
<?php if ($strict): ?><p class="alert alert-info"><?= $t('This account requires a passkey. Authenticator and recovery codes cannot replace it.') ?></p><?php endif ?>
<p id="um-security-feedback" role="status" aria-live="polite" class="text-danger"></p>
<?php if (!$strict && $setup): ?>
<?php if (isset($otp)): ?><?= $this->render('_totpSetup', compact('otp')) ?>
<?php else: ?>
<h2 class="h5 mt-4"><?= $t('Set up Google Authenticator') ?></h2>
<p><?= $t('Link your account to Google Authenticator or a compatible app. Scan the QR code and confirm a six-digit code to activate protection.') ?></p>
<?= Html::beginForm(['totp-setup'], 'post') ?>
<?= Html::submitButton($t('Set up Google Authenticator'), ['class' => 'btn btn-primary w-100']) ?><?= Html::endForm() ?>
<?php endif ?>
<?php elseif (!$strict && $profile['totp_secret']): ?>
<h2 class="h5 mt-4"><?= $t('Google Authenticator code') ?></h2>
<?= Html::beginForm(['challenge'], 'post') ?>
<?= Html::hiddenInput('method', 'totp') ?>
<?= Html::label($t('Six-digit code'), 'um-code', ['class' => 'form-label']) ?>
<?= Html::textInput('code', '', ['id' => 'um-code', 'class' => 'form-control mb-3', 'autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'pattern' => '[0-9]{6}', 'maxlength' => 6, 'required' => true]) ?>
<?= Html::submitButton($t('Verify and sign in'), ['class' => 'btn btn-primary w-100']) ?><?= Html::endForm() ?>
<?php endif ?>
<?php if (!$strict && !$setup): ?>
<details class="mt-3"><summary><?= $t('Use a recovery code') ?></summary>
<?= Html::beginForm(['challenge'], 'post', ['class' => 'mt-3']) ?>
<?= Html::hiddenInput('method', 'recovery') ?>
<?= Html::label($t('Recovery code'), 'um-recovery-code', ['class' => 'form-label']) ?>
<?= Html::textInput('code', '', ['id' => 'um-recovery-code', 'class' => 'form-control mb-3', 'autocomplete' => 'off', 'maxlength' => 40, 'required' => true]) ?>
<?= Html::submitButton($t('Continue'), ['class' => 'btn btn-secondary w-100']) ?><?= Html::endForm() ?>
</details>
<?php endif ?>
<?php if ($keys && !Security::recovered($user)): ?><div class="mt-3"><?= $this->render('_passkeyButton', ['operation' => 'login', 'label' => 'Continue with a passkey']) ?></div><?php endif ?>
<?php if ($setup): ?>
<?php if (!$strict): ?><details class="mt-3"><summary><?= $t('Use a passkey instead') ?></summary><?php endif ?>
<div class="mt-3"><h2 class="h5"><?= $t('Register a passkey') ?></h2>
<p><?= $t('Use your device PIN, biometrics or a security key. Register a backup device after signing in.') ?></p>
<?= Html::label($t('Device name'), 'um-passkey-name', ['class' => 'form-label']) ?>
<?= Html::textInput('name', '', ['id' => 'um-passkey-name', 'class' => 'form-control mb-3', 'maxlength' => 100]) ?>
<?= $this->render('_passkeyButton', ['operation' => 'register', 'label' => 'Register a passkey']) ?></div>
<?php if (!$strict): ?></details><?php endif ?>
<?php endif ?>
<?= Html::a($t('Back to login'), ['/user-management/auth/login'], ['class' => 'btn btn-secondary w-100 mt-4']) ?>
</div></div>

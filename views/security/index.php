<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\SecuritySessions;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$this->title = UM::t('front', 'Account security');
$this->params['breadcrumbs'][] = $this->title;
$t = fn($text) => Html::encode(UM::t('front', $text));
$postButton = function ($route, $fields, $label, $color = 'secondary') use ($t) {
    $html = Html::beginForm([$route], 'post', ['class' => 'um-security-action']);
    foreach ($fields as $name => $value) $html .= Html::hiddenInput($name, $value);
    return $html . Html::submitButton($t($label), ['class' => 'btn btn-' . $color . ' w-100', 'data-confirm' => UM::t('front', 'Confirm this security change?')]) . Html::endForm();
};
?>
<div class="um-security-page">
<p id="um-security-feedback" role="status" aria-live="polite" class="text-danger"></p>
<?php if (!$recent): ?>
<section class="card um-card mb-4"><div class="card-body"><h2 class="h5"><?= $t('Verify your identity') ?></h2>
<?php if ($keys): ?><?= $this->render('_passkeyButton', ['operation' => 'reauth', 'label' => 'Verify with a passkey']) ?><?php endif ?>
<?php if (!$strict): ?>
<?= Html::beginForm(['reauthenticate'], 'post', ['class' => 'mt-3']) ?>
<?= Html::label($t('Password'), 'um-current-password', ['class' => 'form-label']) ?>
<?= Html::passwordInput('password', '', ['id' => 'um-current-password', 'class' => 'form-control mb-3', 'autocomplete' => 'current-password', 'required' => true]) ?>
<?php if ($profile['totp_secret']): ?>
<?= Html::label($t('Authenticator code'), 'um-reauth-code', ['class' => 'form-label']) ?>
<?= Html::textInput('code', '', ['id' => 'um-reauth-code', 'class' => 'form-control mb-3', 'autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'required' => true]) ?>
<?php endif ?>
<?= Html::submitButton($t('Confirm'), ['class' => 'btn btn-primary w-100']) ?><?= Html::endForm() ?>
<?php endif ?>
</div></section>
<?php endif ?>
<div class="um-security-settings">
<div>
<section class="card um-card"><div class="card-body"><h2 class="h5"><?= $t('Passkeys') ?></h2>
<p><?= $t('Passkeys verify the website domain and require your device PIN or biometrics.') ?></p>
<?php if (UM::instance()->requirePhishingResistant): ?><p><?= $t('Registering a passkey activates phishing-resistant login for this account.') ?></p><?php endif ?>
<?php if ($strict): ?><p class="alert alert-info"><?= $t('This account requires a passkey. Authenticator and recovery codes cannot replace it.') ?></p><?php endif ?>
<?php if ($keys): ?><ul class="list-group list-group-flush mb-3">
<?php foreach ($keys as $key): ?><li class="list-group-item um-security-key"><span><?= Html::encode($key['name']) ?></span>
<?php if ($recent): ?><?= $postButton('remove-factor', ['kind' => 'passkey', 'id' => $key['id']], 'Remove', 'danger') ?><?php endif ?></li><?php endforeach ?>
</ul><?php else: ?><p class="text-body-secondary"><?= $t('No passkey registered') ?></p><?php endif ?>
<?php if ($recent): ?>
<?= Html::label($t('Device name'), 'um-passkey-name', ['class' => 'form-label']) ?>
<?= Html::textInput('name', '', ['id' => 'um-passkey-name', 'class' => 'form-control mb-3', 'maxlength' => 100]) ?>
<?= $this->render('_passkeyButton', ['operation' => 'register', 'label' => 'Register a passkey']) ?>
<?php endif ?>
<p class="small text-body-secondary mt-3"><?= $t('Keep a second passkey on another device. Administrator recovery requires an authorized operator.') ?></p>
</div></section></div>
<div><section class="card um-card"><div class="card-body"><h2 class="h5"><?= $t('Authenticator and recovery') ?></h2>
<?php if (!$strict): ?>
<p><?= $t('Authenticator codes add a second factor but are not phishing-resistant.') ?></p>
<?php if ($recent): ?>
<div class="d-grid gap-2">
<?= $postButton('totp-setup', [], 'Set up Google Authenticator', 'primary') ?>
<?php if ($profile['totp_secret']): ?><?= $postButton('remove-factor', ['kind' => 'totp'], 'Remove authenticator', 'danger') ?><?php endif ?>
<?php if ($profile['totp_secret'] || $keys): ?><?= $postButton('recovery-codes', [], 'Generate recovery codes') ?><?php endif ?>
</div>
<?php endif ?>
<?php else: ?><p><?= $t('Typed codes are disabled by the phishing-resistant policy. Use another registered passkey.') ?></p><?php endif ?>
<?php if (isset($otp)): ?><?= $this->render('_totpSetup', compact('otp')) ?><?php endif ?>
<?php if (isset($codes)): ?><div class="alert alert-warning mt-3"><p><?= $t('Save these codes now. Each code works once and this list will not be shown again.') ?></p>
<ul class="list-unstyled text-break"><?php foreach ($codes as $code): ?><li><code><?= Html::encode($code) ?></code></li><?php endforeach ?></ul></div><?php endif ?>
</div></section></div></div>
<section class="card um-card mt-4"><div class="card-body"><div class="um-security-session-heading"><h2 class="h5"><?= $t('Active sessions') ?></h2>
<?php if ($recent): ?><?= $postButton('revoke-session', ['id' => 'others'], 'Sign out other sessions', 'danger') ?><?php endif ?></div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th><?= $t('Device') ?></th><th>IP</th><th><?= $t('Last activity') ?></th><th><?= $t('Method') ?></th><th></th></tr></thead><tbody>
<?php foreach ($sessions as $session): ?><tr><td class="text-break"><?= Html::encode(\webvimark\modules\UserManagement\components\SecurityPresentation::device($session['user_agent'])) ?><?php if ($session['id'] === SecuritySessions::currentId()): ?><span class="badge bg-success ms-2"><?= $t('Current session') ?></span><?php endif ?></td><td><?= Html::encode($session['ip']) ?></td><td><?= Html::encode(Yii::$app->formatter->asDatetime($session['last_seen_at'])) ?></td><td><?= $t(['password' => 'Password', 'passkey' => 'Passkey', 'totp' => 'Authenticator code'][$session['method']] ?? 'Verification method') ?></td><td class="um-security-session-action"><?php if ($recent): ?><?= $postButton('revoke-session', ['id' => $session['id']], 'Sign out', 'danger') ?><?php endif ?></td></tr><?php endforeach ?>
</tbody></table></div></div></section>
</div>

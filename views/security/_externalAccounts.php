<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\ExternalAuth;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$enabled = ExternalAuth::enabled();
$links = ExternalAuth::links($user);
if (!$enabled && !$links) return;
$visible = array_intersect_key(['google' => 'Google', 'microsoft' => 'Microsoft'], $enabled + $links);
$t = fn($text, $params = []) => Html::encode(UM::t('front', $text, $params));
?>
<section class="card um-card mt-4"><div class="card-body">
<h2 class="h5"><?= $t('Linked accounts') ?></h2>
<p class="text-body-secondary"><?= $t('Link an account to use it at sign-in. Your local roles and additional verification still apply.') ?></p>
<div class="row g-3">
<?php foreach ($visible as $provider => $label): ?>
<div class="col-12<?= count($visible) > 1 ? ' col-lg-6' : '' ?>"><div class="border rounded p-3 h-100">
<h3 class="h6"><?= Html::encode($label) ?> <span class="badge bg-<?= isset($links[$provider]) ? 'success' : 'secondary' ?>"><?= $t(isset($links[$provider]) ? 'Linked' : 'Not linked') ?></span></h3>
<?php if (!isset($enabled[$provider])): ?><p class="small text-body-secondary"><?= $t('This sign-in provider is disabled.') ?></p><?php endif ?>
<?php if ($recent): ?>
<?php if (isset($enabled[$provider])): ?>
<?= Html::beginForm(['/user-management/external-auth/start', 'provider' => $provider], 'post') ?>
<?= Html::hiddenInput('intent', 'link') ?>
<?= Html::submitButton($t(isset($links[$provider]) ? 'Change linked {provider} account' : 'Link {provider}', ['provider' => $label]), ['class' => 'btn btn-primary w-100']) ?><?= Html::endForm() ?>
<?php endif ?>
<?php if (isset($links[$provider])): ?>
<?= Html::beginForm(['/user-management/external-auth/unlink', 'provider' => $provider], 'post', ['class' => 'mt-3']) ?>
<?= Html::label($t('Local password (required if no other provider is enabled)'), 'um-unlink-' . $provider, ['class' => 'form-label small']) ?>
<?= Html::passwordInput('password', '', ['id' => 'um-unlink-' . $provider, 'class' => 'form-control mb-2', 'autocomplete' => 'current-password']) ?>
<?= Html::submitButton($t('Unlink {provider}', ['provider' => $label]), ['class' => 'btn btn-danger w-100', 'data-confirm' => UM::t('front', 'Confirm this security change?')]) ?><?= Html::endForm() ?>
<?php endif ?>
<?php else: ?><p class="small mb-0"><?= $t('Verify your identity before making changes.') ?></p><?php endif ?>
</div></div>
<?php endforeach ?>
</div></div></section>

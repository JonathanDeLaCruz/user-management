<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\components\ExternalAuth;
use webvimark\modules\UserManagement\UserManagementModule as UM;
$providers = ExternalAuth::enabled();
// Official Google sign-in asset: https://developers.google.com/static/identity/images/g-logo.png
$assets = $providers ? \webvimark\modules\UserManagement\assets\ModuleAsset::register($this) : null;
?>
<?php if ($providers): ?><div class="d-grid gap-2 mt-4 um-external-login">
<?php foreach ($providers as $provider => $label): ?>
<?= Html::beginForm(['/user-management/external-auth/start', 'provider' => $provider], 'post') ?>
<?= Html::submitButton(($provider === 'google' ? Html::img($assets->baseUrl . '/google-g.png', ['alt' => '', 'aria-hidden' => 'true', 'width' => 20, 'height' => 20]) : '') . '<span>' . Html::encode(UM::t('front', 'Continue with {provider}', ['provider' => $label])) . '</span>', ['class' => 'btn um-external-button ' . ($provider === 'google' ? 'um-external-google' : 'btn-dark') . ' w-100']) ?>
<?= Html::endForm() ?>
<?php endforeach ?>
</div><?php endif ?>

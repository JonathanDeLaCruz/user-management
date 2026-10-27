<?php
use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule as UM;
?>
<section class="border rounded p-3 my-3">
<h2 class="h5"><?= Html::encode(UM::t('front', 'Set up an authenticator')) ?></h2>
<p><?= Html::encode(UM::t('front', 'Add this secret to your authenticator app, then enter a code to confirm.')) ?></p>
<?php
$renderer = new \BaconQrCode\Renderer\ImageRenderer(new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220), new \BaconQrCode\Renderer\Image\SvgImageBackEnd());
echo (new \BaconQrCode\Writer($renderer))->writeString($otp->getProvisioningUri());
?>
<p class="text-break"><code><?= Html::encode($otp->getSecret()) ?></code></p>
<?= Html::beginForm(['totp-confirm'], 'post') ?>
<?= Html::label(Html::encode(UM::t('front', 'Verification code')), 'um-confirm-code', ['class' => 'form-label']) ?>
<?= Html::textInput('code', '', ['id' => 'um-confirm-code', 'class' => 'form-control mb-3', 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'pattern' => '[0-9]{6}', 'maxlength' => 6, 'required' => true]) ?>
<?= Html::submitButton(Html::encode(UM::t('front', 'Confirm')), ['class' => 'btn btn-primary']) ?>
<?= Html::endForm() ?>
</section>

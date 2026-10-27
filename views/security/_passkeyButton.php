<?php
use yii\helpers\{Html, Url};
use webvimark\modules\UserManagement\UserManagementModule as UM;
webvimark\modules\UserManagement\assets\SecurityAsset::register($this);
echo Html::button(Html::encode(UM::t('front', $label)), [
    'type' => 'button', 'class' => 'btn btn-primary', 'data-um-passkey' => $operation,
    'data-options' => Url::to(['passkey-options']), 'data-finish' => Url::to(['passkey-finish']),
    'data-unsupported' => UM::t('front', 'Passkeys require HTTPS and a compatible browser.'),
    'data-failed' => UM::t('front', 'Verification failed. Try again.'),
    'data-cancelled' => UM::t('front', 'Verification cancelled. You can try again.'),
]);
?>

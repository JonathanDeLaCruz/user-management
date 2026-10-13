<?php
/**
 * @var $this yii\web\View
 * @var $user webvimark\modules\UserManagement\models\User
 */
use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule;

?>
<?php
$resetLink = Yii::$app->urlManager->createAbsoluteUrl(['/user-management/auth/confirm-email-receive', 'token' => $user->confirmationToken]);
?>

<?= Html::encode(UserManagementModule::t('front', 'Hello {username}, follow this link to confirm your email:', ['username' => $user->username])) ?>

<?= Html::a(Html::encode(UserManagementModule::t('front', 'Confirm E-mail')), $resetLink) ?>

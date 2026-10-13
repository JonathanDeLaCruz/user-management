<?php
/**
 * @var $this yii\web\View
 * @var $user webvimark\modules\UserManagement\models\User
 */
use yii\helpers\Html;
use webvimark\modules\UserManagement\UserManagementModule;

?>
<?php
$returnUrl = Yii::$app->user->returnUrl == Yii::$app->homeUrl ? null : rtrim(Yii::$app->homeUrl, '/') . Yii::$app->user->returnUrl;

$confirmLink = Yii::$app->urlManager->createAbsoluteUrl(['/user-management/auth/confirm-registration-email', 'token' => $user->confirmationToken, 'returnUrl'=>$returnUrl]);
?>

<?= Html::encode(UserManagementModule::t('front', 'You have registered on {site}.', ['site' => Yii::$app->urlManager->hostInfo])) ?>

<br/><br/>
<?= Html::encode(UserManagementModule::t('front', 'Follow this link to confirm your email and activate your account:')) ?>

<?= Html::a(Html::encode(UserManagementModule::t('front', 'Confirm E-mail')), $confirmLink) ?>

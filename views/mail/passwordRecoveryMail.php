<?php
/**
 * @var $this yii\web\View
 * @var $user webvimark\modules\UserManagement\models\User
 */
use yii\helpers\Html;

?>
<?php
$resetLink = Yii::$app->urlManager->createAbsoluteUrl(['/user-management/auth/password-recovery-receive', 'token' => $user->confirmationToken]);
?>

Hello <?= Html::encode($user->username) ?>, follow this link to reset your password:

<?= Html::a('Reset password', $resetLink) ?>

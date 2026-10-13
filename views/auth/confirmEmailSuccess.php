<?php

use webvimark\modules\UserManagement\UserManagementModule;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\User $user
 */

$this->title = UserManagementModule::t('front', 'E-mail confirmed');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="change-own-password-success">

	<div class="alert alert-success text-center">
		<?= UserManagementModule::t('front', 'E-mail confirmed') ?> - <b><?= \yii\helpers\Html::encode($user->email) ?></b>

		<?php if ( isset(Yii::$app->homeUrl) ): ?>
			<br/>
			<br/>
			<b><?= Html::a(UserManagementModule::t('front', 'Continue'), Yii::$app->homeUrl) ?></b>
		<?php endif; ?>
	</div>

</div>

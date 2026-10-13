<?php

use webvimark\modules\UserManagement\models\User;

use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var webvimark\modules\UserManagement\models\User $model
 */

$this->title = UserManagementModule::t('back', 'Editing user: ') . ' ' . $model->username;
$this->params['breadcrumbs'][] = ['label' => UserManagementModule::t('back', 'Users'), 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->username, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = UserManagementModule::t('back', 'Editing');
?>
<div class="user-update">

	<h2 class="lte-hide-title"><?= Html::encode($this->title) ?></h2>

	<div class="card um-card">
		<div class="card-body">

			<?= $this->render('_form', compact('model')) ?>
		</div>
	</div>

</div>

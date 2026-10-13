<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\modules\UserManagement\components\BaseController;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\web\NotFoundHttpException;
use Yii;

class UserPermissionController extends BaseController
{

	/**
	 * @param int $id User ID
	 *
	 * @throws \yii\web\NotFoundHttpException
	 * @return string
	 */
	public function actionSet($id)
	{
		$user = User::findOne($id);

		if ( !$user )
		{
			throw new NotFoundHttpException('User not found');
		}

		$permissionsByGroup = [];
		$permissions = Permission::find()
			->andWhere([
				\webvimark\modules\UserManagement\UserManagementModule::instance()->auth_item_table . '.name'=>array_keys(Permission::getUserPermissions($user->id))
			])
			->joinWith('group')
			->all();

		foreach ($permissions as $permission)
		{
			$permissionsByGroup[($permission->group?->name ?? 'Sin grupo')][] = $permission;
		}

		return $this->renderIsAjax('set', compact('user', 'permissionsByGroup'));
	}

	/**
	 * @param int $id - User ID
	 *
	 * @return \yii\web\Response
	 */
	public function actionSetRoles($id)
	{
		$target = User::findOne($id);
		if (!$target) throw new NotFoundHttpException();
		if ($target->superadmin && !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin) throw new \yii\web\ForbiddenHttpException();
		if ( !\webvimark\modules\UserManagement\models\User::component()->isSuperadmin AND \webvimark\modules\UserManagement\models\User::component()->id == $id )
		{
			Yii::$app->session->setFlash('error', UserManagementModule::t('back', 'You can not change own permissions'));
			return $this->redirect(['set', 'id'=>$id]);
		}

		$oldAssignments = array_keys(Role::getUserRoles($id));

		// To be sure that user didn't attempt to assign himself some unavailable roles
		$newAssignments = array_intersect(Role::getAvailableRoles(\webvimark\modules\UserManagement\models\User::component()->isSuperadmin, true), (array)Yii::$app->request->post('roles', []));

		$toAssign = array_diff($newAssignments, $oldAssignments);
		$toRevoke = array_diff($oldAssignments, $newAssignments);
		if (!\webvimark\modules\UserManagement\models\User::component()->isSuperadmin) $toRevoke = array_intersect($toRevoke, Role::getAvailableRoles(false, true));

		foreach ($toRevoke as $role)
		{
			User::revokeRole($id, $role);
		}

		foreach ($toAssign as $role)
		{
			User::assignRole($id, $role);
		}

		Yii::$app->session->setFlash('success', UserManagementModule::t('back', 'Saved'));

		return $this->redirect(['set', 'id'=>$id]);
	}
}

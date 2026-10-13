<?php

namespace webvimark\modules\UserManagement\components;

use yii\web\User;
use Yii;

/**
 * Class UserConfig
 * @extends User<\webvimark\modules\UserManagement\models\User>
 * @property-read bool $isSuperadmin
 * @property-read string|null $username
 * @package webvimark\modules\UserManagement\components
 */
class UserConfig extends User
{
	/**
	 * @inheritdoc
	 */
	public $identityClass = 'webvimark\modules\UserManagement\models\User';

	/**
	 * @inheritdoc
	 */
	public $enableAutoLogin = true;

	/**
 	 * @inheritdoc
	 */
	public $cookieLifetime = 2592000;

	/**
	 * @inheritdoc
	 */
	public $loginUrl = ['/user-management/auth/login'];

	/**
	 * Allows to call \webvimark\modules\UserManagement\models\User::component()->isSuperadmin
	 *
	 * @return bool
	 */
	public function getIsSuperadmin()
	{
		return $this->identity !== null && (int)$this->identity->status === \webvimark\modules\UserManagement\models\User::STATUS_ACTIVE && (int)$this->identity->superadmin === 1;
	}

	/**
	 * @return string
	 */
	public function getUsername()
	{
		return $this->identity?->username;
	}

	/**
	 * @inheritdoc
	 */
	protected function afterLogin($identity, $cookieBased, $duration)
	{
		AuthHelper::updatePermissions($identity);

		parent::afterLogin($identity, $cookieBased, $duration);
	}

}

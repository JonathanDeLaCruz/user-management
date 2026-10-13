<?php
namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\User;
use yii\base\Security;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use Yii;

/**
 * Parent class for User model
 *
 * @property integer $id
 * @property string $username
 * @property string $auth_key
 * @property string $password_hash
 * @property string $confirmation_token
 * @property integer $status
 * @property integer $superadmin
 * @property integer $created_at
 * @property integer $updated_at
 */
abstract class UserIdentity extends ActiveRecord implements IdentityInterface
{
	/**
	 * @inheritdoc
	 */
	public static function findIdentity($id)
	{
		return static::findOne(['id' => $id, 'status' => User::STATUS_ACTIVE]);
	}

	/**
	 * @inheritdoc
	 */
	public static function findIdentityByAccessToken($token, $type = null)
	{
		throw new \yii\base\NotSupportedException('Use a separate scoped, expiring API-token identity. Remember-me keys are not API tokens.');
	}

	/**
	 * Finds user by username
	 *
	 * @param  string      $username
	 * @return static|null
	 */
	public static function findByUsername($username)
	{
		return static::findOne(['username' => $username, 'status' => User::STATUS_ACTIVE]);
	}

	/**
	 * Finds user by confirmation token
	 *
	 * @param  string      $token confirmation token
	 * @return static|null|User
	 */
	public static function findByConfirmationToken($token, $purpose = 'password-reset')
	{
		if (!self::isValidConfirmationToken($token, $purpose)) return null;
		$expire    = \webvimark\modules\UserManagement\UserManagementModule::instance()->confirmationTokenExpire;

		$parts     = explode('_', $token);
		$timestamp = (int)end($parts);

		if ( $timestamp + $expire < time() )
		{
			// token expired
			return null;
		}

		return static::findOne([
			'confirmation_token' => hash('sha256', $token) . '_' . $timestamp,
			'status'             => User::STATUS_ACTIVE,
		]);
	}

	/**
	 * Finds user by confirmation token
	 *
	 * @param  string      $token confirmation token
	 * @return static|null|User
	 */
	public static function findInactiveByConfirmationToken($token, $purpose = 'registration')
	{
		if (!self::isValidConfirmationToken($token, $purpose)) return null;
		$expire    = \webvimark\modules\UserManagement\UserManagementModule::instance()->confirmationTokenExpire;

		$parts     = explode('_', $token);
		$timestamp = (int)end($parts);

		if ( $timestamp + $expire < time() )
		{
			// token expired
			return null;
		}

		return static::findOne([
			'confirmation_token' => hash('sha256', $token) . '_' . $timestamp,
			'status'             => User::STATUS_INACTIVE,
		]);
	}

	/**
	 * @inheritdoc
	 */
	public function getId()
	{
		return $this->getPrimaryKey();
	}

	/**
	 * @inheritdoc
	 */
	public function getAuthKey()
	{
		return $this->auth_key;
	}

	/**
	 * @inheritdoc
	 */
	public function validateAuthKey($authKey)
	{
		return is_string($authKey) && is_string($this->getAuthKey()) && hash_equals($this->getAuthKey(), $authKey);
	}

	/**
	 * Validates password
	 *
	 * @param  string  $password password to validate
	 * @return boolean if password provided is valid for current user
	 */
	public function validatePassword($password)
	{
		return Yii::$app->security->validatePassword($password, $this->password_hash);
	}

	/**
	 * Generates password hash from password and sets it to the model
	 *
	 * @param string $password
	 */
	public function setPassword($password)
	{
		if ( php_sapi_name() == 'cli' )
		{
			$security = new Security();
			$this->password_hash = $security->generatePasswordHash($password);
		}
		else
		{
			$this->password_hash = Yii::$app->security->generatePasswordHash($password);
		}
	}

	/**
	 * Generates "remember me" authentication key
	 */
	public function generateAuthKey()
	{
		if ( php_sapi_name() == 'cli' )
		{
			$security = new Security();
			$this->auth_key = $security->generateRandomString();
		}
		else
		{
			$this->auth_key = Yii::$app->security->generateRandomString();
		}
	}

	/**
	 * Generates new confirmation token
	 */
	public $confirmationToken;

	public function generateConfirmationToken($purpose = 'password-reset')
	{
		if (!in_array($purpose, ['password-reset', 'email', 'registration'], true)) throw new \InvalidArgumentException('Invalid token purpose');
		$timestamp = time();
		$this->confirmationToken = $purpose . '_' . Yii::$app->security->generateRandomString(32) . '_' . $timestamp;
		$this->confirmation_token = hash('sha256', $this->confirmationToken) . '_' . $timestamp;
	}

	private static function isValidConfirmationToken($token, $purpose): bool
	{
		if (!is_string($token) || !preg_match('/^' . preg_quote($purpose, '/') . '_[A-Za-z0-9_-]{32}_([0-9]{10})$/D', $token, $matches)) return false;
		$timestamp = (int)$matches[1];
		return $timestamp <= time() && $timestamp + \webvimark\modules\UserManagement\UserManagementModule::instance()->confirmationTokenExpire >= time();
	}

	/**
	 * Removes confirmation token
	 */
	public function removeConfirmationToken()
	{
		$this->confirmation_token = null;
	}
}

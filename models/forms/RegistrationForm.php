<?php
namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;
use yii\helpers\Html;

class RegistrationForm extends Model
{
	public $username;
	public $password;
	public $repeat_password;
	public $captcha;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		$rules = [
			['captcha', 'captcha', 'captchaAction'=>'/user-management/auth/captcha'],

			[['username', 'password', 'repeat_password', 'captcha'], 'required'],
			['username', 'trim'],

			['username', 'unique',
				'targetClass'     => 'webvimark\modules\UserManagement\models\User',
				'targetAttribute' => 'username',
			],



			['password', 'string', 'min' => 12, 'max' => 72],
			['password', 'match', 'pattern' => \webvimark\modules\UserManagement\UserManagementModule::instance()->passwordRegexp],

			['repeat_password', 'compare', 'compareAttribute'=>'password'],
		];

		if ( \webvimark\modules\UserManagement\UserManagementModule::instance()->useEmailAsLogin )
		{
			$rules[] = ['username', 'email'];
		}
		else
		{
			$rules[] = ['username', 'string', 'max' => 50];

			$rules[] = ['username', 'match', 'pattern'=>\webvimark\modules\UserManagement\UserManagementModule::instance()->registrationRegexp];
			$rules[] = ['username', 'match', 'not'=>true, 'pattern'=>\webvimark\modules\UserManagement\UserManagementModule::instance()->registrationBlackRegexp];
		}

		return $rules;
	}

	/**
	 * Remove possible XSS stuff
	 *
	 * @param $attribute
	 */
	public function purgeXSS($attribute)
	{
		$this->$attribute = Html::encode($this->$attribute);
	}

	/**
	 * @return array
	 */
	public function attributeLabels()
	{
		return [
			'username'        => \webvimark\modules\UserManagement\UserManagementModule::instance()->useEmailAsLogin ? 'E-mail' : UserManagementModule::t('front', 'Login'),
			'password'        => UserManagementModule::t('front', 'Password'),
			'repeat_password' => UserManagementModule::t('front', 'Repeat password'),
			'captcha'         => UserManagementModule::t('front', 'Captcha'),
		];
	}

	/**
	 * @param bool $performValidation
	 *
	 * @return bool|User
	 */
	public function registerUser($performValidation = true)
	{
		if ($performValidation && !$this->validate()) return false;
		$module = \webvimark\modules\UserManagement\UserManagementModule::instance();
		$needsConfirmation = $module->useEmailAsLogin && $module->emailConfirmationRequired;
		$user = new User(['username'=>$this->username, 'password'=>$this->password, 'status'=>$needsConfirmation ? User::STATUS_INACTIVE : User::STATUS_ACTIVE]);
		if ($module->useEmailAsLogin) $user->email = $this->username;
		if ($needsConfirmation) $user->generateConfirmationToken('registration');
		$transaction = Yii::$app->db->beginTransaction();
		try {
			if (!$user->save()) { $transaction->rollBack(); $this->addError('username', UserManagementModule::t('front', 'Login has been taken')); return false; }
			$this->saveProfile($user);
			if ($needsConfirmation && !$this->sendConfirmationEmail($user)) { $transaction->rollBack(); $this->addError('username', UserManagementModule::t('front', 'Could not send confirmation email')); return false; }
			$transaction->commit();
			return $user;
		} catch (\yii\db\IntegrityException $e) {
			$transaction->rollBack(); $this->addError('username', UserManagementModule::t('front','Login has been taken')); return false;
		} catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
	}

	/**
	 * Implement your own logic if you have user profile and save some there after registration
	 *
	 * @param User $user
	 */
	protected function saveProfile($user)
	{
	}


	/**
	 * @param User $user
	 *
	 * @return bool
	 */
	protected function sendConfirmationEmail($user)
	{
		return Yii::$app->mailer->compose(\webvimark\modules\UserManagement\UserManagementModule::instance()->mailerOptions['registrationFormViewFile'], ['user' => $user])
			->setFrom(\webvimark\modules\UserManagement\UserManagementModule::instance()->mailerOptions['from'])
			->setTo($user->email)
			->setSubject(UserManagementModule::t('front', 'E-mail confirmation for') . ' ' . Yii::$app->name)
			->send();
	}

	/**
	 * Check received confirmation token and if user found - activate it, set username, roles and log him in
	 *
	 * @param string $token
	 *
	 * @return bool|User
	 */
	public function checkConfirmationToken($token)
	{
		$user = User::findInactiveByConfirmationToken($token, 'registration');

		if ( $user )
		{
			$user->username = $user->email;
			$user->status = User::STATUS_ACTIVE;
			$user->email_confirmed = 1;
			$user->removeConfirmationToken();
			$user->save(false);

			$roles = (array)\webvimark\modules\UserManagement\UserManagementModule::instance()->rolesAfterRegistration;

			foreach ($roles as $role)
			{
				User::assignRole($user->id, $role);
			}

			\webvimark\modules\UserManagement\models\User::component()->login($user);

			return $user;
		}

		return false;
	}
}

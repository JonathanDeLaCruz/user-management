<?php
namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;

class ChangeOwnPasswordForm extends Model
{
	/**
	 * @var User
	 */
	public $user;

	/**
	 * @var string
	 */
	public $current_password;
	/**
	 * @var string
	 */
	public $password;
	/**
	 * @var string
	 */
	public $repeat_password;

	/**
	 * @inheritdoc
	 */
	public function rules()
	{
		return [
			[['password', 'repeat_password'], 'required'],
			[['password', 'repeat_password', 'current_password'], 'string', 'max'=>255],
			['password', 'string', 'min' => 12, 'max' => 72],
			['password', 'match', 'pattern' => \webvimark\modules\UserManagement\UserManagementModule::instance()->passwordRegexp],

			['repeat_password', 'compare', 'compareAttribute'=>'password'],

			['current_password', 'required', 'except'=>'restoreViaEmail'],
			['current_password', 'validateCurrentPassword', 'except'=>'restoreViaEmail'],
		];
	}

	public function attributeLabels()
	{
		return [
			'current_password' => UserManagementModule::t('back', 'Current password'),
			'password'         => UserManagementModule::t('front', 'Password'),
			'repeat_password'  => UserManagementModule::t('front', 'Repeat password'),
		];
	}


	/**
	 * Validates current password
	 */
	public function validateCurrentPassword()
	{
		if ( !\webvimark\modules\UserManagement\UserManagementModule::instance()->checkAttempts((string)$this->user->id, 'password-change') )
		{
			$this->addError('current_password', UserManagementModule::t('back', 'Too many attempts'));

			return false;
		}

		if ( !Yii::$app->security->validatePassword($this->current_password, $this->user->password_hash) )
		{
			$this->addError('current_password', UserManagementModule::t('back', "Wrong password"));
		}
	}

	/**
	 * @param bool $performValidation
	 *
	 * @return bool
	 */
	public function changePassword($performValidation = true)
	{
		if ( $performValidation AND !$this->validate() )
		{
			return false;
		}

		$expectedToken = $this->user->confirmation_token;
		if ($this->scenario === 'restoreViaEmail') {
			if (!$expectedToken) return false;
			$this->user->setPassword($this->password);
			$this->user->generateAuthKey();
            $changed = User::updateAll(['password_hash'=>$this->user->password_hash, 'auth_key'=>$this->user->auth_key, 'confirmation_token'=>null, 'updated_at'=>time()], ['id'=>$this->user->id, 'confirmation_token'=>$expectedToken]) === 1;
            if ($changed) {
                \webvimark\modules\UserManagement\components\SecuritySessions::revoke((int)$this->user->id);
                \webvimark\modules\UserManagement\components\AccountSecurity::notify($this->user, 'Password changed');
            }
            return $changed;
		}
		$this->user->password = $this->password;
		$this->user->removeConfirmationToken();
		$this->user->generateAuthKey();
		return $this->user->save();
	}
}

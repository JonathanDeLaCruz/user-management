<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\modules\UserManagement\components\BaseController;
use webvimark\modules\UserManagement\components\UserAuthEvent;
use webvimark\modules\UserManagement\models\forms\ChangeOwnPasswordForm;
use webvimark\modules\UserManagement\models\forms\ConfirmEmailForm;
use webvimark\modules\UserManagement\models\forms\LoginForm;
use webvimark\modules\UserManagement\models\forms\PasswordRecoveryForm;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\widgets\ActiveForm;

class AuthController extends BaseController
{
	public $layout = '@webvimark/modules/UserManagement/views/layouts/auth';

	/**
	 * @var array
	 */
	public $freeAccessActions = ['login', 'logout', 'confirm-registration-email', 'password-recovery', 'password-recovery-receive', 'confirm-email-receive', 'change-own-password', 'confirm-email'];

	/**
	 * @return array
	 */
	public function actions()
	{
		return [
			'captcha' => UserManagementModule::instance()->captchaOptions,
		];
	}

	/**
	 * Login form
	 *
	 * @return string
	 */
	public function actionLogin()
	{
		if ( !\webvimark\modules\UserManagement\models\User::component()->isGuest )
		{
			return $this->redirect(UserManagementModule::instance()->resolveAfterLoginUrl());
		}

		$model = new LoginForm();

		if ( Yii::$app->request->isAjax AND $model->load(Yii::$app->request->post()) )
		{
			Yii::$app->response->format = Response::FORMAT_JSON;
			return ActiveForm::validate($model);
		}

		if ( $model->load(Yii::$app->request->post()) AND $model->login() )
		{
			return $this->redirect(UserManagementModule::instance()->resolveAfterLoginUrl(true));
		}

        if ($model->requiresMfa) return $this->redirect(['/user-management/security/challenge']);
		return $this->renderIsAjax('login', compact('model'));
	}

	/**
	 * Logout and redirect to home page
	 */
	public function actionLogout()
	{
		\webvimark\modules\UserManagement\models\User::component()->logout();

		return $this->redirect(Yii::$app->homeUrl);
	}

	/**
	 * Change your own password
	 *
	 * @throws \yii\web\ForbiddenHttpException
	 * @return string|\yii\web\Response
	 */
	public function actionChangeOwnPassword()
	{
		if ( \webvimark\modules\UserManagement\models\User::component()->isGuest )
		{
			return $this->goHome();
		}

		$user = User::getCurrentUser();

		if ( $user->status != User::STATUS_ACTIVE )
		{
			throw new ForbiddenHttpException();
		}

		$model = new ChangeOwnPasswordForm(['user'=>$user]);


		if ( Yii::$app->request->isAjax AND $model->load(Yii::$app->request->post()) )
		{
			Yii::$app->response->format = Response::FORMAT_JSON;
			return ActiveForm::validate($model);
		}

		if ( $model->load(Yii::$app->request->post()) AND $model->changePassword() )
		{
			return $this->renderIsAjax('changeOwnPasswordSuccess');
		}

		return $this->renderIsAjax('changeOwnPassword', compact('model'));
	}

	/**
	 * Registration logic
	 *
	 * @return string
	 */
	public function actionRegistration()
	{
		if ( !\webvimark\modules\UserManagement\models\User::component()->isGuest )
		{
			return $this->goHome();
		}

		$model = Yii::createObject(UserManagementModule::instance()->registrationFormClass);


		if ( Yii::$app->request->isAjax AND $model->load(Yii::$app->request->post()) )
		{

			Yii::$app->response->format = Response::FORMAT_JSON;

			// Ajax validation breaks captcha. See https://github.com/yiisoft/yii2/issues/6115
			// Thanks to TomskDiver
			$validateAttributes = $model->attributes;
			unset($validateAttributes['captcha']);

			return ActiveForm::validate($model, $validateAttributes);
		}

		if ( $model->load(Yii::$app->request->post()) AND $model->validate() )
		{
			// Trigger event "before registration" and checks if it's valid
			if ( $this->triggerModuleEvent(UserAuthEvent::BEFORE_REGISTRATION, ['model'=>$model]) )
			{
				$user = $model->registerUser(false);

				// Trigger event "after registration" and checks if it's valid
				if ( $this->triggerModuleEvent(UserAuthEvent::AFTER_REGISTRATION, ['model'=>$model, 'user'=>$user]) )
				{
					if ( $user )
					{
						if ( \webvimark\modules\UserManagement\UserManagementModule::instance()->useEmailAsLogin AND \webvimark\modules\UserManagement\UserManagementModule::instance()->emailConfirmationRequired )
						{
							return $this->renderIsAjax('registrationWaitForEmailConfirmation', compact('user'));
						}
						else
						{
							$roles = (array)UserManagementModule::instance()->rolesAfterRegistration;

							foreach ($roles as $role)
							{
								User::assignRole($user->id, $role);
							}

							\webvimark\modules\UserManagement\models\User::component()->login($user);

							return $this->redirect(\webvimark\modules\UserManagement\models\User::component()->returnUrl);
						}

					}
				}
			}

		}

		return $this->renderIsAjax('registration', compact('model'));
	}


	/**
	 * Receive token after registration, find user by it and confirm email
	 *
	 * @param string $token
	 *
	 * @throws \yii\web\NotFoundHttpException
	 * @return string|\yii\web\Response
	 */
	public function actionConfirmRegistrationEmail($token)
	{
		if ( \webvimark\modules\UserManagement\UserManagementModule::instance()->useEmailAsLogin AND \webvimark\modules\UserManagement\UserManagementModule::instance()->emailConfirmationRequired )
		{
			$model = Yii::createObject(UserManagementModule::instance()->registrationFormClass);

			$user = $model->checkConfirmationToken($token);

			if ( $user )
			{
				return $this->renderIsAjax('confirmEmailSuccess', compact('user'));
			}

			throw new NotFoundHttpException(UserManagementModule::t('front', 'Token not found. It may be expired'));
		}
		throw new NotFoundHttpException('La confirmación de registro no está habilitada.');
	}


	/**
	 * Form to recover password
	 *
	 * @return string|\yii\web\Response
	 */
	public function actionPasswordRecovery()
	{
		if ( !\webvimark\modules\UserManagement\models\User::component()->isGuest )
		{
			return $this->goHome();
		}

		$model = new PasswordRecoveryForm();

		if ( Yii::$app->request->isAjax AND $model->load(Yii::$app->request->post()) )
		{
			Yii::$app->response->format = Response::FORMAT_JSON;

			// Ajax validation breaks captcha. See https://github.com/yiisoft/yii2/issues/6115
			// Thanks to TomskDiver
			$validateAttributes = $model->attributes;
			unset($validateAttributes['captcha']);

			return ActiveForm::validate($model, $validateAttributes);
		}

		if ( $model->load(Yii::$app->request->post()) AND $model->validate() )
		{
			if ( $this->triggerModuleEvent(UserAuthEvent::BEFORE_PASSWORD_RECOVERY_REQUEST, ['model'=>$model]) )
			{
				if ( $model->sendEmail(false) )
				{
					if ( $this->triggerModuleEvent(UserAuthEvent::AFTER_PASSWORD_RECOVERY_REQUEST, ['model'=>$model]) )
					{
						return $this->renderIsAjax('passwordRecoverySuccess');
					}
				}
				else
				{
					Yii::$app->session->setFlash('error', UserManagementModule::t('front', "Unable to send message for email provided"));
				}
			}
		}

		return $this->renderIsAjax('passwordRecovery', compact('model'));
	}

	/**
	 * Receive token, find user by it and show form to change password
	 *
	 * @param string $token
	 *
	 * @throws \yii\web\NotFoundHttpException
	 * @return string|\yii\web\Response
	 */
	public function actionPasswordRecoveryReceive($token)
	{
		if ( !\webvimark\modules\UserManagement\models\User::component()->isGuest )
		{
			return $this->goHome();
		}

		$user = User::findByConfirmationToken($token, 'password-reset');

		if ( !$user )
		{
			throw new NotFoundHttpException(UserManagementModule::t('front', 'Token not found. It may be expired. Try reset password once more'));
		}

		$model = new ChangeOwnPasswordForm([
			'scenario'=>'restoreViaEmail',
			'user'=>$user,
		]);

		if ( $model->load(Yii::$app->request->post()) AND $model->validate() )
		{
			if ( $this->triggerModuleEvent(UserAuthEvent::BEFORE_PASSWORD_RECOVERY_COMPLETE, ['model'=>$model]) )
			{
				if (!$model->changePassword(false)) { throw new \yii\web\ServerErrorHttpException('No se pudo guardar la contraseña.'); }

				if ( $this->triggerModuleEvent(UserAuthEvent::AFTER_PASSWORD_RECOVERY_COMPLETE, ['model'=>$model]) )
				{
					return $this->renderIsAjax('changeOwnPasswordSuccess');
				}
			}
		}

		return $this->renderIsAjax('changeOwnPassword', compact('model'));
	}

	/**
	 * @return string|\yii\web\Response
	 */
	public function actionConfirmEmail()
	{
		if ( \webvimark\modules\UserManagement\models\User::component()->isGuest )
		{
			return $this->goHome();
		}

		$user = User::getCurrentUser();

		if ( $user->email_confirmed == 1 )
		{
			return $this->renderIsAjax('confirmEmailSuccess', compact('user'));
		}

		$model = new ConfirmEmailForm([
			'email'=>$user->email,
			'user'=>$user,
		]);

		if ( Yii::$app->request->isAjax AND $model->load(Yii::$app->request->post()) )
		{
			Yii::$app->response->format = Response::FORMAT_JSON;
			return ActiveForm::validate($model);
		}

		if ( $model->load(Yii::$app->request->post()) AND $model->validate() )
		{
			if ( $this->triggerModuleEvent(UserAuthEvent::BEFORE_EMAIL_CONFIRMATION_REQUEST, ['model'=>$model]) )
			{
				if ( $model->sendEmail(false) )
				{
					if ( $this->triggerModuleEvent(UserAuthEvent::AFTER_EMAIL_CONFIRMATION_REQUEST, ['model'=>$model]) )
					{
						return $this->refresh();
					}
				}
				else
				{
					Yii::$app->session->setFlash('error', UserManagementModule::t('front', "Unable to send message for email provided"));
				}
			}
		}

		return $this->renderIsAjax('confirmEmail', compact('model'));
	}

	/**
	 * Receive token, find user by it and confirm email
	 *
	 * @param string $token
	 *
	 * @throws \yii\web\NotFoundHttpException
	 * @return string|\yii\web\Response
	 */
	public function actionConfirmEmailReceive($token)
	{
		$user = User::findByConfirmationToken($token, 'email');

		if ( !$user )
		{
			throw new NotFoundHttpException(UserManagementModule::t('front', 'Token not found. It may be expired'));
		}

		if (!$this->triggerModuleEvent(UserAuthEvent::BEFORE_EMAIL_CONFIRMATION_COMPLETE, ['user'=>$user])) throw new ForbiddenHttpException();
		if (User::updateAll(['email_confirmed'=>1, 'confirmation_token'=>null, 'updated_at'=>time()], ['id'=>$user->id, 'confirmation_token'=>$user->confirmation_token]) !== 1) throw new NotFoundHttpException('Token ya utilizado.');
		$user->refresh();
		$this->triggerModuleEvent(UserAuthEvent::AFTER_EMAIL_CONFIRMATION_COMPLETE, ['user'=>$user]);

		return $this->renderIsAjax('confirmEmailSuccess', compact('user'));
	}

	/**
	 * Universal method for triggering events like "before registration", "after registration" and so on
	 *
	 * @param string $eventName
	 * @param array  $data
	 *
	 * @return bool
	 */
	protected function triggerModuleEvent($eventName, $data = [])
	{
		$event = new UserAuthEvent($data);

		UserManagementModule::instance()->trigger($eventName, $event);

		return $event->isValid;
	}
}

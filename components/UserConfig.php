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
    private ?string $verifiedMethod = null;

    public function completeSecureLogin(\webvimark\modules\UserManagement\models\User $identity, string $method): bool
    {
        if (!in_array($method, ['passkey', 'totp'], true)) throw new \InvalidArgumentException('Invalid authentication method.');
        $this->verifiedMethod = $method;
        try { return $this->login($identity, 0); }
        finally { $this->verifiedMethod = null; }
    }

    protected function beforeLogin($identity, $cookieBased, $duration)
    {
        if ($cookieBased) return false;
        if (!AccountSecurity::ipAllowed($identity)) return false;
        if (AccountSecurity::requiresMfa($identity) && ($cookieBased || !in_array($this->verifiedMethod, AccountSecurity::strict($identity) ? ['passkey'] : ['passkey', 'totp'], true))) return false;
        return parent::beforeLogin($identity, $cookieBased, $duration);
    }

    protected function renewAuthStatus()
    {
        parent::renewAuthStatus();
        $identity = $this->getIdentity(false);
        if ($identity !== null && !SecuritySessions::valid($identity)) $this->logout(false);
    }

    protected function beforeLogout($identity)
    {
        if (!parent::beforeLogout($identity)) return false;
        if ($this->enableSession) SecuritySessions::revoke((int)$identity->getId(), SecuritySessions::currentId());
        foreach (['um_pending_login', 'um_ceremony', 'um_recovered_user', 'um_security_verified', 'um_totp_setup', 'um_session_token'] as $key) Yii::$app->session->remove($key);
        return true;
    }
	/**
	 * @inheritdoc
	 */
	public $identityClass = 'webvimark\modules\UserManagement\models\User';

	/**
	 * @inheritdoc
	 */
	public $enableAutoLogin = false;

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
        SecuritySessions::start($identity, $this->verifiedMethod ?? 'password');
        foreach (['um_pending_login', 'um_ceremony', 'um_recovered_user', 'um_totp_setup'] as $key) Yii::$app->session->remove($key);
		AuthHelper::updatePermissions($identity);

		parent::afterLogin($identity, $cookieBased, $duration);
	}

}

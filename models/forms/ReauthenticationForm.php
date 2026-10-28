<?php
namespace webvimark\modules\UserManagement\models\forms;

use yii\base\Model;
use webvimark\modules\UserManagement\components\{AccountSecurity, ExternalAuth};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

final class ReauthenticationForm extends Model
{
    public User $user;
    public $password = '';
    public $code = '';
    private ?bool $hasTotp = null;

    public function formName(): string { return ''; }

    public function rules(): array
    {
        return [
            [['password', 'code'], 'string'],
            ['password', 'required', 'when' => fn() => !ExternalAuth::proof($this->user)],
            ['code', 'required', 'when' => fn() => $this->requiresCode()],
            ['code', 'match', 'pattern' => '/^[0-9]{6}$/D', 'when' => fn() => $this->requiresCode(),
                'message' => UM::t('front', 'Enter a valid six-digit authenticator code.')],
        ];
    }

    public function attributeLabels(): array
    {
        return ['password' => UM::t('front', 'Password'), 'code' => UM::t('front', 'Authenticator code')];
    }

    public function requiresCode(): bool { return $this->hasTotp ??= !empty(AccountSecurity::profile($this->user)['totp_secret']); }

    public function verificationMethod(): string { return $this->requiresCode() ? 'totp' : 'password'; }

    public function verify(): bool
    {
        if (AccountSecurity::strict($this->user) || (AccountSecurity::requiresMfa($this->user) && !$this->requiresCode())) {
            $this->addError('password', UM::t('front', 'Verify with a passkey'));
            return false;
        }
        if (!$this->validate()) return false;
        if (!ExternalAuth::proof($this->user) && !$this->user->validatePassword($this->password)) {
            $this->addError('password', UM::t('front', 'Password is incorrect.'));
            return false;
        }
        if ($this->requiresCode() && !AccountSecurity::verifyTotp($this->user, $this->code)) {
            $this->addError('code', UM::t('front', 'The authenticator code is incorrect, expired or already used.'));
            return false;
        }
        return true;
    }
}

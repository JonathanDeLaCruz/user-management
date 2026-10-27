<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use OTPHP\TOTP;
use yii\db\Query;
use yii\web\ForbiddenHttpException;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

final class AccountSecurity
{
    public static function profile(User $user): array
    {
        $query = (new Query())->from('{{%um_mfa}}')->where(['user_id' => $user->id]);
        $row = $query->one();
        if (!$row) {
            Yii::$app->db->createCommand()->upsert('{{%um_mfa}}', [
                'user_id' => $user->id, 'user_handle' => bin2hex(random_bytes(32)),
            ], false)->execute();
            $row = $query->one();
        }
        return $row;
    }

    public static function passkeys(User $user): array
    {
        return (new Query())->from('{{%um_passkey}}')->where(['user_id' => $user->id])->orderBy(['created_at' => SORT_ASC])->all();
    }

    public static function policyRequires(User $user): bool
    {
        $module = UM::instance();
        if ($module->mfaPolicy === 'all') return true;
        if ($module->mfaPolicy !== 'admins') return false;
        if ((int)$user->superadmin === 1) return true;
        foreach (Yii::$app->authManager->getRolesByUser($user->id) as $role) {
            if (in_array($role->name, $module->mfaRequiredRoles, true)
                || array_intersect(array_keys(Yii::$app->authManager->getChildRoles($role->name)), $module->mfaRequiredRoles)) return true;
        }
        return false;
    }

    public static function strict(User $user): bool
    {
        return UM::instance()->requirePhishingResistant && (self::policyRequires($user) || self::passkeys($user) !== []);
    }

    public static function enrolled(User $user): bool
    {
        return self::passkeys($user) !== [] || !empty(self::profile($user)['totp_secret']);
    }

    public static function requiresMfa(User $user): bool
    {
        return self::policyRequires($user) || self::enrolled($user);
    }

    public static function begin(User $user): void
    {
        Yii::$app->session->regenerateID(true);
        Yii::$app->session->set('um_pending_login', [
            'user' => $user->id, 'key' => hash('sha256', $user->auth_key), 'expires' => time() + 300,
        ]);
        Yii::$app->session->remove('um_ceremony');
        Audit::record('mfa.challenge', (string)$user->id);
    }

    public static function pending(): ?User
    {
        $state = Yii::$app->session->get('um_pending_login');
        $user = is_array($state) && ($state['expires'] ?? 0) >= time() ? User::findIdentity($state['user'] ?? null) : null;
        if (!$user || !hash_equals($state['key'], hash('sha256', $user->auth_key)) || !self::ipAllowed($user)) {
            Yii::$app->session->remove('um_pending_login');
            return null;
        }
        return $user;
    }

    public static function ipAllowed(User $user): bool
    {
        return !$user->bind_to_ip || in_array(Yii::$app->request->userIP, array_map('trim', explode(',', $user->bind_to_ip)), true);
    }

    public static function rate(User $user, string $action): void
    {
        if (!UM::instance()->checkAttempts((string)$user->id, 'security-' . $action)) {
            throw new ForbiddenHttpException(UM::t('front', 'Too many attempts'));
        }
    }

    public static function markRecent(User $user, string $method): void
    {
        Yii::$app->session->set('um_security_verified', ['at' => time(), 'method' => $method, 'key' => hash('sha256', $user->auth_key)]);
    }

    public static function recent(User $user): bool
    {
        $state = Yii::$app->session->get('um_security_verified', []);
        return ($state['at'] ?? 0) >= time() - 300 && hash_equals(hash('sha256', $user->auth_key), $state['key'] ?? '')
            && (!self::requiresMfa($user) || in_array($state['method'] ?? '', self::strict($user) ? ['passkey'] : ['passkey', 'totp'], true));
    }

    public static function mayEnroll(User $user): bool
    {
        // A password-only pending session can enroll only when no factor exists yet.
        return self::recent($user) || self::recovered($user) || (User::component()->isGuest && self::pending()?->id === $user->id && !self::enrolled($user));
    }

    private static function key(): string
    {
        $encoded = UM::instance()->mfaEncryptionKey ?: getenv('UM_MFA_ENCRYPTION_KEY');
        $key = base64_decode((string)$encoded, true);
        if ($key === false || strlen($key) !== 32) throw new \yii\base\InvalidConfigException('Configure UM_MFA_ENCRYPTION_KEY with a base64-encoded random 32-byte key.');
        return $key;
    }

    public static function encrypt(string $secret, int $userId): string
    {
        return base64_encode(Yii::$app->security->encryptByKey($secret, self::key(), 'um-totp:' . $userId));
    }

    private static function decrypt(string $cipher, int $userId): string
    {
        $value = Yii::$app->security->decryptByKey((string)base64_decode($cipher, true), self::key(), 'um-totp:' . $userId);
        if ($value === false) throw new \yii\base\InvalidConfigException('The authenticator secret could not be decrypted. Restore the original encryption key.');
        return $value;
    }

    public static function totpSetup(User $user): TOTP
    {
        if (self::strict($user) || !self::mayEnroll($user)) throw new ForbiddenHttpException();
        $otp = TOTP::generate();
        $otp->setIssuer(Yii::$app->name);
        $otp->setLabel($user->username);
        Yii::$app->session->set('um_totp_setup', ['user' => $user->id, 'key' => hash('sha256', $user->auth_key),
            'expires' => time() + 300, 'secret' => self::encrypt($otp->getSecret(), $user->id)]);
        return $otp;
    }

    private static function matchingStep(string $secret, string $code): ?int
    {
        if (!preg_match('/^[0-9]{6}$/D', $code)) return null;
        $otp = TOTP::createFromSecret($secret);
        $step = intdiv(time(), 30);
        foreach ([$step, $step - 1, $step + 1] as $candidate) {
            if ($otp->verify($code, $candidate * 30)) return $candidate;
        }
        return null;
    }

    public static function confirmTotp(User $user, string $code): bool
    {
        self::rate($user, 'totp-setup');
        if (self::strict($user) || !self::mayEnroll($user)) return false;
        $state = Yii::$app->session->get('um_totp_setup', []);
        if (($state['user'] ?? null) !== $user->id || ($state['expires'] ?? 0) < time()
            || !hash_equals(hash('sha256', $user->auth_key), $state['key'] ?? '')) return false;
        $step = self::matchingStep(self::decrypt($state['secret'], $user->id), $code);
        if ($step === null) return false;
        self::profile($user);
        Yii::$app->db->createCommand()->update('{{%um_mfa}}', ['totp_secret' => $state['secret'], 'totp_step' => $step], ['user_id' => $user->id])->execute();
        Yii::$app->session->remove('um_totp_setup');
        self::markRecent($user, 'totp');
        self::notify($user, 'Authenticator enabled');
        return true;
    }

    public static function verifyTotp(User $user, string $code): bool
    {
        self::rate($user, 'totp');
        if (self::strict($user)) return false;
        $row = self::profile($user);
        if (!$row['totp_secret']) return false;
        $step = self::matchingStep(self::decrypt($row['totp_secret'], $user->id), $code);
        if ($step === null || $step <= (int)$row['totp_step']) return false;
        return Yii::$app->db->createCommand()->update('{{%um_mfa}}', ['totp_step' => $step], [
            'user_id' => $user->id, 'totp_step' => $row['totp_step'], 'totp_secret' => $row['totp_secret'],
        ])->execute() === 1;
    }

    public static function recoveryCodes(User $user): array
    {
        if (self::strict($user) || !self::recent($user) || !self::enrolled($user)) throw new ForbiddenHttpException();
        $tx = Yii::$app->db->beginTransaction();
        try {
            Yii::$app->db->createCommand()->delete('{{%um_recovery_code}}', ['user_id' => $user->id])->execute();
            $codes = [];
            for ($i = 0; $i < 10; $i++) {
                $code = bin2hex(random_bytes(16));
                $codes[] = implode('-', str_split($code, 8));
                Yii::$app->db->createCommand()->insert('{{%um_recovery_code}}', ['hash' => hash('sha256', $code), 'user_id' => $user->id, 'created_at' => time()])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) { $tx->rollBack(); throw $e; }
        self::notify($user, 'Recovery codes regenerated');
        return $codes;
    }

    public static function recover(User $user, string $code): bool
    {
        self::rate($user, 'recovery');
        // Typed recovery codes cannot satisfy the phishing-resistant administrator policy.
        if (self::strict($user)) return false;
        $code = strtolower(str_replace('-', '', trim($code)));
        if (!preg_match('/^[a-f0-9]{32}$/D', $code)) return false;
        if (Yii::$app->db->createCommand()->delete('{{%um_recovery_code}}', ['hash' => hash('sha256', $code), 'user_id' => $user->id])->execute() !== 1) return false;
        // Recovery only grants a limited enrollment state, never a full login.
        Yii::$app->session->set('um_recovered_user', ['user' => $user->id, 'key' => hash('sha256', $user->auth_key), 'expires' => time() + 300]);
        self::notify($user, 'Recovery code used');
        return true;
    }

    public static function recovered(User $user): bool
    {
        $state = Yii::$app->session->get('um_recovered_user', []);
        return self::pending()?->id === $user->id && ($state['user'] ?? null) === $user->id
            && ($state['expires'] ?? 0) >= time() && hash_equals(hash('sha256', $user->auth_key), $state['key'] ?? '');
    }

    public static function notify(User $user, string $event): void
    {
        Audit::record('security.' . $event, (string)$user->id);
        if (!$user->email || !(int)$user->email_confirmed || !Yii::$app->has('mailer')) return;
        $from = UM::instance()->mailerOptions['from'] ?? null;
        if (!$from) return;
        try {
            $sent = Yii::$app->mailer->compose()->setFrom($from)->setTo($user->email)
                ->setSubject(UM::t('front', 'Account security notification'))
                ->setTextBody(UM::t('front', $event) . "\n" . gmdate('Y-m-d H:i:s') . ' UTC')->send();
            if (!$sent) Audit::record('notification.failed', (string)$user->id);
        } catch (\Throwable $e) { Audit::record('notification.failed', (string)$user->id); }
    }
}

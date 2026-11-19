<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\Query;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

final class SecuritySessions
{
    public const EVENT_SESSIONS_REVOKED = 'sessionsRevoked';
    private const REASONS = ['logout', 'revoked', 'revoked-others', 'password-changed', 'account-disabled', 'account-deleted', 'mfa-reset'];

    public static function start(User $user, string $method): void
    {
        if (!User::component()->enableSession) return;
        $token = Yii::$app->security->generateRandomString(64);
        Yii::$app->session->set('um_session_token', $token);
        Yii::$app->session->set('um_security_verified', ['at' => time(), 'method' => $method, 'key' => hash('sha256', $user->auth_key)]);
        Yii::$app->db->createCommand()->insert('{{%um_session}}', [
            'id' => hash('sha256', $token), 'user_id' => $user->id,
            'auth_key_hash' => hash('sha256', $user->auth_key), 'method' => $method,
            'password_digest' => hash('sha256', (string)$user->password_hash),
            'ip' => Yii::$app->request->userIP, 'user_agent' => mb_substr((string)Yii::$app->request->userAgent, 0, 255),
            'created_at' => time(), 'last_seen_at' => time(), 'expires_at' => time() + UM::instance()->sessionLifetime,
        ])->execute();
        Audit::record('session.started', (string)$user->id, ['method' => $method]);
    }

    public static function currentId(): string
    {
        return hash('sha256', (string)Yii::$app->session->get('um_session_token', ''));
    }

    /** Read-only API: does not renew the session, disclose failures or grant authentication. */
    public static function isActive(int $userId, string $sessionId): bool
    {
        if ($userId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $sessionId)) return false;
        $user = User::findIdentity($userId);
        if (!$user) return false;
        $row = (new Query())->from('{{%um_session}}')->where(['id' => $sessionId, 'user_id' => $userId, 'revoked_at' => null])->one();
        if (!$row || (UM::instance()->localAuthentication && $row['method'] === 'central')) return false;
        if (AccountSecurity::requiresMfa($user) && !in_array($row['method'], AccountSecurity::strict($user) ? ['passkey'] : ['passkey', 'totp'], true)) return false;
        return $row && (int)$row['expires_at'] > time()
            && (int)$row['last_seen_at'] + UM::instance()->sessionIdleTimeout > time()
            && hash_equals((string)$row['auth_key_hash'], hash('sha256', $user->auth_key))
            && hash_equals((string)$row['password_digest'], hash('sha256', (string)$user->password_hash));
    }

    public static function valid(User $user): bool
    {
        if (!AccountSecurity::ipAllowed($user) || !self::isActive((int)$user->id, self::currentId())) return false;
        $row = (new Query())->from('{{%um_session}}')->where(['id' => self::currentId(), 'user_id' => $user->id, 'revoked_at' => null])->one();
        if (!$row || $row['expires_at'] <= time() || $row['last_seen_at'] + UM::instance()->sessionIdleTimeout <= time()
            || !hash_equals($row['auth_key_hash'], hash('sha256', $user->auth_key))) return false;
        if (UM::instance()->localAuthentication && $row['method'] === 'central') return false;
        if (AccountSecurity::requiresMfa($user) && !in_array($row['method'], AccountSecurity::strict($user) ? ['passkey'] : ['passkey', 'totp'], true)) return false;
        if ($row['last_seen_at'] < time() - 60) Yii::$app->db->createCommand()->update('{{%um_session}}', ['last_seen_at' => time()], ['id' => $row['id'], 'revoked_at' => null])->execute();
        return true;
    }

    public static function revoke(int $userId, ?string $id = null, bool $exceptCurrent = false, ?string $reason = null): void
    {
        $reason ??= $exceptCurrent ? 'revoked-others' : 'revoked';
        if (!in_array($reason, self::REASONS, true)) throw new \InvalidArgumentException('Invalid session revocation reason.');
        $where = ['user_id' => $userId, 'revoked_at' => null];
        if ($id !== null) $where['id'] = $id;
        if ($exceptCurrent) $where = ['and', $where, ['<>', 'id', self::currentId()]];
        $tx = Yii::$app->db->beginTransaction();
        try {
            $marker = time();
            $ids = (new Query())->select('id')->from('{{%um_session}}')->where($where)->column();
            if ($ids) {
                $changed = [];
                foreach ($ids as $sessionId) {
                    if (Yii::$app->db->createCommand()->update('{{%um_session}}', ['revoked_at' => $marker],
                        ['id' => $sessionId, 'user_id' => $userId, 'revoked_at' => null])->execute() === 1) $changed[] = $sessionId;
                }
                if ($changed) {
                    Audit::record('session.revoked', (string)$userId, ['all' => $id === null, 'reason' => $reason]);
                    CommittedSessionEvent::schedule($userId, $changed, $reason);
                }
            }
            $tx->commit();
        } catch (\Throwable $e) { $tx->rollBack(); throw $e; }
    }
}

<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\Query;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

final class SecuritySessions
{
    public static function start(User $user, string $method): void
    {
        if (!User::component()->enableSession) return;
        $token = Yii::$app->security->generateRandomString(64);
        Yii::$app->session->set('um_session_token', $token);
        Yii::$app->session->set('um_security_verified', ['at' => time(), 'method' => $method, 'key' => hash('sha256', $user->auth_key)]);
        Yii::$app->db->createCommand()->insert('{{%um_session}}', [
            'id' => hash('sha256', $token), 'user_id' => $user->id,
            'auth_key_hash' => hash('sha256', $user->auth_key), 'method' => $method,
            'ip' => Yii::$app->request->userIP, 'user_agent' => mb_substr((string)Yii::$app->request->userAgent, 0, 255),
            'created_at' => time(), 'last_seen_at' => time(), 'expires_at' => time() + UM::instance()->sessionLifetime,
        ])->execute();
        Audit::record('session.started', (string)$user->id, ['method' => $method]);
    }

    public static function currentId(): string
    {
        return hash('sha256', (string)Yii::$app->session->get('um_session_token', ''));
    }

    public static function valid(User $user): bool
    {
        if (!AccountSecurity::ipAllowed($user)) return false;
        $row = (new Query())->from('{{%um_session}}')->where(['id' => self::currentId(), 'user_id' => $user->id, 'revoked_at' => null])->one();
        if (!$row || $row['expires_at'] <= time() || $row['last_seen_at'] + UM::instance()->sessionIdleTimeout <= time()
            || !hash_equals($row['auth_key_hash'], hash('sha256', $user->auth_key))) return false;
        if (AccountSecurity::requiresMfa($user) && !in_array($row['method'], AccountSecurity::strict($user) ? ['passkey'] : ['passkey', 'totp'], true)) return false;
        if ($row['last_seen_at'] < time() - 60) Yii::$app->db->createCommand()->update('{{%um_session}}', ['last_seen_at' => time()], ['id' => $row['id'], 'revoked_at' => null])->execute();
        return true;
    }

    public static function revoke(int $userId, ?string $id = null, bool $exceptCurrent = false): void
    {
        $where = ['user_id' => $userId, 'revoked_at' => null];
        if ($id !== null) $where['id'] = $id;
        if ($exceptCurrent) $where = ['and', $where, ['<>', 'id', self::currentId()]];
        Yii::$app->db->createCommand()->update('{{%um_session}}', ['revoked_at' => time()], $where)->execute();
        Audit::record('session.revoked', (string)$userId, ['all' => $id === null]);
    }
}

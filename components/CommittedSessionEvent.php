<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\Connection;

/** Transaction marker also handles rollback to a nested savepoint. */
final class CommittedSessionEvent
{
    public static function schedule(int $userId, array $ids, string $reason): void
    {
        $db = Yii::$app->db;
        $id = bin2hex(random_bytes(16));
        $db->createCommand()->insert('{{%um_session_revocation}}', [
            'id' => $id, 'user_id' => $userId, 'session_ids' => json_encode($ids, JSON_THROW_ON_ERROR),
            'reason' => $reason,
        ])->execute();
        $deliver = static function () use ($db, $id): void {
            $row = $db->createCommand('SELECT * FROM {{%um_session_revocation}} WHERE [[id]]=:id', [':id' => $id])->queryOne();
            if (!$row) return; // Rolled back, including rollback to a savepoint.
            $db->createCommand()->delete('{{%um_session_revocation}}', ['id' => $id])->execute();
            try {
                \yii\base\Event::trigger(SecuritySessions::class, SecuritySessions::EVENT_SESSIONS_REVOKED,
                    new SessionsRevokedEvent(['userId' => (int)$row['user_id'],
                        'sessionIds' => json_decode($row['session_ids'], true, 512, JSON_THROW_ON_ERROR), 'reason' => $row['reason']]));
            } catch (\Throwable $e) {
                // Listener exception messages/arguments can contain credentials; log neither.
                Yii::error('Session revocation listener failed (' . get_class($e) . ').', SecuritySessions::class);
            }
        };
        if (!$db->getTransaction()) { $deliver(); return; }
        $commit = $rollback = null;
        $detach = static function () use ($db, &$commit, &$rollback): void {
            $db->off(Connection::EVENT_COMMIT_TRANSACTION, $commit);
            $db->off(Connection::EVENT_ROLLBACK_TRANSACTION, $rollback);
        };
        $commit = static function () use ($detach, $deliver): void { $detach(); $deliver(); };
        $rollback = static function () use ($detach): void { $detach(); };
        $db->on(Connection::EVENT_COMMIT_TRANSACTION, $commit);
        $db->on(Connection::EVENT_ROLLBACK_TRANSACTION, $rollback);
    }
}

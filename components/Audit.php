<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\ActiveRecord;
use yii\web\Application;

final class Audit
{
    public static function record(string $event, ?string $target = null, array $details = []): void
    {
        if (!Yii::$app->db->getTableSchema('{{%um_audit_log}}')) return; // During initial migrations.
        $actor = Yii::$app instanceof Application ? Yii::$app->user->getIdentity(false)?->getId() : null;
        Yii::$app->db->createCommand()->insert('{{%um_audit_log}}', [
            'actor_id' => $actor, 'event' => $event, 'target' => mb_substr((string)$target, 0, 255),
            'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => Yii::$app instanceof Application ? Yii::$app->request->userIP : null,
            'created_at' => time(),
        ])->execute();
    }

    public static function modelChanged($event): void
    {
        $model = $event->sender;
        // Record field names, never passwords, tokens, secret keys, emails or request bodies.
        $fields = array_keys($event->changedAttributes ?? []);
        $fields = array_values(array_intersect($fields, ['username', 'status', 'superadmin', 'name', 'description', 'group_code', 'rule_name', 'email_confirmed']));
        if ($event->name === ActiveRecord::EVENT_AFTER_UPDATE && !$fields) return;
        self::record('model.' . $event->name, $model::class . ':' . json_encode($model->getPrimaryKey()), ['fields' => $fields]);
    }
}

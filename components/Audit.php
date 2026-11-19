<?php
namespace webvimark\modules\UserManagement\components;

use Yii;
use yii\db\ActiveRecord;
use yii\web\Application;

final class Audit
{
    /** Observe configuration changes on the next module web request, once per application. */
    public static function authenticationMode(bool $enabled): void
    {
        $db = Yii::$app->db;
        if (!$db->getTableSchema('{{%um_state}}') || !$db->getTableSchema('{{%um_audit_log}}')) return;
        $name = 'auth-mode:' . substr(hash('sha256', Yii::$app->id), 0, 48);
        $value = $enabled ? '1' : '0';
        $old = (new \yii\db\Query())->select('value')->from('{{%um_state}}')->where(['name' => $name])->scalar();
        if ($old === $value) return;
        $db->transaction(static function () use ($db, $name, $value): void {
            $db->createCommand()->upsert('{{%um_state}}', ['name' => $name, 'value' => '1'], false)->execute();
            if ($db->createCommand()->update('{{%um_state}}', ['value' => $value], ['name' => $name, 'value' => $value === '1' ? '0' : '1'])->execute() === 1)
                self::record($value === '1' ? 'authentication.local_enabled' : 'authentication.local_disabled');
        });
    }

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

<?php
use webvimark\modules\UserManagement\components\Migration;
class m261013_000000_harden_users extends Migration
{
    public function safeUp()
    {
        $module = Yii::$app->getModule('user-management');
        $this->createIndex('idx_um_username_unique', $module->user_table, 'username', true);
        $this->createIndex('idx_um_email', $module->user_table, 'email');
        // Keep already hashed, purpose-bound links when recovering an installation.
        $tokens = (new \yii\db\Query())->select(['id', 'confirmation_token'])->from($module->user_table)->where(['not', ['confirmation_token' => null]])->each(100, $this->db);
        foreach ($tokens as $user) {
            if (!preg_match('/^[a-f0-9]{64}_[0-9]+$/D', $user['confirmation_token'])) {
                $this->update($module->user_table, ['confirmation_token' => null], ['id' => $user['id']]);
            }
        }
        if ($this->db->driverName !== 'sqlite') {
            $this->alterColumn($module->user_table, 'registration_ip', $this->string(45));
            $this->alterColumn($module->user_visit_log_table, 'ip', $this->string(45)->notNull());
        }
    }
    public function safeDown() { $m=Yii::$app->getModule('user-management'); $this->dropIndex('idx_um_username_unique',$m->user_table); $this->dropIndex('idx_um_email',$m->user_table); }
}

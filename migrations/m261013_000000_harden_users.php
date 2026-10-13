<?php
use yii\db\Migration;
class m261013_000000_harden_users extends Migration
{
    public function safeUp()
    {
        $module = Yii::$app->getModule('user-management');
        $this->createIndex('idx_um_username_unique', $module->user_table, 'username', true);
        $this->createIndex('idx_um_email', $module->user_table, 'email');
        // Existing bearer tokens are deliberately invalidated; new links are purpose-bound and hashed.
        $this->update($module->user_table, ['confirmation_token'=>null]);
        if ($this->db->driverName !== 'sqlite') {
            $this->alterColumn($module->user_table, 'registration_ip', $this->string(45));
            $this->alterColumn($module->user_visit_log_table, 'ip', $this->string(45)->notNull());
        }
    }
    public function safeDown() { $m=Yii::$app->getModule('user-management'); $this->dropIndex('idx_um_username_unique',$m->user_table); $this->dropIndex('idx_um_email',$m->user_table); }
}

<?php
use webvimark\modules\UserManagement\components\Migration;

class m261027_000001_add_external_identity extends Migration
{
    public function safeUp()
    {
        $options = $this->db->driverName === 'mysql' ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null;
        $this->createTable('{{%um_external_identity}}', [
            'id' => $this->string(64)->notNull(), 'user_id' => $this->integer()->notNull(),
            'provider' => $this->string(20)->notNull(), 'issuer' => $this->string(200)->notNull(),
            'subject' => $this->string(255)->notNull(), 'client_id' => $this->string(255)->notNull(),
            'created_at' => $this->integer()->notNull(), 'PRIMARY KEY ([[id]])',
        ], $options);
        $this->createIndex('idx_um_external_user_provider', '{{%um_external_identity}}', ['user_id', 'provider'], true);
    }

    public function safeDown()
    {
        echo "External account links cannot be automatically removed. Restore a backup to downgrade.\n";
        return false;
    }
}

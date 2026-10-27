<?php
use webvimark\modules\UserManagement\components\Migration;

class m261027_000000_add_account_security extends Migration
{
    public function safeUp()
    {
        $options = $this->db->driverName === 'mysql' ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null;
        $this->createTable('{{%um_mfa}}', [
            'user_id' => $this->integer()->notNull(), 'user_handle' => $this->string(64)->notNull(),
            'totp_secret' => $this->text(), 'totp_step' => $this->bigInteger()->notNull()->defaultValue(-1),
            'PRIMARY KEY ([[user_id]])',
        ], $options);
        $this->createTable('{{%um_passkey}}', [
            'id' => $this->string(64)->notNull(), 'user_id' => $this->integer()->notNull(),
            'name' => $this->string(100)->notNull(), 'record' => $this->text()->notNull(),
            'version' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(), 'last_used_at' => $this->integer(),
            'PRIMARY KEY ([[id]])',
        ], $options);
        $this->createIndex('idx_um_passkey_user', '{{%um_passkey}}', 'user_id');
        $this->createTable('{{%um_recovery_code}}', [
            'hash' => $this->string(64)->notNull(), 'user_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(), 'PRIMARY KEY ([[hash]])',
        ], $options);
        $this->createIndex('idx_um_recovery_user', '{{%um_recovery_code}}', 'user_id');
        $this->createTable('{{%um_session}}', [
            'id' => $this->string(64)->notNull(), 'user_id' => $this->integer()->notNull(),
            'auth_key_hash' => $this->string(64)->notNull(), 'method' => $this->string(20)->notNull(),
            'ip' => $this->string(45), 'user_agent' => $this->string(255),
            'created_at' => $this->integer()->notNull(), 'last_seen_at' => $this->integer()->notNull(),
            'expires_at' => $this->integer()->notNull(), 'revoked_at' => $this->integer(),
            'PRIMARY KEY ([[id]])',
        ], $options);
        $this->createIndex('idx_um_session_user', '{{%um_session}}', 'user_id');
        $this->createTable('{{%um_audit_log}}', [
            'id' => $this->primaryKey(), 'actor_id' => $this->integer(),
            'event' => $this->string(100)->notNull(), 'target' => $this->string(255),
            'details' => $this->text(), 'ip' => $this->string(45),
            'created_at' => $this->integer()->notNull(),
        ], $options);
        $this->createIndex('idx_um_audit_time', '{{%um_audit_log}}', 'created_at');
        $this->createIndex('idx_um_audit_actor', '{{%um_audit_log}}', 'actor_id');
    }

    public function safeDown()
    {
        echo "Security credentials and audit records cannot be automatically removed. Restore a backup to downgrade.\n";
        return false;
    }
}

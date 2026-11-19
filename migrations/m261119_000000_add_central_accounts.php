<?php
use webvimark\modules\UserManagement\components\Migration;
use webvimark\modules\UserManagement\UserManagementModule as UM;
use yii\db\Query;

class m261119_000000_add_central_accounts extends Migration
{
    public function safeUp()
    {
        $this->addColumn(UM::instance()->user_table, 'auth_source', $this->string(16)->notNull()->defaultValue('local'));
        $this->addColumn('{{%um_session}}', 'password_digest', $this->string(64));
        // Preserve existing sessions when upgrading, without retaining another password hash.
        foreach ((new Query())->from('{{%um_session}}')->where(['password_digest' => null])->batch(100, $this->db) as $rows) {
            foreach ($rows as $row) {
                $hash = (new Query())->select('password_hash')->from(UM::instance()->user_table)->where(['id' => $row['user_id']])->scalar($this->db);
                if ($hash !== false) $this->update('{{%um_session}}', ['password_digest' => hash('sha256', (string)$hash)], ['id' => $row['id']]);
            }
        }
        $options = $this->db->driverName === 'mysql' ? 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB' : null;
        $this->createTable('{{%um_session_revocation}}', [
            'id' => $this->string(32)->notNull(), 'user_id' => $this->integer()->notNull(),
            'session_ids' => $this->text()->notNull(), 'reason' => $this->string(32)->notNull(),
            'PRIMARY KEY ([[id]])',
        ], $options);
    }

    public function safeDown()
    {
        echo "This migration preserves accounts without local credentials and cannot be reverted safely.\n";
        return false;
    }
}

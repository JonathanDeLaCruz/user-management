<?php
use yii\db\Migration;
class m140601_000000_create_security_state extends Migration
{
    public function safeUp()
    {
        if (!$this->db->schema->getTableSchema('{{%um_state}}')) {
            $this->createTable('{{%um_state}}', ['name'=>$this->string(64)->notNull(), 'value'=>$this->string(64)->notNull(), 'PRIMARY KEY (name)']);
            $this->insert('{{%um_state}}', ['name'=>'permissions', 'value'=>Yii::$app->security->generateRandomString()]);
        }
        if (!$this->db->schema->getTableSchema('{{%um_attempt}}')) {
            $this->createTable('{{%um_attempt}}', ['key'=>$this->string(64)->notNull(), 'count'=>$this->integer()->notNull(), 'expires_at'=>$this->integer()->notNull(), 'PRIMARY KEY (key)']);
            $this->createIndex('idx_um_attempt_expiry', '{{%um_attempt}}', 'expires_at');
        }
    }
    public function safeDown() { $this->dropTable('{{%um_attempt}}'); $this->dropTable('{{%um_state}}'); }
}

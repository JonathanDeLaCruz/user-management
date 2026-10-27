<?php
use webvimark\modules\UserManagement\components\Migration;

class m261013_000001_index_visit_time extends Migration
{
    public function safeUp()
    {
        $this->createIndex('idx_um_visit_time', Yii::$app->getModule('user-management')->user_visit_log_table, 'visit_time');
    }

    public function safeDown()
    {
        $this->dropIndex('idx_um_visit_time', Yii::$app->getModule('user-management')->user_visit_log_table);
    }
}

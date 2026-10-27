<?php
use webvimark\modules\UserManagement\components\Migration;

/** Runs before historical migrations, including on existing installations. */
class m140531_000000_normalize_table_names extends Migration
{
    public function safeUp()
    {
        $module = Yii::$app->getModule('user-management');
        $tables = [
            'user' => $module->user_table,
            'user_visit_log' => $module->user_visit_log_table,
            'auth_rule' => $module->auth_rule_table,
            'auth_item' => $module->auth_item_table,
            'auth_item_group' => $module->auth_item_group_table,
            'auth_item_child' => $module->auth_item_child_table,
            'auth_assignment' => $module->auth_assignment_table,
        ];
        // Validate the entire plan before renaming: never merge or discard data.
        foreach ($tables as $old => $new) {
            $old = '{{%' . $old . '}}';
            if ($this->db->schema->getRawTableName($old) === $this->db->schema->getRawTableName($new)) continue;
            if ($this->db->getTableSchema($old, true) && $this->db->getTableSchema($new, true)) {
                throw new \yii\base\InvalidConfigException("Both {$old} and {$new} exist. Resolve the duplicate tables before migrating; no data was merged or deleted.");
            }
        }
        foreach ($tables as $old => $new) {
            $old = '{{%' . $old . '}}';
            if ($this->db->schema->getRawTableName($old) === $this->db->schema->getRawTableName($new)) continue;
            if ($this->db->getTableSchema($old, true) && !$this->db->getTableSchema($new, true)) {
                $this->renameTable($old, $new);
            }
        }
        $this->db->schema->refresh();
        if (Yii::$app->authManager instanceof \yii\rbac\DbManager) Yii::$app->authManager->invalidateCache();
        if (Yii::$app->has('cache')) Yii::$app->cache->delete('__commonRoutes');
    }

    public function safeDown()
    {
        echo "Table renaming cannot be automatically reverted. Restore a backup to downgrade.\n";
        return false;
    }
}

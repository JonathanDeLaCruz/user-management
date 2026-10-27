<?php
namespace webvimark\modules\UserManagement\components;

/** Additive schema operations for fresh and partially installed databases. */
class Migration extends \yii\db\Migration
{
    public function init()
    {
        parent::init();
        $app = \Yii::$app;
        if ($app instanceof \yii\console\Application) {
            if (!$app->has('authManager')) {
                $app->set('authManager', ['class' => \yii\rbac\DbManager::class]);
            }
            if (!$app->hasModule('user-management')) {
                $app->setModule('user-management', [
                    'class' => \webvimark\modules\UserManagement\UserManagementModule::class,
                    'controllerNamespace' => 'webvimark\\modules\\UserManagement\\commands',
                ]);
            }
        }
        // Load once before any migration reads the table names or seeds RBAC.
        \webvimark\modules\UserManagement\UserManagementModule::instance();
    }

    public function createTable($table, $columns, $options = null)
    {
        if ($this->db->getTableSchema($table, true) === null) {
            parent::createTable($table, $columns, $options);
            return;
        }
        foreach ($columns as $name => $type) {
            if ($name === 'browser_and_os' && $this->db->getTableSchema($table, true)->getColumn('user_agent')) continue;
            if (is_string($name)) $this->addColumn($table, $name, $type);
        }
    }

    public function addColumn($table, $column, $type)
    {
        if ($this->db->getTableSchema($table, true)->getColumn($column) === null) {
            parent::addColumn($table, $column, $type);
        }
    }

    public function createIndex($name, $table, $columns, $unique = false)
    {
        $wanted = is_array($columns) ? $columns : array_map('trim', explode(',', $columns));
        foreach ($this->db->schema->getTableIndexes($table, true) as $index) {
            if ($index->columnNames === $wanted && (!$unique || $index->isUnique)) return;
        }
        parent::createIndex($name, $table, $columns, $unique);
    }

    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $wanted = is_array($columns) ? $columns : array_map('trim', explode(',', $columns));
        foreach ($this->db->schema->getTableForeignKeys($table, true) as $key) {
            if ($key->columnNames === $wanted) return;
        }
        parent::addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }
}

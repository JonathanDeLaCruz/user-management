<?php

use yii\db\Migration;

class m141121_194858_split_browser_and_os_column extends Migration
{
	public function safeUp()
	{
		$this->addColumn(Yii::$app->getModule('user-management')->user_visit_log_table, 'browser', 'varchar(30)');
		$this->addColumn(Yii::$app->getModule('user-management')->user_visit_log_table, 'os', 'varchar(20)');
		if ($this->db->driverName === 'sqlite') {
			$this->execute('ALTER TABLE ' . $this->db->quoteTableName(Yii::$app->getModule('user-management')->user_visit_log_table) . ' RENAME COLUMN browser_and_os TO user_agent');
		} else $this->renameColumn(Yii::$app->getModule('user-management')->user_visit_log_table, 'browser_and_os', 'user_agent');

		if (Yii::$app->cache) {
			Yii::$app->cache->flush();
		}
	}

	public function safeDown()
	{
		$this->dropColumn(Yii::$app->getModule('user-management')->user_visit_log_table, 'os');
		$this->dropColumn(Yii::$app->getModule('user-management')->user_visit_log_table, 'browser');
		$this->renameColumn(Yii::$app->getModule('user-management')->user_visit_log_table, 'user_agent', 'browser_and_os');

		if (Yii::$app->cache) {
			Yii::$app->cache->flush();
		}
	}
}

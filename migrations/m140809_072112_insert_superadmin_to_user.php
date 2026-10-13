<?php

use yii\db\Schema;
use yii\db\Migration;
use webvimark\modules\UserManagement\models\User;

class m140809_072112_insert_superadmin_to_user extends Migration
{
	public function safeUp()
	{
		// Administrators are created explicitly by the console command, never with a shared password.
	}

	public function safeDown()
	{
		// No account was inserted by this migration.
	}
}

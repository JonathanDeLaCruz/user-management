<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;
use yii\db\Connection;
use yii\db\Query;

class MigrationUpgradeTest extends TestCase
{
    private $previousDb;
    private $db;

    protected function setUp(): void
    {
        $this->previousDb = Yii::$app->db;
        $this->db = new Connection(['dsn' => 'sqlite::memory:', 'tablePrefix' => 'app_']);
        Yii::$app->set('db', $this->db);
        Yii::$app->authManager->db = $this->db;
        $this->migrate();
    }

    protected function tearDown(): void
    {
        Yii::$app->set('db', $this->previousDb);
        Yii::$app->authManager->db = $this->previousDb;
        $this->db->close();
    }

    private function migrate(): void
    {
        ob_start();
        try {
            foreach (glob(dirname(__DIR__) . '/migrations/m*.php') as $file) {
                require_once $file;
                $class = basename($file, '.php');
                self::assertNotFalse((new $class(['db' => $this->db]))->up(), $class);
            }
        } finally { ob_end_clean(); }
    }

    public function testUpgradePreservesExistingSessionsAndLocalAccounts(): void
    {
        $this->db->createCommand()->insert(User::tableName(), ['id' => 91, 'username' => 'before-central-upgrade',
            'status' => 1, 'password_hash' => 'old-password-hash', 'auth_key' => 'old-auth-key', 'created_at' => 1, 'updated_at' => 1])->execute();
        $this->db->createCommand('ALTER TABLE ' . User::tableName() . ' DROP COLUMN [[auth_source]]')->execute();
        $this->db->createCommand('ALTER TABLE {{%um_session}} DROP COLUMN [[password_digest]]')->execute();
        $this->db->createCommand()->dropTable('{{%um_session_revocation}}')->execute();
        $id = str_repeat('b', 64);
        $this->db->createCommand()->insert('{{%um_session}}', ['id' => $id, 'user_id' => 91,
            'auth_key_hash' => hash('sha256', 'old-auth-key'), 'method' => 'password',
            'created_at' => time(), 'last_seen_at' => time(), 'expires_at' => time() + 300])->execute();
        $this->db->schema->refresh(); $this->migrate();
        $row = (new Query())->from(User::tableName())->where(['id' => 91])->one($this->db);
        self::assertSame('local', $row['auth_source']); self::assertSame('old-password-hash', $row['password_hash']);
        self::assertSame(hash('sha256', 'old-password-hash'), (new Query())->select('password_digest')->from('{{%um_session}}')->where(['id' => $id])->scalar($this->db));
        self::assertTrue(webvimark\modules\UserManagement\components\SecuritySessions::isActive(91, $id));
        $this->migrate(); self::assertTrue(webvimark\modules\UserManagement\components\SecuritySessions::isActive(91, $id));
    }

    public function testPrefixedInstallationCanReplayWithoutLosingAccountsOrTokens(): void
    {
        $token = str_repeat('a', 64) . '_' . time();
        $this->db->createCommand()->insert(User::tableName(), [
            'id' => 77, 'username' => 'preserved', 'password_hash' => 'existing-hash',
            'auth_key' => 'existing-key', 'created_at' => 1, 'updated_at' => 1,
            'confirmation_token' => $token,
        ])->execute();
        $count = (new Query())->from('{{%um_auth_item}}')->count('*', $this->db);
        $this->migrate();
        $user = (new Query())->from(User::tableName())->where(['id' => 77])->one($this->db);
        self::assertSame('existing-hash', $user['password_hash']);
        self::assertSame($token, $user['confirmation_token']);
        self::assertSame($count, (new Query())->from('{{%um_auth_item}}')->count('*', $this->db));
        self::assertNotNull($this->db->getTableSchema('app_um_user', true));
        self::assertNull($this->db->getTableSchema('app_user', true));
        self::assertSame([], $this->db->createCommand('PRAGMA foreign_key_check')->queryAll());
    }

    public function testLegacyTablesAreRenamedAndMissingColumnsAdded(): void
    {
        $tables = ['user', 'user_visit_log', 'auth_rule', 'auth_item', 'auth_item_group', 'auth_item_child', 'auth_assignment'];
        $this->db->createCommand()->insert('{{%um_auth_assignment}}', ['item_name' => 'Admin', 'user_id' => 999])->execute();
        foreach ($tables as $table) $this->db->createCommand()->renameTable('{{%um_' . $table . '}}', '{{%' . $table . '}}')->execute();
        $this->db->schema->refresh();
        $this->db->createCommand('ALTER TABLE {{%user}} DROP COLUMN [[bind_to_ip]]')->execute();
        $this->migrate();
        foreach ($tables as $table) {
            self::assertNotNull($this->db->getTableSchema('{{%um_' . $table . '}}', true));
            self::assertNull($this->db->getTableSchema('{{%' . $table . '}}', true));
        }
        self::assertNotNull($this->db->getTableSchema('{{%um_user}}', true)->getColumn('bind_to_ip'));
        self::assertTrue((new Query())->from('{{%um_auth_assignment}}')->where(['item_name' => 'Admin', 'user_id' => 999])->exists($this->db));
    }

    public function testConflictingNamesStopBeforeAnyRename(): void
    {
        $this->db->createCommand()->createTable('{{%user}}', ['id' => 'pk'])->execute();
        self::assertNotNull($this->db->getTableSchema('{{%user}}', true));
        self::assertNotNull($this->db->getTableSchema('{{%um_user}}', true));
        ob_start();
        try { $result = (new m140531_000000_normalize_table_names(['db' => $this->db]))->up(); }
        finally { $output = ob_get_clean(); }
        self::assertFalse($result);
        self::assertStringContainsString('Both', $output);
        self::assertNotNull($this->db->getTableSchema('{{%user}}', true));
        self::assertNotNull($this->db->getTableSchema('{{%um_user}}', true));
    }
}

<?php
use PHPUnit\Framework\TestCase;
use yii\db\Query;
use webvimark\modules\UserManagement\components\{SecuritySessions, SessionTouchClock};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

require_once __DIR__ . '/fixtures/SessionClock.php';

final class SessionTouchTest extends TestCase
{
    private $tx;
    private $user;
    private array $settings;
    private int $now;

    protected function setUp(): void
    {
        $this->tx = Yii::$app->db->beginTransaction();
        $m = UM::instance();
        $this->settings = [$m->mfaPolicy, $m->requirePhishingResistant, $m->localAuthentication, $m->sessionIdleTimeout];
        $m->mfaPolicy = 'optional'; $m->requirePhishingResistant = false; $m->localAuthentication = true; $m->sessionIdleTimeout = 1800;
        $this->now = time(); SessionTouchClock::$now = $this->now;
        Yii::$app->user->setIdentity(null); Yii::$app->session->removeAll();
        Yii::$app->user->enableSession = true;
        $this->user = new User(['username' => 'session-touch-test', 'status' => 1, 'password_hash' => 'test-only-invalid-hash']);
        self::assertTrue($this->user->save()); $this->user->refresh();
    }

    protected function tearDown(): void
    {
        SessionTouchClock::$now = null;
        $m = UM::instance();
        [$m->mfaPolicy, $m->requirePhishingResistant, $m->localAuthentication, $m->sessionIdleTimeout] = $this->settings;
        Yii::$app->user->setIdentity(null); Yii::$app->user->enableSession = false; Yii::$app->session->removeAll();
        $this->tx->rollBack();
    }

    private function session(array $changes = []): string
    {
        SecuritySessions::start($this->user, 'password');
        $id = SecuritySessions::currentId();
        Yii::$app->db->createCommand()->update('{{%um_session}}', $changes + ['last_seen_at' => $this->now - 1200], ['id' => $id])->execute();
        return $id;
    }

    private function row(string $id): array
    {
        return (new Query())->from('{{%um_session}}')->where(['id' => $id])->one();
    }

    public function testChildActivityRenewsOnlyLastSeenWithoutPhpSessionOrAuditChanges(): void
    {
        $renewed = $this->session(); $untouched = $this->session();
        $before = $this->row($renewed);
        $audits = (new Query())->from('{{%um_audit_log}}')->count();
        $markers = (new Query())->from('{{%um_session_revocation}}')->count();
        Yii::$app->session->removeAll(); Yii::$app->user->enableSession = false;
        self::assertTrue(SecuritySessions::touch((int)$this->user->id, $renewed));
        $after = $this->row($renewed);
        self::assertSame($this->now, (int)$after['last_seen_at']);
        $before['last_seen_at'] = $after['last_seen_at'];
        self::assertSame($before, $after);
        self::assertFalse((new Query())->from('{{%um_mfa}}')->where(['user_id' => $this->user->id])->exists(), 'Touch must not create a profile.');
        self::assertSame($audits, (new Query())->from('{{%um_audit_log}}')->count());
        self::assertSame($markers, (new Query())->from('{{%um_session_revocation}}')->count());
        SessionTouchClock::$now += 1200;
        self::assertTrue(SecuritySessions::isActive((int)$this->user->id, $renewed));
        self::assertFalse(SecuritySessions::isActive((int)$this->user->id, $untouched));
        SessionTouchClock::$now += 600;
        self::assertFalse(SecuritySessions::isActive((int)$this->user->id, $renewed), 'Expiry boundary remains exclusive.');
    }

    public function testInvalidOwnershipRevocationExpiryCredentialsAndDisabledAccountsNeverChangeAnyRow(): void
    {
        foreach ([['revoked_at' => $this->now], ['expires_at' => $this->now],
            ['last_seen_at' => $this->now - 1800], ['auth_key_hash' => str_repeat('0', 64)],
            ['password_digest' => str_repeat('0', 64)]] as $changes) {
            $id = $this->session($changes); $before = $this->row($id);
            self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id)); self::assertSame($before, $this->row($id));
        }
        $id = $this->session(); $before = $this->row($id);
        foreach (['', 'invalid', str_repeat('A', 64), str_repeat('a', 63), str_repeat('b', 64), $id . "\n"] as $invalid)
            self::assertFalse(SecuritySessions::touch((int)$this->user->id, $invalid));
        self::assertFalse(SecuritySessions::touch(0, $id));
        self::assertFalse(SecuritySessions::touch((int)$this->user->id + 10000, $id));
        foreach (['status' => 0, 'auth_key' => 'new-key', 'password_hash' => 'new-hash'] as $field => $changed) {
            $old = $this->user->$field;
            Yii::$app->db->createCommand()->update(User::tableName(), [$field => $changed], ['id' => $this->user->id])->execute();
            self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id)); self::assertSame($before, $this->row($id));
            Yii::$app->db->createCommand()->update(User::tableName(), [$field => $old], ['id' => $this->user->id])->execute();
        }
        self::assertFalse((new Query())->from('{{%um_mfa}}')->where(['user_id' => $this->user->id])->exists());
    }

    public function testRepeatedTouchesThrottleWritesAndCannotExtendAbsoluteLifetime(): void
    {
        $expiry = $this->now + 3600;
        $id = $this->session(['expires_at' => $expiry]);
        for ($i = 0; $i < 60; $i++) {
            SessionTouchClock::$now = $this->now + $i * 60;
            self::assertTrue(SecuritySessions::touch((int)$this->user->id, $id));
            $row = $this->row($id);
            self::assertSame($expiry, (int)$row['expires_at']); self::assertSame('password', $row['method']);
            self::assertTrue(SecuritySessions::touch((int)$this->user->id, $id)); self::assertSame($row, $this->row($id));
        }
        SessionTouchClock::$now = $expiry;
        $before = $this->row($id);
        self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id)); self::assertSame($before, $this->row($id));
    }

    public function testMfaAndChildModeMatchIsActiveWithoutEnrollingAnyFactors(): void
    {
        $m = UM::instance(); $m->mfaPolicy = 'all';
        foreach ([false, true] as $strict) {
            $m->requirePhishingResistant = $strict;
            foreach (['password', 'google', 'totp', 'passkey', 'central'] as $method) {
                $id = $this->session(['method' => $method]); $before = $this->row($id);
                $allowed = $method === 'passkey' || (!$strict && $method === 'totp');
                self::assertSame($allowed, SecuritySessions::isActive((int)$this->user->id, $id));
                self::assertSame($allowed, SecuritySessions::touch((int)$this->user->id, $id));
                if (!$allowed) self::assertSame($before, $this->row($id));
            }
        }
        // Optional policy must still enforce an enrolled authenticator.
        $m->mfaPolicy = 'optional'; $m->requirePhishingResistant = false;
        Yii::$app->db->createCommand()->insert('{{%um_mfa}}', ['user_id' => $this->user->id,
            'user_handle' => str_repeat('a', 64), 'totp_secret' => 'encrypted-test-fixture'])->execute();
        $id = $this->session(); self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id));
        $id = $this->session(['method' => 'totp']); self::assertTrue(SecuritySessions::touch((int)$this->user->id, $id));
        // A registered passkey also enforces MFA under the optional policy; strict mode rejects TOTP.
        Yii::$app->db->createCommand()->insert('{{%um_passkey}}', ['id' => str_repeat('c', 64), 'user_id' => $this->user->id,
            'name' => 'Test key', 'record' => '{}', 'created_at' => $this->now])->execute();
        $m->requirePhishingResistant = true;
        $id = $this->session(['method' => 'totp']);
        self::assertFalse(SecuritySessions::isActive((int)$this->user->id, $id));
        self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id));
        $id = $this->session(['method' => 'passkey']); self::assertTrue(SecuritySessions::touch((int)$this->user->id, $id));
        $m->localAuthentication = false;
        $id = $this->session(['method' => 'central']); self::assertTrue(SecuritySessions::touch((int)$this->user->id, $id));
        $m->localAuthentication = true; $m->mfaPolicy = 'admins'; $m->requirePhishingResistant = false;
        Yii::$app->db->createCommand()->delete('{{%um_mfa}}', ['user_id' => $this->user->id])->execute();
        Yii::$app->db->createCommand()->delete('{{%um_passkey}}', ['user_id' => $this->user->id])->execute();
        self::assertTrue(User::assignRole($this->user->id, 'Admin'));
        $id = $this->session(); self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id));
    }

    public function testDatabaseFailureReturnsFalseWithoutThrowingOrChangingTheSession(): void
    {
        $id = $this->session(); $before = $this->row($id);
        $db = Yii::$app->db;
        Yii::$app->set('db', new yii\db\Connection(['dsn' => 'unsupported-test-driver:']));
        try { self::assertFalse(SecuritySessions::touch((int)$this->user->id, $id)); }
        finally { Yii::$app->set('db', $db); }
        self::assertSame($before, $this->row($id));
    }
}

<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\search\AuditLogSearch;
use webvimark\modules\UserManagement\components\SecurityPresentation as Presentation;
use webvimark\modules\UserManagement\controllers\SecurityController;

final class AuditSearchTest extends TestCase
{
    private $transaction;
    private $identity;
    private $controller;
    private $user;

    protected function setUp(): void
    {
        $this->transaction = Yii::$app->db->beginTransaction();
        $this->identity = Yii::$app->user->identity;
        $this->controller = Yii::$app->controller;
        $this->user = new User(['username' => 'audit-reader', 'superadmin' => 1, 'status' => 1, 'password_hash' => Yii::$app->security->generatePasswordHash('Audit test password')]);
        $this->user->save(false);
        Yii::$app->user->setIdentity($this->user);
        Yii::$app->db->createCommand()->delete('{{%um_audit_log}}')->execute();
        foreach ([['session.started', '2026-10-27 12:00:00', $this->user->id, ['method' => 'totp']],
            ['model.afterUpdate', '2026-10-26 12:00:00', $this->user->id, ['fields' => ['status', 'password_hash']]],
            ['login.failed', '2026-10-25 12:00:00', null, []]] as [$event, $date, $actor, $details]) {
            Yii::$app->db->createCommand()->insert('{{%um_audit_log}}', [
                'actor_id' => $actor, 'event' => $event, 'target' => (string)$this->user->id,
                'created_at' => (new DateTimeImmutable($date, new DateTimeZone(Yii::$app->timeZone)))->getTimestamp(),
                'details' => json_encode($details), 'ip' => '127.0.0.1',
            ])->execute();
        }
    }

    protected function tearDown(): void
    {
        $this->transaction->rollBack();
        Yii::$app->user->setIdentity($this->identity);
        Yii::$app->controller = $this->controller;
    }

    public function testFiltersUseUsernamesDatesAndTranslatedActions(): void
    {
        $search = fn($fields) => (new AuditLogSearch())->search(['AuditLogSearch' => $fields])->getModels();
        $rows = $search([]);
        self::assertCount(3, $rows);
        self::assertSame('session.started', $rows[0]['event']);
        self::assertCount(2, $search(['username' => 'audit-reader']));
        self::assertCount(1, $search(['event' => 'model.afterUpdate']));
        self::assertCount(1, $search(['from' => '2026-10-26', 'to' => '2026-10-26']));
        self::assertCount(1, $search(['search' => 'Inicio de sesión completado']));
        self::assertCount(3, $search(['search' => '127.0.0.1']));
        self::assertCount(0, $search(['username' => "' OR 1=1 --"]));
        self::assertCount(0, $search(['from' => 'not-a-date']));
        self::assertCount(0, $search(['event' => ['invalid']]));
    }

    public function testAuditExplainsActionsWithoutRawIdsOrSecretFieldNames(): void
    {
        $rows = (new AuditLogSearch())->search([])->getModels();
        self::assertSame('audit-reader', Presentation::actor($rows[0]));
        self::assertSame('audit-reader', Presentation::target($rows[0]));
        self::assertStringContainsString('Código de la app autenticadora', Presentation::details($rows[0]));
        self::assertSame('Campos afectados: Estado de la cuenta.', Presentation::details($rows[1]));
        self::assertSame('Sistema o visitante', Presentation::actor($rows[2]));
        self::assertSame('Usuario eliminado', Presentation::actor(['actor_id' => 987654, 'username' => null]));
        self::assertSame('Chrome 156 · Windows', Presentation::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/156.0.0.0 Safari/537.36'));
        $controller = new SecurityController('security', Yii::$app->getModule('user-management'));
        $controller->action = new yii\base\Action('audit', $controller);
        Yii::$app->controller = $controller;
        $markup = $controller->render('audit', ['provider' => (new AuditLogSearch())->search([]), 'searchModel' => new AuditLogSearch()]);
        self::assertStringNotContainsString('model.afterUpdate</td>', $markup);
        self::assertStringNotContainsString('password_hash', $markup);
        self::assertStringContainsString('audit-reader</td>', $markup);
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $markup);
        $xpath = new DOMXPath($document);
        self::assertSame(1, $xpath->query('//nav//a[contains(@href,"/security/index")]')->length);
        self::assertSame(0, $xpath->query('//header//a[contains(@href,"/security/index")]')->length);
        self::assertSame(1, $xpath->query('//nav//a[@aria-current="page" and contains(@href,"/security/audit")]')->length);
    }

    public function testAuditRemainsRestrictedToSuperadmins(): void
    {
        Yii::$app->user->setIdentity(new User(['id' => $this->user->id, 'superadmin' => 0]));
        $this->expectException(yii\web\ForbiddenHttpException::class);
        (new SecurityController('security', Yii::$app->getModule('user-management')))->actionAudit();
    }
}

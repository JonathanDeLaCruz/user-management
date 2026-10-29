<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\components\{DateFilter, SecurityPresentation};
use webvimark\modules\UserManagement\models\{User, UserVisitLog};
use webvimark\modules\UserManagement\models\search\{AuditLogSearch, UserSearch, UserVisitLogSearch};
use webvimark\modules\UserManagement\models\rbacDB\{Role, Permission, AuthItemGroup};
use webvimark\modules\UserManagement\models\rbacDB\search\{RoleSearch, PermissionSearch, AuthItemGroupSearch};
use webvimark\modules\UserManagement\widgets\GridView;

final class GridQueryTest extends TestCase
{
    private $transaction;
    private $identity;
    private $controller;
    private $zone;
    private $params;
    private array $users;

    protected function setUp(): void
    {
        $this->transaction = Yii::$app->db->beginTransaction();
        $this->identity = Yii::$app->user->identity;
        $this->controller = Yii::$app->controller;
        $this->zone = Yii::$app->timeZone;
        $this->params = Yii::$app->request->queryParams;
        Yii::$app->timeZone = 'America/New_York';
        Yii::$app->user->setIdentity(new User(['id' => 999999, 'superadmin' => 1, 'status' => 1]));
        $this->users = [];
        foreach (['zulu', 'alpha', 'beta'] as $index => $name) {
            $user = new User(['username' => 'grid-' . $name, 'email' => $name . '@example.test', 'email_confirmed' => 0,
                'superadmin' => $index === 2 ? 1 : 0, 'status' => 0, 'password_hash' => 'not-a-real-password-hash']);
            $user->save(false);
            $this->users[] = $user;
        }
    }

    protected function tearDown(): void
    {
        $this->transaction->rollBack();
        Yii::$app->user->setIdentity($this->identity);
        Yii::$app->controller = $this->controller;
        Yii::$app->timeZone = $this->zone;
        Yii::$app->request->setQueryParams($this->params);
        Yii::$app->authManager->invalidateCache();
    }

    private function provider($model, array $fields, string $sort)
    {
        $provider = $model->search([$model->formName() => $fields]);
        $provider->getSort()->params = ['sort' => $sort];
        return $provider;
    }

    public function testRoleOrderingHasOneRowPerUserAndStableCounts(): void
    {
        $roles = [];
        foreach (['Zulu', 'Alpha', 'Beta'] as $description) $roles[] = Role::create('grid-' . $description, $description);
        Yii::$app->authManager->assign($roles[0], $this->users[0]->id);
        Yii::$app->authManager->assign($roles[1], $this->users[0]->id);
        Yii::$app->authManager->assign($roles[2], $this->users[1]->id);
        Yii::$app->authManager->assign($roles[1], $this->users[2]->id);
        $fields = ['username' => 'grid-'];
        $provider = $this->provider(new UserSearch(), $fields, 'gridRoleSearch');
        self::assertSame(3, $provider->getTotalCount());
        self::assertSame(['grid-zulu', 'grid-beta', 'grid-alpha'], array_column($provider->getModels(), 'username'));
        $provider = $this->provider(new UserSearch(), $fields, '-gridRoleSearch');
        self::assertSame(['grid-alpha', 'grid-beta', 'grid-zulu'], array_column($provider->getModels(), 'username'));
        $provider = $this->provider(new UserSearch(), $fields + ['gridRoleSearch' => 'grid-Alpha', 'superadmin' => '0', 'status' => '0', 'email_confirmed' => '0'], 'gridRoleSearch');
        self::assertSame(1, $provider->getTotalCount());
        self::assertSame('grid-zulu', $provider->getModels()[0]->username);
        // A role whose name is literally "0" must not turn into an empty filter.
        $zero = Role::create('0', 'Zero role');
        Yii::$app->authManager->assign($zero, $this->users[1]->id);
        self::assertSame('grid-alpha', $this->provider(new UserSearch(), $fields + ['gridRoleSearch' => '0'], 'gridRoleSearch')->getModels()[0]->username);
        Yii::$app->user->setIdentity($this->users[1]);
        foreach (['gridRoleSearch', '-gridRoleSearch', 'username', '-status'] as $sort) {
            $rows = $this->provider(new UserSearch(), $fields, $sort)->getModels();
            self::assertCount(2, $rows);
            self::assertNotContains('grid-beta', array_column($rows, 'username'));
        }
    }

    public function testVisitDayUsesLocalMidnightsAndSortsByVisibleUsername(): void
    {
        Yii::$app->db->createCommand()->delete(UserVisitLog::tableName())->execute();
        $bounds = DateFilter::bounds('2026-03-08');
        self::assertSame(23 * 3600, $bounds[1] - $bounds[0]);
        $fall = DateFilter::bounds('2026-11-01');
        self::assertSame(25 * 3600, $fall[1] - $fall[0]);
        foreach ([[$this->users[0], $bounds[0]], [$this->users[1], $bounds[1] - 1], [$this->users[2], $bounds[1]], [$this->users[0], $bounds[0] - 1]] as [$user, $time]) {
            Yii::$app->db->createCommand()->insert(UserVisitLog::tableName(), ['user_agent' => 'Grid test agent', 'token' => bin2hex(random_bytes(16)), 'user_id' => $user->id, 'visit_time' => $time, 'language' => 'es', 'os' => 'Android', 'browser' => 'Brave', 'ip' => '127.0.0.1'])->execute();
        }
        $fields = ['visit_time' => '2026-03-08', 'language' => 'es', 'os' => 'Android', 'browser' => 'Brave', 'ip' => '127.0.0.1'];
        self::assertSame(['grid-alpha', 'grid-zulu'], array_map(fn($row) => $row->user->username, $this->provider(new UserVisitLogSearch(), $fields, 'user_id')->getModels()));
        self::assertSame(['grid-zulu', 'grid-alpha'], array_map(fn($row) => $row->user->username, $this->provider(new UserVisitLogSearch(), $fields, '-user_id')->getModels()));
        self::assertSame(2, $this->provider(new UserVisitLogSearch(), ['visit_time' => '2026-03-08 - 2026-03-08'], 'visit_time')->getTotalCount());
        self::assertSame(0, $this->provider(new UserVisitLogSearch(), ['visit_time' => '2026-02-30'], 'visit_time')->getTotalCount());
        foreach (['language', 'os', 'browser', 'ip', 'visit_time'] as $attribute) {
            self::assertSame(2, $this->provider(new UserVisitLogSearch(), $fields, $attribute)->getCount());
            self::assertSame(2, $this->provider(new UserVisitLogSearch(), $fields, '-' . $attribute)->getCount());
        }
        Yii::$app->user->setIdentity($this->users[1]);
        self::assertNotContains($this->users[2]->id, array_column($this->provider(new UserVisitLogSearch(), [], '-user_id')->getModels(), 'user_id'));
    }

    public function testAuditFiltersAndSortsTheExactTranslatedVisibleText(): void
    {
        Yii::$app->db->createCommand()->delete('{{%um_audit_log}}')->execute();
        $cases = [
            ['session.started', (string)$this->users[0]->id, ['method' => 'totp']],
            ['model.afterUpdate', User::class . ':' . $this->users[1]->id, ['fields' => ['status', 'username', 'password_hash']]],
            ['model.afterUpdate', Role::class . ':"grid-Alpha"', ['fields' => ['description', 'name']]],
            ['action.completed', '/user-management/user/set-roles', []],
            ['external.failed', '', ['provider' => 'google']],
            ['operator.retention', 'unknown', ['days' => 30]],
            ['unknown', '', []],
        ];
        $time = DateFilter::bounds('2026-03-08')[0];
        foreach ($cases as $index => [$event, $target, $details]) Yii::$app->db->createCommand()->insert('{{%um_audit_log}}', [
            'actor_id' => $index % 2 ? $this->users[1]->id : null, 'event' => $event, 'target' => $target,
            'details' => json_encode($details), 'ip' => '127.0.0.' . $index, 'created_at' => $time + $index,
        ])->execute();
        $provider = $this->provider(new AuditLogSearch(), [], 'created_at');
        $rows = $provider->getModels();
        self::assertCount(count($cases), $rows);
        foreach ($rows as $row) {
            self::assertSame(SecurityPresentation::actor($row), $row['display_username']);
            self::assertSame(SecurityPresentation::target($row), $row['display_target']);
            self::assertSame(SecurityPresentation::details($row), $row['display_details']);
            foreach (['username', 'target', 'details'] as $attribute) {
                $visible = $row['display_' . $attribute];
                self::assertContains($row['id'], array_column($this->provider(new AuditLogSearch(), [$attribute => $visible], 'created_at')->getModels(), 'id'));
                self::assertContains($row['id'], array_column($this->provider(new AuditLogSearch(), ['search' => $visible], 'created_at')->getModels(), 'id'));
            }
        }
        foreach (['username', 'event', 'target', 'ip', 'details', 'created_at'] as $attribute) {
            $asc = $this->provider(new AuditLogSearch(), ['created_at' => '2026-03-08'], $attribute)->getModels();
            $desc = $this->provider(new AuditLogSearch(), ['created_at' => '2026-03-08'], '-' . $attribute)->getModels();
            self::assertSame(array_reverse(array_column($asc, 'id')), array_column($desc, 'id'), $attribute);
        }
        self::assertSame(1, $this->provider(new AuditLogSearch(), ['target' => 'grid-alpha', 'details' => 'Estado de la cuenta', 'event' => 'model.afterUpdate', 'username' => 'grid-alpha', 'ip' => '127.0.0.1', 'from' => '2026-03-08', 'to' => '2026-03-08'], 'target')->getTotalCount());
        self::assertSame(0, $this->provider(new AuditLogSearch(), ['details' => 'password_hash'], 'details')->getTotalCount());
        self::assertSame(0, $this->provider(new AuditLogSearch(), ['target' => "' OR 1=1 --"], 'target')->getTotalCount());
        self::assertSame(0, $this->provider(new AuditLogSearch(), ['created_at' => '2026-02-30'], 'created_at')->getTotalCount());
    }

    public function testRolePermissionAndGroupColumnsHaveRealFiltersAndSorting(): void
    {
        $module = Yii::$app->getModule('user-management');
        foreach (['z' => 'Alpha group', '0' => 'Zulu group'] as $code => $name) Yii::$app->db->createCommand()->insert($module->auth_item_group_table, ['code' => (string)$code, 'name' => $name, 'created_at' => 1, 'updated_at' => 1])->execute();
        foreach ([['grid-permission-z', 'Alpha permission', '0'], ['grid-permission-a', 'Zulu permission', 'z']] as [$name, $description, $group]) Yii::$app->db->createCommand()->insert($module->auth_item_table, ['name' => $name, 'description' => $description, 'type' => 2, 'group_code' => $group])->execute();
        Role::create('grid-role-z', 'Alpha role'); Role::create('grid-role-a', 'Zulu role');
        foreach ([new PermissionSearch(), new RoleSearch(), new AuthItemGroupSearch()] as $model) {
            $fields = $model instanceof AuthItemGroupSearch ? ['name' => 'group'] : ['name' => $model instanceof RoleSearch ? 'grid-role-' : 'grid-permission-'];
            foreach ($model instanceof AuthItemGroupSearch ? ['code', 'name'] : ($model instanceof PermissionSearch ? ['name', 'description', 'group_code'] : ['name', 'description']) as $attribute) {
                $asc = $this->provider(clone $model, $fields, $attribute)->getModels();
                $desc = $this->provider(clone $model, $fields, '-' . $attribute)->getModels();
                self::assertCount(2, $asc);
                self::assertSame(array_reverse(array_column($asc, $model instanceof AuthItemGroupSearch ? 'code' : 'name')), array_column($desc, $model instanceof AuthItemGroupSearch ? 'code' : 'name'));
            }
        }
        self::assertSame('grid-permission-z', $this->provider(new PermissionSearch(), ['group_code' => '0'], 'group_code')->getModels()[0]->name);
        self::assertSame('grid-permission-a', $this->provider(new PermissionSearch(), ['name' => 'grid-permission-', 'description' => 'Zulu'], '-description')->getModels()[0]->name);
    }

    public function testClearAndFilterUrlsPreserveHostContextAndResetOnlyPagination(): void
    {
        Yii::$app->controller = new yii\web\Controller('permission', Yii::$app->getModule('user-management'));
        Yii::$app->controller->action = new yii\base\Action('index', Yii::$app->controller);
        Yii::$app->request->setQueryParams(['r' => 'user-management/permission/index', 'tenant' => '42', 'sort' => '-name', 'page' => 7,
            '_pjax' => '#permission-grid-pjax', 'PermissionSearch' => ['name' => 'abc']]);
        $model = new PermissionSearch();
        $grid = new GridView(['dataProvider' => $model->search(Yii::$app->request->queryParams), 'filterModel' => $model]);
        self::assertSame(['/user-management/permission/index', 'tenant' => '42', 'sort' => '-name'], $grid->queryUrl());
        self::assertSame(['/user-management/permission/index', 'tenant' => '42', 'sort' => '-name', 'PermissionSearch' => ['name' => 'abc']], $grid->filterUrl);
        self::assertSame('/user-management/permission/index?tenant=42&sort=-name', yii\helpers\Url::to($grid->queryUrl()));
    }
}

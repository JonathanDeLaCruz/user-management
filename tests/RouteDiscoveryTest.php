<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use routefixtures\controllers\{SiteController, AbstractBaseController, SupportController};
use routefixtures\mapped\MappedController;

final class RouteDiscoveryTest extends TestCase
{
    private $namespace;
    private $map;
    private $container;
    private $controller;
    private $transaction;

    protected function setUp(): void
    {
        $this->transaction = Yii::$app->db->beginTransaction();
        $this->namespace = Yii::$app->controllerNamespace;
        $this->map = Yii::$app->controllerMap;
        $this->container = Yii::$container;
        $this->controller = Yii::$app->controller;
        Yii::$container = clone Yii::$container;
        Yii::$container->set(yii\mail\MailerInterface::class, Yii::$app->mailer);
        Yii::$container->set(yii\base\Security::class, Yii::$app->security);
        Yii::setAlias('@routefixtures', __DIR__ . '/fixtures/routes');
        Yii::$app->controllerNamespace = 'routefixtures\\controllers';
        Yii::$app->controllerMap = [];
        SiteController::$dependencies = [];
        MappedController::$labels = [];
    }

    protected function tearDown(): void
    {
        $this->transaction->rollBack();
        Yii::$app->controllerNamespace = $this->namespace;
        Yii::$app->controllerMap = $this->map;
        Yii::$container = $this->container;
        Yii::$app->controller = $this->controller;
        Yii::$app->authManager->invalidateCache();
    }

    public function testBasicSiteControllerGetsMailerAndSecurityWithoutRunningActions(): void
    {
        $before = (new yii\db\Query())->from('{{%um_auth_assignment}}')->all();
        $routes = AuthHelper::getRoutes();
        foreach (['/site/index', '/site/contact', '/site/error', '/site/inherited', '/site/*', '/admin/root', '/admin/report/download', '/admin/report/inherited', '/user-management/user/index'] as $route) self::assertArrayHasKey($route, $routes);
        foreach (['/abstract-base/inherited', '/abstract-base/*', '/support/*', '/site/private-route', '/site/static-route', '/admin/report/root'] as $route) self::assertArrayNotHasKey($route, $routes);
        self::assertCount(1, SiteController::$dependencies);
        self::assertSame('site', SiteController::$dependencies[0][0]);
        self::assertSame(Yii::$app->mailer, SiteController::$dependencies[0][1]);
        self::assertSame(Yii::$app->security, SiteController::$dependencies[0][2]);
        self::assertSame($this->controller, Yii::$app->controller);
        self::assertSame($before, (new yii\db\Query())->from('{{%um_auth_assignment}}')->all());
    }

    public function testControllerMapTakesPrecedenceAndSupportsConfigurationAndFactories(): void
    {
        Yii::$container->set('discovery-controller', ['class' => MappedController::class, 'label' => 'aliased']);
        Yii::$app->controllerMap = [
            'site' => ['class' => MappedController::class, 'label' => 'mapped-config'],
            'injected' => SiteController::class,
            'factory' => static fn($id, $module) => new MappedController($id, $module, ['label' => 'factory']),
            'aliased' => 'discovery-controller',
            'abstract' => AbstractBaseController::class,
            'support' => SupportController::class,
        ];
        $routes = AuthHelper::getRoutes();
        self::assertArrayHasKey('/site/mapped', $routes);
        self::assertArrayNotHasKey('/site/contact', $routes);
        self::assertArrayHasKey('/injected/contact', $routes);
        self::assertArrayHasKey('/factory/mapped', $routes);
        self::assertArrayHasKey('/aliased/mapped', $routes);
        self::assertArrayNotHasKey('/abstract/*', $routes);
        self::assertArrayNotHasKey('/support/*', $routes);
        self::assertSame(['mapped-config', 'factory', 'aliased'], MappedController::$labels);
        self::assertCount(1, SiteController::$dependencies);
        self::assertSame('injected', SiteController::$dependencies[0][0]);
    }

    public function testRefreshStoresRoutesWithoutGrantingPermissionsOrRunningActions(): void
    {
        $before = (new yii\db\Query())->from('{{%um_auth_assignment}}')->all();
        Route::refreshRoutes(false);
        foreach (['/site/contact', '/site/error', '/admin/report/download'] as $route) self::assertTrue(Route::find()->where(['name' => $route])->exists());
        self::assertFalse(Route::find()->where(['name' => '/abstract-base/*'])->exists());
        self::assertSame($before, (new yii\db\Query())->from('{{%um_auth_assignment}}')->all());
        self::assertFalse(Route::isRouteAllowed('/site/contact', []));
        self::assertTrue(Route::isRouteAllowed('/site/contact', ['/site/*']));
        self::assertFalse(Route::isRouteAllowed('/admin/report/download', ['/site/*']));
    }

    public function testMappedControllerAlsoTakesPrecedenceOverAModuleWithTheSameId(): void
    {
        Yii::$app->setModule('route-shadow', new yii\base\Module('route-shadow', Yii::$app, ['controllerNamespace' => 'routefixtures\\controllers']));
        Yii::$app->controllerMap['route-shadow'] = MappedController::class;
        try {
            $routes = AuthHelper::getRoutes();
            self::assertArrayHasKey('/route-shadow/mapped', $routes);
            self::assertArrayNotHasKey('/route-shadow/site/contact', $routes);
        } finally { Yii::$app->setModule('route-shadow', null); }
    }
}

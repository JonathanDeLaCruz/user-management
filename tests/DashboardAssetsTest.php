<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\controllers\DashboardController;
use yii\web\View;

final class DashboardAssetsTest extends TestCase
{
    public function testDashboardRegistersItsOwnResourcesWithDefaultAndExternalLayouts(): void
    {
        $previous = Yii::$app->controller;
        $series = [];
        $start = new DateTimeImmutable('2026-10-01');
        for ($day = 0; $day < 30; $day++) $series[] = ['date' => $start->modify('+' . $day . ' days')->format('Y-m-d'), 'count' => $day === 29 ? 6 : 0];
        try {
            foreach (['@webvimark/modules/UserManagement/views/layouts/main', '@app/views/layouts/external'] as $layout) {
                $controller = new DashboardController('dashboard', Yii::$app->getModule('user-management'));
                $controller->layout = $layout;
                $controller->setView(new View());
                Yii::$app->controller = $controller;
                $html = $controller->render('index', ['stats' => ['Users' => 1, 'Roles' => 1, 'Permissions' => 1, 'Visit log' => 6],
                    'activity' => ['series' => $series, 'total' => 6, 'users' => 1, 'peak' => 6]]);
                self::assertStringContainsString('dashboard.css', $html);
                self::assertStringContainsString('class="um-dashboard"', $html);
                self::assertSame(30, substr_count($html, 'class="activity-bar-fill"'));
                self::assertStringContainsString('style="height:75%"', $html);
                if ($layout === '@app/views/layouts/external') {
                    self::assertStringContainsString('host-layout', $html);
                    self::assertStringNotContainsString('layout.css', $html);
                } else self::assertStringContainsString('layout.css', $html);
            }
        } finally { Yii::$app->controller = $previous; }
    }
}

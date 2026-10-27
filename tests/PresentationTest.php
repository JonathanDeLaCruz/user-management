<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\UserManagementModule as UM;
use webvimark\modules\UserManagement\models\forms\LoginForm;
use webvimark\modules\UserManagement\widgets\ActionColumn;
use webvimark\modules\UserManagement\widgets\GridBulkActions;
use yii\grid\GridView;
use yii\data\ArrayDataProvider;

class PresentationTest extends TestCase
{
    public function testDefaultLoginIncludesTheModulesOwnLayoutAndAssets(): void
    {
        $previous = Yii::$app->controller;
        try {
            $controller = new \webvimark\modules\UserManagement\controllers\AuthController('auth', Yii::$app->getModule('user-management'));
            Yii::$app->controller = $controller;
            $markup = $controller->render('login', ['model' => new LoginForm()]);
            self::assertStringContainsString('class="um-auth-page"', $markup);
            self::assertStringContainsString('class="um-auth-intro"', $markup);
            self::assertStringContainsString('Un lugar para gestionar cada acceso.', $markup);
            self::assertStringContainsString('auth.css', $markup);
        } finally {
            Yii::$app->controller = $previous;
        }
    }

    public function testLoginAndActionsFollowApplicationLanguage(): void
    {
        $previous = Yii::$app->language;
        try {
            foreach (['es' => ['Inicio de sesión', 'Nombre de usuario', 'Contraseña', 'Mostrar contraseña', 'Ocultar contraseña'], 'en' => ['Login', 'Username', 'Password', 'Show password', 'Hide password']] as $language => $labels) {
                Yii::$app->language = $language;
                $markup = Yii::$app->view->renderFile(dirname(__DIR__) . '/views/auth/login.php', ['model' => new LoginForm()]);
                foreach ($labels as $label) self::assertStringContainsString($label, $markup);
                self::assertStringContainsString('type="password"', $markup);
                self::assertStringContainsString('type="button"', $markup);
                self::assertStringContainsString('aria-controls="loginform-password"', $markup);
                self::assertStringContainsString('aria-pressed="false"', $markup);
                self::assertStringNotContainsString('Entrar al panel', $markup);
                $grid = new GridView(['dataProvider' => new ArrayDataProvider(['allModels' => [['id' => 1]]])]);
                $column = new ActionColumn(['grid' => $grid, 'urlCreator' => fn($action) => '/' . $action]);
                $delete = ($column->buttons['delete'])('/delete', ['id' => 1], 1);
                self::assertStringContainsString('data-method="post"', $delete);
                self::assertStringContainsString('data-confirm=', $delete);
                self::assertStringContainsString('aria-label="' . Yii::t('yii', 'Delete') . '"', $delete);
                self::assertStringContainsString('<svg', $delete);
                self::assertSame($language === 'es' ? 'Salir' : 'Logout', UM::t('back', 'Logout'));
                $bulk = (new GridBulkActions(['gridId' => 'presentation-grid']))->run();
                self::assertStringContainsString($language === 'es' ? 'Acciones seleccionadas' : 'Selected actions', $bulk);
            }
        } finally {
            Yii::$app->language = $previous;
        }
    }
}

<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\UserVisitLog;
use webvimark\modules\UserManagement\models\search\UserSearch;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\controllers\UserController;
use webvimark\modules\UserManagement\components\StatusColumn;
use yii\data\ArrayDataProvider;
use yii\grid\GridView;

class UserFiltersTest extends TestCase
{
    public function testSelectorsUseAvailableRolesAndKeepFalseValues(): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        $identity = Yii::$app->user->identity;
        $previousController = Yii::$app->controller;
        try {
            Yii::$app->user->setIdentity(new User(['id'=>999, 'superadmin'=>1, 'status'=>User::STATUS_ACTIVE]));
            $role = Role::create('NewCustomRole', 'Nuevo rol personalizado');
            $user = new User(['username'=>'filter-test','superadmin'=>0,'status'=>User::STATUS_INACTIVE, 'password_hash'=>Yii::$app->security->generatePasswordHash('Test password phrase')]);
            self::assertTrue($user->save(false));
            Yii::$app->authManager->assign($role, $user->id);
            $model = new UserSearch();
            $provider = $model->search(['UserSearch'=>['gridRoleSearch'=>'NewCustomRole','superadmin'=>'0','status'=>'0']]);
            self::assertCount(1, $provider->getModels());
            self::assertSame('filter-test', $provider->getModels()[0]->username);
            $controller = new UserController('user', Yii::$app->getModule('user-management'));
            Yii::$app->controller = $controller;
            $markup = Yii::$app->view->renderFile(dirname(__DIR__).'/views/user/index.php', ['dataProvider'=>$provider,'searchModel'=>$model], $controller);
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">'.$markup);
            $xpath = new DOMXPath($document);
            foreach (['gridRoleSearch', 'status', 'superadmin'] as $attribute) {
                $selectors = $xpath->query('//select[@name="UserSearch['.$attribute.']"]');
                self::assertSame(1, $selectors->length);
                self::assertStringContainsString('form-select', $selectors->item(0)->getAttribute('class'));
            }
            self::assertStringContainsString('Nuevo rol personalizado', $markup);
            $clear = $xpath->query('//a[@aria-label="Limpiar filtros"]');
            self::assertSame(1, $clear->length);
            self::assertNull(parse_url($clear->item(0)->getAttribute('href'), PHP_URL_QUERY));
            self::assertStringContainsString('value="NewCustomRole" selected', $markup);
        } finally {
            $transaction->rollBack();
            Yii::$app->controller = $previousController;
            Yii::$app->user->setIdentity($identity);
            Yii::$app->authManager->invalidateCache();
        }
    }

    public function testVisitHeadingUsesEscapedUsernameAndHandlesDeletedUser(): void
    {
        $model = new UserVisitLog(['id'=>987,'visit_time'=>time()]);
        $model->populateRelation('user', new User(['username'=>'<Example user>']));
        $markup = Yii::$app->view->renderFile(dirname(__DIR__).'/views/user-visit-log/view.php', ['model'=>$model]);
        self::assertSame('<Example user>', Yii::$app->view->title);
        self::assertStringContainsString('&lt;Example user&gt;</h2>', $markup);
        $model->populateRelation('user', null);
        $markup = Yii::$app->view->renderFile(dirname(__DIR__).'/views/user-visit-log/view.php', ['model'=>$model]);
        self::assertStringContainsString('Usuario eliminado</h2>', $markup);
        self::assertStringNotContainsString('>987</h2>', $markup);
    }
}

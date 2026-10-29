<?php
namespace webvimark\modules\UserManagement\controllers;

use Yii;
use yii\web\{Controller, ForbiddenHttpException};
use yii\filters\VerbFilter;
use webvimark\modules\UserManagement\components\{ExternalAuth, Audit};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

class ExternalAuthController extends Controller
{
    public $freeAccess = true; // Authorization is checked per intent by ExternalAuth.

    public function behaviors()
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['start' => ['POST'], 'callback' => ['GET'], 'unlink' => ['POST']]]];
    }

    public function beforeAction($action)
    {
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return parent::beforeAction($action);
    }

    public function actionStart(string $provider)
    {
        $intent = Yii::$app->request->post('intent', 'login');
        if (!is_string($intent)) throw new ForbiddenHttpException();
        return $this->redirect((new ExternalAuth())->start($provider, $intent));
    }

    public function actionCallback(string $provider)
    {
        if (!UM::instance()->checkAttempts((in_array($provider, ['google', 'microsoft'], true) ? $provider : 'unknown') . ':' . Yii::$app->request->userIP, 'external-callback')) {
            Yii::$app->session->setFlash('danger', UM::t('front', 'Too many attempts'));
            return $this->redirect(['/user-management/auth/login']);
        }
        try {
            $destination = $this->authClient()->callback($provider, Yii::$app->request->queryParams);
            return $this->redirect(match ($destination) {
                'dashboard' => UM::instance()->resolveAfterLoginUrl(),
                'security' => ['/user-management/security/index'],
                'mfa' => ['/user-management/security/challenge'],
                default => throw new \UnexpectedValueException('Unknown authentication outcome.'),
            });
        } catch (\Throwable $e) {
            Audit::record('external.failed', null, ['provider' => in_array($provider, ['google', 'microsoft'], true) ? $provider : 'unknown']);
            // Never expose provider responses, authorization codes, JWTs or exception traces to the user.
            Yii::$app->session->setFlash('danger', UM::t('front', 'External sign-in failed or expired. Try again, or use your existing sign-in method.'));
            return $this->redirect(['/user-management/' . (User::component()->isGuest ? 'auth/login' : 'security/index')]);
        }
    }

    protected function authClient(): ExternalAuth { return new ExternalAuth(); }

    public function actionUnlink(string $provider)
    {
        if (User::component()->isGuest) throw new ForbiddenHttpException();
        (new ExternalAuth())->unlink(User::component()->identity, $provider, (string)Yii::$app->request->post('password', ''));
        Yii::$app->session->setFlash('success', UM::t('front', 'External account unlinked.'));
        return $this->redirect(['/user-management/security/index']);
    }
}

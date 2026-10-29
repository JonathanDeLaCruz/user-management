<?php
namespace webvimark\modules\UserManagement\controllers;

use Yii;
use yii\db\Query;
use yii\filters\VerbFilter;
use yii\web\{ForbiddenHttpException, Response};
use webvimark\modules\UserManagement\components\{BaseController, AccountSecurity as Security, Passkeys, SecuritySessions, Audit};
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule as UM;

class SecurityController extends BaseController
{
    public $freeAccess = true;

    public function behaviors()
    {
        return ['security-verbs' => ['class' => VerbFilter::class, 'actions' => array_fill_keys([
            'passkey-options', 'passkey-finish', 'reauthenticate', 'totp-setup', 'totp-confirm',
            'remove-factor', 'recovery-codes', 'revoke-session',
        ], ['POST'])]] + parent::behaviors();
    }

    public function beforeAction($action)
    {
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->response->headers->set('Referrer-Policy', 'same-origin');
        if (User::component()->isGuest) {
            if (!Security::pending() || !in_array($action->id, ['challenge', 'passkey-options', 'passkey-finish', 'totp-setup', 'totp-confirm'], true)) {
                User::component()->loginRequired();
                return false;
            }
            $this->layout = '@webvimark/modules/UserManagement/views/layouts/auth';
        }
        return parent::beforeAction($action);
    }

    private function user(): User
    {
        $user = User::component()->isGuest ? Security::pending() : User::findIdentity(User::component()->id);
        if (!$user || !Security::ipAllowed($user)) throw new ForbiddenHttpException();
        return $user;
    }

    private function recent(): User
    {
        $user = $this->user();
        if (User::component()->isGuest || !Security::recent($user)) throw new ForbiddenHttpException(UM::t('front', 'Verify your identity before making changes.'));
        return $user;
    }

    private function data(User $user, array $extra = []): array
    {
        return $extra + ['user' => $user, 'keys' => Security::passkeys($user), 'strict' => Security::strict($user),
            'recent' => Security::recent($user), 'profile' => Security::profile($user),
            'sessions' => (new Query())->from('{{%um_session}}')->where(['user_id' => $user->id, 'revoked_at' => null])
                ->andWhere(['>', 'expires_at', time()])->orderBy(['last_seen_at' => SORT_DESC])->all()];
    }

    public function actionIndex() { return $this->render('index', $this->data($this->user())); }

    public function actionChallenge()
    {
        if (!User::component()->isGuest) return $this->redirect(['index']);
        $user = $this->user(); $error = null;
        if (Yii::$app->request->isPost) {
            $method = Yii::$app->request->post('method');
            $code = (string)Yii::$app->request->post('code', '');
            if ($method === 'totp' && Security::verifyTotp($user, $code)) return $this->redirect($this->finishLogin($user, 'totp'));
            if ($method !== 'recovery' || !Security::recover($user, $code)) {
                Audit::record('mfa.failed', (string)$user->id);
                $error = UM::t('front', 'Verification failed. Try again.');
            }
        }
        return $this->render('challenge', $this->data($user, ['error' => $error]));
    }

    private function finishLogin(User $user, string $method): string
    {
        if (Security::recovered($user)) {
            SecuritySessions::revoke($user->id);
            $user->generateAuthKey(); $user->save(false);
        }
        if (!User::component()->completeSecureLogin($user, $method)) throw new ForbiddenHttpException();
        return UM::instance()->resolveAfterLoginUrl();
    }

    public function actionPasskeyOptions()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $user = $this->user(); $operation = (string)Yii::$app->request->post('operation', '');
        if (($operation === 'login') !== User::component()->isGuest && $operation !== 'register') throw new ForbiddenHttpException();
        if ($operation === 'register' && count(Security::passkeys($user)) >= 10) throw new ForbiddenHttpException();
        return Passkeys::options($user, $operation);
    }

    public function actionPasskeyFinish()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $user = $this->user(); $operation = (string)Yii::$app->request->post('operation', '');
        if (($operation === 'login') !== User::component()->isGuest && $operation !== 'register') throw new ForbiddenHttpException();
        try {
            $raw = (string)Yii::$app->request->post('credential', '');
            if (strlen($raw) > 100000) throw new ForbiddenHttpException();
            Passkeys::verify($user, $operation, json_decode($raw, true, 32, JSON_THROW_ON_ERROR), (string)Yii::$app->request->post('name', ''));
        } catch (\Throwable $e) {
            Audit::record('passkey.failed', (string)$user->id);
            Yii::$app->response->statusCode = 400;
            return ['error' => UM::t('front', 'Verification failed. Try again.')];
        }
        if (User::component()->isGuest) {
            $destination = $this->finishLogin($user, 'passkey');
            Yii::$app->response->statusCode = 200;
            Yii::$app->response->headers->remove('Location');
            return ['redirect' => $destination];
        }
        return ['redirect' => \yii\helpers\Url::to(['index'])];
    }

    public function actionReauthenticate()
    {
        $user = $this->user();
        $reauthModel = new \webvimark\modules\UserManagement\models\forms\ReauthenticationForm(['user' => $user]);
        $reauthModel->load(Yii::$app->request->post(), '');
        try {
            Security::rate($user, 'reauth');
            if ($reauthModel->verify()) {
                Security::markRecent($user, $reauthModel->verificationMethod());
                Yii::$app->session->remove('um_external_reauth');
                return $this->redirect(['index']);
            }
        } catch (ForbiddenHttpException $e) {
            $reauthModel->addError('password', UM::t('front', 'Too many attempts'));
        }
        $reauthModel->password = ''; $reauthModel->code = '';
        Audit::record('reauth.failed', (string)$user->id);
        return $this->render('index', $this->data($user, compact('reauthModel')));
    }

    public function actionTotpSetup()
    {
        $user = $this->user(); $otp = Security::totpSetup($user);
        return $this->render(User::component()->isGuest ? 'challenge' : 'index', $this->data($user, ['otp' => $otp, 'error' => null]));
    }

    public function actionTotpConfirm()
    {
        $user = $this->user();
        if (!Security::confirmTotp($user, (string)Yii::$app->request->post('code', ''))) throw new ForbiddenHttpException(UM::t('front', 'Verification failed. Try again.'));
        if (User::component()->isGuest) return $this->redirect($this->finishLogin($user, 'totp'));
        return $this->redirect(['index']);
    }

    public function actionRemoveFactor()
    {
        $user = $this->recent(); Security::rate($user, 'factor-remove');
        $transaction = Yii::$app->db->beginTransaction();
        try {
        Security::profile($user);
        // Serialize concurrent removals for this account so two requests cannot remove both backup keys.
        Yii::$app->db->createCommand()->update('{{%um_mfa}}', ['user_handle' => new \yii\db\Expression('[[user_handle]]')], ['user_id' => $user->id])->execute();
        $kind = Yii::$app->request->post('kind');
        if ($kind === 'passkey') {
            if (count(Security::passkeys($user)) <= 1 && Security::policyRequires($user)) throw new ForbiddenHttpException(UM::t('front', 'Register another factor before removing this one.'));
            if (Yii::$app->db->createCommand()->delete('{{%um_passkey}}', ['id' => (string)Yii::$app->request->post('id', ''), 'user_id' => $user->id])->execute() !== 1) throw new ForbiddenHttpException();
        } elseif ($kind === 'totp') {
            if (!Security::passkeys($user) && Security::policyRequires($user)) throw new ForbiddenHttpException();
            Yii::$app->db->createCommand()->update('{{%um_mfa}}', ['totp_secret' => null, 'totp_step' => -1], ['user_id' => $user->id])->execute();
        } else throw new ForbiddenHttpException();
        Yii::$app->db->createCommand()->delete('{{%um_recovery_code}}', ['user_id' => $user->id])->execute();
        SecuritySessions::revoke($user->id, null, true);
        $transaction->commit();
        } catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
        Security::notify($user, 'Authentication factor removed');
        return $this->redirect(['index']);
    }

    public function actionRecoveryCodes()
    {
        $user = $this->recent();
        return $this->render('index', $this->data($user, ['codes' => Security::recoveryCodes($user)]));
    }

    public function actionRevokeSession()
    {
        $user = $this->recent(); $id = (string)Yii::$app->request->post('id', '');
        if ($id === 'others') SecuritySessions::revoke($user->id, null, true);
        elseif (preg_match('/^[a-f0-9]{64}$/D', $id)) SecuritySessions::revoke($user->id, $id);
        else throw new ForbiddenHttpException();
        return $this->redirect(['index']);
    }

    public function actionAudit()
    {
        if (!User::component()->isSuperadmin) throw new ForbiddenHttpException();
        $searchModel = new \webvimark\modules\UserManagement\models\search\AuditLogSearch();
        return $this->render('audit', ['provider' => $searchModel->search(Yii::$app->request->queryParams), 'searchModel' => $searchModel]);
    }
}

<?php
namespace webvimark\modules\UserManagement\commands;

use Yii;
use yii\console\ExitCode;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\components\{Passkeys, AccountSecurity, SecuritySessions, Audit};

class SecurityController extends \yii\console\Controller
{
    public $confirmRecovery = false;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), $actionID === 'reset-mfa' ? ['confirmRecovery'] : []);
    }

    public function actionCheck()
    {
        $minimum = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
        $branch = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        if (!isset($minimum[$branch]) || version_compare(PHP_VERSION, $minimum[$branch], '<')) {
            $this->stderr("Update PHP to a supported security release.\n"); return ExitCode::CONFIG;
        }
        if (PHP_OS_FAMILY === 'Windows' && extension_loaded('curl')) {
            $brotli = curl_version()['brotli_version'] ?? '';
            if ($brotli && version_compare(str_replace('brotli/', '', $brotli), '1.2.1', '<')) {
                $this->stderr("Update the Windows PHP build to revision -1 or later (Brotli >=1.2.1).\n"); return ExitCode::CONFIG;
            }
        }
        $this->stdout('PHP ' . PHP_VERSION . ": OK\n");
        $module = \webvimark\modules\UserManagement\UserManagementModule::instance();
        if ($module->requirePhishingResistant || $module->webauthnOrigin || getenv('UM_WEBAUTHN_ORIGIN')) {
            try { Passkeys::origin(); $this->stdout("WebAuthn origin: configured\n"); }
            catch (\Throwable $e) { $this->stderr($e->getMessage() . "\n"); return ExitCode::CONFIG; }
        } else $this->stdout("WebAuthn origin: optional (TOTP policy)\n");
        $key = base64_decode((string)($module->mfaEncryptionKey ?: getenv('UM_MFA_ENCRYPTION_KEY')), true);
        if ($key === false || strlen($key) !== 32) { $this->stderr("Configure a random 32-byte base64 UM_MFA_ENCRYPTION_KEY for TOTP.\n"); return ExitCode::CONFIG; }
        foreach (['um_mfa', 'um_passkey', 'um_recovery_code', 'um_session', 'um_audit_log', 'um_external_identity'] as $table) {
            if (!Yii::$app->db->getTableSchema('{{%' . $table . '}}', true)) { $this->stderr("Run the module migrations.\n"); return ExitCode::CONFIG; }
        }
        foreach (\webvimark\modules\UserManagement\components\ExternalAuth::enabled() as $provider => $label) {
            try { \webvimark\modules\UserManagement\components\ExternalAuth::config($provider); }
            catch (\Throwable $e) { $this->stderr($e->getMessage() . "\n"); return ExitCode::CONFIG; }
            $this->stdout($label . ": configured (external connection not tested)\n");
        }
        $this->stdout("Security tables and TOTP encryption key: OK\n");
        return ExitCode::OK;
    }

    public function actionResetMfa(string $username)
    {
        if (!$this->confirmRecovery) {
            $this->stderr("Verify the account owner's identity out of band. Re-run with --confirmRecovery=1 to revoke all factors and sessions.\n");
            return ExitCode::USAGE;
        }
        $user = User::findByUsername($username);
        if (!$user) { $this->stderr("User not found.\n"); return ExitCode::DATAERR; }
        $tx = Yii::$app->db->beginTransaction();
        try {
            foreach (['um_passkey', 'um_recovery_code', 'um_mfa'] as $table) Yii::$app->db->createCommand()->delete('{{%' . $table . '}}', ['user_id' => $user->id])->execute();
            SecuritySessions::revoke($user->id);
            $user->generateAuthKey(); $user->confirmation_token = null;
            if (!$user->save(false)) throw new \RuntimeException('Cannot update user.');
            Audit::record('operator.mfa_reset', (string)$user->id);
            $tx->commit();
        } catch (\Throwable $e) { $tx->rollBack(); throw $e; }
        AccountSecurity::notify($user, 'Authentication factors reset by operator');
        $this->stdout("Factors and sessions revoked. The next login must enroll again if the policy requires MFA.\n");
        return ExitCode::OK;
    }

    public function actionCleanup(int $days = 90)
    {
        if ($days < 30) { $this->stderr("Retention must be at least 30 days.\n"); return ExitCode::USAGE; }
        $before = time() - $days * 86400;
        Yii::$app->db->createCommand()->delete('{{%um_session}}', ['<', 'expires_at', $before])->execute();
        $deleted = Yii::$app->db->createCommand()->delete('{{%um_audit_log}}', ['<', 'created_at', $before])->execute();
        Audit::record('operator.retention', null, ['days' => $days, 'deleted' => $deleted]);
        $this->stdout("Expired sessions and old audit records cleaned.\n");
        return ExitCode::OK;
    }
}

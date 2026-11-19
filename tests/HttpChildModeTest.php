<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;

final class HttpChildModeTest extends TestCase
{
    public function testChildHttpSessionAdministrationBlockedAuthAndPostLogout(): void
    {
        $root = dirname(__DIR__);
        $temp = Yii::$app->runtimePath . '/http-child-' . bin2hex(random_bytes(6));
        mkdir($temp . '/sessions', 0755, true);
        file_put_contents($temp . '/cookie-key.php', '<?php return ' . var_export(bin2hex(random_bytes(32)), true) . ';');
        $database = $temp . '/db.sqlite';
        Yii::$app->db->createCommand('VACUUM INTO ' . Yii::$app->db->quoteValue($database))->execute();
        $db = new yii\db\Connection(['dsn' => 'sqlite:' . $database]);
        $db->createCommand()->insert(User::tableName(), ['username' => 'http-central-account', 'auth_source' => 'central',
            'password_hash' => '!', 'auth_key' => Yii::$app->security->generateRandomString(), 'status' => 1,
            'superadmin' => 1, 'created_at' => time(), 'updated_at' => time()])->execute();
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($socket, $error); $address = stream_socket_get_name($socket, false); fclose($socket);
        $env = getenv(); $env['UM_DB_DSN'] = 'sqlite:' . $database; $env['UM_RUNTIME_PATH'] = $temp; $env['UM_TEST_CHILD_MODE'] = '1';
        $process = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/tests/fixtures/app/web', $root . '/tests/fixtures/app/web/router.php'],
            [['pipe', 'r'], ['file', $temp . '/out.log', 'w'], ['file', $temp . '/err.log', 'w']], $pipes, $root, $env);
        self::assertIsResource($process); fclose($pipes[0]);
        $request = static function (string $path, ?array $data = null) use ($address, $temp): array {
            $curl = curl_init('http://' . $address . $path);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $temp . '/cookies.txt',
                CURLOPT_COOKIEJAR => $temp . '/cookies.txt', CURLOPT_TIMEOUT => 15]);
            if ($data !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]);
            $body = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $curl = null;
            return [$code, $body];
        };
        try {
            $ready = false;
            for ($i = 0; $i < 50; $i++) {
                $probe = @fsockopen('127.0.0.1', (int)substr(strrchr($address, ':'), 1), $errno, $error, .1);
                if ($probe) { fclose($probe); $ready = true; break; } usleep(100000);
            }
            self::assertTrue($ready);
            [$code] = $request('/user-management/user/index'); self::assertSame(302, $code);
            foreach (['auth/login', 'auth/registration', 'auth/password-recovery', 'auth/password-recovery-receive?token=invalid',
                'auth/change-own-password', 'auth/confirm-email', 'auth/confirm-email-receive?token=invalid',
                'auth/confirm-registration-email?token=invalid', 'security/challenge', 'security/passkey-options',
                'security/passkey-finish', 'security/totp-setup', 'security/totp-confirm', 'security/recovery-codes',
                'security/remove-factor', 'security/reauthenticate', 'external-auth/start?provider=google',
                'external-auth/callback?provider=microsoft'] as $route) {
                [$code] = $request('/user-management/' . $route); self::assertSame(404, $code, $route);
            }
            [$code, $body] = $request('/central-test/entry'); self::assertSame(200, $code);
            preg_match('/name="_csrf" value="([^"]+)"/', $body, $matches); self::assertNotEmpty($matches);
            [$code] = $request('/central-test/entry', ['_csrf' => html_entity_decode($matches[1])]); self::assertSame(302, $code);
            [$code, $body] = $request('/user-management/security/index'); self::assertSame(200, $code);
            self::assertStringContainsString('Sesiones activas', $body);
            self::assertStringNotContainsString('totp-setup', $body); self::assertStringNotContainsString('passkey-options', $body);
            preg_match('/name="_csrf" value="([^"]+)"/', $body, $matches); self::assertNotEmpty($matches);
            $csrf = html_entity_decode($matches[1]);
            foreach (['user/index', 'role/index', 'permission/index', 'auth-item-group/index', 'security/audit'] as $route) {
                [$code, $html] = $request('/user-management/' . $route); self::assertSame(200, $code, $route);
                if ($route === 'user/index') {
                    self::assertStringContainsString('Cuenta del sistema central', $html);
                    self::assertStringNotContainsString('change-password?', $html);
                }
            }
            $session = (new yii\db\Query())->from('{{%um_session}}')->where(['method' => 'central'])->one($db);
            self::assertIsArray($session); self::assertNull($session['revoked_at']);
            [$code] = $request('/user-management/auth/logout'); self::assertSame(405, $code);
            [$code] = $request('/user-management/auth/logout', []); self::assertSame(400, $code);
            [$code] = $request('/user-management/auth/logout', ['_csrf' => $csrf]); self::assertSame(302, $code);
            self::assertNotNull((new yii\db\Query())->select('revoked_at')->from('{{%um_session}}')->where(['id' => $session['id']])->scalar($db));
            [$code] = $request('/user-management/user/index'); self::assertSame(302, $code);
        } finally { proc_terminate($process); proc_close($process); $db->close(); }
    }
}

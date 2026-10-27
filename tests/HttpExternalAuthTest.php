<?php
use PHPUnit\Framework\TestCase;

final class HttpExternalAuthTest extends TestCase
{
    public function testButtonsPostCsrfPkceAndRejectedCallbacksWithoutContactingAProvider(): void
    {
        $root = dirname(__DIR__);
        $temp = Yii::$app->runtimePath . '/http-external-' . bin2hex(random_bytes(6));
        mkdir($temp . '/sessions', 0755, true);
        file_put_contents($temp . '/cookie-key.php', '<?php return ' . var_export(bin2hex(random_bytes(32)), true) . ';');
        $database = $temp . '/db.sqlite';
        Yii::$app->db->createCommand('VACUUM INTO ' . Yii::$app->db->quoteValue($database))->execute();
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($probe); $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1); fclose($probe);
        $environment = getenv(); $environment['UM_DB_DSN'] = 'sqlite:' . $database;
        $environment['UM_RUNTIME_PATH'] = $temp; $environment['UM_TEST_EXTERNAL_PROVIDERS'] = '1';
        $environment['UM_WEBAUTHN_ORIGIN'] = 'http://localhost:' . $port;
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-S', 'localhost:' . $port, '-t', $root . '/tests/fixtures/app/web', $root . '/tests/fixtures/app/web/router.php'],
            [['pipe', 'r'], ['file', $temp . '/out.log', 'w'], ['file', $temp . '/err.log', 'w']], $pipes, $root, $environment);
        self::assertIsResource($process); fclose($pipes[0]);
        $cookie = $temp . '/cookies.txt';
        $request = function ($path, $data = null) use ($port, $cookie) {
            $curl = curl_init('http://localhost:' . $port . '/user-management/' . $path);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
                CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_TIMEOUT => 10]);
            if ($data !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]);
            $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
            return [$status, $body]; // Redirects are deliberately never followed to the real providers.
        };
        try {
            for ($i = 0; $i < 50; $i++) { $socket = @fsockopen('localhost', $port, $errno, $error, .1); if ($socket) { fclose($socket); break; } usleep(100000); }
            [$code, $html] = $request('auth/login'); self::assertSame(200, $code);
            self::assertStringContainsString('Continuar con Google', $html); self::assertStringContainsString('Continuar con Microsoft', $html);
            preg_match('/name="_csrf" value="([^"]+)"/', $html, $match); self::assertNotEmpty($match); $csrf = html_entity_decode($match[1]);
            [$code] = $request('external-auth/start?provider=google'); self::assertSame(405, $code);
            [$code] = $request('external-auth/start?provider=google', []); self::assertSame(400, $code);
            foreach (['google' => 'accounts.google.com', 'microsoft' => 'login.microsoftonline.com'] as $provider => $host) {
                [$code, $response] = $request('external-auth/start?provider=' . $provider, ['_csrf' => $csrf]); self::assertSame(302, $code);
                preg_match('/^Location: ([^\r\n]+)/mi', $response, $location); self::assertNotEmpty($location);
                self::assertSame($host, parse_url($location[1], PHP_URL_HOST));
                parse_str(parse_url($location[1], PHP_URL_QUERY), $params);
                self::assertSame('S256', $params['code_challenge_method']); self::assertSame('code', $params['response_type']);
                self::assertNotEmpty($params['nonce']); self::assertNotEmpty($params['state']);
                self::assertStringNotContainsString('test-only-secret', $location[1]);
            }
            [$code] = $request('external-auth/callback?provider=google&state=forged&code=forged'); self::assertSame(302, $code);
            [$code] = $request('dashboard/index'); self::assertSame(302, $code, 'A forged callback must not authenticate the browser.');
        } finally { proc_terminate($process); proc_close($process); }
    }
}

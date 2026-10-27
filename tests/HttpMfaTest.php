<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;
require_once __DIR__ . '/fixtures/VirtualAuthenticator.php';

class HttpMfaTest extends TestCase
{
    public function testPasswordDoesNotGrantAccessAndSignedPasskeyCompletesLogin(): void
    {
        $root = dirname(__DIR__);
        $temp = Yii::$app->runtimePath . '/http-mfa-' . bin2hex(random_bytes(6));
        mkdir($temp . '/sessions',0755,true);
        file_put_contents($temp.'/cookie-key.php','<?php return '.var_export(bin2hex(random_bytes(32)),true).';');
        $database = $temp.'/db.sqlite';
        Yii::$app->db->createCommand('VACUUM INTO '.Yii::$app->db->quoteValue($database))->execute();
        $db = new yii\db\Connection(['dsn'=>'sqlite:'.$database]);
        $password = Yii::$app->security->generateRandomString(24);
        $db->createCommand()->insert(User::tableName(),['username'=>'mfa-http-admin','password_hash'=>Yii::$app->security->generatePasswordHash($password),
            'auth_key'=>Yii::$app->security->generateRandomString(),'status'=>1,'superadmin'=>1,'created_at'=>time(),'updated_at'=>time()])->execute();
        $probe = stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
        $port = (int)substr(strrchr(stream_socket_get_name($probe,false),':'),1); fclose($probe);
        $origin = 'http://localhost:'.$port;
        $environment = getenv();
        $environment['UM_DB_DSN']='sqlite:'.$database; $environment['UM_RUNTIME_PATH']=$temp;
        $environment['UM_TEST_MFA_POLICY']='admins'; $environment['UM_WEBAUTHN_ORIGIN']=$origin;
        $pipes=[];
        $process=proc_open([PHP_BINARY,'-S','localhost:'.$port,'-t',$root.'/tests/fixtures/app/web',$root.'/tests/fixtures/app/web/router.php'],
            [['pipe','r'],['file',$temp.'/out.log','w'],['file',$temp.'/err.log','w']],$pipes,$root,$environment);
        self::assertIsResource($process); fclose($pipes[0]);
        $cookie=$temp.'/cookies.txt';
        $request=function(string $path,?array $body=null) use($origin,$cookie): array {
            $curl=curl_init($origin.'/user-management/'.$path);
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>15]);
            if($body!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($body)]);
            $response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);return [$status,$response];
        };
        $csrf=function(string $page): string {
            preg_match('/name="(?:_csrf|csrf-token)" (?:content|value)="([^"]+)"/',$page,$match);
            self::assertNotEmpty($match);return html_entity_decode($match[1]);
        };
        try {
            $ready=false;
            for($i=0;$i<50;$i++){ $socket=@fsockopen('localhost',$port,$errno,$error,.1);if($socket){fclose($socket);$ready=true;break;}usleep(100000); }
            self::assertTrue($ready);
            [$code,$html]=$request('auth/login');self::assertSame(200,$code);$token=$csrf($html);
            [$code]=$request('auth/login',['_csrf'=>$token,'LoginForm'=>['username'=>'mfa-http-admin','password'=>$password]]);self::assertSame(302,$code);
            [$code]=$request('dashboard/index');self::assertSame(302,$code,'Password must not grant a full session.');
            self::assertSame(0,(int)(new yii\db\Query())->from('{{%um_session}}')->count('*',$db));
            [$code,$html]=$request('security/challenge');self::assertSame(200,$code);$token=$csrf($html);
            [$code]=$request('security/passkey-options',['operation'=>'register']);self::assertSame(400,$code,'CSRF must protect enrollment.');
            [$code,$json]=$request('security/passkey-options',['_csrf'=>$token,'operation'=>'register']);self::assertSame(200,$code);
            $options=json_decode($json,true,32,JSON_THROW_ON_ERROR);self::assertSame('required',$options['authenticatorSelection']['userVerification']);
            $authenticator=new VirtualAuthenticator();
            [$code,$json]=$request('security/passkey-finish',['_csrf'=>$token,'operation'=>'register','name'=>'HTTP passkey','credential'=>json_encode($authenticator->create($options,$origin))]);
            self::assertSame(200,$code,$json);self::assertArrayHasKey('redirect',json_decode($json,true));
            [$code,$html]=$request('dashboard/index');self::assertSame(200,$code);$token=$csrf($html);
            self::assertSame('passkey',(new yii\db\Query())->select('method')->from('{{%um_session}}')->scalar($db));
            [$code]=$request('auth/logout',['_csrf'=>$token]);self::assertSame(302,$code);
            [$code,$html]=$request('auth/login');$token=$csrf($html);
            [$code]=$request('auth/login',['_csrf'=>$token,'LoginForm'=>['username'=>'mfa-http-admin','password'=>$password]]);self::assertSame(302,$code);
            [$code,$html]=$request('security/challenge');$token=$csrf($html);
            [$code,$json]=$request('security/passkey-options',['_csrf'=>$token,'operation'=>'login']);self::assertSame(200,$code);
            $options=json_decode($json,true,32,JSON_THROW_ON_ERROR);
            [$code]=$request('security/passkey-finish',['_csrf'=>$token,'operation'=>'login','credential'=>json_encode($authenticator->get($options,'https://phishing.test'))]);self::assertSame(400,$code);
            [$code]=$request('dashboard/index');self::assertSame(302,$code);
            [$code,$json]=$request('security/passkey-options',['_csrf'=>$token,'operation'=>'login']);self::assertSame(200,$code);
            $options=json_decode($json,true,32,JSON_THROW_ON_ERROR);
            [$code,$json]=$request('security/passkey-finish',['_csrf'=>$token,'operation'=>'login','credential'=>json_encode($authenticator->get($options,$origin))]);self::assertSame(200,$code,$json);
            [$code]=$request('security/index');self::assertSame(200,$code);
        } finally { proc_terminate($process);proc_close($process);$db->close(); }
    }
}

<?php
use PHPUnit\Framework\TestCase;
use webvimark\modules\UserManagement\models\User;

class HttpSmokeTest extends TestCase
{
    public function testAdministrativePagesAndPostProtections(): void
    {
        $root=dirname(__DIR__);
        $temp=Yii::$app->runtimePath . '/http-test-' . bin2hex(random_bytes(6));
        mkdir($temp . '/sessions',0755,true);
        file_put_contents($temp . '/cookie-key.php','<?php return ' . var_export(bin2hex(random_bytes(32)),true) . ';');
        $dbFile=$temp . '/test.sqlite';
        // Copy the migrated in-memory test schema and data to a completely separate HTTP database.
        Yii::$app->db->createCommand('VACUUM INTO ' . Yii::$app->db->quoteValue($dbFile))->execute();
        $db=new yii\db\Connection(['dsn'=>'sqlite:'.$dbFile]);
        $security=Yii::$app->security; $password=$security->generateRandomString(24);
        $db->createCommand()->insert(User::tableName(),['username'=>'http-admin','password_hash'=>$security->generatePasswordHash($password),'auth_key'=>$security->generateRandomString(),'status'=>1,'superadmin'=>1,'created_at'=>time(),'updated_at'=>time(),'email'=>'http@example.test','email_confirmed'=>1])->execute();
        $userId=$db->getLastInsertID();
        $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); self::assertNotFalse($socket,$error); $address=stream_socket_get_name($socket,false); fclose($socket);
        $environment=getenv(); $environment['UM_DB_DSN']='sqlite:'.$dbFile; $environment['UM_RUNTIME_PATH']=$temp;
        $environment['UM_TEST_EXTERNAL_LAYOUT']='1';
        $pipes=[]; $process=proc_open([PHP_BINARY,'-S',$address,'-t',$root.'/tests/fixtures/app/web',$root.'/tests/fixtures/app/web/router.php'],[['pipe','r'],['file',$temp.'/stdout.log','w'],['file',$temp.'/stderr.log','w']],$pipes,$root,$environment);
        self::assertIsResource($process); fclose($pipes[0]);
        $base='http://'.$address; $cookie=$temp.'/cookies.txt';
        $request=function($path,$data=null) use($base,$cookie): array {
            $curl=curl_init($base.$path); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>15]);
            if($data!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
            $body=curl_exec($curl); $code=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $curl=null; return [$code,$body];
        };
        try {
            $ready=false; for($i=0;$i<50;$i++){ $probe=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1),$errno,$error,.1); if($probe){fclose($probe);$ready=true;break;}usleep(100000); }
            self::assertTrue($ready);
            [$guestCode] = $request('/user-management/dashboard/index'); self::assertSame(302, $guestCode);
            [$code,$body]=$request('/user-management/auth/login'); self::assertSame(200,$code); self::assertStringContainsString('bootstrap.css',$body);
            preg_match('/name="_csrf" value="([^"]+)"/',$body,$matches); self::assertNotEmpty($matches); $csrf=html_entity_decode($matches[1]);
            [$code]=$request('/user-management/auth/login',['_csrf'=>$csrf,'LoginForm'=>['username'=>'http-admin','password'=>$password]]); self::assertSame(302,$code);
            foreach(['/user-management/security/index','/user-management/security/audit','/site/index','/user-management/dashboard/index','/user-management','/user-management/user/index','/user-management/user/create','/user-management/user/view?id='.$userId,'/user-management/user/update?id='.$userId,'/user-management/user/change-password?id='.$userId,'/user-management/user-permission/set?id='.$userId,'/user-management/role/index','/user-management/role/create','/user-management/role/view?id=Admin','/user-management/role/update?id=Admin','/user-management/permission/index','/user-management/permission/create','/user-management/permission/view?id=viewUsers','/user-management/permission/update?id=viewUsers','/user-management/auth-item-group/index','/user-management/auth-item-group/create','/user-management/auth-item-group/view?id=userManagement','/user-management/auth-item-group/update?id=userManagement','/user-management/user-visit-log/index','/user-management/auth/change-own-password','/user-management/auth/confirm-email'] as $path) { [$code,$body]=$request($path); self::assertSame(200,$code,$path . ': ' . strip_tags(substr($body,0,300))); self::assertStringNotContainsString('glyphicon',$body); }
            foreach(['/user-management/security/passkey-options','/user-management/security/passkey-finish','/user-management/security/remove-factor','/user-management/security/revoke-session','/user-management/auth/logout','/user-management/user/delete?id='.$userId,'/user-management/user/bulk-delete','/user-management/user-permission/set-roles?id='.$userId,'/user-management/permission/refresh-routes?id=viewUsers'] as $path) { [$code]=$request($path); self::assertSame(405,$code,$path); }
            [$code]=$request('/user-management/user/delete?id='.$userId,[]); self::assertSame(400,$code,'CSRF must be enforced');
            [$code]=$request('/user-management/external-auth/start?provider=google'); self::assertSame(405,$code);
            [$code]=$request('/user-management/external-auth/unlink?provider=google'); self::assertSame(405,$code);
            [$code]=$request('/user-management/external-auth/start?provider=google',[]); self::assertSame(400,$code,'CSRF must protect external sign-in.');
            [$code,$body]=$request('/user-management/dashboard/index'); self::assertSame(200,$code);
            self::assertStringContainsString('host-layout',$body);
            self::assertStringContainsString('dashboard.css',$body);
            self::assertStringNotContainsString('layout.css',$body);
            [$code,$body]=$request('/site/index'); preg_match('/name="csrf-token" content="([^"]+)"/',$body,$matches); self::assertNotEmpty($matches); $csrf=html_entity_decode($matches[1]);
            $post=function($path,$data) use($request,$csrf): array { return $request($path,['_' . 'csrf'=>$csrf]+$data); };
            [$code]=$post('/user-management/external-auth/start?provider=google', []); self::assertSame(404,$code,'Providers must be disabled by default.');
            [$code,$body]=$post('/user-management/security/reauthenticate',['password'=>'wrong phrase','code'=>'Samsung']);
            self::assertSame(200,$code,'Invalid verification values must be shown in the form, not as a 403 page.');
            self::assertStringContainsString('alert alert-danger',$body);
            foreach([
                ['/user-management/auth-item-group/create',['AuthItemGroup'=>['code'=>'httpGroup','name'=>'HTTP test group']]],
                ['/user-management/role/create',['Role'=>['name'=>'HttpRole','description'=>'HTTP role']]],
                ['/user-management/permission/create',['Permission'=>['name'=>'httpPermission','description'=>'HTTP permission','group_code'=>'httpGroup']]],
                ['/user-management/role/set-child-permissions?id=HttpRole',['child_permissions'=>['httpPermission']]],
                ['/user-management/permission/set-child-routes?id=httpPermission',['child_routes'=>['/user-management/user/view']]],
                ['/user-management/user/create',['User'=>['username'=>'http-user','password'=>'A test phrase 1234','repeat_password'=>'A test phrase 1234','email'=>'sample@example.test','status'=>1]]],
            ] as [$path,$data]) { [$code]=$post($path,$data); self::assertSame(302,$code,$path); }
            $createdId=$db->createCommand('SELECT id FROM {{%um_user}} WHERE username=:username',[':username'=>'http-user'])->queryScalar(); self::assertNotFalse($createdId);
            foreach([
                ['/user-management/user-permission/set-roles?id='.$createdId,['roles'=>['HttpRole']]],
                ['/user-management/user/update?id='.$createdId,['User'=>['username'=>'http-user-updated','status'=>1]]],
                ['/user-management/user/change-password?id='.$createdId,['User'=>['password'=>'A changed phrase 1234','repeat_password'=>'A changed phrase 1234']]],
                ['/user-management/user/bulk-deactivate',['selection'=>[$createdId]]],
                ['/user-management/user/bulk-activate',['selection'=>[$createdId]]],
            ] as [$path,$data]) { [$code]=$post($path,$data); self::assertSame(302,$code,$path); }
            self::assertSame('http-user-updated',$db->createCommand('SELECT username FROM {{%um_user}} WHERE id=:id',[':id'=>$createdId])->queryScalar());
            self::assertTrue($security->validatePassword('A changed phrase 1234',$db->createCommand('SELECT password_hash FROM {{%um_user}} WHERE id=:id',[':id'=>$createdId])->queryScalar()));
            self::assertSame('HttpRole',$db->createCommand('SELECT item_name FROM {{%um_auth_assignment}} WHERE user_id=:id',[':id'=>$createdId])->queryScalar());
            [$code]=$post('/user-management/user/delete?id='.$userId,[]); self::assertSame(403,$code,'Cannot delete own admin account');
            [$code]=$post('/user-management/user/bulk-delete',['selection'=>[$createdId]]); self::assertSame(302,$code);
            self::assertFalse($db->createCommand('SELECT id FROM {{%um_user}} WHERE id=:id',[':id'=>$createdId])->queryScalar());
            [$code]=$request('/user-management/auth/logout',['_csrf'=>$csrf]); self::assertSame(302,$code);
            foreach(['/user-management/auth/registration','/user-management/auth/password-recovery','/user-management/auth/captcha'] as $path) { [$code]=$request($path); self::assertSame(200,$code,$path); }
            [$code]=$request('/user-management/user/index'); self::assertSame(302,$code,'Guest should be redirected to login');
        } finally { proc_terminate($process); proc_close($process); $db->close(); }
    }
}

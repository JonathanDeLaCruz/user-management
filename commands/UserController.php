<?php
namespace webvimark\modules\UserManagement\commands;
use Yii;
use webvimark\modules\UserManagement\models\User;
class UserController extends \yii\console\Controller
{
    public function actionCreateAdmin($username = 'admin')
    {
        if (User::find()->where(['username'=>$username])->exists()) { $this->stderr("El usuario ya existe.\n"); return 1; }
        $password = getenv('UM_ADMIN_PASSWORD') ?: Yii::$app->security->generateRandomString(24);
        $user = new User(['scenario'=>'newUser', 'username'=>$username, 'password'=>$password, 'repeat_password'=>$password, 'superadmin'=>1, 'status'=>User::STATUS_ACTIVE]);
        if (!$user->save()) { $this->stderr(json_encode($user->errors)."\n"); return 1; }
        $this->stdout("Usuario: $username\nContraseña inicial: $password\nGuárdala de forma segura; no se almacena en el repositorio.\n");
        return 0;
    }
}

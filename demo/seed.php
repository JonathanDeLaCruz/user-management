<?php
require __DIR__ . '/vendor/autoload.php'; require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app',__DIR__);
new yii\console\Application(require __DIR__ . '/config/console.php');
if (!str_starts_with(Yii::$app->db->dsn, 'sqlite:')) throw new RuntimeException('Demo samples are restricted to SQLite.');
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Role;
foreach(['Editor','Lector'] as $name) if (!Role::findOne(['name'=>$name])) Role::create($name);
foreach([['ana.editor','ana@example.test','Editor',1],['carlos.lector','carlos@example.test','Lector',1],['lucia.pendiente','lucia@example.test','Lector',0]] as [$name,$email,$role,$status]) {
    if (User::find()->where(['username'=>$name])->exists()) continue;
    $password=Yii::$app->security->generateRandomString(24);
    $user=new User(['scenario'=>'newUser','username'=>$name,'email'=>$email,'email_confirmed'=>$status,'status'=>$status,'password'=>$password,'repeat_password'=>$password]);
    if (!$user->save()) throw new RuntimeException(json_encode($user->errors));
    User::assignRole($user->id,$role);
}
echo "Datos de ejemplo preparados. Las contraseñas de las cuentas de ejemplo son aleatorias.\n";

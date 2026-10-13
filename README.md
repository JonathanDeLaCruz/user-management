# Gestión de usuarios para Yii 2

Usuarios, roles y permisos RBAC, registro, inicio de sesión, recuperación de contraseña e historial de accesos. PHP 8.2–8.5, Yii 2.0.55+ y Bootstrap 5.

## Instalación

Instala la versión estable desde [Packagist](https://packagist.org/packages/yii2-access/user-management):

```bash
composer require "yii2-access/user-management:^2.0.6"
```

Si utilizas `webvimark/module-user-management`, elimina esa dependencia con `--no-update` antes de instalar este paquete.


El módulo incluye el diseño completo: menú lateral minimizable, formularios de acceso y un panel con gráfica de actividad. Se muestra automáticamente en sus rutas. El inicio está en `/user-management/dashboard/index`. Se conserva el namespace `webvimark\modules\UserManagement`.

## Configuración

### 1. Configuración web

Añade estas entradas a `config/web.php`, conservando la conexión `db` y los demás componentes de tu aplicación:

```php
'modules' => [
    'user-management' => [
        'class' => webvimark\modules\UserManagement\UserManagementModule::class,
        // 'enableRegistration' => true,
        // 'useEmailAsLogin' => true,
        // 'emailConfirmationRequired' => true,
        // 'rolesAfterRegistration' => ['Cliente'],
    ],
],
'components' => [
    'user' => [
        'class' => webvimark\modules\UserManagement\components\UserConfig::class,
        'on afterLogin' => function ($event) {
            webvimark\modules\UserManagement\models\UserVisitLog::newVisitor($event->identity->id);
        },
    ],
    'authManager' => ['class' => yii\rbac\DbManager::class],
    'cache' => ['class' => yii\caching\FileCache::class],
    'mailer' => [
        'class' => yii\symfonymailer\Mailer::class,
        'viewPath' => '@webvimark/modules/UserManagement/views',
        'useFileTransport' => true,
    ],
],
```

`authManager` gestiona roles y permisos en la base de datos. `cache` almacena las rutas comunes; conserva tu componente si ya lo tienes. `mailer` se utiliza para recuperar contraseñas y confirmar correos: conserva tu implementación si ya está configurada y adapta `viewPath` o las vistas de `mailerOptions`.

`yii\mail\MailerInterface` es una interfaz, no una implementación que puedas instanciar directamente. El ejemplo utiliza Symfony Mailer. Para enviar correos, configura su transporte SMTP y establece `useFileTransport => false`; con `true` solo se guardan archivos en `@runtime/mail`.

En `config/params.php`, añade o modifica el remitente:

```php
return [
    // Conserva tus demás parámetros.
    'adminEmail' => 'administracion@example.com',
];
```

Tanto `config/web.php` como `config/console.php` deben cargar ese archivo (el proyecto básico de Yii ya lo hace):

```php
$params = require __DIR__ . '/params.php';
// En el array de configuración:
'params' => $params,
```

El registro y la recuperación requieren GD con FreeType o Imagick con soporte PNG para el CAPTCHA.

### 2. Configuración de consola

Añade a `config/console.php`, utilizando la misma base de datos que en la configuración web:

```php
'modules' => [
    'user-management' => [
        'class' => webvimark\modules\UserManagement\UserManagementModule::class,
        'controllerNamespace' => 'webvimark\modules\UserManagement\commands',
    ],
],
'controllerMap' => [
    'um-user' => webvimark\modules\UserManagement\commands\UserController::class,
],
'components' => [
    'authManager' => ['class' => yii\rbac\DbManager::class],
    'cache' => ['class' => yii\caching\FileCache::class],
],
```

### 3. Migraciones

```bash
php yii migrate --migrationPath=@webvimark/modules/UserManagement/migrations
```

### 4. Crear el superadministrador

```bash
php yii um-user/create-admin superadmin
```

El comando genera y muestra una contraseña aleatoria de 24 caracteres; la base de datos guarda su hash. Guarda esa contraseña para iniciar sesión. Si el usuario ya existe, el comando no lo modifica.

Para elegir la contraseña, define `UM_ADMIN_PASSWORD` antes de ejecutar el comando. Ejemplo en PowerShell:

```powershell
$env:UM_ADMIN_PASSWORD = 'Tu contraseña de al menos 12 caracteres'
php yii um-user/create-admin superadmin
Remove-Item Env:UM_ADMIN_PASSWORD
```

### 5. Proteger tus controladores

Añade `GhostAccessControl` a `behaviors()` del controlador base de tu aplicación, del que hereden los controladores que quieres proteger:

```php
public function behaviors()
{
    $behaviors = parent::behaviors();
    $behaviors['ghost-access'] = [
        'class' => webvimark\modules\UserManagement\components\GhostAccessControl::class,
    ];
    return $behaviors;
}
```

Este ejemplo conserva los filtros de `parent::behaviors()`. Si el controlador ya declara filtros como `AccessControl` o `VerbFilter`, conserva también esas entradas y añade `ghost-access` al mismo array. No declares un segundo método `behaviors()` ni sustituyas el método completo.

Los controladores del módulo ya incluyen este filtro en `components/BaseController.php`; solo debes añadirlo a los de tu aplicación.

## Primeros pasos

1. Inicia sesión en `/user-management/auth/login` con la cuenta creada.
2. Abre **Permisos**, actualiza las rutas y vincúlalas a permisos.
3. Añade los permisos a un rol en **Roles**.
4. Asigna los roles a las cuentas en **Usuarios**.

## Menú

Añade al layout:

```php
use webvimark\modules\UserManagement\components\GhostNav;
use webvimark\modules\UserManagement\UserManagementModule;

echo GhostNav::widget([
    'encodeLabels' => false,
    'options' => ['class' => 'nav flex-column'],
    'items' => UserManagementModule::menuItems(),
]);
```

| Función | Ruta |
|---|---|
| Usuarios | `/user-management/user/index` |
| Roles | `/user-management/role/index` |
| Permisos | `/user-management/permission/index` |
| Grupos | `/user-management/auth-item-group/index` |
| Historial | `/user-management/user-visit-log/index` |
| Registro | `/user-management/auth/registration` |
| Recuperar contraseña | `/user-management/auth/password-recovery` |
| Cambiar contraseña propia | `/user-management/auth/change-own-password` |
| Confirmar email | `/user-management/auth/confirm-email` |

Cierre de sesión:

```php
echo yii\helpers\Html::a(
    webvimark\modules\UserManagement\UserManagementModule::t('back', 'Logout'),
    ['/user-management/auth/logout'], ['data-method' => 'post']
);
```

## Opciones

Configúralas dentro de `modules['user-management']`:

| Opción | Predeterminado |
|---|---|
| `enableRegistration` | `false` |
| `useEmailAsLogin` | `false` |
| `emailConfirmationRequired` | `false` |
| `rolesAfterRegistration` | `[]` |
| `userCanHaveMultipleRoles` | `true` |
| `confirmationTokenExpire` | `3600` segundos |
| `maxAttempts` | `5` |
| `attemptsTimeout` | `60` segundos |
| `passwordRegexp` | `/^.{12,}$/us` |

Crea los roles de `rolesAfterRegistration` antes de habilitar el registro. La confirmación del registro exige `useEmailAsLogin` y `emailConfirmationRequired` activados.

Controlador público:

```php
public $freeAccess = true;
```

Acciones públicas específicas:

```php
public $freeAccessActions = ['index', 'contact'];
```

## Helpers

```php
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\rbacDB\Permission;

User::hasRole('Admin');
User::hasPermission('viewUsers');
User::canRoute('/user-management/user/index');
User::getCurrentUser();
User::assignRole($userId, 'Admin');
User::revokeRole($userId, 'Admin');

Role::create('Editor', 'Editor');
Permission::create('editArticles', 'Editar artículos');
Role::addChildren('Editor', ['editArticles'], true);
Role::removeChildren('Editor', ['editArticles']);
```

`GhostHtml::a()`, `GhostMenu` y `GhostNav` muestran únicamente los enlaces autorizados.

## Eventos

```php
'user-management' => [
    'class' => webvimark\modules\UserManagement\UserManagementModule::class,
    'on afterRegistration' => function ($event) {
        // Tu lógica con $event->user y $event->model.
    },
],
```

Los eventos están definidos en `components/UserAuthEvent.php`. Para personalizar el registro, extiende `models/forms/RegistrationForm.php` y configura `registrationFormClass`.

## Actualizar desde el original

Haz una copia de seguridad y aplica las migraciones pendientes. Resuelve nombres de usuario duplicados antes de migrar. Los enlaces de confirmación anteriores se invalidan; las cuentas existentes se conservan. Cambia la contraseña de cualquier cuenta antigua que siga usando `superadmin/superadmin`.

Basado en [webvimark/user-management](https://github.com/webvimark/user-management). Se conserva la autoría y el historial; el original no incluye una licencia formal.

# Módulo de gestión de usuarios para Yii 2

Gestión de usuarios, registro, inicio de sesión, recuperación de contraseña, roles y permisos RBAC con interfaz Bootstrap 5. Basado en [webvimark/user-management](https://github.com/webvimark/user-management), conservando su namespace para facilitar la integración.

## Requisitos

- Una aplicación Yii 2.0.55 o superior de la rama 2.0.
- PHP 8.2, 8.3, 8.4 o 8.5.
- Bootstrap 5. Se instala mediante `yiisoft/yii2-bootstrap5`.
- Base de datos configurada en el componente `db` de Yii.
- Extensiones PHP requeridas por Yii; GD o Imagick para el CAPTCHA.
- Correo configurado para recuperación de contraseña y confirmación de email.

El repositorio contiene el módulo para instalarlo en tu aplicación. Tu proyecto proporciona el layout, la conexión a base de datos, el servidor PHP y la configuración del correo.

## Instalación

Añade estos repositorios al `composer.json` de tu aplicación:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/JonathanDeLaCruz/user-management.git"
    },
    {
        "type": "composer",
        "url": "https://asset-packagist.org"
    }
]
```

Instala el módulo desde la carpeta de tu aplicación:

```bash
composer require jonathandelacruz/yii2-user-management:dev-master
```

Si ya utilizas `webvimark/module-user-management`, sustituye esa dependencia por este paquete: ambos utilizan el namespace `webvimark\modules\UserManagement` y no deben instalarse juntos.

## Configuración web

Integra estas entradas en tu configuración web (`config/web.php` en la plantilla básica o el archivo equivalente en tu proyecto). Conserva el resto de componentes de tu aplicación, incluyendo `db`, `request` y su `cookieValidationKey`.

```php
use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\components\UserConfig;
use webvimark\modules\UserManagement\models\UserVisitLog;

return [
    // ...configuración existente...
    'modules' => [
        'user-management' => [
            'class' => UserManagementModule::class,
            'enableRegistration' => false,
        ],
    ],
    'components' => [
        // ...db, request y demás componentes existentes...
        'user' => [
            'class' => UserConfig::class,
            'identityCookie' => [
                'name' => '_identity',
                'httpOnly' => true,
                'sameSite' => 'Lax',
            ],
            // Quita este evento si no quieres registrar los inicios de sesión.
            'on afterLogin' => function ($event) {
                UserVisitLog::newVisitor($event->identity->id);
            },
        ],
        'authManager' => [
            'class' => yii\rbac\DbManager::class,
        ],
        'cache' => [
            'class' => yii\caching\FileCache::class,
        ],
        'mailer' => [
            'class' => yii\symfonymailer\Mailer::class,
            'viewPath' => '@webvimark/modules/UserManagement/views',
            'useFileTransport' => true,
        ],
    ],
    'params' => [
        // ...parámetros existentes...
        'adminEmail' => 'administracion@example.com',
    ],
];
```

Mantén el identificador `user-management` y utiliza `UserConfig` y `DbManager`. Con `useFileTransport => true`, los correos se guardan en `@runtime/mail`; no se envían. Para enviarlos, configura tu transporte SMTP y establece `useFileTransport => false`.

## Configuración de consola

Añade estas entradas a `config/console.php` o a la configuración de consola equivalente. Debe utilizar la misma base de datos que la aplicación web.

```php
use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\commands\UserController;

return [
    // ...configuración existente...
    'modules' => [
        'user-management' => [
            'class' => UserManagementModule::class,
            'controllerNamespace' => 'webvimark\modules\UserManagement\commands',
        ],
    ],
    'controllerMap' => [
        'um-user' => UserController::class,
    ],
    'components' => [
        // ...db y demás componentes existentes...
        'authManager' => [
            'class' => yii\rbac\DbManager::class,
        ],
        'cache' => [
            'class' => yii\caching\FileCache::class,
        ],
    ],
    'params' => [
        // ...parámetros existentes...
        'adminEmail' => 'administracion@example.com',
    ],
];
```

## Migraciones

Desde la raíz de tu aplicación:

```bash
php yii migrate --migrationPath=@webvimark/modules/UserManagement/migrations
```

Esto crea las tablas de usuarios, RBAC, historial y control de intentos. También crea el rol `Admin` y permisos básicos. Las migraciones no crean automáticamente una cuenta con contraseña compartida.

Si actualizas una instalación del módulo original, haz una copia de seguridad de la base de datos y aplica las migraciones pendientes. La nueva migración exige nombres de usuario únicos e invalida los enlaces de confirmación anteriores. Resuelve nombres duplicados antes de ejecutarla. Las cuentas existentes se conservan, incluida una cuenta `superadmin` creada por una instalación antigua; cambia su contraseña si todavía es la predeterminada.

## Crear el superadministrador y su contraseña

Después de las migraciones:

```bash
php yii um-user/create-admin superadmin
```

El argumento `superadmin` es el nombre del usuario; puedes utilizar otro. El comando crea una cuenta activa con `superadmin = 1` y muestra su contraseña inicial. Si el nombre ya existe, rechaza la creación y conserva la cuenta existente.

La contraseña se genera con `Yii::$app->security->generateRandomString(24)`. En la base de datos se guarda el hash generado por `yii\base\Security::generatePasswordHash()`, no la contraseña en texto. Guarda la contraseña mostrada para el primer acceso. No existe el acceso automático `superadmin/superadmin` del módulo original.

Para utilizar una contraseña elegida por ti, define `UM_ADMIN_PASSWORD` en el entorno del proceso antes de ejecutar el comando. Por ejemplo, en PowerShell:

```powershell
$env:UM_ADMIN_PASSWORD = 'Tu contraseña de al menos 12 caracteres'
php yii um-user/create-admin superadmin
Remove-Item Env:UM_ADMIN_PASSWORD
```

Sustituye el texto del ejemplo por tu contraseña. El comando también muestra una contraseña proporcionada mediante esta variable; utiliza una consola privada. Para cambiarla después, inicia sesión y utiliza `/user-management/auth/change-own-password`.

## Acceso y menú

Inicia sesión en:

```text
/user-management/auth/login
```

Puedes añadir el menú al layout de tu aplicación:

```php
use webvimark\modules\UserManagement\components\GhostNav;
use webvimark\modules\UserManagement\UserManagementModule;

echo GhostNav::widget([
    'encodeLabels' => false,
    'options' => ['class' => 'nav flex-column'],
    'items' => UserManagementModule::menuItems(),
]);
```

El menú muestra los enlaces autorizados para el usuario actual. Las páginas principales son:

| Página | Ruta |
|---|---|
| Usuarios | `/user-management/user/index` |
| Roles | `/user-management/role/index` |
| Permisos | `/user-management/permission/index` |
| Grupos de permisos | `/user-management/auth-item-group/index` |
| Historial de accesos | `/user-management/user-visit-log/index` |
| Registro | `/user-management/auth/registration` |
| Recuperar contraseña | `/user-management/auth/password-recovery` |
| Confirmar email | `/user-management/auth/confirm-email` |
| Cambiar la propia contraseña | `/user-management/auth/change-own-password` |

Para cerrar sesión, utiliza POST:

```php
echo yii\helpers\Html::a('Salir', ['/user-management/auth/logout'], [
    'data-method' => 'post',
]);
```

## Proteger tus controladores

Añade `GhostAccessControl` a los comportamientos de tu controlador base, conservando los demás filtros existentes:

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

Desde la página de permisos, actualiza la lista de rutas, vincula las rutas a permisos, añade esos permisos a un rol y asigna el rol al usuario. El superadministrador tiene acceso completo mientras su cuenta esté activa.

Para declarar un controlador público:

```php
public $freeAccess = true;
```

Para declarar solamente algunas acciones públicas:

```php
public $freeAccessActions = ['index', 'contact'];
```

Las rutas incluidas en `commonPermission` también son públicas. Mantén habilitada la validación CSRF de Yii. Las acciones que escriben datos del módulo requieren POST.

## Registro, email y opciones del módulo

Las opciones se configuran dentro de `modules['user-management']`:

```php
'user-management' => [
    'class' => webvimark\modules\UserManagement\UserManagementModule::class,
    'enableRegistration' => true,
    'useEmailAsLogin' => true,
    'emailConfirmationRequired' => true,
    'rolesAfterRegistration' => ['Cliente'],
    'userCanHaveMultipleRoles' => true,
    'confirmationTokenExpire' => 3600,
    'maxAttempts' => 5,
    'attemptsTimeout' => 60,
    'mailerOptions' => [
        'from' => ['administracion@example.com' => 'Mi aplicación'],
    ],
],
```

Crea primero el rol `Cliente` si lo utilizas en `rolesAfterRegistration`. La confirmación de registro por email requiere `useEmailAsLogin` y `emailConfirmationRequired` activados y un transporte de correo operativo.

| Opción | Uso | Valor predeterminado |
|---|---|---|
| `enableRegistration` | Habilita el registro público | `false` |
| `useEmailAsLogin` | Utiliza email como nombre de usuario en el registro | `false` |
| `emailConfirmationRequired` | Exige confirmar email para activar el registro | `false` |
| `rolesAfterRegistration` | Roles asignados al completar el registro | `[]` |
| `userCanHaveMultipleRoles` | Permite elegir varios roles en la interfaz | `true` |
| `confirmationTokenExpire` | Caducidad de los enlaces, en segundos | `3600` |
| `maxAttempts` | Máximo de intentos por ventana, por IP y cuenta | `5` |
| `attemptsTimeout` | Duración de la ventana, en segundos | `60` |
| `passwordRegexp` | Validación adicional de contraseñas | `/^.{12,}$/us` |
| `registrationFormClass` | Clase personalizada para registrar usuarios | `RegistrationForm` del módulo |

Las contraseñas nuevas requieren al menos 12 caracteres y no se recortan con `trim()`. Al cambiarlas se rota la clave de autenticación. Los enlaces de recuperación y confirmación están separados por propósito, caducan y se almacenan como hash. El contador de intentos se guarda en base de datos y no se reinicia al abrir otra sesión.

Para configurar “recordarme”:

```php
'user' => [
    'class' => webvimark\modules\UserManagement\components\UserConfig::class,
    'enableAutoLogin' => true,
    'cookieLifetime' => 2592000, // 30 días.
],
```

## Bootstrap: elegir y actualizar la versión

El módulo usa widgets `yii\bootstrap5`, formularios Bootstrap 5 y sus assets. La dependencia `yiisoft/yii2-bootstrap5` admite `twbs/bootstrap` desde `5.2.3` dentro de la rama 5.x. Bootstrap 5.3 es la rama utilizada para modernizar la interfaz.

Para seleccionar Bootstrap 5.3 en tu aplicación:

```bash
composer require "twbs/bootstrap:^5.3" --with-all-dependencies
```

Para fijar una versión concreta compatible, por ejemplo la utilizada en esta actualización:

```bash
composer require twbs/bootstrap:5.3.8 --with-all-dependencies
```

Para actualizar las dependencias de Bootstrap respetando las restricciones de tu aplicación:

```bash
composer update yiisoft/yii2-bootstrap5 twbs/bootstrap --with-all-dependencies
```

Elige la versión mediante Composer en tu aplicación; no edites `vendor`. El módulo registra Bootstrap 5 automáticamente mediante `assets/ModuleAsset.php`. Si tu plantilla lo carga mediante CDN o archivos propios, configura los bundles de Yii para utilizar esos archivos y evita cargar dos copias de Bootstrap.

Bootstrap 3 y 4 no son intercambiables con Bootstrap 5. Este módulo no incluye un selector para ellas: usarlas exige otra adaptación de vistas, widgets y dependencias. Una plantilla que aún use Bootstrap 3 debe migrarse a Bootstrap 5 antes de integrar esta versión.

El módulo utiliza el layout de tu aplicación. Puedes cambiar el layout de las pantallas de autenticación sin editar el paquete:

```php
'user-management' => [
    'class' => webvimark\modules\UserManagement\UserManagementModule::class,
    'on beforeAction' => function ($event) {
        if ($event->action->controller->id === 'auth') {
            $event->action->controller->layout = '@app/views/layouts/login';
        }
    },
],
```

El archivo `views/layouts/login.php` de tu aplicación debe existir. Los estilos propios del módulo se publican desde `assets/dist/module.css`; tu plantilla puede personalizarlos con su propia hoja de estilos.

## PHP: cambiar la versión

PHP se selecciona en el servidor o entorno de tu aplicación, no desde una opción del módulo. Esta versión admite PHP 8.2–8.5 y Composer comprueba ese requisito durante la instalación.

Para ejecutar comandos con otro PHP instalado en Windows, utiliza la ruta de su ejecutable:

```powershell
& 'C:\php84\php.exe' yii migrate --migrationPath=@webvimark/modules/UserManagement/migrations
& 'C:\php85\php.exe' yii um-user/create-admin superadmin
```

Las rutas son ejemplos: sustitúyelas por las instalaciones reales. Cambiar el ejecutable de consola no cambia el PHP de IIS, Apache, PHP-FPM o tu contenedor; selecciona allí la misma versión para las peticiones web.

Comprueba la versión y los requisitos de las dependencias con:

```bash
php -v
composer check-platform-reqs
```

Ejecuta Composer con la versión PHP que vayas a utilizar. Si una dependencia de tu aplicación requiere otra versión, actualízala o elige una compatible. `config.platform.php` de Composer sirve para resolver dependencias contra una versión objetivo; no cambia el PHP real.

## Helpers y eventos

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

`GhostHtml::a()`, `GhostMenu` y `GhostNav` permiten ocultar enlaces no autorizados. Esto complementa el filtro del controlador; no lo sustituye.

Para ejecutar lógica después del registro:

```php
'user-management' => [
    'class' => webvimark\modules\UserManagement\UserManagementModule::class,
    'on afterRegistration' => function ($event) {
        // Tu lógica con $event->user y $event->model.
    },
],
```

Los nombres de eventos están en `components/UserAuthEvent.php`. Para añadir perfiles propios, usa un modelo o tabla relacionados con `user_id` y una clase que extienda `models/forms/RegistrationForm.php` mediante `registrationFormClass`.

## Actualizar desde el módulo original

Se conserva el namespace y las rutas principales. Esta versión sustituye los widgets y controladores auxiliares antiguos por clases dentro del propio módulo, usa Bootstrap 5 y aplica las migraciones de seguridad descritas arriba.

Si tu código hereda directamente de `webvimark/components` o de las extensiones antiguas, adapta esas referencias. `getPermissionsLastModFile()` ya no se utiliza: los permisos se invalidan mediante la base de datos. `findIdentityByAccessToken()` rechaza las claves de “recordarme” como tokens API; si tu aplicación tiene una API, utiliza identidades y tokens específicos para ella.

Los campos IP nuevos admiten IPv6 y las tablas MySQL nuevas usan `utf8mb4`. Las tablas antiguas no se convierten automáticamente a `utf8mb4`. El historial conserva el user-agent; el detector antiguo de navegador y sistema operativo se retiró, por lo que esos campos muestran `Unknown`. Las acciones masivas y el tamaño de página usan controles Bootstrap 5 sin las extensiones antiguas.

## Autoría

Basado en el trabajo de webvimark y los colaboradores del repositorio original. Se conserva su historial. El original invita a realizar forks, pero no incluye una licencia formal; este fork no asigna una licencia nueva al código de terceros.

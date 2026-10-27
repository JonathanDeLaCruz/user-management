# Gestión de usuarios para Yii 2

Usuarios, roles y permisos RBAC, registro, inicio de sesión, recuperación de contraseña, passkeys, MFA, sesiones y auditoría. PHP 8.2–8.5 actualizado, Yii 2.0.55+ y Bootstrap 5.

## Instalación

Instala la versión estable desde [Packagist](https://packagist.org/packages/yii2-access/user-management):

```bash
composer require "yii2-access/user-management:^3.0.0"
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
        'webauthnOrigin' => getenv('UM_WEBAUTHN_ORIGIN'),
        'mfaEncryptionKey' => getenv('UM_MFA_ENCRYPTION_KEY'),
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

Las migraciones cargan el módulo con su configuración predeterminada si no está declarado en consola. Para usar nombres de tablas personalizados, declara el módulo también en `config/console.php` con esos mismos nombres. La configuración web no se carga automáticamente al ejecutar `php yii`.

Las tablas del módulo usan el prefijo `um_`: `um_user`, `um_user_visit_log`, `um_auth_item`, `um_auth_item_child`, `um_auth_item_group`, `um_auth_assignment`, `um_auth_rule`, `um_state` y `um_attempt`. Si tu conexión define `tablePrefix`, se conserva delante (por ejemplo, `app_um_user`). La tabla de historial de migraciones de Yii no cambia.

Para actualizar una instalación existente, respalda la base de datos, actualiza el paquete y ejecuta el mismo comando de migraciones. Se renombran las tablas antiguas conservando los datos; las que ya tienen el nombre nuevo se reutilizan y solo se añaden columnas o índices pendientes. No borres el historial de migraciones. Si coexisten los nombres antiguo y nuevo para una tabla, la migración se detiene para que resuelvas la duplicidad sin sobrescribir datos.

El módulo configura `DbManager` para utilizar sus tablas RBAC. Si utilizas `authManager` antes de cargar el módulo (por ejemplo, desde un proceso de consola independiente), declara también estos nombres en su configuración:

```php
'authManager' => [
    'class' => yii\rbac\DbManager::class,
    'itemTable' => '{{%um_auth_item}}',
    'itemChildTable' => '{{%um_auth_item_child}}',
    'assignmentTable' => '{{%um_auth_assignment}}',
    'ruleTable' => '{{%um_auth_rule}}',
],
```

### 4. Crear el superadministrador

Antes del primer acceso, configura `UM_WEBAUTHN_ORIGIN` con el origen HTTPS exacto de tu aplicación, por ejemplo `https://acceso.example.com` (sin rutas). Para desarrollo se admite `http://localhost:8081`. Incluye el puerto si no es el predeterminado. No se obtiene el dominio del encabezado `Host` y no se aceptan subdominios diferentes.

Genera una clave para cifrar los secretos de las apps autenticadoras:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Guárdala como `UM_MFA_ENCRYPTION_KEY` en las variables de entorno del servidor web y la consola. Conserva la misma clave entre despliegues y respáldala fuera del repositorio. No la generes en cada petición ni la guardes en `vendor`. Las passkeys almacenan claves públicas; no requieren esta clave de cifrado.

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

## Seguridad de la cuenta

La política predeterminada exige MFA a superadministradores y cuentas con el rol `Admin`, incluyendo roles que lo heredan. Después de verificar la contraseña, esas cuentas deben registrar o usar una passkey. La sesión completa no se crea antes de esa verificación. El dominio, el desafío de un solo uso, la firma, la pertenencia de la credencial y el PIN o biometría del dispositivo se validan en el servidor.

En **Seguridad de la cuenta** (`/user-management/security/index`) puedes registrar una passkey de respaldo, eliminar factores, revisar sesiones activas y cerrarlas. Los cambios sensibles requieren una verificación reciente; la política obligatoria impide eliminar la última passkey. Las sesiones caducan por inactividad o por duración máxima y se revocan al cambiar la contraseña. Las cookies persistentes de «Recordarme» no restauran sesiones.

Opciones dentro de `modules['user-management']` (usa la misma política en web y consola):

```php
'mfaPolicy' => 'admins', // 'optional', 'admins' o 'all'
'mfaRequiredRoles' => ['Admin'], // Tus roles administrativos; superadmin siempre se incluye con 'admins'.
'requirePhishingResistant' => true,
'sessionLifetime' => 86400,
'sessionIdleTimeout' => 1800,
```

Con `requirePhishingResistant => true`, las cuentas obligadas por la política y las que registren una passkey no pueden sustituirla con códigos TOTP, SMS, correo ni recuperación. Registra una segunda passkey en otro dispositivo. El resto puede activar una app autenticadora mediante un QR generado localmente; sus secretos se guardan cifrados y los códigos aceptados no se pueden reutilizar. Los códigos TOTP son MFA, pero no son resistentes al phishing.

Las cuentas que no requieren passkey pueden generar diez códigos de recuperación de un solo uso. Se muestran una vez y se guardan sus hashes. La recuperación exige primero la contraseña y solo permite registrar un factor nuevo; no concede acceso al panel directamente. Al completarla se revocan las sesiones anteriores. Recuperar la contraseña por correo no elimina MFA.

Si una cuenta con política estricta pierde todas sus passkeys, un operador con acceso a la consola debe verificar la identidad de su propietario por un canal independiente antes de ejecutar:

```bash
php yii user-management/security/reset-mfa nombreUsuario --confirmRecovery=1
```

El comando revoca factores, sesiones y enlaces pendientes, registra la intervención y exige volver a inscribir una passkey al siguiente acceso si la política lo requiere. No se ofrece una ruta web para saltarse la política estricta.

Para comprobar la configuración del despliegue:

```bash
php yii user-management/security/check
```

Los cambios de contraseña y factores generan avisos al correo confirmado del usuario cuando el remitente y SMTP están configurados. Con `useFileTransport => true` esos avisos se guardan en archivos.

Los superadministradores pueden consultar **Auditoría de seguridad** en `/user-management/security/audit`. Registra cambios de cuentas, roles y permisos, verificaciones y revocaciones. No guarda contraseñas, secretos TOTP, códigos de recuperación, claves privadas ni cuerpos de peticiones. Para aplicar una retención de 90 días:

```bash
php yii user-management/security/cleanup 90
```

Las nuevas tablas son `um_mfa`, `um_passkey`, `um_recovery_code`, `um_session` y `um_audit_log`; respetan `tablePrefix`. Aplica las migraciones antes de atender peticiones con esta versión. En una actualización, configura el origen y la clave antes de que los administradores inicien sesión.

## PHP y comprobaciones

Se requieren como mínimo PHP **8.2.35, 8.3.36, 8.4.27 o 8.5.12**, que contienen las correcciones críticas de octubre de 2026. En Windows utiliza los builds **`-1` o posteriores**, que incorporan también la corrección de Brotli. Actualiza tanto PHP de consola como el del servidor web; Composer comprueba la versión del PHP que lo ejecuta. [Avisos oficiales de PHP](https://www.php.net/archive/2026.php).

Las pruebas usan credenciales ficticias para verificar firmas WebAuthn, rechazo de orígenes falsos, desafíos y códigos reutilizados, política MFA, sesiones y migraciones. GitHub Actions las ejecuta en PHP 8.2–8.5 y comprueba instalaciones y actualizaciones en MariaDB. `tests/` y `.github/` están excluidos de la distribución Composer.

Para ejecutar las pruebas desde una copia Git del repositorio:

```bash
composer config repositories.asset-packagist composer https://asset-packagist.org
composer install
vendor/bin/phpunit --configuration phpunit.xml.dist
```

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

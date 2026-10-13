# Yii2 User Management · Bootstrap 5

Fork de [webvimark/user-management](https://github.com/webvimark/user-management), mantenido en [JonathanDeLaCruz/user-management](https://github.com/JonathanDeLaCruz/user-management). Conserva el namespace original y moderniza el módulo para Yii 2, Bootstrap 5.3 y PHP 8.2, 8.3, 8.4 y 8.5.

## Demo local en Windows

Desde la raíz del repositorio:

```powershell
composer install
cd demo
composer install
cd ..
.\scripts\Install-PhpRuntimes.ps1
& .\.runtimes\8.4\php.exe demo\setup.php
& .\.runtimes\8.4\php.exe demo\yii migrate --interactive=0
& .\.runtimes\8.4\php.exe demo\yii um-user/create-admin admin
& .\.runtimes\8.4\php.exe demo\seed.php
.\scripts\Start-Demo.ps1 -PhpVersion 8.4
```

Abrir http://127.0.0.1:8080. El comando de creación muestra una contraseña aleatoria; no existe una contraseña predeterminada compartida. Los usuarios de ejemplo usan correos ficticios y contraseñas aleatorias. El correo se guarda como archivo en `demo/runtime/mail`; no se envía a destinatarios reales.

Para cambiar PHP, detener el servidor con Ctrl+C y ejecutar:

```powershell
.\scripts\Start-Demo.ps1 -PhpVersion 8.5
```

Las versiones portables se descargan desde php.net y se verifican con su SHA256 oficial. No se cambia el PHP global de Windows. En Linux/macOS se puede usar el contenedor: seleccionar `PHP_VERSION` en `.env` a partir de `.env.example`, ejecutar `docker compose build`, instalar dependencias y preparar la demo dentro del contenedor antes de `docker compose up`.

## Instalar tu paquete en otro proyecto Yii2

Mientras no haya una versión publicada en Packagist, añadir estos repositorios al `composer.json` del proyecto:

```json
"repositories": [
  {"type":"vcs","url":"https://github.com/JonathanDeLaCruz/user-management.git"},
  {"type":"composer","url":"https://asset-packagist.org"}
]
```

Instalar `composer require jonathandelacruz/yii2-user-management:dev-master`. Quitar la dependencia `webvimark/module-user-management` antes: ambos paquetes exponen el mismo namespace. El layout del proyecto debe cargar Bootstrap 5; no combinar sus assets con Bootstrap 3.

Configuración web mínima:

```php
'modules' => [
    'user-management' => [
        'class' => webvimark\modules\UserManagement\UserManagementModule::class,
    ],
],
'components' => [
    'user' => ['class' => webvimark\modules\UserManagement\components\UserConfig::class],
    'authManager' => ['class' => yii\rbac\DbManager::class],
    'mailer' => [
        'class' => yii\symfonymailer\Mailer::class,
        'viewPath' => '@webvimark/modules/UserManagement/views',
        // Configurar transporte SMTP en tu aplicación.
    ],
],
```

Configurar el módulo también en consola con `controllerNamespace` igual a `webvimark\modules\UserManagement\commands` y mapear `um-user` a `webvimark\modules\UserManagement\commands\UserController`. Consultar los archivos completos de `demo/config` para el resto de la configuración. Ejecutar migraciones con `--migrationPath=@webvimark/modules/UserManagement/migrations`.

## Cambios y actualización desde el original

- Interfaz, formularios, navegación, paginación, badges y acciones compatibles con Bootstrap 5. Se retiraron las dependencias BootstrapSwitch, DateRangePicker, GridBulkActions, GridPageSize y webvimark/components antiguas.
- Rutas con comodines comparadas por prefijo completo y reglas RBAC evaluadas. Invalidación de permisos compartida por base de datos; sin archivos con permisos 0777.
- Limitación de intentos por IP y cuenta en base de datos. Usuarios inactivos/bloqueados no restauran identidades; superadministradores también están sujetos a esa comprobación.
- Tokens de correo y recuperación vinculados a su propósito y almacenados como hash. Recuperación de contraseña consumida mediante actualización condicional para impedir reutilización concurrente.
- Claves de autenticación rotadas al cambiar contraseñas. Las claves remember-me no se admiten como tokens API.
- Acciones de escritura protegidas con POST y CSRF, salida HTML escapada y eliminación del enlace de continuación controlado mediante parámetros externos.
- Contraseñas con mínimo 12 caracteres, sin `trim`. Base nueva con utf8mb4 e IPs IPv6. Registro con confirmación de correo transaccional.

Para actualizar una instalación existente, hacer copia de seguridad y aplicar todas las migraciones pendientes. La migración de endurecimiento invalida los enlaces de confirmación existentes y crea un índice único para usuarios; resolver nombres duplicados antes de ejecutarla. No convierte automáticamente todas las tablas MySQL antiguas a utf8mb4. El SQL de ejemplo de 2015 fue retirado: las migraciones son la fuente de instalación.

`getPermissionsLastModFile()` y `findIdentityByAccessToken()` ahora rechazan su uso antiguo. Las extensiones que hereden de controladores/widgets del proveedor anterior deben adaptarse. El módulo requiere `UserConfig`, `DbManager` y el identificador `user-management`.

## Verificación

```powershell
& .\.runtimes\8.4\php.exe vendor\bin\phpunit
& .\.runtimes\8.4\php.exe vendor\bin\phpstan analyse --memory-limit=512M
composer audit
```

Las pruebas integran SQLite, autenticación, tokens, permisos, registro, contraseñas y un servidor HTTP aislado que comprueba pantallas, CRUD y protecciones POST/CSRF. GitHub Actions ejecuta la suite en PHP 8.2–8.5. Para ejecutar la prueba HTTP se debe instalar la demo (`cd demo; composer install`).

Leer [el informe de revisión](docs/REVIEW.md) para conocer el alcance y las limitaciones. Las pruebas no constituyen una garantía de ausencia de errores en toda configuración de producción.

## Procedencia y licencia

Se conserva el historial y la atribución a webvimark y sus colaboradores. El README original invita a realizar forks, pero no contiene una licencia formal; no se ha añadido una licencia que pretenda cubrir el código de terceros. El [README original](docs/UPSTREAM-README.md) queda conservado como referencia histórica, no como instrucciones de instalación de esta versión. Aclarar la licencia con los titulares antes de una redistribución que requiera permisos adicionales.
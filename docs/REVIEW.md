# Revisión técnica — 13 de octubre de 2026

Base: `webvimark/user-management`, commit `6e4e7498a95870eaf16c5f317e79ec318e0e023a`. El namespace se conserva; el nombre del paquete cambia a `jonathandelacruz/yii2-user-management`.

## Alcance

Se revisaron autenticación, formularios, identidad, sesiones, autorización por rutas, RBAC, controladores CRUD, modelos y búsquedas, migraciones, dependencias, navegación, vistas y catálogo de traducciones. Se comprobó la sintaxis de los 130 archivos PHP del módulo, demo y pruebas. PHPStan nivel 2 analiza componentes, controladores, modelos, widgets, assets y el módulo sin supresiones ni baseline.

La revisión encontró y corrigió el comparador de comodines que ignoraba el orden de segmentos, las credenciales iniciales compartidas, la limitación de intentos ligada a sesión, la mezcla de propósitos de los tokens, la restauración de usuarios bloqueados, la falta de rotación de claves y la invalidación por archivo local. También se corrigieron firmas incompatibles con Bootstrap 5, retornos ausentes, una variable no definida en eventos RBAC, falta de imports en vistas, escritura mediante GET, salida HTML sin escapar, un enlace de continuación controlado externamente y manejo incompleto de fallos al guardar registros.

## Resultados reproducibles

| PHP | Resultado |
|---|---|
| 8.2.34 | 13 pruebas, 138 aserciones, sin errores |
| 8.3.35 | 13 pruebas, 138 aserciones, sin errores |
| 8.4.26 | 13 pruebas, 138 aserciones, sin errores |
| 8.5.11 | 13 pruebas, 138 aserciones, sin errores |

- PHPStan nivel 2: sin errores.
- Lint de 130 archivos PHP: sin errores.
- Composer audit: sin avisos de vulnerabilidad conocidos para las dependencias instaladas en esta fecha.
- Composer validate: válido; advierte de la ausencia de licencia formal del código original.
- Dependencias: Yii 2.0.55, yii2-bootstrap5 2.0.51, Bootstrap 5.3.8. No se instalan las extensiones antiguas de Bootstrap 3.

Los registros de las ejecuciones locales se conservan en `reports/php-8.x.txt`. `scripts/Test-PhpMatrix.ps1` repite la matriz. La configuración de Actions prepara también la demo y ejecuta PHPUnit, PHPStan y Composer audit.

## Qué comprueban las pruebas

Comodines y límites de rutas; ausencia de administrador predeterminado; contraseñas y login; separación de propósito de tokens; hash en almacenamiento; consumo único incluso con dos modelos cargados antes del cambio; claves rotadas; identidad de superadministrador bloqueado; contadores que sobreviven al cambio de sesión; concesión y revocación de roles; actualización de permisos; rechazo de ciclos y relaciones permiso→rol; rechazo de claves remember-me como token API; rollback del registro cuando falla el correo.

La prueba HTTP inicia un servidor en un puerto aleatorio con una base SQLite aislada. Revisa 22 pantallas autenticadas, registro, recuperación y CAPTCHA; crea grupos, roles, permisos y usuarios; asigna rutas y roles; edita usuarios; cambia contraseñas; activa/desactiva y elimina en bloque; comprueba rechazo de GET, CSRF ausente, eliminación de la propia cuenta y acceso de invitados. No toca los usuarios de la demo.

## Límites y cambios incompatibles

La prueba real de base de datos se realizó con SQLite. Las migraciones MySQL y su actualización sobre bases heredadas necesitan validación en una copia de la base real antes de producción. No se probó PostgreSQL ni transporte SMTP externo, ni extensiones propias del proyecto del usuario, reglas RBAC particulares, alta concurrencia o varios nodos en producción. Los logs de navegador y la inspección visual comprueban la demo; no sustituyen una auditoría de accesibilidad completa.

El registro de navegador/SO ahora guarda `Unknown` y conserva el user-agent original, en lugar de depender de un detector antiguo fijado en 1.12.0. El selector visual de fechas antiguo se retiró; los filtros de rango del modelo se conservan. Las acciones masivas y el tamaño de página funcionan con controles nativos de Bootstrap 5. La interfaz puede usar el layout de la aplicación anfitriona: el sidebar y el dashboard incluidos pertenecen a la demo.

Se exige PHP 8.2–8.5, `UserConfig`, `DbManager` y el identificador de módulo `user-management`. Las API de tokens de acceso y de invalidación por archivo ya no mantienen su comportamiento anterior. La nueva migración invalida enlaces de correo antiguos y exige nombres de usuario únicos. No convierte automáticamente el charset de todas las tablas existentes ni elimina una cuenta `superadmin` creada por una instalación anterior.

La copia conserva la autoría original. No se declara una licencia para el código de terceros sin permiso del titular. No se puede garantizar que ninguna función falle bajo toda entrada o configuración posible; los resultados anteriores describen exactamente lo verificado.

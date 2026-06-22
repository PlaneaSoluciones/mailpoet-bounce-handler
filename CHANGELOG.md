# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/),
y este proyecto se adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [Unreleased]

## [1.5.0] - 2026-06-22

### Añadido

- Nueva columna "Acciones" en el log de bounces: botón "Reactivar" si el suscriptor está marcado como rebotado (lo pasa a activo), y botón "Marcar rebotado" si está activo (útil para forzar manualmente lo que el parser no clasificó). Los cambios se aplican sin recargar la página.
- La dirección de email en el log es ahora un enlace directo a la edición del suscriptor en MailPoet, permitiendo corregir errores tipográficos con un clic. Los registros anteriores muestran el email como texto plano.

### Eliminado

- Columnas "Estado anterior" y "Estado posterior" del log de bounces (vista, exportación CSV y base de datos): nunca se habían utilizado en la práctica. Los datos históricos se eliminan de la base de datos automáticamente al actualizar.

## [1.4.1] - 2026-06-22

### Corregido

- Los rebotes con código SMTP `5xx` sin subcode DSN estándar (p.ej. `550 #5.1.0 Address rejected`, `554 30 mailbox is disabled`) se clasificaban incorrectamente como `soft` y el suscriptor no se marcaba como rebotado en MailPoet. Ahora se clasifican como `hard`.
- Los subcodes DSN en notación de puntos dentro de `Diagnostic-Code` (p.ej. `#5.1.0`) se reconocen correctamente y determinan el tipo de rebote.
- Los buzones deshabilitados (`mailbox is disabled`, `mailbox disabled`) se clasifican como rebote `hard`.

## [1.4.0] - 2026-06-22

### Añadido

- El log de bounces muestra ahora el código de diagnóstico SMTP completo (campo `Diagnostic-Code` RFC 3464, o `Status` como fallback). Se ve truncado en la tabla con el texto completo al pasar el ratón, y completo en el CSV.
- Los bounces por política/reputación (blacklists, bloqueos Outlook, 5.7.x) se guardan con tipo `policy` en lugar de `soft`, tienen filtro propio en el log y se muestran en color diferente. Se incluye migración automática de registros históricos.

### Cambiado

- Los `policy_block` existentes en BD se migran automáticamente a `bounce_type = 'policy'` al activar la nueva versión.

## [1.3.0] - 2026-06-22

### Añadido

- El log de bounces ahora muestra el estado del suscriptor en MailPoet ("Estado anterior" / "Estado posterior") también para soft bounces parciales y bloqueos por política, no solo cuando se marca al suscriptor como bounced.
- Nueva opción de mantenimiento: límite máximo de filas en el log (por defecto 10 000). Al superarse, se eliminan automáticamente las entradas más antiguas, evitando el crecimiento ilimitado de la tabla.

### Cambiado

- La purga automática del log por antigüedad está ahora desactivada por defecto (antes: 90 días). Las instalaciones existentes conservan su valor configurado. Actívala explícitamente en Ajustes → Mantenimiento si la necesitas.

## [1.2.1] - 2026-06-22

### Corregido

- Los correos de rebote se reprocesaban en cada ejecución del cron porque `imap_expunge()` se llamaba inmediatamente tras cada `imap_delete()`, renumerando los mensajes restantes y dejando algunos sin borrar. Ahora solo se marca para borrado con `imap_delete()` durante el bucle, y el expunge único ocurre al cerrar la conexión con `imap_close(..., CL_EXPUNGE)`.

## [1.2.0] - 2026-06-16

### Corregido

- URL del autor (`Author URI` y `homepage` en `composer.json`) apuntaba a `planea.com.es` (dominio de infraestructura interna); ahora apunta a la web comercial https://planeasoluciones.com.

### Añadido

- Cabecera `Requires Plugins: mailpoet` para activar la funcionalidad nativa de WordPress 6.5+ "Plugin Dependencies": en el listado de plugins, WordPress avisa automáticamente si se intenta activar este plugin sin MailPoet activo, y bloquea la desactivación/borrado de MailPoet mientras este plugin esté activo (el mismo aviso que muestran plugins como las extensiones de WooCommerce). La comprobación manual existente se mantiene como red de seguridad para WordPress < 6.5 y desactivaciones fuera de la UI de wp-admin.

## [1.1.5] - 2026-06-16

### Corregido

- "Probar conexión" fallaba con "No se pudo conectar" aunque host, usuario y contraseña fueran correctos. Causa: con SSL activado, `imap_open()` no incluía el flag `/novalidate-cert`, y la librería c-client (no soporta SNI) puede recibir un certificado distinto al esperado en hosting compartido, rechazando la conexión aunque el certificado real sea válido. Ahora siempre se añade `/novalidate-cert`. Además, el mensaje de error ahora incluye el detalle reportado por `imap_last_error()` para facilitar el diagnóstico de futuros fallos de conexión.

## [1.1.4] - 2026-06-16

### Corregido

- Las páginas del plugin (submenú de MailPoet) aparecían en cualquier orden y daban 404 al pulsarlas en algunos sitios. Causa: nuestro hook `admin_menu` podía ejecutarse antes que el de MailPoet, registrando nuestra página como primer hijo y haciendo que WordPress la usara como "padre real" de todo el menú de MailPoet (ver `wp-admin/includes/menu.php`). Ahora se registra con prioridad 20 para garantizar que MailPoet registre primero su página "Home".

## [1.1.3] - 2026-06-16

### Cambiado

- Las páginas "Bounce Handler" y "Bounce Log" ya no viven bajo Herramientas; ahora aparecen como submenú dentro del menú de MailPoet.

## [1.1.2] - 2026-06-16

### Corregido

- Fatal error al abrir la página de Ajustes: `MBH_MailPoet_Updater::get_reply_to_email()` llamaba a un método (`getSettings()`) que no existe en la API pública `MP('v1')` de MailPoet. Ahora se lee directamente la opción `mailpoet_settings`.
- Los `try/catch` de `MBH_MailPoet_Updater` solo capturaban `\Exception`; un `\Error` (como el de método indefinido) no se atrapaba y tumbaba toda la página. Ahora capturan `\Throwable`.

## [1.1.1] - 2026-06-16

### Corregido

- Añadido enlace "Ajustes" en la lista de plugins para acceder directamente a la página de configuración.

## [1.1.0] - 2026-06-16

### Añadido

- Clasificación de bounces por política/reputación (blacklist, spam, bloqueo de servidor) como categoría independiente. El suscriptor ya no se marca como bounced en estos casos; se muestra una alerta en el admin y se incluye en el email de resumen.

### Corregido

- Corregidas violaciones de estilo PHPCS/WPCS y ajustada la configuración del linter.
- El ZIP del release ahora incluye el número de versión en el nombre del archivo, manteniendo la carpeta interna con el slug del plugin para que WordPress sustituya la instalación anterior.

[Unreleased]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.4...HEAD
[1.1.4]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.3...v1.1.4
[1.1.3]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/releases/tag/v1.1.0

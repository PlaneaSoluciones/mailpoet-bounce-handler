# MailPoet Bounce Handler

Plugin de WordPress para la gestión automática de bounces (rebotes de correo) vía IMAP/POP3 en instalaciones con [MailPoet 3](https://wordpress.org/plugins/mailpoet/) (versión gratuita). Lee periódicamente un buzón dedicado a bounces, clasifica los mensajes según el informe DSN (RFC 3464) y actualiza el estado de los suscriptores en MailPoet.

Sustituye al plugin abandonado "Bounce Handler MailPoet 3".

## Características

- Conexión IMAP o POP3 (con o sin SSL/TLS) al buzón de bounces.
- Parseo de informes DSN y clasificación en *hard bounce* (rebote definitivo) y *soft bounce* (rebote temporal).
- Umbral configurable de soft bounces antes de marcar al suscriptor como `bounced` en MailPoet.
- Detección de bloqueos por política/reputación (blacklist, spam, bloqueo del servidor) sin marcar al suscriptor, con aviso en el panel de administración.
- Procesado automático vía WP-Cron (frecuencia configurable) y botón de "Procesar ahora" manual.
- Registro histórico de bounces con visor (filtros, paginación) y exportación a CSV.
- Notificación por email con el resumen de cada procesado.
- Contraseña IMAP cifrada en base de datos, nunca en texto plano.
- Compatible con multisite: ajustes y cron independientes por sitio.
- Depende de que MailPoet 3 esté instalado y activo; se desactiva automáticamente si no lo detecta.

## Requisitos

- WordPress y PHP: ver la cabecera del plugin (`mailpoet-bounce-handler.php`) para las versiones mínimas soportadas.
- [MailPoet 3](https://wordpress.org/plugins/mailpoet/) (versión gratuita) instalado y activo.
- Extensión PHP `imap` habilitada en el servidor.

## Instalación

1. Sube la carpeta del plugin a `wp-content/plugins/`.
2. Activa "MailPoet Bounce Handler" desde el listado de plugins de WordPress (MailPoet debe estar activo).
3. Ve a **MailPoet → Bounce Handler** para configurar la conexión IMAP/POP3, las reglas de bounce, la programación y las notificaciones.

## Configuración

Desde la página de ajustes del plugin se configuran:

- Conexión IMAP/POP3 (servidor, puerto, protocolo, SSL, usuario, contraseña) con prueba de conexión integrada.
- Umbral de soft bounces antes de marcar como `bounced`.
- Frecuencia del procesado automático (cada hora, cada 6 horas o diario).
- Email de notificación del resumen de cada procesado.
- Retención del log en días.

## Desarrollo

Arquitectura del código, comandos de lint, convenciones de nombres y flujo de releases: ver [`CLAUDE.md`](CLAUDE.md).

## Historial de cambios

Ver [`CHANGELOG.md`](CHANGELOG.md).

## Licencia

GPL-2.0-or-later. Ver [texto de la licencia](https://www.gnu.org/licenses/gpl-2.0.html).

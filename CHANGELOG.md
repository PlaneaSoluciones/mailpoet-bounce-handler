# Changelog

Todos los cambios notables de este proyecto se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/),
y este proyecto se adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [Unreleased]

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

[Unreleased]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.2...HEAD
[1.1.2]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/PlaneaSoluciones/mailpoet-bounce-handler/releases/tag/v1.1.0

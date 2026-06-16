# CLAUDE.md

## Qué es esto

Plugin WordPress para gestión automática de bounces IMAP en instalaciones con MailPoet 3 (versión gratuita). Requiere MailPoet 3 activo — se desactiva si no está presente. Reemplaza el plugin abandonado "Bounce Handler MailPoet 3".

## Comandos

```bash
# Instalar dependencias de desarrollo y configurar git hook
composer install

# Ejecutar linter
composer lint

# Corregir automáticamente lo que PHPCS pueda arreglar
composer lint:fix
```

## Arquitectura

```
mailpoet-bounce-handler.php   Plugin header, bootstrap, dependency check, cron
includes/
  class-imap-reader.php       Conexión IMAP/POP3 via imap_open()
  class-bounce-parser.php     Parseo DSN RFC 3464, clasificación hard/soft
  class-mailpoet-updater.php  API interna MailPoet MP('v1'), actualiza estado a 'bounced'
  class-notifier.php          wp_mail() con resumen de procesado
  class-logger.php            CRUD tablas {prefix}_mbh_log y {prefix}_mbh_soft_counts
admin/
  class-admin.php             Submenú dentro del menú de MailPoet, AJAX handlers
  settings-page.php           Vista ajustes (IMAP, reglas, cron, notificaciones)
  log-viewer.php              WP_List_Table con filtros, paginación y export CSV
languages/
  mailpoet-bounce-handler-es_ES.po
```

**Prefijos:**
- Tablas BD: `{wpdb->prefix}mbh_log`, `{wpdb->prefix}mbh_soft_counts`
- Opciones WP: `mbh_*`
- Hooks WP: `mbh_process_bounces`
- Clases PHP: `MBH_*`
- Text domain: `mailpoet-bounce-handler`

**Dependencia MailPoet:** doble capa.
- Cabecera nativa `Requires Plugins: mailpoet` (WordPress ≥ 6.5, feature "Plugin Dependencies"): WordPress Core muestra automáticamente en el listado de plugins el aviso "Requires"/enlace de activación deshabilitado si MailPoet no está activo, y en la fila de MailPoet el aviso "Required by"/Desactivar-Borrar deshabilitados mientras este plugin esté activo. Solo afecta a la UI de wp-admin, no a `activate_plugin()`/`deactivate_plugins()` a nivel de API.
- Fallback manual con `is_plugin_active('mailpoet/mailpoet.php')` verificado en activation hook y en `admin_init`. Si no está activo → plugin se desactiva con `wp_die()`. Necesario para WP < 6.5 y para cubrir desactivaciones de MailPoet que no pasan por la UI (WP-CLI, multisite red).

**Contraseña IMAP:** cifrada con `openssl_encrypt()` antes de guardar en BD. Nunca en texto plano.

**Multisite:** ajustes con `get_option/update_option` (independientes por sitio). Cron registrado en contexto de cada blog.

## Releases

`CHANGELOG.md` se mantiene actualizado de forma continua, no solo al taggear: cada commit `feat:`/`fix:` añade su entrada en `## [Unreleased]` en el momento del cambio (formato Keep a Changelog).

Antes de taggear: mover el contenido de `[Unreleased]` a una nueva sección `## [X.Y.Z] - AAAA-MM-DD`. El workflow extrae esa sección con `awk` y la usa como notas del release — si no encuentra la sección para la versión taggeada, falla el build.

```bash
git tag vX.Y.Z
git push && git push --tags
```

Trigger: `push: tags: v*` en `.github/workflows/release.yml`.

## Documentación operacional

Pendiente de crear página en BookStack (https://bookstack.planea.com.es).

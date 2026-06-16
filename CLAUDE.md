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
  class-admin.php             Menú Herramientas > Bounce Handler, AJAX handlers
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

**Dependencia MailPoet:** `is_plugin_active('mailpoet/mailpoet.php')` verificado en activation hook y en `admin_init`. Si no está activo → plugin se desactiva con `wp_die()`.

**Contraseña IMAP:** cifrada con `openssl_encrypt()` antes de guardar en BD. Nunca en texto plano.

**Multisite:** ajustes con `get_option/update_option` (independientes por sitio). Cron registrado en contexto de cada blog.

## Releases

Antes de taggear: añadir una sección `## [X.Y.Z] - AAAA-MM-DD` en `CHANGELOG.md` (formato Keep a Changelog) con los cambios de esa versión. El workflow extrae esa sección y la usa como notas del release — si no encuentra la sección para la versión taggeada, falla el build.

```bash
git tag vX.Y.Z
git push && git push --tags
```

Trigger: `push: tags: v*` en `.github/workflows/release.yml`.

## Documentación operacional

Pendiente de crear página en BookStack (https://bookstack.planea.com.es).

# AGENTS.md

## Qué es esto

Plugin WordPress para gestión automática de bounces IMAP en instalaciones con MailPoet 3 (versión gratuita). Requiere MailPoet 3 activo — se desactiva si no está presente. Reemplaza el plugin abandonado "Bounce Handler MailPoet 3".

## Comandos

```bash
# Instalar dependencias y activar el hook .githooks/pre-commit (gitleaks + PHPCS)
composer install

# PHPCS + PHPStan (obligatorio antes de cada bump)
composer check

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

## Actualizaciones automáticas

[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) v6
(`yahnis-elsts/plugin-update-checker`, única dependencia de producción en `composer.json`). En
`mailpoet-bounce-handler.php`, solo en `is_admin()`, `PucFactory::buildUpdateChecker()` apunta al
repo de GitHub con `enableReleaseAssets()`: WordPress ofrece la actualización nativa usando el ZIP
adjunto a la Release, no el código crudo del tag.

- Requiere que el repo siga siendo público; si pasa a privado, `setAuthentication()` con token.
- Sin `vendor/autoload.php` (clon sin `composer install`) el chequeo no se activa y el resto funciona.
- Por eso el ZIP sí lleva `vendor/`, pero solo el de `composer install --no-dev` (solo PUC).

## Workflows

- `pr-title-lint.yml` — título del PR en Conventional Commits
- `secret-scan.yml` — gitleaks en cada push/PR
- `lint-php.yml` — `php -l` en matriz 8.1–8.5 + PHPCS + PHPStan (nivel 5, `phpVersion: 80100`)
- `release-php.yml` — al pushear un tag `v*`: `composer install --no-dev`, ZIP
  `mailpoet-bounce-handler.zip`, notas desde `CHANGELOG.md` y GitHub Release
- `.github/dependabot.yml` — Composer y Actions, semanal (lunes)

## Releases

`CHANGELOG.md` se mantiene actualizado de forma continua, no solo al taggear: cada commit `feat:`/`fix:` añade su entrada en `## [Unreleased]` en el momento del cambio (formato Keep a Changelog).

Bump en su propio PR (`chore: bump version to X.Y.Z`): cabecera `Version:` y `MBH_VERSION` en
`mailpoet-bounce-handler.php`, `Stable tag:` y `== Changelog ==` en `readme.txt`, y mover
`[Unreleased]` a `## [X.Y.Z] - AAAA-MM-DD` en `CHANGELOG.md` (si el workflow no encuentra la
sección, falla). Tras el merge, en `main` actualizado:

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

## Pendientes

- Crear página en BookStack y enlazarla aquí.

## Documentación operacional

Pendiente de crear página en BookStack (https://bookstack.planea.com.es).

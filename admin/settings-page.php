<?php
/**
 * Vista de la página de ajustes del plugin.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$imap_host    = get_option( 'mbh_imap_host', '' );
$imap_port    = get_option( 'mbh_imap_port', 993 );
$imap_ssl     = (bool) get_option( 'mbh_imap_ssl', true );
$imap_proto   = get_option( 'mbh_imap_protocol', 'imap' );
$imap_user    = get_option( 'mbh_imap_user', '' );
$threshold    = get_option( 'mbh_soft_threshold', 3 );
$cron_freq    = get_option( 'mbh_cron_frequency', 'hourly' );
$notify_email = get_option( 'mbh_notify_email', '' );
$retention    = get_option( 'mbh_log_retention_days', 90 );

if ( ! $notify_email ) {
	$updater      = new MBH_MailPoet_Updater();
	$notify_email = $updater->get_reply_to_email() ?? get_option( 'admin_email' );
}

$has_imap_extension = extension_loaded( 'imap' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bounce Handler — Ajustes', 'mailpoet-bounce-handler' ); ?></h1>

	<?php if ( ! $has_imap_extension ) : ?>
	<div class="notice notice-error inline">
		<p><?php esc_html_e( 'La extensión PHP imap no está disponible. El plugin no puede conectarse al buzón hasta que se habilite.', 'mailpoet-bounce-handler' ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( ! $imap_host ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'La conexión IMAP no está configurada. El procesado automático no funcionará hasta completar los ajustes.', 'mailpoet-bounce-handler' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'mbh_settings_group' ); ?>

		<!-- ── Conexión IMAP ── -->
		<h2><?php esc_html_e( 'Conexión IMAP / POP3', 'mailpoet-bounce-handler' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mbh_imap_host"><?php esc_html_e( 'Servidor', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="text" id="mbh_imap_host" name="mbh_imap_host"
						value="<?php echo esc_attr( $imap_host ); ?>"
						class="regular-text" placeholder="mail.ejemplo.com">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mbh_imap_port"><?php esc_html_e( 'Puerto', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="number" id="mbh_imap_port" name="mbh_imap_port"
						value="<?php echo esc_attr( $imap_port ); ?>"
						class="small-text" min="1" max="65535">
					<p class="description"><?php esc_html_e( 'IMAP con SSL: 993. POP3 con SSL: 995. Sin SSL: 143 / 110.', 'mailpoet-bounce-handler' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Protocolo', 'mailpoet-bounce-handler' ); ?></th>
				<td>
					<label>
						<input type="radio" name="mbh_imap_protocol" value="imap" <?php checked( $imap_proto, 'imap' ); ?>>
						IMAP
					</label>
					&nbsp;&nbsp;
					<label>
						<input type="radio" name="mbh_imap_protocol" value="pop3" <?php checked( $imap_proto, 'pop3' ); ?>>
						POP3
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Conexión segura (SSL)', 'mailpoet-bounce-handler' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mbh_imap_ssl" value="1" <?php checked( $imap_ssl ); ?>>
						<?php esc_html_e( 'Usar SSL/TLS', 'mailpoet-bounce-handler' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mbh_imap_user"><?php esc_html_e( 'Usuario', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="text" id="mbh_imap_user" name="mbh_imap_user"
						value="<?php echo esc_attr( $imap_user ); ?>"
						class="regular-text" autocomplete="username">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mbh_imap_pass"><?php esc_html_e( 'Contraseña', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="password" id="mbh_imap_pass" name="mbh_imap_pass"
						value="" class="regular-text" autocomplete="new-password"
						placeholder="<?php esc_attr_e( 'Dejar vacío para no cambiarla', 'mailpoet-bounce-handler' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"></th>
				<td>
					<button type="button" id="mbh-test-connection" class="button">
						<?php esc_html_e( 'Probar conexión', 'mailpoet-bounce-handler' ); ?>
					</button>
					<span id="mbh-connection-result" style="margin-left:10px;"></span>
				</td>
			</tr>
		</table>

		<!-- ── Reglas de bounce ── -->
		<h2><?php esc_html_e( 'Reglas', 'mailpoet-bounce-handler' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mbh_soft_threshold"><?php esc_html_e( 'Umbral soft bounce', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="number" id="mbh_soft_threshold" name="mbh_soft_threshold"
						value="<?php echo esc_attr( $threshold ); ?>"
						class="small-text" min="1" max="20">
					<p class="description"><?php esc_html_e( 'Número de soft bounces antes de marcar el suscriptor como bounced.', 'mailpoet-bounce-handler' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- ── Programación ── -->
		<h2><?php esc_html_e( 'Programación', 'mailpoet-bounce-handler' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Frecuencia de procesado', 'mailpoet-bounce-handler' ); ?></th>
				<td>
					<select name="mbh_cron_frequency">
						<option value="hourly" <?php selected( $cron_freq, 'hourly' ); ?>><?php esc_html_e( 'Cada hora', 'mailpoet-bounce-handler' ); ?></option>
						<option value="sixhourly" <?php selected( $cron_freq, 'sixhourly' ); ?>><?php esc_html_e( 'Cada 6 horas', 'mailpoet-bounce-handler' ); ?></option>
						<option value="daily" <?php selected( $cron_freq, 'daily' ); ?>><?php esc_html_e( 'Diariamente', 'mailpoet-bounce-handler' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<!-- ── Notificaciones ── -->
		<h2><?php esc_html_e( 'Notificaciones', 'mailpoet-bounce-handler' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mbh_notify_email"><?php esc_html_e( 'Email de notificación', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="email" id="mbh_notify_email" name="mbh_notify_email"
						value="<?php echo esc_attr( $notify_email ); ?>"
						class="regular-text">
					<p class="description"><?php esc_html_e( 'Recibirá el resumen al finalizar cada procesado con bounces. Hereda el reply-to de MailPoet si se deja vacío.', 'mailpoet-bounce-handler' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- ── Mantenimiento ── -->
		<h2><?php esc_html_e( 'Mantenimiento', 'mailpoet-bounce-handler' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mbh_log_retention_days"><?php esc_html_e( 'Retención del log (días)', 'mailpoet-bounce-handler' ); ?></label></th>
				<td>
					<input type="number" id="mbh_log_retention_days" name="mbh_log_retention_days"
						value="<?php echo esc_attr( $retention ); ?>"
						class="small-text" min="1" max="3650">
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>

	<!-- Acciones manuales fuera del formulario de ajustes -->
	<hr>
	<h2><?php esc_html_e( 'Acciones manuales', 'mailpoet-bounce-handler' ); ?></h2>
	<p>
		<button type="button" id="mbh-process-now" class="button button-secondary">
			<?php esc_html_e( 'Procesar ahora', 'mailpoet-bounce-handler' ); ?>
		</button>
		<span id="mbh-process-result" style="margin-left:10px;"></span>
	</p>
</div>

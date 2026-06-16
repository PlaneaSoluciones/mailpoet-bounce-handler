<?php
/**
 * Plugin Name: MailPoet Bounce Handler
 * Plugin URI:  https://github.com/PlanaSoluciones/mailpoet-bounce-handler
 * Description: Gestión automática de bounces IMAP/POP3 para MailPoet 3. Requiere MailPoet 3 instalado y activo.
 * Version:     1.1.0
 * Author:      Planea Soluciones
 * Author URI:  https://planea.com.es
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mailpoet-bounce-handler
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MBH_VERSION', '1.1.0' );
define( 'MBH_PLUGIN_FILE', __FILE__ );
define( 'MBH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Carga de clases.
require_once MBH_PLUGIN_DIR . 'includes/class-imap-reader.php';
require_once MBH_PLUGIN_DIR . 'includes/class-bounce-parser.php';
require_once MBH_PLUGIN_DIR . 'includes/class-mailpoet-updater.php';
require_once MBH_PLUGIN_DIR . 'includes/class-logger.php';
require_once MBH_PLUGIN_DIR . 'includes/class-notifier.php';

// Carga diferida del área admin.
if ( is_admin() ) {
	require_once MBH_PLUGIN_DIR . 'admin/class-admin.php';
	( new MBH_Admin() )->init();
}

// ─── Hooks de ciclo de vida ───────────────────────────────────────────────────

register_activation_hook( __FILE__, 'mbh_on_activation' );
register_deactivation_hook( __FILE__, 'mbh_on_deactivation' );

/**
 * Acciones al activar el plugin.
 */
function mbh_on_activation(): void {
	mbh_require_plugin_file();

	if ( ! is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
		wp_die(
			esc_html__( 'MailPoet Bounce Handler requiere que MailPoet 3 esté instalado y activo. Plugin no activado.', 'mailpoet-bounce-handler' ),
			esc_html__( 'Dependencia no satisfecha', 'mailpoet-bounce-handler' ),
			array( 'back_link' => true )
		);
	}

	mbh_create_tables();
	mbh_schedule_cron();
}

/**
 * Acciones al desactivar el plugin. NO elimina datos.
 */
function mbh_on_deactivation(): void {
	wp_clear_scheduled_hook( 'mbh_process_bounces' );
}

// ─── Text domain ──────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', 'mbh_load_textdomain' );

/**
 * Carga el archivo de traducción del plugin.
 */
function mbh_load_textdomain(): void {
	load_plugin_textdomain(
		'mailpoet-bounce-handler',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

// ─── Verificación de dependencia en tiempo de ejecución ──────────────────────

add_action( 'admin_init', 'mbh_check_mailpoet_dependency' );

/**
 * Desactiva el plugin si MailPoet deja de estar activo tras la activación.
 */
function mbh_check_mailpoet_dependency(): void {
	mbh_require_plugin_file();

	if ( is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
		return;
	}

	deactivate_plugins( plugin_basename( __FILE__ ) );

	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'MailPoet Bounce Handler ha sido desactivado porque MailPoet 3 no está activo.', 'mailpoet-bounce-handler' )
				. '</p></div>';
		}
	);
}

// ─── Avisos admin ─────────────────────────────────────────────────────────────

add_action( 'admin_notices', 'mbh_admin_notices' );

/**
 * Muestra avisos de configuración relevantes en el panel de administración.
 */
function mbh_admin_notices(): void {
	if ( ! extension_loaded( 'imap' ) ) {
		echo '<div class="notice notice-error"><p>'
			. esc_html__( 'MailPoet Bounce Handler: la extensión PHP imap no está disponible. El plugin no puede conectarse al buzón de correo.', 'mailpoet-bounce-handler' )
			. '</p></div>';
	}

	$policy_bounces = get_transient( 'mbh_policy_alert' );
	if ( ! empty( $policy_bounces ) && current_user_can( 'manage_options' ) ) {
		$count       = count( $policy_bounces );
		$log_url     = admin_url( 'admin.php?page=mbh-log' );
		$dismiss_url = wp_nonce_url(
			admin_url( 'admin.php?page=mbh-settings&mbh_dismiss_policy=1' ),
			'mbh_dismiss_policy'
		);

		echo '<div class="notice notice-warning"><p>'
			. '<strong>' . esc_html__( 'MailPoet Bounce Handler — Alerta de bloqueo por política', 'mailpoet-bounce-handler' ) . '</strong><br>'
			. esc_html(
				sprintf(
					/* translators: %d: número de rebotes por política detectados */
					_n(
						'Se ha detectado %d rebote por política/reputación (blacklist, spam o bloqueo del servidor). El suscriptor NO ha sido marcado como bounced. Revisa la reputación de tu servidor de envío.',
						'Se han detectado %d rebotes por política/reputación (blacklist, spam o bloqueo del servidor). Los suscriptores NO han sido marcados como bounced. Revisa la reputación de tu servidor de envío.',
						$count,
						'mailpoet-bounce-handler'
					),
					$count
				)
			)
			. ' <a href="' . esc_url( $log_url ) . '">' . esc_html__( 'Ver log', 'mailpoet-bounce-handler' ) . '</a>'
			. ' &nbsp; <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Descartar', 'mailpoet-bounce-handler' ) . '</a>'
			. '</p></div>';
	}
}

add_action( 'admin_init', 'mbh_handle_policy_dismiss' );

/**
 * Procesa el descarte manual de la alerta de policy bounces.
 */
function mbh_handle_policy_dismiss(): void {
	if (
		isset( $_GET['mbh_dismiss_policy'] ) &&
		isset( $_GET['_wpnonce'] ) &&
		wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'mbh_dismiss_policy' ) &&
		current_user_can( 'manage_options' )
	) {
		delete_transient( 'mbh_policy_alert' );
		wp_safe_redirect( admin_url( 'admin.php?page=mbh-settings' ) );
		exit;
	}
}

// ─── Cron ─────────────────────────────────────────────────────────────────────

add_filter( 'cron_schedules', 'mbh_add_cron_intervals' );

/**
 * Registra el intervalo personalizado "sixhourly".
 *
 * @param array $schedules Intervalos existentes de WordPress.
 * @return array
 */
function mbh_add_cron_intervals( array $schedules ): array {
	$schedules['sixhourly'] = array(
		'interval' => 6 * HOUR_IN_SECONDS,
		'display'  => __( 'Cada 6 horas', 'mailpoet-bounce-handler' ),
	);
	return $schedules;
}

/**
 * Programa el evento cron según la frecuencia configurada.
 */
function mbh_schedule_cron(): void {
	if ( wp_next_scheduled( 'mbh_process_bounces' ) ) {
		wp_clear_scheduled_hook( 'mbh_process_bounces' );
	}

	$frequency = get_option( 'mbh_cron_frequency', 'hourly' );
	wp_schedule_event( time(), $frequency, 'mbh_process_bounces' );
}

add_action( 'mbh_process_bounces', 'mbh_run_bounce_processing' );

/**
 * Punto de entrada del procesado de bounces (cron y manual).
 *
 * @return array Resultados del procesado.
 */
function mbh_run_bounce_processing(): array {
	$config = array(
		'host'     => get_option( 'mbh_imap_host', '' ),
		'port'     => (int) get_option( 'mbh_imap_port', 993 ),
		'ssl'      => (bool) get_option( 'mbh_imap_ssl', true ),
		'protocol' => get_option( 'mbh_imap_protocol', 'imap' ),
		'user'     => get_option( 'mbh_imap_user', '' ),
		'pass'     => mbh_decrypt_password( get_option( 'mbh_imap_pass', '' ) ),
	);

	if ( ! $config['host'] || ! $config['user'] ) {
		return array();
	}

	$reader   = new MBH_IMAP_Reader( $config );
	$parser   = new MBH_Bounce_Parser();
	$updater  = new MBH_MailPoet_Updater();
	$logger   = new MBH_Logger();
	$notifier = new MBH_Notifier();

	try {
		$connected = $reader->connect();
	} catch ( RuntimeException $e ) {
		return array();
	}

	if ( ! $connected ) {
		return array();
	}

	$messages  = $reader->get_messages();
	$threshold = (int) get_option( 'mbh_soft_threshold', 3 );

	$results = array(
		'hard'                   => array(),
		'soft'                   => array(),
		'soft_threshold_reached' => array(),
		'policy'                 => array(),
	);

	foreach ( $messages as $message ) {
		$parsed = $parser->parse( $message );

		if ( null === $parsed['email'] || null === $parsed['type'] ) {
			continue;
		}

		$email   = $parsed['email'];
		$type    = $parsed['type'];
		$subject = $message['header']->subject ?? '';

		if ( 'hard' === $type ) {
			$status = $updater->mark_as_bounced( $email );
			$logger->reset_soft_count( $email );

			$logger->log_bounce(
				array(
					'email'         => $email,
					'bounce_type'   => 'hard',
					'soft_count'    => 0,
					'action_taken'  => 'marked_bounced',
					'status_before' => $status['before'] ?? '',
					'status_after'  => $status['after'] ?? '',
					'raw_subject'   => $subject,
				)
			);

			$results['hard'][] = array( 'email' => $email );

		} elseif ( 'soft' === $type ) {
			$soft_count = $logger->increment_soft_count( $email );

			if ( $soft_count >= $threshold ) {
				$status = $updater->mark_as_bounced( $email );
				$logger->reset_soft_count( $email );

				$logger->log_bounce(
					array(
						'email'         => $email,
						'bounce_type'   => 'soft',
						'soft_count'    => $soft_count,
						'action_taken'  => 'marked_bounced_threshold',
						'status_before' => $status['before'] ?? '',
						'status_after'  => $status['after'] ?? '',
						'raw_subject'   => $subject,
					)
				);

				$results['soft_threshold_reached'][] = array( 'email' => $email );
			} else {
				$logger->log_bounce(
					array(
						'email'         => $email,
						'bounce_type'   => 'soft',
						'soft_count'    => $soft_count,
						'action_taken'  => 'soft_count_incremented',
						'status_before' => '',
						'status_after'  => '',
						'raw_subject'   => $subject,
					)
				);

				$results['soft'][] = array( 'email' => $email );
			}
		} elseif ( 'policy' === $type ) {
			// Bloqueo por política/reputación: no marcar el suscriptor, solo registrar y alertar.
			$logger->log_bounce(
				array(
					'email'         => $email,
					'bounce_type'   => 'soft',
					'soft_count'    => 0,
					'action_taken'  => 'policy_block',
					'status_before' => '',
					'status_after'  => '',
					'raw_subject'   => $subject,
				)
			);

			$results['policy'][] = array( 'email' => $email );
		}

		$reader->delete_message( $message['num'] );
	}

	$reader->close();
	$logger->purge_old_logs();

	if ( ! empty( $results['policy'] ) ) {
		// Acumular alertas de policy en el transient existente para mostrar en el admin.
		$existing        = get_transient( 'mbh_policy_alert' );
		$existing        = ! empty( $existing ) ? $existing : array();
		$combined        = array_merge( $existing, $results['policy'] );
		$unique_emails   = array_values( array_unique( array_column( $combined, 'email' ) ) );
		$unique_combined = array_map( static fn( $e ) => array( 'email' => $e ), $unique_emails );
		set_transient( 'mbh_policy_alert', $unique_combined, 30 * DAY_IN_SECONDS );
	}

	$notifier->send_summary( $results );

	return $results;
}

// ─── Creación de tablas ───────────────────────────────────────────────────────

/**
 * Crea o actualiza las tablas del plugin usando dbDelta.
 */
function mbh_create_tables(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$sql_log = "CREATE TABLE {$wpdb->prefix}mbh_log (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		processed_at DATETIME NOT NULL,
		email VARCHAR(255) NOT NULL,
		bounce_type ENUM('hard','soft') NOT NULL,
		soft_count TINYINT DEFAULT 0,
		action_taken VARCHAR(100) DEFAULT '',
		status_before VARCHAR(50) DEFAULT '',
		status_after VARCHAR(50) DEFAULT '',
		raw_subject TEXT,
		PRIMARY KEY (id),
		KEY email (email),
		KEY processed_at (processed_at)
	) $charset_collate;";

	$sql_soft = "CREATE TABLE {$wpdb->prefix}mbh_soft_counts (
		email VARCHAR(255) NOT NULL,
		count TINYINT NOT NULL DEFAULT 1,
		first_seen DATETIME DEFAULT NULL,
		last_seen DATETIME DEFAULT NULL,
		PRIMARY KEY (email)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_log );
	dbDelta( $sql_soft );

	update_option( 'mbh_db_version', MBH_VERSION );
}

// ─── Cifrado contraseña IMAP ──────────────────────────────────────────────────

/**
 * Cifra la contraseña IMAP para su almacenamiento.
 *
 * @param string $password Contraseña en texto plano.
 * @return string Contraseña cifrada en base64.
 */
function mbh_encrypt_password( string $password ): string {
	if ( '' === $password ) {
		return '';
	}

	$key    = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );
	$iv     = openssl_random_pseudo_bytes( 16 );
	$cipher = openssl_encrypt( $password, 'AES-256-CBC', $key, 0, $iv );

	return base64_encode( $iv . '::' . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * Descifra la contraseña IMAP almacenada.
 *
 * @param string $stored Contraseña cifrada.
 * @return string Contraseña en texto plano.
 */
function mbh_decrypt_password( string $stored ): string {
	if ( '' === $stored ) {
		return '';
	}

	$decoded = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $decoded || ! str_contains( $decoded, '::' ) ) {
		return '';
	}

	list( $iv, $cipher ) = explode( '::', $decoded, 2 );
	$key                 = substr( AUTH_KEY . SECURE_AUTH_KEY, 0, 32 );

	$plain = openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );

	return false !== $plain ? $plain : '';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Incluye plugin.php fuera del área admin si es necesario.
 */
function mbh_require_plugin_file(): void {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
}

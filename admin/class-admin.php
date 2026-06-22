<?php
/**
 * Registro del área de administración del plugin.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestiona el menú, páginas y AJAX handlers del área admin.
 */
class MBH_Admin {

	/** Slug del menú principal de MailPoet (Menu::MAIN_PAGE_SLUG, no es API pública). */
	private const MAILPOET_MENU_SLUG = 'mailpoet-homepage';

	/**
	 * Hook suffix de la página de ajustes, devuelto por add_submenu_page().
	 *
	 * @var string
	 */
	private string $settings_hook = '';

	/**
	 * Hook suffix de la página de log, devuelto por add_submenu_page().
	 *
	 * @var string
	 */
	private string $log_hook = '';

	/**
	 * Registra todos los hooks de WordPress para el área admin.
	 */
	public function init(): void {
		// Prioridad 20: debe ejecutarse después de que MailPoet registre su propia
		// página "Home" (mismo slug que el padre). Si nuestras páginas se registran
		// antes, WordPress usa la primera como "padre real" del menú de MailPoet
		// completo (ver wp-admin/includes/menu.php), rompiendo sus URLs.
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_mbh_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_mbh_process_now', array( $this, 'ajax_process_now' ) );
		add_action( 'wp_ajax_mbh_change_subscriber_status', array( $this, 'ajax_change_subscriber_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MBH_PLUGIN_FILE ), array( $this, 'add_action_links' ) );
	}

	/**
	 * Añade el enlace "Ajustes" en la lista de plugins.
	 *
	 * @param string[] $links Enlaces de acción existentes.
	 * @return string[]
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=mbh-settings' ) ),
			esc_html__( 'Ajustes', 'mailpoet-bounce-handler' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Registra las páginas de administración como submenú del menú de MailPoet.
	 */
	public function add_menu_pages(): void {
		$this->settings_hook = (string) add_submenu_page(
			self::MAILPOET_MENU_SLUG,
			__( 'Bounce Handler', 'mailpoet-bounce-handler' ),
			__( 'Bounce Handler', 'mailpoet-bounce-handler' ),
			'manage_options',
			'mbh-settings',
			array( $this, 'render_settings_page' )
		);

		$this->log_hook = (string) add_submenu_page(
			self::MAILPOET_MENU_SLUG,
			__( 'Bounce Handler — Log', 'mailpoet-bounce-handler' ),
			__( 'Bounce Log', 'mailpoet-bounce-handler' ),
			'manage_options',
			'mbh-log',
			array( $this, 'render_log_page' )
		);
	}

	/**
	 * Registra las opciones del plugin con la Settings API de WordPress.
	 */
	public function register_settings(): void {
		$options = array(
			'mbh_imap_host'          => array( 'sanitize_text_field', '' ),
			'mbh_imap_port'          => array( 'absint', 993 ),
			'mbh_imap_ssl'           => array( 'absint', 1 ),
			'mbh_imap_protocol'      => array( 'sanitize_text_field', 'imap' ),
			'mbh_imap_user'          => array( 'sanitize_text_field', '' ),
			'mbh_soft_threshold'     => array( 'absint', 3 ),
			'mbh_cron_frequency'     => array( 'sanitize_text_field', 'hourly' ),
			'mbh_notify_email'       => array( 'sanitize_email', '' ),
			'mbh_log_retention_days' => array( 'absint', 0 ),
			'mbh_log_max_rows'       => array( 'absint', 10000 ),
		);

		foreach ( $options as $key => list( $sanitize, $default ) ) {
			register_setting(
				'mbh_settings_group',
				$key,
				array(
					'sanitize_callback' => $sanitize,
					'default'           => $default,
				)
			);
		}

		// La contraseña tiene sanitización propia.
		register_setting(
			'mbh_settings_group',
			'mbh_imap_pass',
			array( 'sanitize_callback' => array( $this, 'sanitize_imap_pass' ) )
		);
	}

	/**
	 * Sanitiza y cifra la contraseña IMAP antes de guardarla.
	 *
	 * @param string $value Contraseña en texto plano enviada por el formulario.
	 * @return string Contraseña cifrada o vacía.
	 */
	public function sanitize_imap_pass( string $value ): string {
		if ( '' === $value ) {
			// Si el campo viene vacío, conserva la contraseña ya guardada.
			return get_option( 'mbh_imap_pass', '' );
		}

		return mbh_encrypt_password( $value );
	}

	/**
	 * Carga scripts y estilos solo en páginas del plugin.
	 *
	 * @param string $hook_suffix Sufijo del hook de la página actual.
	 */
	public function enqueue_scripts( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( $this->settings_hook, $this->log_hook ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'mbh-admin',
			plugins_url( 'admin/mbh-admin.css', MBH_PLUGIN_FILE ),
			array(),
			MBH_VERSION
		);

		wp_enqueue_script(
			'mbh-admin',
			plugins_url( 'admin/mbh-admin.js', MBH_PLUGIN_FILE ),
			array( 'jquery' ),
			MBH_VERSION,
			true
		);

		wp_localize_script(
			'mbh-admin',
			'mbhAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'mbh_admin_nonce' ),
				'testingLabel'    => __( 'Probando...', 'mailpoet-bounce-handler' ),
				'processingLabel' => __( 'Procesando...', 'mailpoet-bounce-handler' ),
			)
		);
	}

	/**
	 * Renderiza la página de ajustes.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once MBH_PLUGIN_DIR . 'admin/settings-page.php';
	}

	/**
	 * Renderiza la página de log.
	 */
	public function render_log_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Manejar export CSV antes de que la página emita HTML.
		if ( isset( $_GET['mbh_export_csv'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->export_csv();
			return;
		}

		require_once MBH_PLUGIN_DIR . 'admin/log-viewer.php';
	}

	/**
	 * AJAX: prueba la conexión IMAP con los datos del formulario.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'mbh_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'mailpoet-bounce-handler' ) );
		}

		$config = array(
			'host'     => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
			'port'     => absint( $_POST['port'] ?? 993 ),
			'ssl'      => (bool) absint( $_POST['ssl'] ?? 1 ),
			'protocol' => sanitize_text_field( wp_unslash( $_POST['protocol'] ?? 'imap' ) ),
			'user'     => sanitize_text_field( wp_unslash( $_POST['user'] ?? '' ) ),
			'pass'     => sanitize_text_field( wp_unslash( $_POST['pass'] ?? '' ) ),
		);

		// Si la contraseña llega vacía del form, usar la almacenada.
		if ( '' === $config['pass'] ) {
			$config['pass'] = mbh_decrypt_password( get_option( 'mbh_imap_pass', '' ) );
		}

		try {
			$reader    = new MBH_IMAP_Reader( $config );
			$connected = $reader->connect();
			$reader->close();

			if ( $connected ) {
				wp_send_json_success( __( 'Conexión establecida correctamente.', 'mailpoet-bounce-handler' ) );
			} else {
				$last_error = $reader->get_last_error();
				$message    = __( 'No se pudo conectar. Verifica los datos.', 'mailpoet-bounce-handler' );

				if ( '' !== $last_error ) {
					$message .= ' (' . $last_error . ')';
				}

				wp_send_json_error( $message );
			}
		} catch ( RuntimeException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX: lanza el procesado de bounces de forma manual.
	 */
	public function ajax_process_now(): void {
		check_ajax_referer( 'mbh_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'mailpoet-bounce-handler' ) );
		}

		$results = mbh_run_bounce_processing();

		$hard  = count( $results['hard'] ?? array() );
		$soft  = count( $results['soft'] ?? array() );
		$reach = count( $results['soft_threshold_reached'] ?? array() );

		wp_send_json_success(
			sprintf(
				// translators: %1$d hard bounces, %2$d soft bounces, %3$d soft que alcanzaron umbral.
				__( 'Procesado: %1$d hard, %2$d soft (pendientes), %3$d soft → bounced (umbral).', 'mailpoet-bounce-handler' ),
				$hard,
				$soft,
				$reach
			)
		);
	}

	/**
	 * AJAX: cambia el estado de un suscriptor en MailPoet desde el log.
	 */
	public function ajax_change_subscriber_status(): void {
		check_ajax_referer( 'mbh_change_subscriber_status', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'mailpoet-bounce-handler' ) );
		}

		$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$action_type = sanitize_key( wp_unslash( $_POST['action_type'] ?? '' ) );

		if ( ! is_email( $email ) || ! in_array( $action_type, array( 'reactivate', 'bounce' ), true ) ) {
			wp_send_json_error( __( 'Datos inválidos.', 'mailpoet-bounce-handler' ) );
		}

		$updater = new MBH_MailPoet_Updater();

		if ( 'reactivate' === $action_type ) {
			$success = $updater->reactivate_subscriber( $email );
		} else {
			$success = $updater->force_bounce_subscriber( $email );
		}

		if ( $success ) {
			wp_send_json_success( array( 'new_status' => $updater->get_subscriber_status( $email ) ) );
		} else {
			wp_send_json_error( __( 'No se pudo actualizar el estado del suscriptor.', 'mailpoet-bounce-handler' ) );
		}
	}

	/**
	 * Exporta el log completo (o filtrado) como CSV.
	 */
	private function export_csv(): void {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'mbh_export_csv' ) ) {
			wp_die( esc_html__( 'Nonce inválido.', 'mailpoet-bounce-handler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'mailpoet-bounce-handler' ) );
		}

		$logger  = new MBH_Logger();
		$filters = array(
			'bounce_type' => sanitize_text_field( wp_unslash( $_GET['bounce_type'] ?? '' ) ),
			'date_from'   => sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ),
			'date_to'     => sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ),
		);

		$data = $logger->get_logs( $filters, 1, 9999 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="bounce-log-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM UTF-8.

		fputcsv(
			$out,
			array(
				__( 'Fecha', 'mailpoet-bounce-handler' ),
				__( 'Email', 'mailpoet-bounce-handler' ),
				__( 'Tipo', 'mailpoet-bounce-handler' ),
				__( 'Intentos soft', 'mailpoet-bounce-handler' ),
				__( 'Acción', 'mailpoet-bounce-handler' ),
				__( 'Diagnóstico', 'mailpoet-bounce-handler' ),
				__( 'Asunto', 'mailpoet-bounce-handler' ),
			)
		);

		foreach ( $data['items'] as $row ) {
			fputcsv(
				$out,
				array(
					$row->processed_at,
					$row->email,
					$row->bounce_type,
					$row->soft_count,
					$row->action_taken,
					$row->diagnostic_code ?? '',
					$row->raw_subject,
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		exit;
	}
}

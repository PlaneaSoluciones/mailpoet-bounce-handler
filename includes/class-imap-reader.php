<?php
/**
 * Conexión y lectura de buzón IMAP/POP3.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestiona la conexión IMAP/POP3 y la lectura de mensajes.
 */
class MBH_IMAP_Reader {

	/**
	 * Stream IMAP activo.
	 *
	 * @var resource|false
	 */
	private $stream = false;

	/**
	 * Opciones de conexión.
	 *
	 * @var array{host: string, port: int, ssl: bool, protocol: string, user: string, pass: string}
	 */
	private array $config;

	/**
	 * Inicializa el lector con la configuración de conexión.
	 *
	 * @param array $config Claves: host, port, ssl, protocol, user, pass.
	 */
	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * Abre la conexión con el servidor de correo.
	 *
	 * @return bool True si la conexión se estableció correctamente.
	 * @throws RuntimeException Si la extensión imap no está disponible.
	 */
	public function connect(): bool {
		if ( ! extension_loaded( 'imap' ) ) {
			throw new RuntimeException(
				__( 'La extensión PHP imap no está disponible.', 'mailpoet-bounce-handler' ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$flags   = $this->build_flags();
		$mailbox = '{' . $this->config['host'] . ':' . $this->config['port'] . $flags . '}INBOX';

		// imap_open puede generar warnings — los suprimimos y comprobamos el retorno.
		$this->stream = @imap_open( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$mailbox,
			$this->config['user'],
			$this->config['pass']
		);

		if ( false === $this->stream ) {
			return false;
		}

		return true;
	}

	/**
	 * Retorna todos los mensajes del buzón como array de objetos stdClass.
	 * Cada objeto tiene: num (int), header (object), body (string).
	 *
	 * @return array<int, array{num: int, header: object, body: string}>
	 */
	public function get_messages(): array {
		if ( false === $this->stream ) {
			return array();
		}

		$count    = imap_num_msg( $this->stream );
		$messages = array();

		for ( $i = 1; $i <= $count; $i++ ) {
			$messages[] = array(
				'num'    => $i,
				'header' => imap_headerinfo( $this->stream, $i ),
				'body'   => imap_fetchbody( $this->stream, $i, '' ),
			);
		}

		return $messages;
	}

	/**
	 * Marca un mensaje para borrado y lo expunge inmediatamente.
	 *
	 * @param int $msg_num Número de mensaje en el buzón.
	 */
	public function delete_message( int $msg_num ): void {
		if ( false === $this->stream ) {
			return;
		}

		imap_delete( $this->stream, $msg_num );
		imap_expunge( $this->stream );
	}

	/**
	 * Cierra la conexión IMAP.
	 */
	public function close(): void {
		if ( false !== $this->stream ) {
			imap_close( $this->stream );
			$this->stream = false;
		}
	}

	/**
	 * Construye los flags para la cadena de conexión IMAP.
	 *
	 * @return string Ej: "/imap/ssl" o "/pop3/novalidate-cert"
	 */
	private function build_flags(): string {
		$protocol = ( 'pop3' === $this->config['protocol'] ) ? '/pop3' : '/imap';
		$ssl      = $this->config['ssl'] ? '/ssl' : '/novalidate-cert';
		return $protocol . $ssl;
	}
}

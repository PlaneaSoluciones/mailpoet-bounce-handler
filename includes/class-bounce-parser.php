<?php
/**
 * Parseo de mensajes DSN (RFC 3464) para clasificar bounces.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analiza un mensaje de correo y determina si es un bounce hard o soft.
 */
class MBH_Bounce_Parser {

	/**
	 * Palabras clave en el Subject que indican un bounce.
	 *
	 * @var string[]
	 */
	private const SUBJECT_PATTERNS = array(
		'mail delivery failed',
		'undelivered mail',
		'delivery status notification',
		'delivery failure',
		'returned mail',
		'mail system error',
	);

	/**
	 * Analiza un mensaje y extrae email destinatario y tipo de bounce.
	 *
	 * @param array $message Array con claves 'header' (object) y 'body' (string).
	 * @return array{email: string|null, type: string|null}
	 *   'type' es 'hard', 'soft' o null si no se puede determinar.
	 */
	public function parse( array $message ): array {
		$result = array(
			'email' => null,
			'type'  => null,
		);

		$subject = isset( $message['header']->subject )
			? strtolower( imap_utf8( $message['header']->subject ) )
			: '';

		if ( ! $this->looks_like_bounce( $subject, $message['body'] ) ) {
			return $result;
		}

		$result['email'] = $this->extract_email( $message['header'], $message['body'] );
		$result['type']  = $this->classify_bounce( $message['body'] );

		return $result;
	}

	/**
	 * Comprueba si el mensaje tiene apariencia de bounce.
	 *
	 * @param string $subject Subject en minúsculas.
	 * @param string $body    Cuerpo completo del mensaje.
	 */
	private function looks_like_bounce( string $subject, string $body ): bool {
		foreach ( self::SUBJECT_PATTERNS as $pattern ) {
			if ( str_contains( $subject, $pattern ) ) {
				return true;
			}
		}

		// Presencia de cabecera DSN en el cuerpo.
		return str_contains( $body, 'Content-Type: message/delivery-status' )
			|| str_contains( $body, 'Final-Recipient:' );
	}

	/**
	 * Extrae el email del destinatario fallido del mensaje.
	 *
	 * @param object $header Cabecera del mensaje.
	 * @param string $body   Cuerpo completo del mensaje.
	 * @return string|null
	 */
	private function extract_email( object $header, string $body ): ?string {
		// 1. Cabecera X-Failed-Recipients.
		if ( isset( $header->{'x-failed-recipients'} ) ) {
			$email = trim( $header->{'x-failed-recipients'} );
			if ( $this->is_valid_email( $email ) ) {
				return strtolower( $email );
			}
		}

		// 2. Final-Recipient en cuerpo DSN (RFC 3464).
		if ( preg_match( '/Final-Recipient:\s*rfc822;\s*(.+)/i', $body, $m ) ) {
			$email = trim( $m[1] );
			if ( $this->is_valid_email( $email ) ) {
				return strtolower( $email );
			}
		}

		// 3. Patrón genérico de email en el cuerpo.
		if ( preg_match( '/[\w.+-]+@[\w-]+\.[\w.-]+/', $body, $m ) ) {
			if ( $this->is_valid_email( $m[0] ) ) {
				return strtolower( $m[0] );
			}
		}

		return null;
	}

	/**
	 * Determina si el bounce es hard o soft a partir del código de estado DSN.
	 *
	 * Códigos 5.x.x → hard (permanente). Códigos 4.x.x → soft (transitorio).
	 *
	 * @param string $body Cuerpo completo del mensaje.
	 * @return string|null 'hard', 'soft' o null si no se puede clasificar.
	 */
	private function classify_bounce( string $body ): ?string {
		// Status DSN (RFC 3464): "Status: 5.1.1" o "Status: 4.2.2".
		if ( preg_match( '/Status:\s*([45])\.\d+\.\d+/i', $body, $m ) ) {
			return ( '5' === $m[1] ) ? 'hard' : 'soft';
		}

		// Diagnostic-Code con código SMTP.
		if ( preg_match( '/Diagnostic-Code:.*\b([45]\d\d)\b/i', $body, $m ) ) {
			return ( '5' === $m[1][0] ) ? 'hard' : 'soft';
		}

		// Palabras clave para hard bounce.
		$hard_keywords = array(
			'user unknown', 'no such user', 'does not exist',
			'invalid address', 'address rejected', 'domain not found',
		);
		$body_lower = strtolower( $body );
		foreach ( $hard_keywords as $kw ) {
			if ( str_contains( $body_lower, $kw ) ) {
				return 'hard';
			}
		}

		// Por defecto tratamos como soft si parece bounce pero no clasificamos.
		return 'soft';
	}

	/**
	 * Valida un email con el filtro nativo de PHP.
	 *
	 * @param string $email Dirección a validar.
	 */
	private function is_valid_email( string $email ): bool {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

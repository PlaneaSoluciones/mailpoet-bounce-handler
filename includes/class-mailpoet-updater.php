<?php
/**
 * Integración con la API interna de MailPoet 3.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actualiza el estado de suscriptores en MailPoet 3 vía su API PHP interna.
 */
class MBH_MailPoet_Updater {

	/**
	 * Obtiene un suscriptor de MailPoet por email.
	 *
	 * @param string $email Dirección de correo.
	 * @return array|null Array de datos del suscriptor o null si no existe.
	 */
	public function get_subscriber( string $email ): ?array {
		try {
			return \MailPoet\API\API::MP( 'v1' )->getSubscriber( $email );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Cambia el estado de un suscriptor a 'bounced'.
	 *
	 * @param string $email Dirección de correo.
	 * @return array{before: string, after: string}|null Null si el suscriptor no existe.
	 */
	public function mark_as_bounced( string $email ): ?array {
		$subscriber = $this->get_subscriber( $email );

		if ( null === $subscriber ) {
			return null;
		}

		$status_before = $subscriber['status'] ?? 'unknown';

		try {
			\MailPoet\API\API::MP( 'v1' )->updateSubscriber(
				$subscriber['id'],
				array( 'status' => 'bounced' )
			);
		} catch ( \Exception $e ) {
			return null;
		}

		return array(
			'before' => $status_before,
			'after'  => 'bounced',
		);
	}

	/**
	 * Obtiene el email reply-to configurado en MailPoet.
	 * Retorna null si no está configurado.
	 *
	 * @return string|null
	 */
	public function get_reply_to_email(): ?string {
		try {
			$settings = \MailPoet\API\API::MP( 'v1' )->getSettings();
			$email    = $settings['reply_to']['address'] ?? null;
			return ( $email && is_email( $email ) ) ? $email : null;
		} catch ( \Exception $e ) {
			return null;
		}
	}
}

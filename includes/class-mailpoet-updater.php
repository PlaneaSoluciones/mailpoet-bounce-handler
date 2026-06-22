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
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Cambia el estado de un suscriptor a 'bounced'.
	 *
	 * @param string $email Dirección de correo.
	 * @return int|null ID del suscriptor, o null si no existe o falla.
	 */
	public function mark_as_bounced( string $email ): ?int {
		$subscriber = $this->get_subscriber( $email );

		if ( null === $subscriber ) {
			return null;
		}

		try {
			\MailPoet\API\API::MP( 'v1' )->updateSubscriber(
				$subscriber['id'],
				array( 'status' => 'bounced' )
			);
		} catch ( \Throwable $e ) {
			// Fallback: escritura directa si la API rechaza el estado 'bounced'.
			global $wpdb;
			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . 'mailpoet_subscribers',
				array(
					'status'     => 'bounced',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $subscriber['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $result ) {
				return null;
			}
		}

		return (int) $subscriber['id'];
	}

	/**
	 * Cambia el estado de un suscriptor a 'subscribed'.
	 *
	 * @param string $email Dirección de correo.
	 * @return bool True si el cambio se aplicó correctamente.
	 */
	public function reactivate_subscriber( string $email ): bool {
		$subscriber = $this->get_subscriber( $email );

		if ( null === $subscriber ) {
			return false;
		}

		try {
			\MailPoet\API\API::MP( 'v1' )->updateSubscriber(
				$subscriber['id'],
				array( 'status' => 'subscribed' )
			);
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Cambia el estado de un suscriptor a 'bounced' de forma manual.
	 *
	 * @param string $email Dirección de correo.
	 * @return bool True si el cambio se aplicó correctamente.
	 */
	public function force_bounce_subscriber( string $email ): bool {
		$subscriber = $this->get_subscriber( $email );

		if ( null === $subscriber ) {
			return false;
		}

		try {
			\MailPoet\API\API::MP( 'v1' )->updateSubscriber(
				$subscriber['id'],
				array( 'status' => 'bounced' )
			);
			return true;
		} catch ( \Throwable $e ) {
			// Fallback: escritura directa si la API rechaza el estado 'bounced'.
			global $wpdb;
			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . 'mailpoet_subscribers',
				array(
					'status'     => 'bounced',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $subscriber['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			return false !== $result;
		}
	}

	/**
	 * Retorna el estado actual de un suscriptor sin modificarlo.
	 *
	 * @param string $email Dirección de correo.
	 * @return string|null Estado del suscriptor o null si no existe.
	 */
	public function get_subscriber_status( string $email ): ?string {
		$subscriber = $this->get_subscriber( $email );
		return null !== $subscriber ? ( $subscriber['status'] ?? 'unknown' ) : null;
	}

	/**
	 * Obtiene el email reply-to configurado en MailPoet.
	 * Retorna null si no está configurado.
	 *
	 * La API pública MP('v1') de MailPoet no expone los ajustes generales,
	 * así que se lee directamente la opción donde MailPoet los almacena.
	 *
	 * @return string|null
	 */
	public function get_reply_to_email(): ?string {
		$settings = get_option( 'mailpoet_settings' );
		$email    = $settings['reply_to']['address'] ?? null;

		return ( $email && is_email( $email ) ) ? $email : null;
	}
}

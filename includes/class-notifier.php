<?php
/**
 * Notificaciones por email al finalizar el procesado de bounces.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Envía un email resumen del procesado de bounces.
 */
class MBH_Notifier {

	/**
	 * Envía un email con el resumen del procesado si hubo bounces.
	 *
	 * @param array{
	 *   hard: array<int, array{email: string}>,
	 *   soft: array<int, array{email: string}>,
	 *   soft_threshold_reached: array<int, array{email: string}>
	 * } $results Resultados del procesado.
	 */
	public function send_summary( array $results ): void {
		$hard_count     = count( $results['hard'] ?? array() );
		$soft_count     = count( $results['soft'] ?? array() );
		$threshold_count = count( $results['soft_threshold_reached'] ?? array() );

		if ( 0 === $hard_count && 0 === $soft_count ) {
			return;
		}

		$to = get_option( 'mbh_notify_email', '' );
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			// translators: %1$d hard bounces, %2$d soft bounces.
			__( '[MailPoet Bounce Handler] %1$d hard bounces, %2$d soft bounces procesados', 'mailpoet-bounce-handler' ),
			$hard_count,
			$soft_count
		);

		$body = $this->build_email_body( $results, $hard_count, $soft_count, $threshold_count );

		wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Construye el cuerpo HTML del email de resumen.
	 *
	 * @param array $results         Resultados del procesado.
	 * @param int   $hard_count      Número de hard bounces.
	 * @param int   $soft_count      Número de soft bounces.
	 * @param int   $threshold_count Soft bounces que alcanzaron el umbral.
	 * @return string HTML del cuerpo.
	 */
	private function build_email_body( array $results, int $hard_count, int $soft_count, int $threshold_count ): string {
		$site_name = get_bloginfo( 'name' );
		$date      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		ob_start();
		?>
		<html>
		<body style="font-family: sans-serif; color: #333;">
		<h2><?php echo esc_html( sprintf( __( 'Bounce Handler — %s', 'mailpoet-bounce-handler' ), $site_name ) ); ?></h2>
		<p><?php echo esc_html( $date ); ?></p>

		<h3><?php esc_html_e( 'Resumen', 'mailpoet-bounce-handler' ); ?></h3>
		<ul>
			<li><?php echo esc_html( sprintf( __( 'Hard bounces: %d', 'mailpoet-bounce-handler' ), $hard_count ) ); ?></li>
			<li><?php echo esc_html( sprintf( __( 'Soft bounces: %d', 'mailpoet-bounce-handler' ), $soft_count ) ); ?></li>
			<?php if ( $threshold_count > 0 ) : ?>
			<li><?php echo esc_html( sprintf( __( 'Soft → marcados como bounced (umbral alcanzado): %d', 'mailpoet-bounce-handler' ), $threshold_count ) ); ?></li>
			<?php endif; ?>
		</ul>

		<?php if ( ! empty( $results['hard'] ) ) : ?>
		<h3><?php esc_html_e( 'Hard bounces', 'mailpoet-bounce-handler' ); ?></h3>
		<ul>
			<?php foreach ( $results['hard'] as $bounce ) : ?>
			<li><?php echo esc_html( $bounce['email'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>

		<?php if ( ! empty( $results['soft_threshold_reached'] ) ) : ?>
		<h3><?php esc_html_e( 'Soft bounces — umbral alcanzado (marcados como bounced)', 'mailpoet-bounce-handler' ); ?></h3>
		<ul>
			<?php foreach ( $results['soft_threshold_reached'] as $bounce ) : ?>
			<li><?php echo esc_html( $bounce['email'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>

		<?php if ( ! empty( $results['soft'] ) ) : ?>
		<h3><?php esc_html_e( 'Soft bounces (pendientes de umbral)', 'mailpoet-bounce-handler' ); ?></h3>
		<ul>
			<?php foreach ( $results['soft'] as $bounce ) : ?>
			<li><?php echo esc_html( $bounce['email'] ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>

		<hr>
		<p style="font-size: 12px; color: #999;">
			<?php esc_html_e( 'MailPoet Bounce Handler — Planea Soluciones', 'mailpoet-bounce-handler' ); ?>
		</p>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}

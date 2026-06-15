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
	 * @param array $results Resultados del procesado: hard, soft, soft_threshold_reached.
	 */
	public function send_summary( array $results ): void {
		$hard_count      = count( $results['hard'] ?? array() );
		$soft_count      = count( $results['soft'] ?? array() );
		$threshold_count = count( $results['soft_threshold_reached'] ?? array() );
		$policy_count    = count( $results['policy'] ?? array() );

		if ( 0 === $hard_count && 0 === $soft_count && 0 === $policy_count ) {
			return;
		}

		$to = get_option( 'mbh_notify_email', '' );
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			// translators: %1$d hard bounces, %2$d soft bounces, %3$d policy bounces.
			__( '[MailPoet Bounce Handler] %1$d hard, %2$d soft, %3$d policy bounces procesados', 'mailpoet-bounce-handler' ),
			$hard_count,
			$soft_count,
			$policy_count
		);

		$body = $this->build_email_body( $results, $hard_count, $soft_count, $threshold_count, $policy_count );

		wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Construye el cuerpo HTML del email de resumen.
	 *
	 * @param array $results         Resultados del procesado.
	 * @param int   $hard_count      Número de hard bounces.
	 * @param int   $soft_count      Número de soft bounces.
	 * @param int   $threshold_count Soft bounces que alcanzaron el umbral.
	 * @param int   $policy_count    Número de policy bounces.
	 * @return string HTML del cuerpo.
	 */
	private function build_email_body( array $results, int $hard_count, int $soft_count, int $threshold_count, int $policy_count = 0 ): string {
		$site_name = get_bloginfo( 'name' );
		$date      = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		ob_start();
		?>
		<html>
		<body style="font-family: sans-serif; color: #333;">
		<h2>
		<?php
		/* translators: %s: nombre del sitio web */
		echo esc_html( sprintf( __( 'Bounce Handler — %s', 'mailpoet-bounce-handler' ), $site_name ) );
		?>
		</h2>
		<p><?php echo esc_html( $date ); ?></p>

		<h3><?php esc_html_e( 'Resumen', 'mailpoet-bounce-handler' ); ?></h3>
		<ul>
			<li>
			<?php
			/* translators: %d: número de hard bounces */
			echo esc_html( sprintf( __( 'Hard bounces: %d', 'mailpoet-bounce-handler' ), $hard_count ) );
			?>
			</li>
			<li>
			<?php
			/* translators: %d: número de soft bounces */
			echo esc_html( sprintf( __( 'Soft bounces: %d', 'mailpoet-bounce-handler' ), $soft_count ) );
			?>
			</li>
			<?php if ( $threshold_count > 0 ) : ?>
			<li>
				<?php
				/* translators: %d: número de soft bounces que alcanzaron el umbral */
				echo esc_html( sprintf( __( 'Soft → marcados como bounced (umbral alcanzado): %d', 'mailpoet-bounce-handler' ), $threshold_count ) );
				?>
			</li>
			<?php endif; ?>
			<?php if ( $policy_count > 0 ) : ?>
			<li style="color:#b32d2e;font-weight:bold">
				<?php
				/* translators: %d: número de policy bounces detectados */
				echo esc_html( sprintf( __( '⚠ Policy/blacklist bounces (servidor bloqueado): %d — suscriptores NO marcados', 'mailpoet-bounce-handler' ), $policy_count ) );
				?>
			</li>
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

		<?php if ( ! empty( $results['policy'] ) ) : ?>
		<h3 style="color:#b32d2e"><?php esc_html_e( '⚠ Policy/blacklist bounces — revisar reputación del servidor', 'mailpoet-bounce-handler' ); ?></h3>
		<p><?php esc_html_e( 'Estos emails fueron rechazados por política o reputación. Los suscriptores NO han sido marcados como bounced. Comprueba si tu servidor de envío está en alguna blacklist.', 'mailpoet-bounce-handler' ); ?></p>
		<ul>
			<?php foreach ( $results['policy'] as $bounce ) : ?>
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

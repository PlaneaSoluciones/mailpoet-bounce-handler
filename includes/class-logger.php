<?php
/**
 * Gestión del log de bounces en base de datos.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD sobre {prefix}mbh_log y {prefix}mbh_soft_counts.
 */
class MBH_Logger {

	/**
	 * Registra una entrada de bounce procesado.
	 *
	 * @param array $data Datos del bounce: email, bounce_type, soft_count, action_taken,
	 *                    subscriber_id, raw_subject, diagnostic_code.
	 * @return int|false ID insertado o false en caso de error.
	 */
	public function log_bounce( array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'mbh_log';

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'processed_at'    => current_time( 'mysql' ),
				'email'           => sanitize_email( $data['email'] ),
				'bounce_type'     => $data['bounce_type'],
				'soft_count'      => (int) ( $data['soft_count'] ?? 0 ),
				'action_taken'    => sanitize_text_field( $data['action_taken'] ?? '' ),
				'subscriber_id'   => isset( $data['subscriber_id'] ) ? (int) $data['subscriber_id'] : null,
				'raw_subject'     => sanitize_text_field( $data['raw_subject'] ?? '' ),
				'diagnostic_code' => isset( $data['diagnostic_code'] ) ? sanitize_text_field( $data['diagnostic_code'] ) : null,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Incrementa el contador de soft bounces de un email y retorna el nuevo valor.
	 *
	 * @param string $email Dirección de correo.
	 * @return int Nuevo contador.
	 */
	public function increment_soft_count( string $email ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mbh_soft_counts';
		$email = sanitize_email( $email );
		$now   = current_time( 'mysql' );

		$existing = $this->get_soft_count( $email );

		if ( 0 === $existing ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				array(
					'email'      => $email,
					'count'      => 1,
					'first_seen' => $now,
					'last_seen'  => $now,
				),
				array( '%s', '%d', '%s', '%s' )
			);
			return 1;
		}

		$new_count = $existing + 1;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'count'     => $new_count,
				'last_seen' => $now,
			),
			array( 'email' => $email ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		return $new_count;
	}

	/**
	 * Retorna el contador de soft bounces actual para un email.
	 *
	 * @param string $email Dirección de correo.
	 * @return int 0 si no existe registro.
	 */
	public function get_soft_count( string $email ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'mbh_soft_counts';
		$email = sanitize_email( $email );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT count FROM `{$table}` WHERE email = %s", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return (int) $count;
	}

	/**
	 * Elimina el registro de soft count de un email (tras marcarlo como bounced).
	 *
	 * @param string $email Dirección de correo.
	 */
	public function reset_soft_count( string $email ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'mbh_soft_counts',
			array( 'email' => sanitize_email( $email ) ),
			array( '%s' )
		);
	}

	/**
	 * Retorna entradas del log con filtros opcionales.
	 *
	 * @param array $filters  Filtros: 'bounce_type', 'date_from', 'date_to'.
	 * @param int   $page     Página actual (1-based).
	 * @param int   $per_page Resultados por página.
	 * @return array{items: array, total: int}
	 */
	public function get_logs( array $filters = array(), int $page = 1, int $per_page = 25 ): array {
		global $wpdb;

		$table  = $wpdb->prefix . 'mbh_log';
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['bounce_type'] ) && in_array( $filters['bounce_type'], array( 'hard', 'soft', 'policy' ), true ) ) {
			$where[]  = 'bounce_type = %s';
			$params[] = $filters['bounce_type'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'processed_at >= %s';
			$params[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'processed_at <= %s';
			$params[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$base_query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY processed_at DESC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results(
				$wpdb->prepare( $base_query . ' LIMIT %d OFFSET %d', array_merge( $params, array( $per_page, $offset ) ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$items = $wpdb->get_results(
				$wpdb->prepare( $base_query . ' LIMIT %d OFFSET %d', $per_page, $offset ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
		}

		return array(
			'items' => ! empty( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Elimina entradas de log según las reglas de retención configuradas.
	 *
	 * Aplica en orden: primero purga por antigüedad (si days > 0), después por
	 * número máximo de filas (si max_rows > 0), borrando las más antiguas.
	 */
	public function purge_old_logs(): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'mbh_log';
		$days     = (int) get_option( 'mbh_log_retention_days', 0 );
		$max_rows = (int) get_option( 'mbh_log_max_rows', 10000 );

		if ( $days > 0 ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE processed_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$days
				)
			);
		}

		if ( $max_rows > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			if ( $total > $max_rows ) {
				$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"DELETE FROM `{$table}` ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$total - $max_rows
					)
				);
			}
		}
	}
}

<?php
/**
 * Vista del log de bounces con filtros, paginación, ordenación y export CSV.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logger   = new MBH_Logger();
$updater  = new MBH_MailPoet_Updater();
$mbh_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$filters  = array(
	'bounce_type' => isset( $_GET['bounce_type'] ) ? sanitize_text_field( wp_unslash( $_GET['bounce_type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',         // phpcs:ignore WordPress.Security.NonceVerification.Recommended
);

$allowed_orderby = array( 'processed_at', 'email', 'bounce_type', 'soft_count' );
$cur_orderby     = ( isset( $_GET['orderby'] ) && in_array( sanitize_key( wp_unslash( $_GET['orderby'] ) ), $allowed_orderby, true ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	? sanitize_key( wp_unslash( $_GET['orderby'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	: 'processed_at';
$cur_order       = ( isset( $_GET['order'] ) && 'asc' === $_GET['order'] ) ? 'asc' : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$mbh_per_page = 25;
$data         = $logger->get_logs( $filters, $mbh_page, $mbh_per_page, $cur_orderby, strtoupper( $cur_order ) );
$items        = $data['items'];
$total        = $data['total'];
$mbh_pages    = (int) ceil( $total / $mbh_per_page );

$base_url     = admin_url( 'admin.php?page=mbh-log' );
$export_nonce = wp_create_nonce( 'mbh_export_csv' );
$export_url   = add_query_arg(
	array_merge(
		$filters,
		array(
			'page'           => 'mbh-log',
			'mbh_export_csv' => '1',
			'_wpnonce'       => $export_nonce,
		)
	),
	admin_url( 'admin.php' )
);

$action_nonce = wp_create_nonce( 'mbh_change_subscriber_status' );

/**
 * Genera un <th> con enlace de ordenación estilo WP_List_Table.
 *
 * @param string $label       Texto visible.
 * @param string $col         Nombre de la columna en BD.
 * @param string $cur_orderby Columna actualmente ordenada.
 * @param string $cur_order   Dirección actual ('asc'|'desc').
 * @param string $base_url    URL base de la página.
 * @param string $width       Valor CSS de width (opcional).
 * @return string HTML del <th>.
 */
$mbh_th = static function ( string $label, string $col, string $cur_orderby, string $cur_order, string $base_url, string $width = '' ): string {
	$is_active  = $col === $cur_orderby;
	$next_order = ( $is_active && 'asc' === $cur_order ) ? 'desc' : 'asc';
	$class      = 'manage-column column-' . $col . ( $is_active ? " sorted {$cur_order}" : ' sortable desc' );
	$href       = add_query_arg(
		array(
			'orderby' => $col,
			'order'   => $next_order,
			'paged'   => '1',
		),
		$base_url
	);
	$style      = $width ? ' style="width:' . esc_attr( $width ) . '"' : '';

	return '<th scope="col" class="' . esc_attr( $class ) . '"' . $style . '>'
		. '<a href="' . esc_url( $href ) . '">'
		. '<span>' . esc_html( $label ) . '</span>'
		. '<span class="sorting-indicators">'
		. '<span class="sorting-indicator asc" aria-hidden="true"></span>'
		. '<span class="sorting-indicator desc" aria-hidden="true"></span>'
		. '</span></a></th>';
};
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bounce Handler — Log', 'mailpoet-bounce-handler' ); ?></h1>

	<!-- Filtros -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:15px;">
		<input type="hidden" name="page" value="mbh-log">
		<label for="mbh-filter-type"><?php esc_html_e( 'Tipo:', 'mailpoet-bounce-handler' ); ?></label>
		<select id="mbh-filter-type" name="bounce_type">
			<option value=""><?php esc_html_e( 'Todos', 'mailpoet-bounce-handler' ); ?></option>
			<option value="hard" <?php selected( $filters['bounce_type'], 'hard' ); ?>>Hard</option>
			<option value="soft" <?php selected( $filters['bounce_type'], 'soft' ); ?>>Soft</option>
			<option value="policy" <?php selected( $filters['bounce_type'], 'policy' ); ?>>Policy</option>
		</select>
		&nbsp;
		<label for="mbh-filter-from"><?php esc_html_e( 'Desde:', 'mailpoet-bounce-handler' ); ?></label>
		<input type="date" id="mbh-filter-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		&nbsp;
		<label for="mbh-filter-to"><?php esc_html_e( 'Hasta:', 'mailpoet-bounce-handler' ); ?></label>
		<input type="date" id="mbh-filter-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		&nbsp;
		<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'mailpoet-bounce-handler' ); ?></button>
		<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'mailpoet-bounce-handler' ); ?></a>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary" style="margin-left:20px;">
			<?php esc_html_e( 'Exportar CSV', 'mailpoet-bounce-handler' ); ?>
		</a>
	</form>

	<p>
	<?php
	/* translators: %d: número de entradas en el log */
	echo esc_html( sprintf( _n( '%d entrada', '%d entradas', $total, 'mailpoet-bounce-handler' ), $total ) );
	?>
	</p>

	<?php if ( empty( $items ) ) : ?>
	<p><?php esc_html_e( 'No hay registros para los filtros seleccionados.', 'mailpoet-bounce-handler' ); ?></p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<?php
				echo wp_kses_post( $mbh_th( __( 'Fecha', 'mailpoet-bounce-handler' ), 'processed_at', $cur_orderby, $cur_order, $base_url, '130px' ) );
				echo wp_kses_post( $mbh_th( __( 'Email', 'mailpoet-bounce-handler' ), 'email', $cur_orderby, $cur_order, $base_url ) );
				echo wp_kses_post( $mbh_th( __( 'Tipo', 'mailpoet-bounce-handler' ), 'bounce_type', $cur_orderby, $cur_order, $base_url, '60px' ) );
				echo wp_kses_post( $mbh_th( __( 'Intentos soft', 'mailpoet-bounce-handler' ), 'soft_count', $cur_orderby, $cur_order, $base_url, '80px' ) );
				?>
				<th style="width:160px"><?php esc_html_e( 'Acción', 'mailpoet-bounce-handler' ); ?></th>
				<th style="width:40px;text-align:center"><?php esc_html_e( 'Acciones', 'mailpoet-bounce-handler' ); ?></th>
				<th><?php esc_html_e( 'Diagnóstico', 'mailpoet-bounce-handler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $row ) : ?>
				<?php
				// Una sola llamada por fila: obtiene id y status actuales en MailPoet.
				$sub_data      = $updater->get_subscriber( $row->email );
				$subscriber_id = $sub_data
				? (int) $sub_data['id']
				: ( ! empty( $row->subscriber_id ) ? (int) $row->subscriber_id : null );
				$sub_status    = $sub_data['status'] ?? null;
				?>
			<tr>
				<td><?php echo esc_html( $row->processed_at ); ?></td>
				<td>
					<?php
					if ( $subscriber_id ) {
						$edit_url = admin_url( 'admin.php?page=mailpoet-subscribers#/edit-subscriber/' . $subscriber_id );
						echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $row->email ) . '</a>';
					} else {
						echo esc_html( $row->email );
					}
					?>
				</td>
				<td>
					<?php
					$type_colors = array(
						'hard'   => '#d63638',
						'soft'   => '#dba617',
						'policy' => '#9b59b6',
					);
					$type_color  = $type_colors[ $row->bounce_type ] ?? '#666';
					?>
					<span style="color: <?php echo esc_attr( $type_color ); ?>; font-weight:bold">
						<?php echo esc_html( $row->bounce_type ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $row->soft_count ); ?></td>
				<td><?php echo esc_html( $row->action_taken ); ?></td>
				<td style="text-align:center">
					<?php if ( 'bounced' === $sub_status || in_array( $sub_status, array( 'subscribed', 'inactive', 'unconfirmed' ), true ) ) : ?>
					<div class="mbh-row-actions">
						<button type="button" class="button-link mbh-actions-toggle" aria-haspopup="true" aria-expanded="false">
							<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Acciones', 'mailpoet-bounce-handler' ); ?></span>
						</button>
						<div class="mbh-actions-dropdown" hidden role="menu">
							<?php if ( 'bounced' === $sub_status ) : ?>
							<button type="button" class="mbh-dropdown-item mbh-action-btn" role="menuitem"
									data-email="<?php echo esc_attr( $row->email ); ?>"
									data-action-type="reactivate"
									data-nonce="<?php echo esc_attr( $action_nonce ); ?>">
								<?php esc_html_e( 'Reactivar', 'mailpoet-bounce-handler' ); ?>
							</button>
							<?php else : ?>
							<button type="button" class="mbh-dropdown-item mbh-action-btn is-destructive" role="menuitem"
									data-email="<?php echo esc_attr( $row->email ); ?>"
									data-action-type="bounce"
									data-nonce="<?php echo esc_attr( $action_nonce ); ?>">
								<?php esc_html_e( 'Marcar rebotado', 'mailpoet-bounce-handler' ); ?>
							</button>
							<?php endif; ?>
						</div>
					</div>
					<?php endif; ?>
				</td>
				<td>
					<?php
					$diag = $row->diagnostic_code ?? '';
					if ( mb_strlen( $diag ) > 80 ) {
						echo '<span title="' . esc_attr( $diag ) . '">' . esc_html( mb_substr( $diag, 0, 80 ) ) . '…</span>';
					} else {
						echo esc_html( $diag );
					}
					?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Paginación -->
		<?php if ( $mbh_pages > 1 ) : ?>
	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<?php
			$pagination_args = array_merge( $filters, array( 'page' => 'mbh-log' ) );
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php' ) ),
						'format'    => '',
						'add_args'  => $pagination_args,
						'current'   => $mbh_page,
						'total'     => $mbh_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			?>
		</div>
	</div>
	<?php endif; ?>

	<?php endif; ?>
</div>

<?php
/**
 * Vista del log de bounces con filtros, paginación y export CSV.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logger   = new MBH_Logger();
$mbh_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$filters  = array(
	'bounce_type' => isset( $_GET['bounce_type'] ) ? sanitize_text_field( wp_unslash( $_GET['bounce_type'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_from'   => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	'date_to'     => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',         // phpcs:ignore WordPress.Security.NonceVerification.Recommended
);

$mbh_per_page = 25;
$data         = $logger->get_logs( $filters, $mbh_page, $mbh_per_page );
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
				<th style="width:140px"><?php esc_html_e( 'Fecha', 'mailpoet-bounce-handler' ); ?></th>
				<th><?php esc_html_e( 'Email', 'mailpoet-bounce-handler' ); ?></th>
				<th style="width:70px"><?php esc_html_e( 'Tipo', 'mailpoet-bounce-handler' ); ?></th>
				<th style="width:80px"><?php esc_html_e( 'Intentos soft', 'mailpoet-bounce-handler' ); ?></th>
				<th><?php esc_html_e( 'Acción', 'mailpoet-bounce-handler' ); ?></th>
				<th><?php esc_html_e( 'Estado anterior', 'mailpoet-bounce-handler' ); ?></th>
				<th><?php esc_html_e( 'Estado posterior', 'mailpoet-bounce-handler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $row ) : ?>
			<tr>
				<td><?php echo esc_html( $row->processed_at ); ?></td>
				<td><?php echo esc_html( $row->email ); ?></td>
				<td>
					<span style="color: <?php echo 'hard' === $row->bounce_type ? '#d63638' : '#dba617'; ?>; font-weight:bold">
						<?php echo esc_html( $row->bounce_type ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $row->soft_count ); ?></td>
				<td><?php echo esc_html( $row->action_taken ); ?></td>
				<td><?php echo esc_html( $row->status_before ); ?></td>
				<td><?php echo esc_html( $row->status_after ); ?></td>
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

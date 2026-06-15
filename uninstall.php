<?php
/**
 * Limpieza completa al desinstalar el plugin.
 *
 * Solo se ejecuta cuando el usuario desinstala el plugin desde el panel de WordPress.
 * NO se ejecuta al desactivarlo.
 *
 * @package MailPoet_Bounce_Handler
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Eliminar tablas.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mbh_log`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}mbh_soft_counts`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Eliminar opciones.
$options = array(
	'mbh_imap_host',
	'mbh_imap_port',
	'mbh_imap_ssl',
	'mbh_imap_protocol',
	'mbh_imap_user',
	'mbh_imap_pass',
	'mbh_soft_threshold',
	'mbh_cron_frequency',
	'mbh_notify_email',
	'mbh_log_retention_days',
	'mbh_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Limpiar evento cron si quedase pendiente.
wp_clear_scheduled_hook( 'mbh_process_bounces' );

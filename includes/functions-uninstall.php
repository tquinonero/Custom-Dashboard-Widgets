<?php
/**
 * Standalone uninstall function, extracted for testability.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perform all CDW plugin cleanup on uninstall.
 *
 * Respects the cdw_delete_on_uninstall option (defaults true so a fresh-
 * install uninstall is always clean even if the option was never set).
 *
 * @return void
 */
function cdw_do_uninstall() {
	global $wpdb;

	$delete_data = get_option( 'cdw_delete_on_uninstall', true );

	if ( ! $delete_data ) {
		return;
	}

	// --- Options ---
	$options = array(
		'cdw_support_email',
		'cdw_docs_url',
		'cdw_font_size',
		'cdw_bg_color',
		'cdw_header_bg_color',
		'cdw_header_text_color',
		'cdw_cli_enabled',
		'cdw_remove_default_widgets',
		'cdw_delete_on_uninstall',
		'cdw_db_version',
		// Legacy option names (v1/v2 backwards compatibility).
		'custom_dashboard_widget_email',
		'custom_dashboard_widget_docs_url',
		'custom_dashboard_widget_font_size',
		'custom_dashboard_widget_background_color',
		'custom_dashboard_widget_header_background_color',
		'custom_dashboard_widget_header_text_color',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// --- Transients (named) ---
	delete_transient( 'cdw_stats_cache' );

	// --- Transients (pattern-matched via SQL) ---
	$patterns = array(
		$wpdb->esc_like( '_transient_cdw_media_cache_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_media_cache_' ) . '%',
		$wpdb->esc_like( '_transient_cdw_posts_cache_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_posts_cache_' ) . '%',
		$wpdb->esc_like( '_transient_cdw_cli_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_cli_rate_' ) . '%',
		$wpdb->esc_like( 'cdw_cli_rate_start_' ) . '%',
	);

	foreach ( $patterns as $pattern ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);
	}

	// --- Custom DB table ---
	$table_name = $wpdb->prefix . 'cdw_cli_logs';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is prefix + constant string, safe; DROP TABLE is intentional on uninstall.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bulk delete on uninstall, performance is acceptable.
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'cdw_tasks' ), array( '%s' ) );
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bulk delete on uninstall, performance is acceptable.
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'cdw_cli_history' ), array( '%s' ) );
}

<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This file should be at the root of your plugin.
 *
 * - Two methods exist for uninstalling: `register_uninstall_hook()`
 *   and `uninstall.php`. See the documentation below for choosing.
 *
 * - For `register_uninstall_hook()`, the plugin should have a
 *   function attached to this hook to perform cleanup.
 *
 * - For `uninstall.php`, all cleanup tasks should be performed
 *   directly in that file.
 *
 * @link https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Respect the admin's choice. Default true so a clean uninstall works
// even before the option has been explicitly set (e.g. fresh install → delete).
$delete_data = get_option( 'cdw_delete_on_uninstall', true );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

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
	// Legacy option names (v1/v2 backwards compatibility)
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
// $wpdb->prepare() handles quoting; esc_like() escapes the LIKE prefix wildcards.
$patterns = array(
	$wpdb->esc_like( '_transient_cdw_media_cache_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_cdw_media_cache_' ) . '%',
	$wpdb->esc_like( '_transient_cdw_posts_cache_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_cdw_posts_cache_' ) . '%',
	$wpdb->esc_like( '_transient_cdw_cli_rate_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_cdw_cli_rate_' ) . '%',
	// Legacy: rate-limit start options written by versions < 3.0.1.
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
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe (prefix + constant string)
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

// --- User meta ---
// Direct SQL bulk-delete avoids loading all user IDs into memory.
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'cdw_tasks' ),       array( '%s' ) );
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'cdw_cli_history' ), array( '%s' ) );

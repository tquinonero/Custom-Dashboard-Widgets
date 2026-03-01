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

// Define whether to remove all data (can be made configurable)
$remove_all_data = true;

if ( $remove_all_data ) {
	global $wpdb;

	// Delete all plugin options (both new and legacy naming)
	$options = array(
		// New option names
		'cdw_support_email',
		'cdw_docs_url',
		'cdw_font_size',
		'cdw_bg_color',
		'cdw_header_bg_color',
		'cdw_header_text_color',
		'cdw_cli_enabled',
		'cdw_remove_default_widgets',
		// Legacy option names (for backwards compatibility)
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

	// Delete transients (stats cache and dynamic media/posts caches)
	delete_transient( 'cdw_stats_cache' );

	// Delete dynamic transient cache keys using SQL for pattern matching
	// Note: Using direct string insertion (safe because we're using esc_like)
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '" 
		. $wpdb->esc_like( '_transient_cdw_media_cache_' ) . "%'"
	);

	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '" 
		. $wpdb->esc_like( '_transient_cdw_posts_cache_' ) . "%'"
	);

	// Also delete timeout transients (WordPress stores these separately)
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '" 
		. $wpdb->esc_like( '_transient_timeout_cdw_media_cache_' ) . "%'"
	);

	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '" 
		. $wpdb->esc_like( '_transient_timeout_cdw_posts_cache_' ) . "%'"
	);

	// Drop the custom audit log table
	$table_name = $wpdb->prefix . 'cdw_cli_logs';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// Delete all user meta created by this plugin
	// Get all users to clean up their meta
	$users = get_users( array( 'fields' => 'ID' ) );
	foreach ( $users as $user_id ) {
		delete_user_meta( $user_id, 'cdw_tasks' );
		delete_user_meta( $user_id, 'cdw_cli_history' );
	}
}

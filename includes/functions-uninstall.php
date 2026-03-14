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
		'cdw_button_bg_color',
		'cdw_button_text_color',
		'cdw_cli_enabled',
		'cdw_floating_enabled',
		'cdw_remove_default_widgets',
		'cdw_delete_on_uninstall',
		'cdw_db_version',
		// MCP / welcome options.
		'cdw_mcp_public',
		'cdw_user_type',
		'cdw_welcome_notice_dismissed',
		// AI options.
		'cdw_ai_enabled',
		'cdw_ai_execution_mode',
		'cdw_ai_custom_system_prompt',
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
	delete_transient( 'cdw_admin_menu_cache' );

	// --- Transients (pattern-matched via SQL) ---
	$patterns = array(
		$wpdb->esc_like( '_transient_cdw_media_cache_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_media_cache_' ) . '%',
		$wpdb->esc_like( '_transient_cdw_posts_cache_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_posts_cache_' ) . '%',
		$wpdb->esc_like( '_transient_cdw_cli_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_cli_rate_' ) . '%',
		$wpdb->esc_like( 'cdw_cli_rate_start_' ) . '%',
		// AI rate-limit transients.
		$wpdb->esc_like( '_transient_cdw_ai_rate_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cdw_ai_rate_' ) . '%',
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

	// --- User meta (exact keys) ---
	$user_meta_keys = array(
		'cdw_tasks',
		'cdw_cli_history',
		// AI per-user settings.
		'cdw_ai_provider',
		'cdw_ai_model',
		'cdw_ai_execution_mode',
		'cdw_ai_token_usage',
		'cdw_ai_base_url',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bulk delete on uninstall, performance is acceptable.
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ), array( '%s' ) );
	}

	// --- User meta (pattern-matched via SQL) — encrypted AI API keys ---
	// Keys are stored as cdw_ai_api_key_{provider} (one row per user per provider).
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'cdw_ai_api_key_' ) . '%'
		)
	);
}

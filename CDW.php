<?php
/**
 * Plugin Name: Custom Dashboard Widgets
 * Description: Modernized WordPress dashboard widgets with React and REST API
 * Author: Toni Quiñonero
 * Author URI: https://tquinonero.github.io
 * Version: 3.0.0
 * License: GPLv3
 * Text Domain: cdw
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Tested up to: 6.9
 * Domain Path: /languages
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CDW_VERSION' ) ) {
	define( 'CDW_VERSION', '3.0.0' );
}
if ( ! defined( 'CDW_PLUGIN_DIR' ) ) {
	define( 'CDW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CDW_PLUGIN_URL' ) ) {
	define( 'CDW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

register_activation_hook( __FILE__, 'CDW_activate' );
register_deactivation_hook( __FILE__, 'CDW_deactivate' );

/**
 * Fired when the plugin is activated.
 *
 * Creates the audit log table and seeds default option values.
 *
 * @return void
 */
function CDW_activate() {
	require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
	$cli_service = new CDW_CLI_Service();
	$cli_service->create_audit_log_table();
	update_option( 'cdw_db_version', CDW_CLI_Service::DB_VERSION, false );
	update_option( 'cdw_cli_enabled', true, false );
	update_option( 'cdw_remove_default_widgets', true, false );
	update_option( 'cdw_delete_on_uninstall', true, false );
}

/**
 * Fired when the plugin is deactivated.
 *
 * Flushes rewrite rules. Data is intentionally preserved —
 * only uninstall removes data.
 *
 * @return void
 */
function CDW_deactivate() {
	flush_rewrite_rules();
}

require_once CDW_PLUGIN_DIR . 'includes/class-cdw-loader.php';

/**
 * Core plugin class.
 *
 * Singleton that boots the loader on first access.
 *
 * @package CDW
 */
class CDW_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var CDW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin loader.
	 *
	 * @var CDW_Loader
	 */
	private $loader;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return CDW_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — loads text domain and boots the plugin loader.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->loader = new CDW_Loader();
		$this->loader->run();
	}
}

/**
 * Returns the singleton CDW_Plugin instance.
 *
 * @return CDW_Plugin
 */
function CDW() {
	return CDW_Plugin::get_instance();
}

CDW();

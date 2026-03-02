<?php
/**
 * Plugin Name: Custom Dashboard Widgets (v2)
 * Description: Modernized WordPress dashboard widgets with React and REST API
 * Author: Toni Quiñonero
 * Version: 3.0.0
 * License: GPLv3
 * Text Domain: cdw
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Tested up to: 6.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CDW_VERSION', '3.0.0' );
define( 'CDW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CDW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, 'CDW_activate' );
register_deactivation_hook( __FILE__, 'CDW_deactivate' );

function CDW_activate() {
    require_once CDW_PLUGIN_DIR . 'includes/class-cdw-rest-api.php';
    $rest_api = new CDW_REST_API();
    $rest_api->create_audit_log_table();
    update_option( 'cdw_db_version', CDW_REST_API::DB_VERSION );
    update_option( 'cdw_cli_enabled', true );
    update_option( 'cdw_remove_default_widgets', true );
}

function CDW_deactivate() {
    flush_rewrite_rules();
}

require_once CDW_PLUGIN_DIR . 'includes/class-cdw-loader.php';

class CDW_Plugin {
    private static $instance = null;
    private $loader;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        load_plugin_textdomain( 'cdw', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        $this->loader = new CDW_Loader();
        $this->loader->run();
    }
}

function CDW() {
    return CDW_Plugin::get_instance();
}

CDW();

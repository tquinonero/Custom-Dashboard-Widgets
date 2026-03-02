<?php
/**
 * Stub for wp-admin/includes/plugin.php
 * Functions conditionally defined so Brain\Monkey mocks take priority.
 */

if ( ! function_exists( 'get_plugins' ) ) {
    function get_plugins( $plugin_folder = '' ) {
        return array();
    }
}

if ( ! function_exists( 'get_plugin_data' ) ) {
    function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {
        return array( 'Name' => '', 'Version' => '', 'Author' => '', 'Description' => '' );
    }
}

if ( ! function_exists( 'is_plugin_active' ) ) {
    function is_plugin_active( $plugin ) {
        return false;
    }
}

if ( ! function_exists( 'activate_plugin' ) ) {
    function activate_plugin( $plugin, $redirect = '', $network_wide = false, $silent = false ) {
        return null;
    }
}

if ( ! function_exists( 'deactivate_plugins' ) ) {
    function deactivate_plugins( $plugins, $silent = false, $network_deactivation = null ) {
        return null;
    }
}

if ( ! function_exists( 'delete_plugins' ) ) {
    function delete_plugins( $plugins ) {
        return true;
    }
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! function_exists( 'plugins_api' ) ) {
    function plugins_api( $action, $args = array() ) {
        return new \stdClass();
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

class CDW_CLI_Controller extends CDW_Base_Controller {
    private $cli_service;

    public function __construct() {
        $this->cli_service = new CDW_CLI_Service();
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/cli/history', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cli_history' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/cli/history', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'clear_cli_history' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/cli/commands', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cli_commands' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/cli/execute', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'execute_cli_command' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    public function get_cli_history() {
        $user_id = get_current_user_id();
        $history = $this->cli_service->get_history( $user_id );

        return rest_ensure_response( is_array( $history ) ? $history : array() );
    }

    public function clear_cli_history() {
        $user_id = get_current_user_id();
        $this->cli_service->clear_history( $user_id );

        return rest_ensure_response( array( 'success' => true, 'cleared' => true ) );
    }

    public function get_cli_commands() {
        return rest_ensure_response( $this->get_command_definitions() );
    }

    public function execute_cli_command( WP_REST_Request $request ) {
        $command = $request->get_param( 'command' );
        $user_id = get_current_user_id();

        $result = $this->cli_service->execute( $command, $user_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        delete_transient( 'cdw_stats_cache' );

        return rest_ensure_response( $result );
    }

    private function get_command_definitions() {
        return array(
            array(
                'category' => 'Plugin Management',
                'commands' => array(
                    array( 'name' => 'plugin list', 'description' => 'List all plugins' ),
                    array( 'name' => 'plugin status <slug>', 'description' => 'Show plugin status' ),
                    array( 'name' => 'plugin install <slug>', 'description' => 'Install a plugin' ),
                    array( 'name' => 'plugin activate <slug>', 'description' => 'Activate a plugin' ),
                    array( 'name' => 'plugin deactivate <slug>', 'description' => 'Deactivate a plugin' ),
                    array( 'name' => 'plugin update <slug>', 'description' => 'Update a plugin' ),
                    array( 'name' => 'plugin delete <slug>', 'description' => 'Delete a plugin (requires --force)' ),
                ),
            ),
            array(
                'category' => 'Theme Management',
                'commands' => array(
                    array( 'name' => 'theme list', 'description' => 'List all themes' ),
                    array( 'name' => 'theme status <slug>', 'description' => 'Show theme status' ),
                    array( 'name' => 'theme activate <slug>', 'description' => 'Activate a theme' ),
                ),
            ),
            array(
                'category' => 'User Management',
                'commands' => array(
                    array( 'name' => 'user list', 'description' => 'List all users' ),
                    array( 'name' => 'user create <user> <email> <role>', 'description' => 'Create user' ),
                ),
            ),
            array(
                'category' => 'Cache',
                'commands' => array(
                    array( 'name' => 'cache flush', 'description' => 'Flush all cache' ),
                ),
            ),
            array(
                'category' => 'Database',
                'commands' => array(
                    array( 'name' => 'db size', 'description' => 'Show database size' ),
                    array( 'name' => 'db tables', 'description' => 'List all tables' ),
                ),
            ),
            array(
                'category' => 'Options',
                'commands' => array(
                    array( 'name' => 'option get <name>', 'description' => 'Get option value' ),
                    array( 'name' => 'option list', 'description' => 'List CDW options' ),
                ),
            ),
            array(
                'category' => 'Cron',
                'commands' => array(
                    array( 'name' => 'cron list', 'description' => 'List scheduled cron events' ),
                ),
            ),
            array(
                'category' => 'Site',
                'commands' => array(
                    array( 'name' => 'site info', 'description' => 'Show site info' ),
                    array( 'name' => 'site status', 'description' => 'Show site status' ),
                ),
            ),
        );
    }
}

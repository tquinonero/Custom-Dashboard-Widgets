<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CDW_REST_API {
    const DB_VERSION = '1.1';

    private $controllers = array();

    public function register() {
        $this->load_controllers();
        $this->register_routes();
        add_action( 'init', array( $this, 'ensure_audit_table' ), 5 );
    }

    private function load_controllers() {
        require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';
        require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';
        require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-stats-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-media-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-posts-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-users-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-updates-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-tasks-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-settings-controller.php';
        require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-cli-controller.php';

        $this->controllers = array(
            new CDW_Stats_Controller(),
            new CDW_Media_Controller(),
            new CDW_Posts_Controller(),
            new CDW_Users_Controller(),
            new CDW_Updates_Controller(),
            new CDW_Tasks_Controller(),
            new CDW_Settings_Controller(),
            new CDW_CLI_Controller(),
        );
    }

    private function register_routes() {
        add_action( 'rest_api_init', array( $this, 'register_controller_routes' ) );
    }

    public function register_controller_routes() {
        foreach ( $this->controllers as $controller ) {
            if ( method_exists( $controller, 'register_routes' ) ) {
                $controller->register_routes();
            }
        }
    }

    public function ensure_audit_table() {
        if ( get_option( 'cdw_db_version' ) === self::DB_VERSION ) {
            return;
        }

        require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
        $cli_service = new CDW_CLI_Service();
        $cli_service->create_audit_log_table();
        update_option( 'cdw_db_version', self::DB_VERSION );
    }
}

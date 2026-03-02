<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';

class CDW_Stats_Controller extends CDW_Base_Controller {
    private $stats_service;

    public function __construct() {
        $this->stats_service = new CDW_Stats_Service();
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
        ) );
    }

    public function get_stats() {
        $stats = $this->stats_service->get_stats();
        return rest_ensure_response( $stats );
    }
}

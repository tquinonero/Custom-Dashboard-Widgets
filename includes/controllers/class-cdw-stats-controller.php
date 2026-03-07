<?php
/**
 * Stats REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';

/**
 * Handles GET /cdw/v1/stats — returns site-wide statistics.
 *
 * @package CDW
 */
class CDW_Stats_Controller extends CDW_Base_Controller {
	/**
	 * Stats service instance.
	 *
	 * @var CDW_Stats_Service
	 */
	private $stats_service;

	/**
	 * Constructor — initialises the stats service.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->stats_service = new CDW_Stats_Service();
	}

	/**
	 * Registers the /stats REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_contributor_permission' ),
			)
		);
	}

	/**
	 * Returns cached site statistics.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_stats() {
		$stats = $this->stats_service->get_stats();
		return rest_ensure_response( $stats );
	}
}

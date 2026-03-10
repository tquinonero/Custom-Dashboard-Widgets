<?php
/**
 * AI providers REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

/**
 * Handles CDW REST AI providers endpoint.
 *
 * All routes require the manage_options capability (Administrator).
 *
 * @package CDW
 */
class CDW_AI_Providers_Controller extends CDW_Base_Controller {

	/**
	 * Registers AI providers REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		// List available providers and their models.
		register_rest_route(
			$this->namespace,
			'/ai/providers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_providers' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Returns the list of supported AI providers and their available models.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_providers() {
		$rate_check = $this->check_rate_limit( 'ai_providers_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		return $this->success_response( CDW_AI_Service::get_providers() );
	}
}

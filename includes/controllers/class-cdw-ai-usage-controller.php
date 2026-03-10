<?php
/**
 * AI usage REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-usage-tracker.php';

/**
 * Handles CDW REST AI usage endpoints.
 *
 * All routes require the manage_options capability (Administrator).
 *
 * @package CDW
 */
class CDW_AI_Usage_Controller extends CDW_Base_Controller {

	/**
	 * Registers AI usage REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Retrieve accumulated token usage statistics for the current user.
		register_rest_route(
			$this->namespace,
			'/ai/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_usage' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Reset token usage statistics for the current user.
		register_rest_route(
			$this->namespace,
			'/ai/usage',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'reset_usage' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Returns the accumulated token usage statistics for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_usage() {
		$rate_check = $this->check_rate_limit( 'ai_usage_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );
		return $this->success_response( $settings['usage'] );
	}

	/**
	 * Resets the token usage statistics for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_usage() {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'ai_usage_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id = get_current_user_id();
		CDW_AI_Usage_Tracker::reset_usage( $user_id );
		return $this->success_response( array( 'reset' => true ) );
	}
}

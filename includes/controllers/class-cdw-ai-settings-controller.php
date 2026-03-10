<?php
/**
 * AI settings REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

/**
 * Handles CDW REST AI settings endpoints.
 *
 * All routes require the manage_options capability (Administrator).
 *
 * @package CDW
 */
class CDW_AI_Settings_Controller extends CDW_Base_Controller {

	/**
	 * Registers AI settings REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/ai/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ai_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/ai/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_ai_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'provider'       => array(
						'type'     => 'string',
						'enum'     => array( 'openai', 'anthropic', 'google', 'custom' ),
						'required' => false,
					),
					'model'          => array(
						'type'     => 'string',
						'required' => false,
					),
					'execution_mode' => array(
						'type'     => 'string',
						'enum'     => array( 'confirm', 'auto' ),
						'required' => false,
					),
					'custom_url'     => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Returns the AI settings for the current user.
	 *
	 * The raw API key is never included; has_key (bool) indicates existence.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_ai_settings() {
		$rate_check = $this->check_rate_limit( 'ai_settings_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );
		return $this->success_response( $settings );
	}

	/**
	 * Saves AI settings (provider, model, execution mode, API key) for the current user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_ai_settings( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'ai_settings_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$params  = $request->get_json_params();
		$user_id = get_current_user_id();
		$result  = CDW_AI_Service::save_user_ai_settings( $user_id, $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( array( 'saved' => true ) );
	}
}

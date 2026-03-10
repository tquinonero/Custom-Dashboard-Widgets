<?php
/**
 * AI test REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/helpers/class-cdw-ai-request-helper.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

/**
 * Handles CDW REST AI connection test endpoint.
 *
 * All routes require the manage_options capability (Administrator).
 *
 * @package CDW
 */
class CDW_AI_Test_Controller extends CDW_Base_Controller {

	/**
	 * Registers AI test REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Test whether the saved API key for a provider is valid.
		register_rest_route(
			$this->namespace,
			'/ai/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'provider' => array(
						'type'     => 'string',
						'enum'     => array( 'openai', 'anthropic', 'google', 'custom' ),
						'required' => false,
					),
					'model'    => array(
						'type'     => 'string',
						'required' => false,
					),
					'base_url' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Tests whether the current user's saved API key for a given provider is valid.
	 *
	 * Expects optional JSON body: { provider: string, model: string }
	 * If omitted, uses the user's currently saved provider/model settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_connection( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'ai_test_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$context  = CDW_AI_Request_Helper::resolve_provider_context( $request );
		$user_id  = $context['user_id'];
		$provider = $context['provider'];
		$model    = $context['model'];
		$base_url = $context['base_url'];

		$api_key = CDW_AI_Service::get_decrypted_api_key( $user_id, $provider );
		if ( '' === $api_key ) {
			return $this->error_response(
				sprintf( 'No API key saved for provider "%s".', $provider ),
				400
			);
		}

		$result = CDW_AI_Service::test_api_key( $api_key, $provider, $model, $base_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response(
			array(
				'connected' => true,
				'provider'  => $provider,
				'model'     => $model,
			)
		);
	}
}

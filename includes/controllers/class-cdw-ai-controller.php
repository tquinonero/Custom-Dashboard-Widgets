<?php
/**
 * AI REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

/**
 * Handles CDW REST AI endpoints: settings, chat, providers, test, and usage.
 *
 * All routes require the manage_options capability (Administrator).
 *
 * @package CDW
 */
class CDW_AI_Controller extends CDW_Base_Controller {

	/**
	 * Registers all AI REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Per-user AI settings (provider, model, execution mode, API key).
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
			)
		);

		// Main chat endpoint — runs the agentic loop.
		register_rest_route(
			$this->namespace,
			'/ai/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Execute a single AI-proposed command (used in confirm-first mode).
		register_rest_route(
			$this->namespace,
			'/ai/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'execute_tool' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

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

		// Test whether the saved API key for a provider is valid.
		register_rest_route(
			$this->namespace,
			'/ai/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

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
	}

	// -------------------------------------------------------------------------
	// GET /ai/settings
	// -------------------------------------------------------------------------

	/**
	 * Returns the AI settings for the current user.
	 *
	 * The raw API key is never included; has_key (bool) indicates existence.
	 *
	 * @return WP_REST_Response
	 */
	public function get_ai_settings() {
		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );
		return $this->success_response( $settings );
	}

	// -------------------------------------------------------------------------
	// POST /ai/settings
	// -------------------------------------------------------------------------

	/**
	 * Saves AI settings (provider, model, execution mode, API key) for the current user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_ai_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return $this->error_response( 'Invalid request body.', 400 );
		}

		$user_id = get_current_user_id();
		$result  = CDW_AI_Service::save_user_ai_settings( $user_id, $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( array( 'saved' => true ) );
	}

	// -------------------------------------------------------------------------
	// POST /ai/chat
	// -------------------------------------------------------------------------

	/**
	 * Runs the agentic loop for a single conversation turn.
	 *
	 * Expects JSON body:
	 * {
	 *   message: string,          // new user message (required)
	 *   history: [{role, content}] // prior turns (optional, capped server-side)
	 * }
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function chat( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return $this->error_response( 'Invalid request body.', 400 );
		}

		$message = isset( $params['message'] ) ? trim( (string) $params['message'] ) : '';
		if ( '' === $message ) {
			return $this->error_response( 'Message cannot be empty.', 400 );
		}

		$history = isset( $params['history'] ) && is_array( $params['history'] ) ? $params['history'] : array();

		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );

		// Rate limit check.
		$rate_check = CDW_AI_Service::check_ai_rate_limit( $user_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		// Retrieve decrypted API key.
		$api_key = CDW_AI_Service::get_decrypted_api_key( $user_id, $settings['provider'] );
		if ( '' === $api_key ) {
			return $this->error_response(
				sprintf(
					'No API key found for provider "%s". Please add your key in AI Settings.',
					$settings['provider']
				),
				400
			);
		}

		// Custom system prompt from site-wide option (set in Settings page).
		$custom_prompt = (string) get_option( 'cdw_ai_custom_system_prompt', '' );

		$result = CDW_AI_Service::execute_agentic_loop(
			$message,
			$history,
			$api_key,
			$settings['provider'],
			$settings['model'],
			$user_id,
			$custom_prompt,
			$settings['base_url']
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	// -------------------------------------------------------------------------
	// POST /ai/execute
	// -------------------------------------------------------------------------

	/**
	 * Executes a single AI-proposed tool call (confirm-first mode).
	 *
	 * Expects JSON body:
	 * {
	 *   tool_name: string,
	 *   arguments: object
	 * }
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function execute_tool( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return $this->error_response( 'Invalid request body.', 400 );
		}

		$tool_name = isset( $params['tool_name'] ) ? sanitize_text_field( $params['tool_name'] ) : '';
		if ( '' === $tool_name ) {
			return $this->error_response( 'tool_name is required.', 400 );
		}

		$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		// Validate tool_name against known tools.
		$known_tools = array_column( CDW_AI_Service::get_tool_definitions(), 'name' );
		if ( ! in_array( $tool_name, $known_tools, true ) ) {
			return $this->error_response( 'Unknown tool: ' . $tool_name, 400 );
		}

		$user_id = get_current_user_id();
		$output  = CDW_AI_Service::execute_tool_call( $tool_name, $arguments, $user_id );

		return $this->success_response(
			array(
				'tool_name' => $tool_name,
				'output'    => $output,
			)
		);
	}

	// -------------------------------------------------------------------------
	// GET /ai/providers
	// -------------------------------------------------------------------------

	/**
	 * Returns the list of supported AI providers and their available models.
	 *
	 * @return WP_REST_Response
	 */
	public function get_providers() {
		return $this->success_response( CDW_AI_Service::get_providers() );
	}

	// -------------------------------------------------------------------------
	// POST /ai/test
	// -------------------------------------------------------------------------

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
		$params   = $request->get_json_params();
		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );

		$provider = isset( $params['provider'] ) ? sanitize_text_field( $params['provider'] ) : $settings['provider'];
		$model    = isset( $params['model'] ) ? sanitize_text_field( $params['model'] ) : $settings['model'];
		$base_url = isset( $params['base_url'] ) ? esc_url_raw( $params['base_url'] ) : $settings['base_url'];

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

	// -------------------------------------------------------------------------
	// GET /ai/usage
	// -------------------------------------------------------------------------

	/**
	 * Returns the accumulated token usage statistics for the current user.
	 *
	 * @return WP_REST_Response
	 */
	public function get_usage() {
		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );
		return $this->success_response( $settings['usage'] );
	}
}

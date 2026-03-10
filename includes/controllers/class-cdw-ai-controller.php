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
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-agentic-loop.php';

/**
 * Handles CDW REST AI endpoints: chat and tool execution.
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
		// Main chat endpoint — runs the agentic loop.
		register_rest_route(
			$this->namespace,
			'/ai/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chat' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'message' => array(
						'type'     => 'string',
						'required' => true,
					),
					'history' => array(
						'type'     => 'array',
						'required' => false,
					),
				),
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
				'args'                => array(
					'tool_name' => array(
						'type'     => 'string',
						'required' => true,
					),
					'arguments' => array(
						'type'     => 'object',
						'required' => false,
					),
				),
			)
		);

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
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$params  = $request->get_json_params();
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

		$result = CDW_Agentic_Loop::execute_agentic_loop(
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
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$params    = $request->get_json_params();
		$tool_name = isset( $params['tool_name'] ) ? sanitize_text_field( $params['tool_name'] ) : '';
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

}

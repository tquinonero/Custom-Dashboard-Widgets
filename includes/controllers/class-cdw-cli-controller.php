<?php
/**
 * CLI REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/cli/class-cdw-cli-command-catalog.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

/**
 * Handles CDW REST CLI endpoints: execute, history, and command definitions.
 *
 * @package CDW
 */
class CDW_CLI_Controller extends CDW_Base_Controller {
	/**
	 * CLI service instance.
	 *
	 * @var CDW_CLI_Service
	 */
	private $cli_service;

	/**
	 * Constructor — instantiates the CLI service.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->cli_service = new CDW_CLI_Service();
	}

	/**
	 * Registers CLI REST routes (history GET/DELETE, commands GET, execute POST).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/cli/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cli_history' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cli/history',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'clear_cli_history' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cli/commands',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cli_commands' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cli/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'execute_cli_command' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'command' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Returns the CLI command history for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cli_history() {
		$rate_check = $this->check_rate_limit( 'cli_history_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id = get_current_user_id();
		$history = $this->cli_service->get_history( $user_id );

		return new WP_REST_Response( is_array( $history ) ? $history : array(), 200 );
	}

	/**
	 * Clears the CLI command history for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_cli_history() {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'cli_history_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id = get_current_user_id();
		$this->cli_service->clear_history( $user_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'cleared' => true,
			),
			200
		);
	}

	/**
	 * Returns the available CLI command definitions for the autocomplete UI.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cli_commands() {
		$rate_check = $this->check_rate_limit( 'cli_commands_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		return new WP_REST_Response( $this->get_command_definitions(), 200 );
	}

	/**
	 * Executes a CLI command and returns the result.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function execute_cli_command( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$command = $request->get_param( 'command' );
		$user_id = get_current_user_id();

		$ob_level = ob_get_level();
		ob_start();

		try {
			$result = $this->cli_service->execute( $command, $user_id );

			ob_end_clean();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			delete_transient( 'cdw_stats_cache' );

			return new WP_REST_Response( $result, 200 );
		} catch ( Exception $e ) {
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
			return new WP_Error(
				'cli_error',
				'Command execution failed: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Returns the grouped command definitions used for autocomplete and help text.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_command_definitions() {
		return CDW_CLI_Command_Catalog::get_modular_categories();
	}
}

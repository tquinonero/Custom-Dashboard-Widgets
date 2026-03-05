<?php
/**
 * REST API registration class.
 *
 * Loads all controllers, registers their routes, and ensures the
 * audit-log table exists before the first CLI command runs.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads all CDW REST API controllers and registers their routes.
 *
 * @package CDW
 */
class CDW_REST_API {
	/**
	 * Registered controller instances.
	 *
	 * @var array<int,CDW_Base_Controller>
	 */
	private $controllers = array();

	/**
	 * Initialises the REST API: loads controllers, schedules route registration
	 * and hooks ensure_audit_table to the init action.
	 *
	 * @return void
	 */
	public function register() {
		$this->load_controllers();
		$this->register_routes();
		add_action( 'init', array( $this, 'ensure_audit_table' ), 5 );
	}

	/**
	 * Requires all controller and service class files and instantiates each controller.
	 *
	 * @return void
	 */
	private function load_controllers() {
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-stats-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-media-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-posts-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-users-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-updates-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-tasks-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-settings-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-cli-controller.php';
		require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-ai-controller.php';

		$this->controllers = array(
			new CDW_Stats_Controller(),
			new CDW_Media_Controller(),
			new CDW_Posts_Controller(),
			new CDW_Users_Controller(),
			new CDW_Updates_Controller(),
			new CDW_Tasks_Controller(),
			new CDW_Settings_Controller(),
			new CDW_CLI_Controller(),
			new CDW_AI_Controller(),
		);
	}

	/**
	 * Hooks register_controller_routes to the rest_api_init action.
	 *
	 * @return void
	 */
	private function register_routes() {
		add_action( 'rest_api_init', array( $this, 'register_controller_routes' ) );
	}

	/**
	 * Calls register_routes() on every loaded controller.
	 *
	 * Called on the rest_api_init action.
	 *
	 * @return void
	 */
	public function register_controller_routes() {
		foreach ( $this->controllers as $controller ) {
			if ( method_exists( $controller, 'register_routes' ) ) {
				$controller->register_routes();
			}
		}
	}

	/**
	 * Creates or upgrades the audit-log DB table when the schema version changes.
	 *
	 * Skips on frontend requests to avoid a DB query on every page load.
	 * Called on the init action (priority 5).
	 *
	 * @return void
	 */
	public function ensure_audit_table() {
		// Only run in contexts where the table is actually needed.
		// Avoids a DB query on every frontend page load.
		if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( get_option( 'cdw_db_version' ) === CDW_CLI_Service::DB_VERSION ) {
			return;
		}

		$cli_service = new CDW_CLI_Service();
		$cli_service->create_audit_log_table();
		update_option( 'cdw_db_version', CDW_CLI_Service::DB_VERSION );
	}
}

<?php
/**
 * Tasks REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';

/**
 * Handles GET and POST /cdw/v1/tasks — per-user personal task list.
 *
 * @package CDW
 */
class CDW_Tasks_Controller extends CDW_Base_Controller {
	/**
	 * Task service instance.
	 *
	 * @var CDW_Task_Service
	 */
	private $task_service;

	/**
	 * Constructor — instantiates the task service.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->task_service = new CDW_Task_Service();
	}

	/**
	 * Registers the GET and POST /tasks REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/tasks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_tasks' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_tasks' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Returns tasks for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_tasks() {
		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return new WP_Error( 'no_user', 'User not logged in', array( 'status' => 401 ) );
		}
		$tasks = $this->task_service->get_tasks();
		return rest_ensure_response( $tasks );
	}

	/**
	 * Validates and persists the submitted task list.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_tasks( WP_REST_Request $request ) {
		$current_user_id = get_current_user_id();

		if ( ! $current_user_id ) {
			return new WP_Error( 'no_user', 'User not logged in', array( 'status' => 401 ) );
		}

		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'tasks_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$tasks       = $request->get_param( 'tasks' );
		$assignee_id = $request->get_param( 'assignee_id' );

		$target_user_id = $current_user_id;
		if ( $assignee_id && current_user_can( 'manage_options' ) ) {
			$target_user_id = intval( $assignee_id );
		}

		$result = $this->task_service->save_tasks( $tasks, $target_user_id, $current_user_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'tasks'   => $result,
			)
		);
	}
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CDW_Tasks_Controller extends CDW_Base_Controller {
    private $task_service;

    public function __construct() {
        $this->task_service = new CDW_Task_Service();
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/tasks', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_tasks' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
        ) );

        register_rest_route( $this->namespace, '/tasks', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'save_tasks' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
        ) );
    }

    public function get_tasks() {
        $tasks = $this->task_service->get_tasks();
        return rest_ensure_response( $tasks );
    }

    public function save_tasks( WP_REST_Request $request ) {
        $current_user_id = get_current_user_id();

        if ( ! $current_user_id ) {
            return new WP_Error( 'no_user', 'User not logged in', array( 'status' => 401 ) );
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

        return rest_ensure_response( array(
            'success' => true,
            'tasks'   => $result,
        ) );
    }
}

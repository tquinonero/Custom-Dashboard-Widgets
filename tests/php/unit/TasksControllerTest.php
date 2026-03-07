<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-tasks-controller.php';

class TasksControllerTest extends CDWTestCase {

    private \CDW_Tasks_Controller $controller;
    private $mockService;

    protected function setUp(): void {
        parent::setUp();

        // Create a Mockery mock for the task service.
        $this->mockService  = \Mockery::mock( 'CDW_Task_Service' );
        $this->controller   = new \CDW_Tasks_Controller();

        // Inject the mock via reflection (the real service is created in __construct).
        $ref  = new \ReflectionClass( $this->controller );
        $prop = $ref->getProperty( 'task_service' );
        $prop->setAccessible( true );
        $prop->setValue( $this->controller, $this->mockService );

        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    // -----------------------------------------------------------------------
    // get_tasks()
    // -----------------------------------------------------------------------

    public function test_get_tasks_returns_error_when_not_logged_in(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 0 );

        $result = $this->controller->get_tasks();

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 401, $result->get_error_data()['status'] );
    }

    public function test_get_tasks_returns_task_service_result_for_logged_in_user(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 5 );
        $this->mockService->shouldReceive( 'get_tasks' )
            ->once()
            ->andReturn( array( array( 'name' => 'Test task' ) ) );

        $result = $this->controller->get_tasks();

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $data = $result->get_data();
        $this->assertCount( 1, $data );
    }

    // -----------------------------------------------------------------------
    // save_tasks()
    // -----------------------------------------------------------------------

    public function test_save_tasks_returns_error_when_not_logged_in(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 0 );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', array() );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 401, $result->get_error_data()['status'] );
    }

    public function test_save_tasks_uses_current_user_id_when_no_assignee(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 3 );
        Functions\when( 'current_user_can' )->justReturn( true );

        $this->mockService->shouldReceive( 'save_tasks' )
            ->once()
            ->with( array(), 3, 3 )
            ->andReturn( array() );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', array() );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
    }

    public function test_save_tasks_uses_assignee_id_when_admin(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'current_user_can' )->justReturn( true );

        $this->mockService->shouldReceive( 'save_tasks' )
            ->once()
            ->with( array(), 7, 1 )
            ->andReturn( array() );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', array() );
        $request->set_param( 'assignee_id', 7 );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
    }

    public function test_save_tasks_ignores_assignee_when_not_admin(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 2 );
        Functions\when( 'current_user_can' )->justReturn( false );

        // target_user_id should fallback to current_user_id (2) since no manage_options
        $this->mockService->shouldReceive( 'save_tasks' )
            ->once()
            ->with( array(), 2, 2 )
            ->andReturn( array() );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', array() );
        $request->set_param( 'assignee_id', 99 );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
    }

    public function test_save_tasks_propagates_wp_error_from_service(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'current_user_can' )->justReturn( false );

        $wpError = new \WP_Error( 'invalid_tasks', 'Tasks must be an array' );
        $this->mockService->shouldReceive( 'save_tasks' )
            ->once()
            ->andReturn( $wpError );

        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', 'not-an-array' );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_tasks', $result->get_error_code() );
    }

    public function test_save_tasks_returns_success_with_tasks_key(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( 'is_wp_error' )->justReturn( false );

        $savedTasks = array( array( 'name' => 'Task A' ) );
        $this->mockService->shouldReceive( 'save_tasks' )
            ->once()
            ->andReturn( $savedTasks );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', $savedTasks );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $data = $result->get_data();
        $this->assertTrue( $data['success'] );
        $this->assertSame( $savedTasks, $data['tasks'] );
    }

    // -----------------------------------------------------------------------
    // save_tasks() — nonce verification
    // -----------------------------------------------------------------------

    public function test_save_tasks_returns_401_when_nonce_missing(): void {
        $_SERVER = array();
        Functions\when( 'get_current_user_id' )->justReturn( 1 );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', array() );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_missing_nonce', $result->get_error_code() );
        $this->assertSame( 401, $result->get_error_data()['status'] );
    }

    public function test_save_tasks_returns_403_when_nonce_invalid(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = 'invalid_nonce';
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'wp_verify_nonce' )->justReturn( false );

        $request = new \WP_REST_Request();
        $request->set_param( 'tasks', array() );

        $result = $this->controller->save_tasks( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_invalid_nonce', $result->get_error_code() );
        $this->assertSame( 403, $result->get_error_data()['status'] );
    }
}

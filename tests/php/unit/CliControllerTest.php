<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-cli-controller.php';

class CliControllerTest extends CDWTestCase {

    private \CDW_CLI_Controller $controller;
    private $mockService;

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['wpdb'] = new \wpdb();
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $this->controller = new \CDW_CLI_Controller();
        $this->mockService = \Mockery::mock( 'CDW_CLI_Service' );

        $ref  = new \ReflectionClass( $this->controller );
        $prop = $ref->getProperty( 'cli_service' );
        $prop->setAccessible( true );
        $prop->setValue( $this->controller, $this->mockService );
    }

    // -----------------------------------------------------------------------
    // get_cli_history()
    // -----------------------------------------------------------------------

    public function test_get_cli_history_returns_200_with_empty_array_when_service_returns_empty(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockService->shouldReceive( 'get_history' )->once()->with( 1 )->andReturn( array() );

        $result = $this->controller->get_cli_history();

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( 200, $result->get_status() );
        $this->assertSame( array(), $result->get_data() );
    }

    public function test_get_cli_history_returns_history_entries_from_service(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 5 );
        $entries = array(
            array( 'command' => 'plugin list', 'output' => 'Active: ...',  'success' => true, 'timestamp' => 1700000000 ),
        );
        $this->mockService->shouldReceive( 'get_history' )->once()->with( 5 )->andReturn( $entries );

        $result = $this->controller->get_cli_history();

        $this->assertSame( $entries, $result->get_data() );
    }

    public function test_get_cli_history_coerces_null_service_response_to_empty_array(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockService->shouldReceive( 'get_history' )->once()->andReturn( null );

        $result = $this->controller->get_cli_history();

        $this->assertSame( array(), $result->get_data() );
    }

    // -----------------------------------------------------------------------
    // clear_cli_history()
    // -----------------------------------------------------------------------

    public function test_clear_cli_history_calls_service_with_current_user_id(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 7 );
        $this->mockService->shouldReceive( 'clear_history' )->once()->with( 7 );

        $this->controller->clear_cli_history();

        $this->addToAssertionCount( 1 ); // Mockery expectation verified in tearDown.
    }

    public function test_clear_cli_history_returns_success_true_and_cleared_true(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockService->shouldReceive( 'clear_history' )->once();

        $result = $this->controller->clear_cli_history();
        $data   = $result->get_data();

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( 200, $result->get_status() );
        $this->assertTrue( $data['success'] );
        $this->assertTrue( $data['cleared'] );
    }

    // -----------------------------------------------------------------------
    // get_cli_commands()
    // -----------------------------------------------------------------------

    public function test_get_cli_commands_returns_200_with_non_empty_array(): void {
        $result = $this->controller->get_cli_commands();

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( 200, $result->get_status() );
        $data = $result->get_data();
        $this->assertIsArray( $data );
        $this->assertNotEmpty( $data );
    }

    public function test_get_cli_commands_each_entry_has_category_and_commands_keys(): void {
        $data = $this->controller->get_cli_commands()->get_data();

        foreach ( $data as $entry ) {
            $this->assertArrayHasKey( 'category', $entry );
            $this->assertArrayHasKey( 'commands', $entry );
            $this->assertIsArray( $entry['commands'] );
        }
    }

    public function test_get_cli_commands_contains_plugin_management_category(): void {
        $categories = array_column( $this->controller->get_cli_commands()->get_data(), 'category' );

        $this->assertContains( 'Plugin Management', $categories );
    }

    public function test_get_cli_commands_each_command_has_name_and_description(): void {
        $data = $this->controller->get_cli_commands()->get_data();

        foreach ( $data as $category ) {
            foreach ( $category['commands'] as $cmd ) {
                $this->assertArrayHasKey( 'name', $cmd );
                $this->assertArrayHasKey( 'description', $cmd );
            }
        }
    }

    // -----------------------------------------------------------------------
    // execute_cli_command()
    // -----------------------------------------------------------------------

    public function test_execute_cli_command_returns_400_when_command_is_empty_string(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $request = new \WP_REST_Request();
        $request->set_param( 'command', '' );

        $result = $this->controller->execute_cli_command( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'empty_command', $result->get_error_code() );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    public function test_execute_cli_command_returns_400_when_command_is_null(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $request = new \WP_REST_Request(); // param not set → null

        $result = $this->controller->execute_cli_command( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    public function test_execute_cli_command_calls_service_and_returns_result(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'delete_transient' )->justReturn( true );

        $serviceResult = array( 'output' => 'plugin list result', 'success' => true );
        $this->mockService->shouldReceive( 'execute' )->once()
            ->with( 'plugin list', 1 )
            ->andReturn( $serviceResult );

        $request = new \WP_REST_Request();
        $request->set_param( 'command', 'plugin list' );

        $result = $this->controller->execute_cli_command( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $this->assertSame( $serviceResult, $result->get_data() );
    }

    public function test_execute_cli_command_propagates_wp_error_from_service(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'delete_transient' )->justReturn( true );
        $error = new \WP_Error( 'cli_error', 'Execution failed', array( 'status' => 500 ) );
        $this->mockService->shouldReceive( 'execute' )->once()->andReturn( $error );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $request = new \WP_REST_Request();
        $request->set_param( 'command', 'bad-command' );

        $result = $this->controller->execute_cli_command( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_execute_cli_command_deletes_stats_cache_on_success(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'is_wp_error' )->justReturn( false );

        $capturedKey = null;
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$capturedKey ) {
            $capturedKey = $key;
        } );

        $this->mockService->shouldReceive( 'execute' )->once()
            ->andReturn( array( 'output' => 'ok', 'success' => true ) );

        $request = new \WP_REST_Request();
        $request->set_param( 'command', 'cache flush' );
        $this->controller->execute_cli_command( $request );

        $this->assertSame( 'cdw_stats_cache', $capturedKey );
    }

    // -----------------------------------------------------------------------
    // execute_cli_command() — nonce verification
    // -----------------------------------------------------------------------

    public function test_execute_cli_command_returns_401_when_nonce_missing(): void {
        $_SERVER = array();

        $request = new \WP_REST_Request();
        $request->set_param( 'command', 'plugin list' );

        $result = $this->controller->execute_cli_command( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_missing_nonce', $result->get_error_code() );
        $this->assertSame( 401, $result->get_error_data()['status'] );
    }

    public function test_execute_cli_command_returns_403_when_nonce_invalid(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = 'invalid_nonce';
        Functions\when( 'wp_verify_nonce' )->justReturn( false );

        $request = new \WP_REST_Request();
        $request->set_param( 'command', 'plugin list' );

        $result = $this->controller->execute_cli_command( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_invalid_nonce', $result->get_error_code() );
        $this->assertSame( 403, $result->get_error_data()['status'] );
    }

    // -----------------------------------------------------------------------
    // clear_cli_history() — nonce verification
    // -----------------------------------------------------------------------

    public function test_clear_cli_history_returns_401_when_nonce_missing(): void {
        $_SERVER = array();

        $result = $this->controller->clear_cli_history();

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_missing_nonce', $result->get_error_code() );
    }
}

<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';

class TaskServiceTest extends CDWTestCase {

    private \CDW_Task_Service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \CDW_Task_Service();
    }

    // -----------------------------------------------------------------------
    // get_tasks()
    // -----------------------------------------------------------------------

    public function test_get_tasks_returns_empty_array_for_user_id_0_without_calling_get_user_meta(): void {
        // get_user_meta must NOT be called — Brain\Monkey would throw if it were called unexpectedly
        Functions\expect( 'get_user_meta' )->never();

        $result = $this->service->get_tasks( 0 );

        $this->assertSame( array(), $result );
    }

    public function test_get_tasks_returns_empty_array_when_meta_is_empty_string(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = $this->service->get_tasks( 1 );

        $this->assertSame( array(), $result );
    }

    public function test_get_tasks_returns_parsed_array_for_valid_json_array(): void {
        $tasks = array(
            array( 'name' => 'Task A', 'timestamp' => 1000, 'created_by' => 1 ),
            array( 'name' => 'Task B', 'timestamp' => 2000, 'created_by' => 1 ),
        );
        Functions\when( 'get_user_meta' )->justReturn( json_encode( $tasks ) );

        $result = $this->service->get_tasks( 1 );

        $this->assertSame( $tasks, $result );
    }

    public function test_get_tasks_returns_empty_array_for_invalid_json(): void {
        Functions\when( 'get_user_meta' )->justReturn( '{bad' );

        $result = $this->service->get_tasks( 1 );

        $this->assertSame( array(), $result );
    }

    public function test_get_tasks_returns_empty_array_for_json_scalar(): void {
        Functions\when( 'get_user_meta' )->justReturn( '"string"' );

        $result = $this->service->get_tasks( 1 );

        $this->assertSame( array(), $result );
    }

    public function test_get_tasks_returns_empty_array_for_json_object(): void {
        Functions\when( 'get_user_meta' )->justReturn( '{"a":1}' );

        $result = $this->service->get_tasks( 1 );

        $this->assertSame( array(), $result );
    }

    public function test_get_tasks_returns_empty_array_for_empty_json_array(): void {
        Functions\when( 'get_user_meta' )->justReturn( '[]' );

        $result = $this->service->get_tasks( 1 );

        $this->assertSame( array(), $result );
    }

    // -----------------------------------------------------------------------
    // save_tasks()
    // -----------------------------------------------------------------------

    public function test_save_tasks_returns_wp_error_for_user_id_0(): void {
        $result = $this->service->save_tasks( array(), null, 0 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'not_logged_in', $result->get_error_code() );
    }

    public function test_save_tasks_returns_wp_error_when_tasks_not_array(): void {
        Functions\when( 'current_user_can' )->justReturn( false );

        $result = $this->service->save_tasks( 'string', null, 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_tasks', $result->get_error_code() );
    }

    public function test_save_tasks_returns_permission_denied_error_for_cross_user_without_capability(): void {
        Functions\when( 'current_user_can' )->justReturn( false );

        $result = $this->service->save_tasks( array(), 99, 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'permission_denied', $result->get_error_code() );
    }

    public function test_save_tasks_returns_invalid_user_error_when_target_does_not_exist(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_userdata' )->justReturn( false );

        $result = $this->service->save_tasks( array(), 99, 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_user', $result->get_error_code() );
    }

    public function test_save_tasks_own_user_calls_update_user_meta_and_returns_sanitized(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $capturedUserId = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedUserId ) {
                $capturedUserId = $uid;
            }
        );

        $past = time() - 100;
        $result = $this->service->save_tasks(
            array( array( 'name' => 'My Task', 'timestamp' => $past ) ),
            null,
            5
        );

        $this->assertIsArray( $result );
        $this->assertCount( 1, $result );
        $this->assertSame( 'My Task', $result[0]['name'] );
        $this->assertSame( 5, $capturedUserId );
    }

    public function test_save_tasks_empty_array_calls_update_user_meta_with_empty_json(): void {
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $capturedValue = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedValue ) {
                $capturedValue = $value;
            }
        );

        $result = $this->service->save_tasks( array(), null, 3 );

        $this->assertSame( array(), $result );
        $this->assertSame( '[]', $capturedValue );
    }

    public function test_save_tasks_cross_user_admin_merges_existing_tasks(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_userdata' )->justReturn( (object) array( 'ID' => 99 ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $existing = array( array( 'name' => 'Existing', 'timestamp' => time() - 200, 'created_by' => 99 ) );
        // get_user_meta will be called by get_tasks(99) inside save_tasks
        Functions\when( 'get_user_meta' )->justReturn( json_encode( $existing ) );

        $capturedUserId = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedUserId ) {
                $capturedUserId = $uid;
            }
        );

        $past   = time() - 50;
        $result = $this->service->save_tasks(
            array( array( 'name' => 'New Task', 'timestamp' => $past ) ),
            99,
            1
        );

        $this->assertIsArray( $result );
        $this->assertCount( 2, $result );
        $this->assertSame( 99, $capturedUserId );
    }

    // -----------------------------------------------------------------------
    // sanitize_tasks() — via save_tasks() with valid user
    // -----------------------------------------------------------------------

    public function test_sanitize_tasks_excludes_task_with_empty_name(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $result = $this->service->save_tasks(
            array( array( 'name' => '', 'timestamp' => time() - 10 ) ),
            null,
            1
        );

        $this->assertSame( array(), $result );
    }

    public function test_sanitize_tasks_excludes_task_with_missing_name_key(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $result = $this->service->save_tasks(
            array( array( 'timestamp' => time() - 10 ) ),
            null,
            1
        );

        $this->assertSame( array(), $result );
    }

    public function test_sanitize_tasks_includes_task_with_valid_name(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $past   = time() - 10;
        $result = $this->service->save_tasks(
            array( array( 'name' => 'Do it', 'timestamp' => $past ) ),
            null,
            1
        );

        $this->assertCount( 1, $result );
        $this->assertSame( 'Do it', $result[0]['name'] );
    }

    public function test_sanitize_tasks_replaces_zero_timestamp_with_current_time(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $result = $this->service->save_tasks(
            array( array( 'name' => 'Task', 'timestamp' => 0 ) ),
            null,
            1
        );

        $this->assertEqualsWithDelta( time(), $result[0]['timestamp'], 2 );
    }

    public function test_sanitize_tasks_replaces_future_timestamp_with_current_time(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $future = mktime( 0, 0, 0, 1, 1, 2099 ); // Year 2099

        $result = $this->service->save_tasks(
            array( array( 'name' => 'Task', 'timestamp' => $future ) ),
            null,
            1
        );

        $this->assertEqualsWithDelta( time(), $result[0]['timestamp'], 2 );
    }

    public function test_sanitize_tasks_preserves_valid_past_timestamp(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $past = time() - 3600; // 1 hour ago

        $result = $this->service->save_tasks(
            array( array( 'name' => 'Task', 'timestamp' => $past ) ),
            null,
            1
        );

        $this->assertSame( $past, $result[0]['timestamp'] );
    }

    public function test_sanitize_tasks_always_sets_created_by_to_current_user_id(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'update_user_meta' )->justReturn( true );

        $past = time() - 10;

        $result = $this->service->save_tasks(
            array( array( 'name' => 'Task', 'timestamp' => $past, 'created_by' => 999 ) ),
            null,
            7
        );

        $this->assertSame( 7, $result[0]['created_by'] );
    }

    // -----------------------------------------------------------------------
    // delete_tasks()
    // -----------------------------------------------------------------------

    public function test_delete_tasks_calls_delete_user_meta_with_correct_args(): void {
        $capturedArgs = null;
        Functions\when( 'delete_user_meta' )->alias(
            function( $uid, $key ) use ( &$capturedArgs ) {
                $capturedArgs = array( $uid, $key );
                return true;
            }
        );

        $this->service->delete_tasks( 42 );

        $this->assertSame( array( 42, 'cdw_tasks' ), $capturedArgs );
    }
}

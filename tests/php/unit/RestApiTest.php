<?php
/**
 * Unit tests for CDW REST API.
 */

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-rest-api.php';

class RestApiTest extends CDWTestCase {

    private \CDW_REST_API $api;

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wpdb'] = new \wpdb();

        $this->api = new \CDW_REST_API();

        // Stub common WP functions so ensure_audit_table() and register() don't crash.
        Functions\when( 'add_action' )->justReturn( true );
        Functions\when( 'register_rest_route' )->justReturn( true );
    }

    // -----------------------------------------------------------------------
    // ensure_audit_table() — early-exit guard (run this test FIRST)
    // -----------------------------------------------------------------------

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_ensure_audit_table_returns_early_when_not_admin_no_rest_no_cli(): void {
        // NOTE: REST_REQUEST and WP_CLI must NOT be defined here.
        Functions\when( 'is_admin' )->justReturn( false );

        $updateOptionCalled = false;
        Functions\when( 'get_option' )->justReturn( 'old_version' );
        Functions\when( 'update_option' )->alias( function() use ( &$updateOptionCalled ) {
            $updateOptionCalled = true;
        } );

        // No REST_REQUEST or WP_CLI defined → should return immediately, no DB work.
        $this->api->ensure_audit_table();

        $this->assertFalse( $updateOptionCalled,
            'update_option should NOT be called when not admin and no REST/CLI context' );
    }

    // -----------------------------------------------------------------------
    // ensure_audit_table() — version already matches
    // -----------------------------------------------------------------------

    public function test_ensure_audit_table_returns_early_when_version_matches(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'get_option' )->justReturn( \CDW_CLI_Service::DB_VERSION );

        $updateOptionCalled = false;
        Functions\when( 'update_option' )->alias( function() use ( &$updateOptionCalled ) {
            $updateOptionCalled = true;
        } );

        $this->api->ensure_audit_table();

        $this->assertFalse( $updateOptionCalled );
    }

    // -----------------------------------------------------------------------
    // ensure_audit_table() — version mismatch triggers table creation
    // -----------------------------------------------------------------------

    public function test_ensure_audit_table_calls_update_option_on_version_mismatch(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_db_version' === $key ) {
                return 'old_version'; // mismatch
            }
            return $default;
        } );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );
        // dbDelta is already defined via stubs/wp-admin/includes/upgrade.php

        $this->api->ensure_audit_table();

        $this->assertArrayHasKey( 'cdw_db_version', $updateCalls );
        $this->assertSame( \CDW_CLI_Service::DB_VERSION, $updateCalls['cdw_db_version'] );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_ensure_audit_table_runs_when_rest_request_is_true(): void {
        define( 'REST_REQUEST', true );

        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( 'old_version' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $this->api->ensure_audit_table();

        $this->assertArrayHasKey( 'cdw_db_version', $updateCalls );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_ensure_audit_table_runs_when_wp_cli_is_true(): void {
        define( 'WP_CLI', true );

        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( 'old_version' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $this->api->ensure_audit_table();

        $this->assertArrayHasKey( 'cdw_db_version', $updateCalls );
    }

    // -----------------------------------------------------------------------
    // register_controller_routes() — calls register_routes() on each controller
    // -----------------------------------------------------------------------

    public function test_register_controller_routes_calls_register_routes_on_each_controller(): void {
        // Use the real register() path but capture add_action / register_rest_route calls.
        $routesCalled = 0;
        Functions\when( 'register_rest_route' )->alias( function() use ( &$routesCalled ) {
            $routesCalled++;
        } );

        $this->api->register();

        // After register(), add_action('rest_api_init', ...) fires when triggered.
        // Manually fire the rest_api_init hook to confirm routes are registered.
        $this->api->register_controller_routes();

        // At least several routes should have been registered across 8 controllers.
        $this->assertGreaterThan( 5, $routesCalled, 'Expected multiple routes to be registered' );
    }

    public function test_register_controller_routes_does_not_error_on_missing_method(): void {
        // Create a mock controller without register_routes()
        $dummy = new \stdClass();

        // Directly test the guard: method_exists($controller, 'register_routes')
        // since stdClass has no register_routes(), no exception should be thrown.
        $ref    = new \ReflectionClass( $this->api );
        $prop   = $ref->getProperty( 'controllers' );
        $prop->setAccessible( true );
        $prop->setValue( $this->api, array( $dummy ) );

        $this->expectNotToPerformAssertions();
        $this->api->register_controller_routes();
    }
}

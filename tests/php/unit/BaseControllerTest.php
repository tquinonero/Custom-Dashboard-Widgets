<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Minimal concrete subclass to expose protected methods under test.
 */
class ConcreteController extends \CDW_Base_Controller {
    public function register_routes() {}

    public function test_is_option_protected( $n ) {
        return $this->is_option_protected( $n );
    }

    public function test_success_response( $d, $s = 200 ) {
        return $this->success_response( $d, $s );
    }

    public function test_error_response( $m, $s = 400 ) {
        return $this->error_response( $m, $s );
    }

    public function test_get_transient_with_cache( $k, $cb, $e = 300 ) {
        return $this->get_transient_with_cache( $k, $cb, $e );
    }

    public function test_delete_transients_by_prefix( $p ) {
        return $this->delete_transients_by_prefix( $p );
    }
}

class BaseControllerTest extends CDWTestCase {

    private ConcreteController $controller;

    protected function setUp(): void {
        parent::setUp();
        $this->controller = new ConcreteController();
    }

    // -----------------------------------------------------------------------
    // check_read_permission
    // -----------------------------------------------------------------------

    public function test_check_read_permission_returns_true_when_user_can_read(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        $this->assertTrue( $this->controller->check_read_permission() );
    }

    public function test_check_read_permission_returns_false_when_user_cannot_read(): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        $this->assertFalse( $this->controller->check_read_permission() );
    }

    // -----------------------------------------------------------------------
    // check_admin_permission
    // -----------------------------------------------------------------------

    public function test_check_admin_permission_returns_true_when_user_can_manage_options(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        $this->assertTrue( $this->controller->check_admin_permission() );
    }

    public function test_check_admin_permission_returns_false_when_user_cannot_manage_options(): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        $this->assertFalse( $this->controller->check_admin_permission() );
    }

    // -----------------------------------------------------------------------
    // is_option_protected
    // -----------------------------------------------------------------------

    public function test_is_option_protected_returns_true_for_siteurl(): void {
        $this->assertTrue( $this->controller->test_is_option_protected( 'siteurl' ) );
    }

    public function test_is_option_protected_returns_true_for_auth_key(): void {
        $this->assertTrue( $this->controller->test_is_option_protected( 'auth_key' ) );
    }

    public function test_is_option_protected_returns_true_for_nonce_salt(): void {
        $this->assertTrue( $this->controller->test_is_option_protected( 'nonce_salt' ) );
    }

    public function test_is_option_protected_returns_false_for_custom_option(): void {
        $this->assertFalse( $this->controller->test_is_option_protected( 'my_custom_option' ) );
    }

    public function test_is_option_protected_returns_false_for_empty_string(): void {
        $this->assertFalse( $this->controller->test_is_option_protected( '' ) );
    }

    public function test_is_option_protected_returns_false_for_near_match(): void {
        $this->assertFalse( $this->controller->test_is_option_protected( 'siteurls' ) );
    }

    public function test_is_option_protected_returns_false_for_prefix_match(): void {
        $this->assertFalse( $this->controller->test_is_option_protected( 'siteurl_extra' ) );
    }

    // -----------------------------------------------------------------------
    // success_response
    // -----------------------------------------------------------------------

    private function stubRestEnsureResponse(): void {
        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    public function test_success_response_returns_wp_rest_response_instance(): void {
        $this->stubRestEnsureResponse();
        $result = $this->controller->test_success_response( array( 'key' => 'value' ) );
        $this->assertInstanceOf( \WP_REST_Response::class, $result );
    }

    public function test_success_response_has_success_true(): void {
        $this->stubRestEnsureResponse();
        $result = $this->controller->test_success_response( array( 'key' => 'value' ) );
        $this->assertTrue( $result->get_data()['success'] );
    }

    public function test_success_response_data_key_matches_input(): void {
        $this->stubRestEnsureResponse();
        $input  = array( 'foo' => 'bar' );
        $result = $this->controller->test_success_response( $input );
        $this->assertSame( $input, $result->get_data()['data'] );
    }

    public function test_success_response_default_status_is_200(): void {
        $this->stubRestEnsureResponse();
        $result = $this->controller->test_success_response( 'test' );
        $this->assertSame( 200, $result->get_status() );
    }

    public function test_success_response_custom_status_201_is_applied(): void {
        $this->stubRestEnsureResponse();
        $result = $this->controller->test_success_response( 'test', 201 );
        $this->assertSame( 201, $result->get_status() );
    }

    // -----------------------------------------------------------------------
    // error_response
    // -----------------------------------------------------------------------

    public function test_error_response_returns_wp_error_instance(): void {
        $result = $this->controller->test_error_response( 'Something went wrong' );
        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_error_response_code_is_cdw_error(): void {
        $result = $this->controller->test_error_response( 'Something went wrong' );
        $this->assertSame( 'cdw_error', $result->get_error_code() );
    }

    public function test_error_response_message_matches_input(): void {
        $result = $this->controller->test_error_response( 'Custom error message' );
        $this->assertSame( 'Custom error message', $result->get_error_message() );
    }

    public function test_error_response_default_status_is_400(): void {
        $result = $this->controller->test_error_response( 'err' );
        $data   = $result->get_error_data();
        $this->assertSame( 400, $data['status'] );
    }

    public function test_error_response_custom_status_403_passed_through(): void {
        $result = $this->controller->test_error_response( 'err', 403 );
        $data   = $result->get_error_data();
        $this->assertSame( 403, $data['status'] );
    }

    // -----------------------------------------------------------------------
    // get_transient_with_cache
    // -----------------------------------------------------------------------

    public function test_cache_hit_returns_cached_value_without_calling_callback(): void {
        Functions\when( 'get_transient' )->justReturn( array( 'cached' => true ) );

        $called = false;
        $result = $this->controller->test_get_transient_with_cache( 'key', function() use ( &$called ) {
            $called = true;
            return array( 'fresh' => true );
        } );

        $this->assertFalse( $called );
        $this->assertSame( array( 'cached' => true ), $result );
    }

    public function test_cache_miss_calls_callback_and_stores_result(): void {
        Functions\when( 'get_transient' )->justReturn( false );

        $storedKey    = null;
        $storedValue  = null;
        $storedExpiry = null;
        Functions\when( 'set_transient' )->alias(
            function( $k, $v, $e ) use ( &$storedKey, &$storedValue, &$storedExpiry ) {
                $storedKey    = $k;
                $storedValue  = $v;
                $storedExpiry = $e;
            }
        );

        $result = $this->controller->test_get_transient_with_cache(
            'key',
            function() { return array( 'fresh' => true ); }
        );

        $this->assertSame( array( 'fresh' => true ), $result );
        $this->assertSame( 'key', $storedKey );
        $this->assertSame( array( 'fresh' => true ), $storedValue );
        $this->assertSame( 300, $storedExpiry );
    }

    public function test_cache_miss_with_zero_callback_return_stores_and_returns_zero(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->controller->test_get_transient_with_cache( 'key', function() { return 0; } );
        $this->assertSame( 0, $result );
    }

    public function test_cache_miss_with_empty_array_callback_return(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->controller->test_get_transient_with_cache( 'key', function() { return array(); } );
        $this->assertSame( array(), $result );
    }

    public function test_cache_miss_with_false_callback_return(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->controller->test_get_transient_with_cache( 'key', function() { return false; } );
        $this->assertFalse( $result );
    }

    public function test_cache_miss_passes_custom_expiration_to_set_transient(): void {
        Functions\when( 'get_transient' )->justReturn( false );
        $capturedExpiry = null;
        Functions\when( 'set_transient' )->alias( function( $key, $value, $expiry ) use ( &$capturedExpiry ) {
            $capturedExpiry = $expiry;
        } );

        $this->controller->test_get_transient_with_cache( 'mykey', function() { return 'data'; }, 600 );

        $this->assertSame( 600, $capturedExpiry );
    }

    // -----------------------------------------------------------------------
    // delete_transients_by_prefix
    // -----------------------------------------------------------------------

    public function test_delete_transients_by_prefix_calls_wpdb_query_twice(): void {
        $wpdb = \cdw_tests_reset_wpdb();

        $this->controller->test_delete_transients_by_prefix( '_transient_cdw_posts_cache_' );

        $this->assertCount( 2, $wpdb->queries );
    }

    public function test_delete_transients_by_prefix_second_query_uses_posts_timeout_prefix(): void {
        $wpdb = \cdw_tests_reset_wpdb();

        $this->controller->test_delete_transients_by_prefix( '_transient_cdw_posts_cache_' );

        $this->assertStringContainsString( '_transient_timeout_cdw_posts_cache_', $wpdb->queries[1] );
    }

    public function test_delete_transients_by_prefix_second_query_uses_media_timeout_prefix(): void {
        $wpdb = \cdw_tests_reset_wpdb();

        $this->controller->test_delete_transients_by_prefix( '_transient_cdw_media_cache_' );

        $this->assertStringContainsString( '_transient_timeout_cdw_media_cache_', $wpdb->queries[1] );
    }
}

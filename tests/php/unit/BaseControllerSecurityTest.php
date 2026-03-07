<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Minimal concrete subclass to expose protected methods under test.
 */
class ConcreteSecurityController extends \CDW_Base_Controller {
    public function register_routes() {}

    public function test_check_contributor_permission() {
        return $this->check_contributor_permission();
    }

    public function test_check_rate_limit( $endpoint, $is_write = false ) {
        return $this->check_rate_limit( $endpoint, $is_write );
    }

    public function test_verify_nonce() {
        return $this->verify_nonce();
    }
}

class BaseControllerSecurityTest extends CDWTestCase {

    private ConcreteSecurityController $controller;

    protected function setUp(): void {
        parent::setUp();
        $this->controller = new ConcreteSecurityController();
    }

    // -----------------------------------------------------------------------
    // check_contributor_permission
    // -----------------------------------------------------------------------

    public function test_check_contributor_permission_returns_true_when_user_can_edit_posts(): void {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->assertTrue( $this->controller->test_check_contributor_permission() );
    }

    public function test_check_contributor_permission_returns_false_when_user_cannot_edit_posts(): void {
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->assertFalse( $this->controller->test_check_contributor_permission() );
    }

    // -----------------------------------------------------------------------
    // check_rate_limit
    // -----------------------------------------------------------------------

    public function test_rate_limit_allows_first_request(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->controller->test_check_rate_limit( 'test_endpoint' );

        $this->assertTrue( $result );
    }

    public function test_rate_limit_allows_requests_under_limit(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( 5 );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = $this->controller->test_check_rate_limit( 'test_endpoint' );

        $this->assertTrue( $result );
    }

    public function test_rate_limit_rejects_at_read_limit(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( 60 ); // RATE_LIMIT_READ_COUNT

        $result = $this->controller->test_check_rate_limit( 'test_endpoint', false );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rate_limited', $result->get_error_code() );
    }

    public function test_rate_limit_rejects_at_write_limit(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( 30 ); // RATE_LIMIT_WRITE_COUNT

        $result = $this->controller->test_check_rate_limit( 'test_endpoint', true );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rate_limited', $result->get_error_code() );
    }

    public function test_rate_limit_returns_true_when_no_user(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 0 );

        $result = $this->controller->test_check_rate_limit( 'test_endpoint' );

        $this->assertTrue( $result );
    }

    public function test_rate_limit_uses_different_keys_per_endpoint(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( false );

        $capturedKeys = array();
        Functions\when( 'set_transient' )->alias(
            function( $key, $value, $expiry ) use ( &$capturedKeys ) {
                $capturedKeys[] = $key;
            }
        );

        $this->controller->test_check_rate_limit( 'endpoint_a' );
        $this->controller->test_check_rate_limit( 'endpoint_b' );

        $this->assertCount( 2, $capturedKeys );
        $this->assertStringContainsString( 'endpoint_a', $capturedKeys[0] );
        $this->assertStringContainsString( 'endpoint_b', $capturedKeys[1] );
    }

    public function test_rate_limit_uses_different_keys_per_user(): void {
        Functions\when( 'get_transient' )->justReturn( false );

        $capturedKeys = array();
        Functions\when( 'set_transient' )->alias(
            function( $key, $value, $expiry ) use ( &$capturedKeys ) {
                $capturedKeys[] = $key;
            }
        );

        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->controller->test_check_rate_limit( 'endpoint' );

        Functions\when( 'get_current_user_id' )->justReturn( 2 );
        $this->controller->test_check_rate_limit( 'endpoint' );

        $this->assertCount( 2, $capturedKeys );
        $this->assertStringContainsString( '_1', $capturedKeys[0] );
        $this->assertStringContainsString( '_2', $capturedKeys[1] );
    }

    // -----------------------------------------------------------------------
    // verify_nonce
    // -----------------------------------------------------------------------

    public function test_verify_nonce_returns_401_when_nonce_missing(): void {
        // Clear any existing server vars
        $_SERVER = array();

        $result = $this->controller->test_verify_nonce();

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_missing_nonce', $result->get_error_code() );
        $this->assertSame( 401, $result->get_error_data()['status'] );
    }

    public function test_verify_nonce_returns_403_when_nonce_invalid(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = 'invalid_nonce';
        Functions\when( 'wp_verify_nonce' )->justReturn( false );

        $result = $this->controller->test_verify_nonce();

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_invalid_nonce', $result->get_error_code() );
        $this->assertSame( 403, $result->get_error_data()['status'] );
    }

    public function test_verify_nonce_returns_true_when_valid(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = 'valid_nonce';
        Functions\when( 'wp_verify_nonce' )->justReturn( 1 );

        $result = $this->controller->test_verify_nonce();

        $this->assertTrue( $result );
    }

    public function test_verify_nonce_sanitizes_nonce_header(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = '  valid_nonce  ';
        Functions\when( 'wp_verify_nonce' )->alias(
            function( $nonce, $action ) use ( & $receivedNonce ) {
                $receivedNonce = $nonce;
                return 1;
            }
        );

        $this->controller->test_verify_nonce();

        $this->assertSame( 'valid_nonce', $receivedNonce );
    }
}

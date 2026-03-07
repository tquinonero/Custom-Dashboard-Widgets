<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-settings-controller.php';

class SettingsControllerTest extends CDWTestCase {

    private \CDW_Settings_Controller $controller;

    protected function setUp(): void {
        parent::setUp();
        $this->controller = new \CDW_Settings_Controller();

        // stub rest_ensure_response to return the data wrapped in a WP_REST_Response
        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    // -----------------------------------------------------------------------
    // get_settings()
    // -----------------------------------------------------------------------

    public function test_get_settings_returns_all_nine_keys(): void {
        Functions\when( 'get_option' )->justReturn( '' );

        $response = $this->controller->get_settings();
        $data     = $response->get_data();

        $expected_keys = array(
            'email', 'docs_url', 'font_size', 'bg_color',
            'header_bg_color', 'header_text_color',
            'cli_enabled', 'remove_default_widgets', 'delete_on_uninstall',
        );
        foreach ( $expected_keys as $key ) {
            $this->assertArrayHasKey( $key, $data, "Missing key: $key" );
        }
    }

    public function test_get_settings_returns_new_option_when_present(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_support_email' === $key ) {
                return 'new@example.com';
            }
            return $default;
        } );

        $response = $this->controller->get_settings();
        $data     = $response->get_data();

        $this->assertSame( 'new@example.com', $data['email'] );
    }

    public function test_get_settings_falls_back_to_legacy_when_new_option_absent(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_support_email' === $key ) {
                return $default; // new option absent → returns legacy as default
            }
            if ( 'custom_dashboard_widget_email' === $key ) {
                return 'legacy@example.com';
            }
            return $default;
        } );

        $response = $this->controller->get_settings();
        $data     = $response->get_data();

        $this->assertSame( 'legacy@example.com', $data['email'] );
    }

    // -----------------------------------------------------------------------
    // save_settings() — validation
    // -----------------------------------------------------------------------

    public function test_save_settings_returns_error_when_body_is_null(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( null );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    public function test_save_settings_returns_error_when_body_is_string(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( 'not-an-array' );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    // -----------------------------------------------------------------------
    // save_settings() — email field
    // -----------------------------------------------------------------------

    public function test_save_settings_valid_email_calls_update_option(): void {
        Functions\when( 'sanitize_email' )->justReturn( 'test@example.com' );
        Functions\when( 'is_email' )->justReturn( true );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'email' => 'test@example.com' ) );

        $result = $this->controller->save_settings( $request );

        $this->assertArrayHasKey( 'cdw_support_email', $updateCalls );
        $this->assertSame( 'test@example.com', $updateCalls['cdw_support_email'] );
    }

    public function test_save_settings_invalid_email_returns_wp_error(): void {
        Functions\when( 'sanitize_email' )->justReturn( 'notanemail' );
        Functions\when( 'is_email' )->justReturn( false );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'email' => 'notanemail' ) );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_email', $result->get_error_code() );
    }

    // -----------------------------------------------------------------------
    // save_settings() — docs_url field
    // -----------------------------------------------------------------------

    public function test_save_settings_valid_https_url_stored(): void {
        Functions\when( 'esc_url_raw' )->justReturn( 'https://example.com' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'docs_url' => 'https://example.com' ) );

        $this->controller->save_settings( $request );

        $this->assertSame( 'https://example.com', $updateCalls['cdw_docs_url'] );
    }

    public function test_save_settings_ftp_url_cleared_to_empty_string(): void {
        // esc_url_raw returns the raw value; controller checks for http/https prefix
        Functions\when( 'esc_url_raw' )->justReturn( 'ftp://bad.com' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'docs_url' => 'ftp://bad.com' ) );

        $this->controller->save_settings( $request );

        $this->assertSame( '', $updateCalls['cdw_docs_url'] );
    }

    public function test_save_settings_javascript_url_cleared_by_esc_url_raw(): void {
        // esc_url_raw strips javascript: schemes → empty string
        Functions\when( 'esc_url_raw' )->justReturn( '' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'docs_url' => 'javascript:alert(1)' ) );

        $this->controller->save_settings( $request );

        // empty after esc_url_raw → stored as ''
        $this->assertSame( '', $updateCalls['cdw_docs_url'] );
    }

    // -----------------------------------------------------------------------
    // save_settings() — bg_color field
    // -----------------------------------------------------------------------

    public function test_save_settings_valid_bg_color_stored(): void {
        Functions\when( 'sanitize_hex_color' )->justReturn( '#ff0000' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'bg_color' => '#ff0000' ) );

        $this->controller->save_settings( $request );

        $this->assertSame( '#ff0000', $updateCalls['cdw_bg_color'] );
    }

    public function test_save_settings_invalid_bg_color_stored_as_empty(): void {
        Functions\when( 'sanitize_hex_color' )->justReturn( '' );

        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'bg_color' => 'invalid' ) );

        $this->controller->save_settings( $request );

        $this->assertSame( '', $updateCalls['cdw_bg_color'] );
    }

    // -----------------------------------------------------------------------
    // save_settings() — boolean fields
    // -----------------------------------------------------------------------

    public function test_save_settings_cli_enabled_false_stored(): void {
        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'cli_enabled' => false ) );

        $this->controller->save_settings( $request );

        $this->assertFalse( $updateCalls['cdw_cli_enabled'] );
    }

    public function test_save_settings_delete_on_uninstall_false_stored(): void {
        $updateCalls = array();
        Functions\when( 'update_option' )->alias( function( $k, $v ) use ( &$updateCalls ) {
            $updateCalls[ $k ] = $v;
        } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'delete_on_uninstall' => false ) );

        $this->controller->save_settings( $request );

        $this->assertFalse( $updateCalls['cdw_delete_on_uninstall'] );
    }

    public function test_save_settings_absent_field_does_not_call_update_option(): void {
        $updateCalled = false;
        Functions\when( 'update_option' )->alias( function() use ( &$updateCalled ) {
            $updateCalled = true;
        } );

        $request = new \WP_REST_Request();
        // Provide an empty settings object — no fields → update_option never called
        $request->set_json_params( array() );

        $this->controller->save_settings( $request );

        $this->assertFalse( $updateCalled );
    }

    public function test_save_settings_all_valid_fields_returns_success(): void {
        Functions\when( 'sanitize_email' )->justReturn( 'a@b.com' );
        Functions\when( 'is_email' )->justReturn( true );
        Functions\when( 'esc_url_raw' )->justReturn( 'https://docs.example.com' );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_hex_color' )->returnArg();
        Functions\when( 'update_option' )->justReturn( true );

        $request = new \WP_REST_Request();
        $request->set_json_params( array(
            'email'                  => 'a@b.com',
            'docs_url'               => 'https://docs.example.com',
            'font_size'              => '14',
            'bg_color'               => '#ffffff',
            'header_bg_color'        => '#000000',
            'header_text_color'      => '#333333',
            'cli_enabled'            => true,
            'remove_default_widgets' => true,
            'delete_on_uninstall'    => true,
        ) );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $data = $result->get_data();
        $this->assertTrue( $data['success'] );
    }

    // -----------------------------------------------------------------------
    // save_settings() — nonce verification
    // -----------------------------------------------------------------------

    public function test_save_settings_returns_401_when_nonce_missing(): void {
        $_SERVER = array();

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'email' => 'test@example.com' ) );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_missing_nonce', $result->get_error_code() );
        $this->assertSame( 401, $result->get_error_data()['status'] );
    }

    public function test_save_settings_returns_403_when_nonce_invalid(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = 'invalid_nonce';
        Functions\when( 'wp_verify_nonce' )->justReturn( false );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'email' => 'test@example.com' ) );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rest_invalid_nonce', $result->get_error_code() );
        $this->assertSame( 403, $result->get_error_data()['status'] );
    }

    public function test_save_settings_proceeds_when_nonce_valid(): void {
        $_SERVER['HTTP_X_WP_NONCE'] = 'valid_nonce';
        Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'sanitize_email' )->justReturn( 'test@example.com' );
        Functions\when( 'is_email' )->justReturn( true );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'email' => 'test@example.com' ) );

        $result = $this->controller->save_settings( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $result );
    }
}

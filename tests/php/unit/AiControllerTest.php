<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-ai-controller.php';

class AiControllerTest extends CDWTestCase {

    private \CDW_AI_Controller $controller;

    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['wpdb'] = new \wpdb();
        $this->controller = new \CDW_AI_Controller();

        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Stubs the minimum WP functions needed to fully build AI user settings. */
    private function stubDefaultUserSettings( int $userId = 1, string $provider = 'openai', bool $hasKey = false ): void {
        Functions\when( 'get_current_user_id' )->justReturn( $userId );
        Functions\when( 'get_user_meta' )->alias( function( $uid, $key ) use ( $provider, $hasKey ) {
            if ( 'cdw_ai_provider' === $key ) {
                return $provider;
            }
            if ( 'cdw_ai_model' === $key ) {
                return 'gpt-4o-mini';
            }
            if ( 'cdw_ai_execution_mode' === $key ) {
                return 'confirm';
            }
            if ( 'cdw_ai_base_url' === $key ) {
                return '';
            }
            // cdw_ai_api_key_* (encrypted key storage)
            if ( str_contains( (string) $key, 'cdw_ai_api_key_' ) ) {
                return $hasKey ? 'encrypted-fake-key' : '';
            }
            if ( 'cdw_ai_token_usage' === $key ) {
                return array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'request_count' => 0 );
            }
            return '';
        } );
    }

    // -----------------------------------------------------------------------
    // get_ai_settings()
    // -----------------------------------------------------------------------

    public function test_get_ai_settings_returns_provider_and_model_for_user(): void {
        $this->stubDefaultUserSettings( 1, 'anthropic' );

        $response = $this->controller->get_ai_settings();
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'provider', $data['data'] );
        $this->assertSame( 'anthropic', $data['data']['provider'] );
    }

    public function test_get_ai_settings_response_contains_has_key_field(): void {
        $this->stubDefaultUserSettings( 1, 'openai', false );

        $response = $this->controller->get_ai_settings();
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'has_key', $data['data'] );
        $this->assertFalse( $data['data']['has_key'] );
    }

    public function test_get_ai_settings_has_key_is_true_when_key_stored(): void {
        $this->stubDefaultUserSettings( 1, 'openai', true );

        $data = $this->controller->get_ai_settings()->get_data();

        $this->assertTrue( $data['data']['has_key'] );
    }

    // -----------------------------------------------------------------------
    // save_ai_settings()
    // -----------------------------------------------------------------------

    public function test_save_ai_settings_returns_400_when_body_is_null(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( null );

        $result = $this->controller->save_ai_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_save_ai_settings_returns_400_when_body_is_not_array(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( 'not-an-array' );

        $result = $this->controller->save_ai_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_save_ai_settings_returns_error_for_invalid_provider(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'provider' => 'unsupported-ai-provider' ) );

        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->controller->save_ai_settings( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_provider', $result->get_error_code() );
    }

    public function test_save_ai_settings_returns_success_response_for_valid_provider(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'is_wp_error' )->justReturn( false );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'provider' => 'openai', 'model' => 'gpt-4o' ) );

        $result = $this->controller->save_ai_settings( $request );

        $this->assertNotInstanceOf( \WP_Error::class, $result );
        $this->assertInstanceOf( \WP_REST_Response::class, $result );
        $data = $result->get_data();
        $this->assertTrue( $data['data']['saved'] );
    }

    // -----------------------------------------------------------------------
    // chat()
    // -----------------------------------------------------------------------

    public function test_chat_returns_400_when_body_is_null(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( null );

        $result = $this->controller->chat( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_chat_returns_400_when_message_is_empty(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'message' => '   ' ) );

        $result = $this->controller->chat( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    public function test_chat_returns_400_when_message_key_is_absent(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( array() );

        $result = $this->controller->chat( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_chat_returns_429_when_rate_limited(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->stubDefaultUserSettings( 1 );
        // Rate limit: count already at RATE_LIMIT_COUNT (30).
        Functions\when( 'get_transient' )->justReturn( \CDW_AI_Service::RATE_LIMIT_COUNT );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'message' => 'Hello AI' ) );

        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->controller->chat( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 429, $result->get_error_data()['status'] );
    }

    public function test_chat_returns_400_when_no_api_key_stored(): void {
        $this->stubDefaultUserSettings( 1, 'openai', false );
        // Rate limit passes (under limit).
        Functions\when( 'get_transient' )->justReturn( 0 );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'message' => 'Hello AI' ) );

        $result = $this->controller->chat( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    // -----------------------------------------------------------------------
    // execute_tool()
    // -----------------------------------------------------------------------

    public function test_execute_tool_returns_400_when_body_is_null(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( null );

        $result = $this->controller->execute_tool( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_execute_tool_returns_400_when_tool_name_is_empty(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'tool_name' => '' ) );

        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->controller->execute_tool( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    public function test_execute_tool_returns_400_for_unknown_tool(): void {
        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'tool_name' => 'nonexistent_tool' ) );

        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->controller->execute_tool( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    // -----------------------------------------------------------------------
    // get_providers()
    // -----------------------------------------------------------------------

    public function test_get_providers_returns_openai_anthropic_google_and_custom(): void {
        $response = $this->controller->get_providers();
        $data     = $response->get_data();

        $this->assertArrayHasKey( 'openai',    $data['data'] );
        $this->assertArrayHasKey( 'anthropic', $data['data'] );
        $this->assertArrayHasKey( 'google',    $data['data'] );
        $this->assertArrayHasKey( 'custom',    $data['data'] );
    }

    public function test_get_providers_each_has_label_and_models(): void {
        $providers = $this->controller->get_providers()->get_data()['data'];

        foreach ( $providers as $key => $provider ) {
            $this->assertArrayHasKey( 'label',  $provider, "Provider $key missing 'label'" );
            $this->assertArrayHasKey( 'models', $provider, "Provider $key missing 'models'" );
            $this->assertNotEmpty( $provider['models'], "Provider $key has no models" );
        }
    }

    // -----------------------------------------------------------------------
    // test_connection()
    // -----------------------------------------------------------------------

    public function test_test_connection_returns_400_when_no_api_key_for_provider(): void {
        $this->stubDefaultUserSettings( 1, 'openai', false );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'esc_url_raw' )->returnArg();

        $request = new \WP_REST_Request();
        $request->set_json_params( array( 'provider' => 'openai', 'model' => 'gpt-4o' ) );

        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->controller->test_connection( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 400, $result->get_error_data()['status'] );
    }

    // -----------------------------------------------------------------------
    // get_usage()
    // -----------------------------------------------------------------------

    public function test_get_usage_returns_usage_data_for_current_user(): void {
        $this->stubDefaultUserSettings( 1 );
        // Override usage return.
        Functions\when( 'get_user_meta' )->alias( function( $uid, $key ) {
            if ( 'cdw_ai_token_usage' === $key ) {
                return array(
                    'prompt_tokens'     => 100,
                    'completion_tokens' => 50,
                    'total_tokens'      => 150,
                    'request_count'     => 5,
                );
            }
            return '';
        } );

        $response = $this->controller->get_usage();
        $usage    = $response->get_data()['data'];

        $this->assertSame( 100, $usage['prompt_tokens'] );
        $this->assertSame( 50,  $usage['completion_tokens'] );
        $this->assertSame( 5,   $usage['request_count'] );
    }
}

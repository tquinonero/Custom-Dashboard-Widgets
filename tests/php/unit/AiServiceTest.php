<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

class AiServiceTest extends CDWTestCase {

    // -----------------------------------------------------------------------
    // get_providers()
    // -----------------------------------------------------------------------

    public function test_get_providers_returns_four_providers(): void {
        $providers = \CDW_AI_Service::get_providers();

        $this->assertArrayHasKey( 'openai',    $providers );
        $this->assertArrayHasKey( 'anthropic', $providers );
        $this->assertArrayHasKey( 'google',    $providers );
        $this->assertArrayHasKey( 'custom',    $providers );
    }

    public function test_get_providers_each_has_label_and_models(): void {
        foreach ( \CDW_AI_Service::get_providers() as $key => $provider ) {
            $this->assertArrayHasKey( 'label',  $provider, "Missing 'label' for $key" );
            $this->assertArrayHasKey( 'models', $provider, "Missing 'models' for $key" );
            $this->assertIsArray( $provider['models'] );
            $this->assertNotEmpty( $provider['models'] );
        }
    }

    public function test_get_providers_openai_has_gpt4o_model(): void {
        $models  = \CDW_AI_Service::get_providers()['openai']['models'];
        $ids     = array_column( $models, 'id' );

        $this->assertContains( 'gpt-4o', $ids );
    }

    public function test_get_providers_anthropic_has_claude_model(): void {
        $models = \CDW_AI_Service::get_providers()['anthropic']['models'];
        $ids    = array_column( $models, 'id' );

        $this->assertNotEmpty( array_filter( $ids, fn( $id ) => str_contains( $id, 'claude' ) ) );
    }

    // -----------------------------------------------------------------------
    // get_tool_definitions()
    // -----------------------------------------------------------------------

    public function test_get_tool_definitions_returns_non_empty_array(): void {
        $tools = \CDW_AI_Service::get_tool_definitions();

        $this->assertIsArray( $tools );
        $this->assertNotEmpty( $tools );
    }

    public function test_get_tool_definitions_each_has_name_description_and_parameters(): void {
        foreach ( \CDW_AI_Service::get_tool_definitions() as $tool ) {
            $this->assertArrayHasKey( 'name',        $tool );
            $this->assertArrayHasKey( 'description', $tool );
            $this->assertArrayHasKey( 'parameters',  $tool );
        }
    }

    public function test_get_tool_definitions_contains_plugin_list(): void {
        $names = array_column( \CDW_AI_Service::get_tool_definitions(), 'name' );

        $this->assertContains( 'plugin_list', $names );
    }

    public function test_get_tool_definitions_contains_cache_flush(): void {
        $names = array_column( \CDW_AI_Service::get_tool_definitions(), 'name' );

        $this->assertContains( 'cache_flush', $names );
    }

    // -----------------------------------------------------------------------
    // encrypt_api_key() / decrypt_api_key() — roundtrip
    // -----------------------------------------------------------------------

    public function test_encrypt_then_decrypt_returns_original_plaintext(): void {
        $plaintext  = 'sk-test-api-key-12345';
        $ciphertext = \CDW_AI_Service::encrypt_api_key( $plaintext );
        $decrypted  = \CDW_AI_Service::decrypt_api_key( $ciphertext );

        $this->assertSame( $plaintext, $decrypted );
    }

    public function test_encrypt_returns_non_empty_string(): void {
        $result = \CDW_AI_Service::encrypt_api_key( 'my-secret-key' );

        $this->assertIsString( $result );
        $this->assertNotEmpty( $result );
    }

    public function test_encrypt_produces_different_ciphertexts_for_same_plaintext(): void {
        // Each call generates a fresh IV, so ciphertexts differ.
        $ct1 = \CDW_AI_Service::encrypt_api_key( 'same-key' );
        $ct2 = \CDW_AI_Service::encrypt_api_key( 'same-key' );

        $this->assertNotSame( $ct1, $ct2 );
    }

    public function test_decrypt_returns_empty_string_for_empty_ciphertext(): void {
        $this->assertSame( '', \CDW_AI_Service::decrypt_api_key( '' ) );
    }

    public function test_decrypt_returns_empty_string_for_garbage_input(): void {
        $this->assertSame( '', \CDW_AI_Service::decrypt_api_key( 'not-valid-base64!!!' ) );
    }

    // -----------------------------------------------------------------------
    // check_ai_rate_limit()
    // -----------------------------------------------------------------------

    public function test_rate_limit_returns_true_when_under_limit(): void {
        Functions\when( 'get_transient' )->justReturn( 0 );
        Functions\when( 'set_transient' )->justReturn( true );

        $result = \CDW_AI_Service::check_ai_rate_limit( 1 );

        $this->assertTrue( $result );
    }

    public function test_rate_limit_increments_counter_via_set_transient(): void {
        Functions\when( 'get_transient' )->justReturn( 5 );

        $capturedCount = null;
        Functions\when( 'set_transient' )->alias(
            function( $key, $count ) use ( &$capturedCount ) {
                $capturedCount = $count;
            }
        );

        \CDW_AI_Service::check_ai_rate_limit( 1 );

        $this->assertSame( 6, $capturedCount );
    }

    public function test_rate_limit_returns_wp_error_when_at_limit(): void {
        Functions\when( 'get_transient' )->justReturn( \CDW_AI_Service::RATE_LIMIT_COUNT );

        $result = \CDW_AI_Service::check_ai_rate_limit( 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'ai_rate_limited', $result->get_error_code() );
        $this->assertSame( 429, $result->get_error_data()['status'] );
    }

    public function test_rate_limit_uses_user_specific_transient_key(): void {
        Functions\when( 'get_transient' )->justReturn( 0 );

        $capturedKey = null;
        Functions\when( 'set_transient' )->alias(
            function( $key ) use ( &$capturedKey ) {
                $capturedKey = $key;
            }
        );

        \CDW_AI_Service::check_ai_rate_limit( 42 );

        $this->assertStringContainsString( '42', $capturedKey );
    }

    // -----------------------------------------------------------------------
    // get_user_ai_settings()
    // -----------------------------------------------------------------------

    public function test_get_user_ai_settings_defaults_to_openai_when_nothing_saved(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        $this->assertSame( 'openai', $settings['provider'] );
    }

    public function test_get_user_ai_settings_defaults_model_to_gpt4o_mini(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        $this->assertSame( 'gpt-4o-mini', $settings['model'] );
    }

    public function test_get_user_ai_settings_defaults_execution_mode_to_confirm(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        $this->assertSame( 'confirm', $settings['execution_mode'] );
    }

    public function test_get_user_ai_settings_returns_saved_provider(): void {
        Functions\when( 'get_user_meta' )->alias( function( $uid, $key ) {
            if ( 'cdw_ai_provider' === $key ) {
                return 'anthropic';
            }
            return '';
        } );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        $this->assertSame( 'anthropic', $settings['provider'] );
    }

    public function test_get_user_ai_settings_response_includes_all_expected_keys(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        foreach ( array( 'provider', 'model', 'execution_mode', 'has_key', 'base_url', 'usage' ) as $key ) {
            $this->assertArrayHasKey( $key, $settings, "Missing key '$key'" );
        }
    }

    public function test_get_user_ai_settings_has_key_false_when_no_key_stored(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        $this->assertFalse( $settings['has_key'] );
    }

    public function test_get_user_ai_settings_has_key_true_when_key_stored(): void {
        Functions\when( 'get_user_meta' )->alias( function( $uid, $key ) {
            // Any api_key meta key returns a non-empty encrypted value.
            if ( str_contains( (string) $key, 'cdw_ai_api_key_' ) ) {
                return 'some-encrypted-value';
            }
            return '';
        } );

        $settings = \CDW_AI_Service::get_user_ai_settings( 1 );

        $this->assertTrue( $settings['has_key'] );
    }

    public function test_get_user_ai_settings_usage_defaults_to_zeroes_when_not_saved(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $usage = \CDW_AI_Service::get_user_ai_settings( 1 )['usage'];

        $this->assertSame( 0, $usage['prompt_tokens'] );
        $this->assertSame( 0, $usage['completion_tokens'] );
        $this->assertSame( 0, $usage['total_tokens'] );
        $this->assertSame( 0, $usage['request_count'] );
    }

    // -----------------------------------------------------------------------
    // save_user_ai_settings() — validation
    // -----------------------------------------------------------------------

    public function test_save_user_ai_settings_returns_error_for_invalid_provider(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_Service::save_user_ai_settings( 1, array( 'provider' => 'bad-provider' ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_provider', $result->get_error_code() );
    }

    public function test_save_user_ai_settings_returns_error_for_invalid_execution_mode(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_Service::save_user_ai_settings( 1, array( 'execution_mode' => 'invalid-mode' ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_execution_mode', $result->get_error_code() );
    }

    public function test_save_user_ai_settings_returns_true_for_valid_provider(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_Service::save_user_ai_settings( 1, array( 'provider' => 'openai' ) );

        $this->assertTrue( $result );
    }

    public function test_save_user_ai_settings_returns_true_for_auto_execution_mode(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_Service::save_user_ai_settings( 1, array( 'execution_mode' => 'auto' ) );

        $this->assertTrue( $result );
    }

    public function test_save_user_ai_settings_returns_error_for_non_http_base_url(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'esc_url_raw' )->returnArg();

        $result = \CDW_AI_Service::save_user_ai_settings( 1, array( 'base_url' => 'ftp://not-valid' ) );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_base_url', $result->get_error_code() );
    }

    public function test_save_user_ai_settings_accepts_valid_https_base_url(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'esc_url_raw' )->returnArg();

        $result = \CDW_AI_Service::save_user_ai_settings( 1, array( 'base_url' => 'https://my-openai-proxy.com' ) );

        $this->assertTrue( $result );
    }
}

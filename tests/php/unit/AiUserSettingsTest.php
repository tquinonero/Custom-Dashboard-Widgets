<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-encryption.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-user-settings.php';

class AiUserSettingsTest extends CDWTestCase {

    public function test_get_settings_defaults_to_openai_when_nothing_saved(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        $this->assertSame( 'openai', $settings['provider'] );
    }

    public function test_get_settings_defaults_model_to_gpt4o_mini(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        $this->assertSame( 'gpt-4o-mini', $settings['model'] );
    }

    public function test_get_settings_defaults_execution_mode_to_confirm(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        $this->assertSame( 'confirm', $settings['execution_mode'] );
    }

    public function test_get_settings_returns_saved_provider(): void {
        Functions\when( 'get_user_meta' )->alias( function( $uid, $key ) {
            if ( 'cdw_ai_provider' === $key ) {
                return 'anthropic';
            }
            return '';
        } );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        $this->assertSame( 'anthropic', $settings['provider'] );
    }

    public function test_get_settings_response_includes_all_expected_keys(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        foreach ( array( 'provider', 'model', 'execution_mode', 'has_key', 'base_url', 'usage' ) as $key ) {
            $this->assertArrayHasKey( $key, $settings, "Missing key '$key'" );
        }
    }

    public function test_get_settings_has_key_false_when_no_key_stored(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        $this->assertFalse( $settings['has_key'] );
    }

    public function test_get_settings_has_key_true_when_key_stored(): void {
        Functions\when( 'get_user_meta' )->alias( function( $uid, $key ) {
            if ( str_contains( (string) $key, 'cdw_ai_api_key_' ) ) {
                return 'some-encrypted-value';
            }
            return '';
        } );

        $settings = \CDW_AI_User_Settings::get_settings( 1 );

        $this->assertTrue( $settings['has_key'] );
    }

    public function test_save_settings_returns_error_for_invalid_provider(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_User_Settings::save_settings( 1, array( 'provider' => 'bad-provider' ), array() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_provider', $result->get_error_code() );
    }

    public function test_save_settings_returns_error_for_invalid_execution_mode(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_User_Settings::save_settings( 1, array( 'execution_mode' => 'invalid-mode' ), array() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_execution_mode', $result->get_error_code() );
    }

    public function test_save_settings_returns_true_for_valid_provider(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_User_Settings::save_settings( 1, array( 'provider' => 'openai' ), array() );

        $this->assertTrue( $result );
    }

    public function test_save_settings_returns_error_for_non_http_base_url(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'esc_url_raw' )->returnArg();

        $result = \CDW_AI_User_Settings::save_settings( 1, array( 'base_url' => 'ftp://not-valid' ), array() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_base_url', $result->get_error_code() );
    }

    public function test_save_settings_accepts_valid_https_base_url(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'esc_url_raw' )->returnArg();

        $result = \CDW_AI_User_Settings::save_settings( 1, array( 'base_url' => 'https://my-openai-proxy.com' ), array() );

        $this->assertTrue( $result );
    }

    public function test_get_decrypted_api_key_returns_empty_when_not_set(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $result = \CDW_AI_User_Settings::get_decrypted_api_key( 1, 'openai' );

        $this->assertSame( '', $result );
    }
}

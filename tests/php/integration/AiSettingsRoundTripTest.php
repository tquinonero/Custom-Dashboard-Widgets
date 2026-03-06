<?php

namespace CDW\Tests\Integration;

/**
 * Integration tests for the AI settings endpoints:
 *  - GET/POST round-trip: saved values are returned verbatim.
 *  - Permission enforcement: subscribers get 403.
 *  - Validation: unknown provider returns 400.
 *  - GET /ai/providers returns expected structure.
 *  - GET /ai/usage returns usage shape.
 *
 * @group integration
 */
class AiSettingsRoundTripTest extends \WP_Test_REST_TestCase {

    /** @var int */
    private int $admin_id;

    /** @var int */
    private int $subscriber_id;

    /** @var \WP_REST_Server */
    private $server;

    public function set_up(): void {
        parent::set_up();

        global $wp_rest_server;
        $this->server = $wp_rest_server = new \WP_REST_Server();
        do_action( 'rest_api_init' );

        $this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
    }

    public function tear_down(): void {
        // Clean up user meta written during tests.
        $meta_keys = array(
            'cdw_ai_provider',
            'cdw_ai_model',
            'cdw_ai_execution_mode',
            'cdw_ai_base_url',
            'cdw_ai_usage',
            'cdw_ai_key_openai',
            'cdw_ai_key_anthropic',
            'cdw_ai_key_google',
            'cdw_ai_key_custom',
        );
        foreach ( array( $this->admin_id, $this->subscriber_id ) as $uid ) {
            foreach ( $meta_keys as $key ) {
                delete_user_meta( $uid, $key );
            }
        }
        parent::tear_down();
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function dispatch( string $method, string $route, array $body = array() ): \WP_REST_Response {
        $request = new \WP_REST_Request( strtoupper( $method ), $route );
        if ( ! empty( $body ) ) {
            $request->set_header( 'Content-Type', 'application/json' );
            $request->set_body( wp_json_encode( $body ) );
        }
        $response = $this->server->dispatch( $request );
        return rest_ensure_response( $response );
    }

    // -----------------------------------------------------------------------
    // Round-trip
    // -----------------------------------------------------------------------

    public function test_post_then_get_returns_saved_provider_and_model(): void {
        wp_set_current_user( $this->admin_id );

        $post = $this->dispatch( 'POST', '/cdw/v1/ai/settings', array(
            'provider' => 'anthropic',
            'model'    => 'claude-3-5-sonnet-20241022',
        ) );
        $this->assertSame( 200, $post->get_status() );
        $this->assertTrue( $post->get_data()['success'] );

        $get  = $this->dispatch( 'GET', '/cdw/v1/ai/settings' );
        $data = $get->get_data()['data'];
        $this->assertSame( 'anthropic',                    $data['provider'] );
        $this->assertSame( 'claude-3-5-sonnet-20241022',   $data['model'] );
    }

    public function test_post_execution_mode_auto_is_persisted(): void {
        wp_set_current_user( $this->admin_id );

        $this->dispatch( 'POST', '/cdw/v1/ai/settings', array( 'execution_mode' => 'auto' ) );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/settings' )->get_data()['data'];
        $this->assertSame( 'auto', $data['execution_mode'] );
    }

    public function test_get_defaults_when_nothing_saved(): void {
        wp_set_current_user( $this->admin_id );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/settings' )->get_data()['data'];
        $this->assertSame( 'openai',     $data['provider'] );
        $this->assertSame( 'gpt-4o-mini', $data['model'] );
        $this->assertSame( 'confirm',    $data['execution_mode'] );
        $this->assertFalse( $data['has_key'] );
    }

    public function test_get_has_key_true_after_api_key_saved(): void {
        wp_set_current_user( $this->admin_id );

        $this->dispatch( 'POST', '/cdw/v1/ai/settings', array(
            'provider' => 'openai',
            'api_key'  => 'sk-test-key-12345',
        ) );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/settings' )->get_data()['data'];
        $this->assertTrue( $data['has_key'] );
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    public function test_post_unknown_provider_returns_400(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/ai/settings', array(
            'provider' => 'not-a-real-provider',
        ) );
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_post_invalid_execution_mode_returns_400(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/ai/settings', array(
            'execution_mode' => 'yolo',
        ) );
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_post_non_https_base_url_returns_400(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/ai/settings', array(
            'base_url' => 'ftp://notvalid.com',
        ) );
        $this->assertSame( 400, $response->get_status() );
    }

    // -----------------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------------

    public function test_get_ai_settings_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'GET', '/cdw/v1/ai/settings' )->get_status() );
    }

    public function test_post_ai_settings_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'POST', '/cdw/v1/ai/settings', array( 'provider' => 'openai' ) )->get_status() );
    }

    public function test_get_ai_providers_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'GET', '/cdw/v1/ai/providers' )->get_status() );
    }

    public function test_get_ai_usage_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'GET', '/cdw/v1/ai/usage' )->get_status() );
    }

    // -----------------------------------------------------------------------
    // GET /ai/providers
    // -----------------------------------------------------------------------

    public function test_get_providers_returns_openai_anthropic_google_custom(): void {
        wp_set_current_user( $this->admin_id );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/providers' )->get_data()['data'];
        $this->assertArrayHasKey( 'openai',    $data );
        $this->assertArrayHasKey( 'anthropic', $data );
        $this->assertArrayHasKey( 'google',    $data );
        $this->assertArrayHasKey( 'custom',    $data );
    }

    public function test_get_providers_each_has_label_and_models(): void {
        wp_set_current_user( $this->admin_id );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/providers' )->get_data()['data'];
        foreach ( $data as $slug => $provider ) {
            $this->assertArrayHasKey( 'label',  $provider, "Provider '$slug' missing label." );
            $this->assertArrayHasKey( 'models', $provider, "Provider '$slug' missing models." );
        }
    }

    // -----------------------------------------------------------------------
    // GET /ai/usage
    // -----------------------------------------------------------------------

    public function test_get_usage_returns_expected_keys(): void {
        wp_set_current_user( $this->admin_id );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/usage' )->get_data()['data'];
        $this->assertArrayHasKey( 'prompt_tokens',     $data );
        $this->assertArrayHasKey( 'completion_tokens', $data );
        $this->assertArrayHasKey( 'total_tokens',      $data );
        $this->assertArrayHasKey( 'request_count',     $data );
    }

    public function test_get_usage_defaults_to_zero(): void {
        wp_set_current_user( $this->admin_id );

        $data = $this->dispatch( 'GET', '/cdw/v1/ai/usage' )->get_data()['data'];
        $this->assertSame( 0, (int) $data['prompt_tokens'] );
        $this->assertSame( 0, (int) $data['completion_tokens'] );
        $this->assertSame( 0, (int) $data['total_tokens'] );
        $this->assertSame( 0, (int) $data['request_count'] );
    }
}

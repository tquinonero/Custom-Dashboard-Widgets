<?php

namespace CDW\Tests\Integration;

/**
 * Integration tests for CDW settings: GET ↔ POST round-trip, permissions,
 * and input validation.
 *
 * @group integration
 */
class SettingsRoundTripTest extends \WP_Test_REST_TestCase {

    /** @var int Admin user ID */
    private int $admin_id;

    /** @var int Subscriber user ID */
    private int $subscriber_id;

    /** @var \WP_REST_Server */
    private $server;

    public function set_up(): void {
        parent::set_up();

        global $wp_rest_server;
        $this->server = $wp_rest_server = new \WP_REST_Server();
        do_action( 'rest_api_init' );

        // Pre-seed all CDW options with their defaults so that
        // update_option(…, false) can actually persist false values.
        // WordPress's add_option() silently ignores false values when the
        // option doesn't yet exist.
        \CDW_activate();

        // Create test users.
        $this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
    }

    public function tear_down(): void {
        global $wpdb;

        // Clean up options set during the test.
        $options = array(
            'cdw_support_email', 'cdw_docs_url', 'cdw_font_size',
            'cdw_bg_color', 'cdw_header_bg_color', 'cdw_header_text_color',
            'cdw_cli_enabled', 'cdw_remove_default_widgets', 'cdw_delete_on_uninstall',
            'cdw_db_version',
        );
        foreach ( $options as $opt ) {
            delete_option( $opt );
        }

        // Drop the audit table created by CDW_activate() in set_up().
        $table_name = $wpdb->prefix . 'cdw_cli_logs';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );

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

        // Unwrap WP_Error if the server returned it.
        if ( is_wp_error( $response ) ) {
            return new \WP_REST_Response(
                array( 'code' => $response->get_error_code(), 'message' => $response->get_error_message() ),
                $response->get_error_data()['status'] ?? 500
            );
        }

        return rest_ensure_response( $response );
    }

    // -----------------------------------------------------------------------
    // Tests — round-trip
    // -----------------------------------------------------------------------

    public function test_post_then_get_returns_saved_values(): void {
        wp_set_current_user( $this->admin_id );

        $payload = array(
            'email'                  => 'support@example.com',
            'docs_url'               => 'https://docs.example.com',
            'font_size'              => '14',
            'bg_color'               => '#aabbcc',
            'header_bg_color'        => '#112233',
            'header_text_color'      => '#ffffff',
            'cli_enabled'            => true,
            'remove_default_widgets' => false,
            'delete_on_uninstall'    => false,
        );

        // POST settings.
        $post_response = $this->dispatch( 'POST', '/cdw/v1/settings', $payload );
        $this->assertSame( 200, $post_response->get_status(), 'POST /cdw/v1/settings should return 200.' );
        $post_data = $post_response->get_data();
        $this->assertTrue( $post_data['success'], 'Response should contain success:true.' );

        // GET settings back.
        $get_response = $this->dispatch( 'GET', '/cdw/v1/settings' );
        $this->assertSame( 200, $get_response->get_status() );
        $get_data = $get_response->get_data();

        $this->assertSame( 'support@example.com',  $get_data['email'] );
        $this->assertSame( 'https://docs.example.com', $get_data['docs_url'] );
        $this->assertSame( '14',                   $get_data['font_size'] );
        $this->assertSame( '#aabbcc',              $get_data['bg_color'] );
        $this->assertSame( '#112233',              $get_data['header_bg_color'] );
        $this->assertSame( '#ffffff',              $get_data['header_text_color'] );
        $this->assertTrue( (bool) $get_data['cli_enabled'] );
        // WordPress stores false as '' in the options table, so compare as bool.
        $this->assertFalse( (bool) $get_data['remove_default_widgets'] );
        $this->assertFalse( (bool) $get_data['delete_on_uninstall'] );
    }

    // -----------------------------------------------------------------------
    // Tests — permissions
    // -----------------------------------------------------------------------

    public function test_get_settings_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );

        $response = $this->dispatch( 'GET', '/cdw/v1/settings' );
        $this->assertSame( 403, $response->get_status() );
    }

    public function test_post_settings_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/settings', array( 'email' => 'x@example.com' ) );
        $this->assertSame( 403, $response->get_status() );
    }

    // -----------------------------------------------------------------------
    // Tests — validation
    // -----------------------------------------------------------------------

    /**
     * A request whose body is not a JSON object (non-array after decode) causes
     * save_settings() to return 400.  This covers the `! is_array( $settings )`
     * guard — the path that is impossible to reach via sanitize_email alone
     * because sanitize_email converts any invalid email to '' (empty string).
     */
    public function test_post_non_array_body_returns_400(): void {
        wp_set_current_user( $this->admin_id );

        $request = new \WP_REST_Request( 'POST', '/cdw/v1/settings' );
        // Send a JSON scalar (not an object) so get_json_params() returns null.
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( '"just-a-string"' );   // valid JSON but not an array
        $response = $this->server->dispatch( $request );

        $this->assertSame( 400, $response->get_status() );
    }

    /**
     * An invalid email like 'notanemail' is silently cleared by sanitize_email()
     * (returns '' because there is no '@'), so save_settings() succeeds with 200
     * and stores '' for the email option.
     */
    public function test_post_invalid_email_is_cleared_not_rejected(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/settings', array( 'email' => 'notanemail' ) );
        $this->assertSame( 200, $response->get_status(), 'sanitize_email strips invalid emails silently.' );
        $this->assertSame( '', get_option( 'cdw_support_email' ) );
    }
}

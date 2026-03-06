<?php

namespace CDW\Tests\Integration;

/**
 * Integration tests for the CDW CLI REST endpoints:
 *  - POST /cdw/v1/cli/execute: runs a command, returns output, writes audit log.
 *  - GET  /cdw/v1/cli/history: returns entries written by execute.
 *  - DELETE /cdw/v1/cli/history: clears history.
 *  - GET  /cdw/v1/cli/commands: returns autocomplete catalogue.
 *  - Permission enforcement: subscribers get 403 on all CLI routes.
 *
 * @group integration
 */
class CliRoundTripTest extends \WP_Test_REST_TestCase {

    /** @var int */
    private int $admin_id;

    /** @var int */
    private int $subscriber_id;

    /** @var \WP_REST_Server */
    private $server;

    /** @var string Audit log table name */
    private string $table_name;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        // Ensure CDW_activate() is available (loaded by integration bootstrap).
    }

    public function set_up(): void {
        parent::set_up();

        global $wp_rest_server, $wpdb;
        $this->server     = $wp_rest_server = new \WP_REST_Server();
        $this->table_name = $wpdb->prefix . 'cdw_cli_logs';

        do_action( 'rest_api_init' );

        // Ensure the audit table exists and CLI is enabled.
        CDW_activate();
        update_option( 'cdw_cli_enabled', true );

        $this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
        $this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
    }

    public function tear_down(): void {
        global $wpdb;

        // Wipe history meta for test users.
        delete_user_meta( $this->admin_id,      'cdw_cli_history' );
        delete_user_meta( $this->subscriber_id, 'cdw_cli_history' );

        // Drop audit table and version option so next test starts clean.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$this->table_name}`" );
        delete_option( 'cdw_db_version' );
        delete_option( 'cdw_cli_enabled' );

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
    // POST /cdw/v1/cli/execute
    // -----------------------------------------------------------------------

    public function test_execute_plugin_list_returns_success(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/cli/execute', array( 'command' => 'plugin list' ) );

        $this->assertSame( 200, $response->get_status() );
        $data = $response->get_data();
        $this->assertTrue( $data['success'], 'plugin list should succeed.' );
        $this->assertNotEmpty( $data['output'], 'Output should not be empty.' );
    }

    public function test_execute_writes_audit_log_row(): void {
        wp_set_current_user( $this->admin_id );

        global $wpdb;
        $before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table_name}`" ); // phpcs:ignore

        $this->dispatch( 'POST', '/cdw/v1/cli/execute', array( 'command' => 'plugin list' ) );

        $after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table_name}`" ); // phpcs:ignore
        $this->assertGreaterThan( $before, $after, 'execute() must write a row to the audit log.' );
    }

    public function test_execute_empty_command_returns_400(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/cli/execute', array( 'command' => '' ) );
        $this->assertSame( 400, $response->get_status() );
    }

    public function test_execute_missing_command_returns_400(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'POST', '/cdw/v1/cli/execute', array() );
        $this->assertSame( 400, $response->get_status() );
    }

    // -----------------------------------------------------------------------
    // GET /cdw/v1/cli/history (populated by execute)
    // -----------------------------------------------------------------------

    public function test_history_contains_entry_after_execute(): void {
        wp_set_current_user( $this->admin_id );

        // Execute a command so there is something in history.
        $this->dispatch( 'POST', '/cdw/v1/cli/execute', array( 'command' => 'cache flush' ) );

        $response = $this->dispatch( 'GET', '/cdw/v1/cli/history' );
        $this->assertSame( 200, $response->get_status() );

        $history = $response->get_data();
        $this->assertIsArray( $history );
        $this->assertNotEmpty( $history, 'History should contain at least one entry after execute.' );
        $this->assertArrayHasKey( 'command', $history[0] );
        $this->assertSame( 'cache flush', $history[0]['command'] );
    }

    public function test_history_is_empty_for_fresh_user(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'GET', '/cdw/v1/cli/history' );
        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( array(), $response->get_data() );
    }

    // -----------------------------------------------------------------------
    // DELETE /cdw/v1/cli/history
    // -----------------------------------------------------------------------

    public function test_delete_history_clears_entries(): void {
        wp_set_current_user( $this->admin_id );

        // Write an entry first.
        $this->dispatch( 'POST', '/cdw/v1/cli/execute', array( 'command' => 'cache flush' ) );

        // Now clear it.
        $delete_response = $this->dispatch( 'DELETE', '/cdw/v1/cli/history' );
        $this->assertSame( 200, $delete_response->get_status() );
        $this->assertTrue( $delete_response->get_data()['cleared'] );

        // Verify history is gone.
        $history = $this->dispatch( 'GET', '/cdw/v1/cli/history' )->get_data();
        $this->assertSame( array(), $history );
    }

    // -----------------------------------------------------------------------
    // GET /cdw/v1/cli/commands
    // -----------------------------------------------------------------------

    public function test_commands_returns_non_empty_catalogue(): void {
        wp_set_current_user( $this->admin_id );

        $response = $this->dispatch( 'GET', '/cdw/v1/cli/commands' );
        $this->assertSame( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertIsArray( $data );
        $this->assertNotEmpty( $data );
    }

    public function test_commands_each_entry_has_category_and_commands(): void {
        wp_set_current_user( $this->admin_id );

        $data = $this->dispatch( 'GET', '/cdw/v1/cli/commands' )->get_data();
        foreach ( $data as $group ) {
            $this->assertArrayHasKey( 'category', $group );
            $this->assertArrayHasKey( 'commands',  $group );
            $this->assertIsArray( $group['commands'] );
        }
    }

    // -----------------------------------------------------------------------
    // Permission enforcement
    // -----------------------------------------------------------------------

    public function test_execute_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'POST', '/cdw/v1/cli/execute', array( 'command' => 'plugin list' ) )->get_status() );
    }

    public function test_get_history_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'GET', '/cdw/v1/cli/history' )->get_status() );
    }

    public function test_delete_history_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'DELETE', '/cdw/v1/cli/history' )->get_status() );
    }

    public function test_get_commands_as_subscriber_returns_403(): void {
        wp_set_current_user( $this->subscriber_id );
        $this->assertSame( 403, $this->dispatch( 'GET', '/cdw/v1/cli/commands' )->get_status() );
    }
}

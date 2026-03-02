<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

class CliServiceCoreTest extends CDWTestCase {

    private \CDW_CLI_Service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \CDW_CLI_Service();
        // Reset static state between tests.
        $this->resetAuditTableConfirmed();
    }

    private function resetAuditTableConfirmed(): void {
        $ref = new \ReflectionClass( \CDW_CLI_Service::class );
        $prop = $ref->getProperty( 'audit_table_confirmed' );
        $prop->setAccessible( true );
        $prop->setValue( null, false );
    }

    /**
     * Set up the minimum mocks so execute() can run past all guards
     * and reach the command dispatcher.
     * Uses 'help' command which needs no additional WP function mocks.
     */
    private function stubExecuteCommonMocks( int $rateLimitCount = 0 ): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_cli_enabled' === $key ) {
                return true;
            }
            if ( 'cdw_db_version' === $key ) {
                return \CDW_CLI_Service::DB_VERSION;
            }
            return $default;
        } );
        Functions\when( 'get_transient' )->justReturn( $rateLimitCount );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $wpdb = \cdw_tests_reset_wpdb();
    }

    // -----------------------------------------------------------------------
    // is_cli_enabled()
    // -----------------------------------------------------------------------

    public function test_is_cli_enabled_returns_true_when_option_is_true(): void {
        Functions\when( 'get_option' )->justReturn( true );
        $this->assertTrue( $this->service->is_cli_enabled() );
    }

    public function test_is_cli_enabled_returns_false_when_option_is_false(): void {
        Functions\when( 'get_option' )->justReturn( false );
        $this->assertFalse( $this->service->is_cli_enabled() );
    }

    // -----------------------------------------------------------------------
    // is_option_protected()
    // -----------------------------------------------------------------------

    public function test_is_option_protected_returns_true_for_siteurl(): void {
        $this->assertTrue( $this->service->is_option_protected( 'siteurl' ) );
    }

    public function test_is_option_protected_returns_false_for_custom_option(): void {
        $this->assertFalse( $this->service->is_option_protected( 'my_custom_option' ) );
    }

    // -----------------------------------------------------------------------
    // check_rate_limit()
    // -----------------------------------------------------------------------

    public function test_rate_limit_allows_first_call_and_calls_set_transient_with_count_1(): void {
        Functions\when( 'get_transient' )->justReturn( false );

        $capturedCount = null;
        $capturedTtl   = null;
        Functions\when( 'set_transient' )->alias(
            function( $key, $count, $ttl ) use ( &$capturedCount, &$capturedTtl ) {
                $capturedCount = $count;
                $capturedTtl   = $ttl;
            }
        );

        $result = $this->service->check_rate_limit( 1 );

        $this->assertTrue( $result );
        $this->assertSame( 1, $capturedCount );
        $this->assertSame( \CDW_CLI_Service::RATE_LIMIT_WINDOW, $capturedTtl );
    }

    public function test_rate_limit_allows_count_5_and_increments_to_6(): void {
        Functions\when( 'get_transient' )->justReturn( 5 );

        $capturedCount = null;
        Functions\when( 'set_transient' )->alias(
            function( $key, $count, $ttl ) use ( &$capturedCount ) { $capturedCount = $count; }
        );

        $this->assertTrue( $this->service->check_rate_limit( 1 ) );
        $this->assertSame( 6, $capturedCount );
    }

    public function test_rate_limit_allows_count_19_and_increments_to_20(): void {
        Functions\when( 'get_transient' )->justReturn( 19 );

        $capturedCount = null;
        Functions\when( 'set_transient' )->alias(
            function( $key, $count, $ttl ) use ( &$capturedCount ) { $capturedCount = $count; }
        );

        $this->assertTrue( $this->service->check_rate_limit( 1 ) );
        $this->assertSame( 20, $capturedCount );
    }

    public function test_rate_limit_rejects_count_20_and_does_not_call_set_transient(): void {
        Functions\when( 'get_transient' )->justReturn( 20 );

        $setTransientCalled = false;
        Functions\when( 'set_transient' )->alias(
            function() use ( &$setTransientCalled ) { $setTransientCalled = true; }
        );

        $result = $this->service->check_rate_limit( 1 );

        $this->assertFalse( $result );
        $this->assertFalse( $setTransientCalled );
    }

    public function test_rate_limit_rejects_count_25(): void {
        Functions\when( 'get_transient' )->justReturn( 25 );
        Functions\when( 'set_transient' )->justReturn( true );

        $this->assertFalse( $this->service->check_rate_limit( 1 ) );
    }

    // -----------------------------------------------------------------------
    // add_to_history()
    // -----------------------------------------------------------------------

    public function test_add_to_history_with_empty_existing_stores_single_entry(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $capturedValue = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedValue ) { $capturedValue = $value; }
        );

        $this->service->add_to_history( 1, 'help', 'output', true );

        $stored = json_decode( $capturedValue, true );
        $this->assertCount( 1, $stored );
    }

    public function test_add_to_history_with_49_existing_stores_50_entries(): void {
        $existing = array_fill( 0, 49, array( 'command' => 'old', 'output' => '', 'success' => true, 'timestamp' => time() ) );
        Functions\when( 'get_user_meta' )->justReturn( json_encode( $existing ) );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $capturedValue = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedValue ) { $capturedValue = $value; }
        );

        $this->service->add_to_history( 1, 'new', 'output', true );

        $stored = json_decode( $capturedValue, true );
        $this->assertCount( 50, $stored );
    }

    public function test_add_to_history_with_50_existing_keeps_50_entries_oldest_dropped(): void {
        $existing = array_fill( 0, 50, array( 'command' => 'old', 'output' => '', 'success' => true, 'timestamp' => time() ) );
        Functions\when( 'get_user_meta' )->justReturn( json_encode( $existing ) );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $capturedValue = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedValue ) { $capturedValue = $value; }
        );

        $this->service->add_to_history( 1, 'newest', 'output', true );

        $stored = json_decode( $capturedValue, true );
        $this->assertCount( 50, $stored );
        $this->assertSame( 'newest', $stored[0]['command'] );
    }

    public function test_add_to_history_entry_has_required_keys(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

        $capturedValue = null;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$capturedValue ) { $capturedValue = $value; }
        );

        $this->service->add_to_history( 1, 'help', 'some output', true );

        $stored = json_decode( $capturedValue, true );
        $entry  = $stored[0];
        $this->assertArrayHasKey( 'command',   $entry );
        $this->assertArrayHasKey( 'output',    $entry );
        $this->assertArrayHasKey( 'success',   $entry );
        $this->assertArrayHasKey( 'timestamp', $entry );
    }

    // -----------------------------------------------------------------------
    // get_history()
    // -----------------------------------------------------------------------

    public function test_get_history_returns_empty_array_for_empty_meta(): void {
        Functions\when( 'get_user_meta' )->justReturn( '' );
        $this->assertSame( array(), $this->service->get_history( 1 ) );
    }

    public function test_get_history_returns_parsed_array_for_valid_json(): void {
        $history = array( array( 'command' => 'help', 'output' => 'text', 'success' => true, 'timestamp' => 1000 ) );
        Functions\when( 'get_user_meta' )->justReturn( json_encode( $history ) );
        $this->assertSame( $history, $this->service->get_history( 1 ) );
    }

    public function test_get_history_returns_empty_array_for_invalid_json(): void {
        Functions\when( 'get_user_meta' )->justReturn( '{bad' );
        $this->assertSame( array(), $this->service->get_history( 1 ) );
    }

    // -----------------------------------------------------------------------
    // clear_history()
    // -----------------------------------------------------------------------

    public function test_clear_history_calls_delete_user_meta_with_correct_args(): void {
        $captured = null;
        Functions\when( 'delete_user_meta' )->alias(
            function( $uid, $key ) use ( &$captured ) { $captured = array( $uid, $key ); return true; }
        );

        $this->service->clear_history( 42 );

        $this->assertSame( array( 42, \CDW_CLI_Service::HISTORY_META_KEY ), $captured );
    }

    // -----------------------------------------------------------------------
    // execute() — top-level guards
    // -----------------------------------------------------------------------

    public function test_execute_returns_cli_disabled_error_when_disabled(): void {
        Functions\when( 'get_option' )->justReturn( false );

        $result = $this->service->execute( 'help', 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'cli_disabled', $result->get_error_code() );
    }

    public function test_execute_returns_empty_command_error_for_empty_string(): void {
        Functions\when( 'get_option' )->justReturn( true );

        $result = $this->service->execute( '', 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'empty_command', $result->get_error_code() );
    }

    public function test_execute_returns_empty_command_error_for_whitespace_only(): void {
        Functions\when( 'get_option' )->justReturn( true );

        $result = $this->service->execute( '   ', 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'empty_command', $result->get_error_code() );
    }

    public function test_execute_returns_rate_limited_error_when_limit_exceeded(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_cli_enabled' === $key ) return true;
            if ( 'cdw_db_version' === $key ) return \CDW_CLI_Service::DB_VERSION;
            return $default;
        } );
        // Rate limit at 20 → check_rate_limit returns false
        Functions\when( 'get_transient' )->justReturn( 20 );

        $result = $this->service->execute( 'help', 1, false );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'rate_limited', $result->get_error_code() );
    }

    public function test_execute_bypasses_rate_check_when_bypass_flag_is_true(): void {
        $this->stubExecuteCommonMocks( 20 ); // count=20 would normally block

        $result = $this->service->execute( 'help', 1, true );

        $this->assertFalse( is_wp_error( $result ) );
        $this->assertIsArray( $result );
    }

    // -----------------------------------------------------------------------
    // execute() — force guard
    // -----------------------------------------------------------------------

    public function test_execute_plugin_delete_without_force_returns_requires_force(): void {
        $this->stubExecuteCommonMocks();

        $result = $this->service->execute( 'plugin delete some-plugin', 1 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'requires_force', $result );
        $this->assertTrue( $result['requires_force'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_execute_plugin_delete_with_force_proceeds_to_handler(): void {
        $this->stubExecuteCommonMocks();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_plugins' )->justReturn( array() ); // empty → "not found"

        $result = $this->service->execute( 'plugin delete some-plugin --force', 1 );

        $this->assertIsArray( $result );
        $this->assertArrayNotHasKey( 'requires_force', $result );
    }

    public function test_execute_search_replace_without_force_or_dry_run_returns_requires_force(): void {
        $this->stubExecuteCommonMocks();

        $result = $this->service->execute( 'search-replace old new', 1 );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'requires_force', $result );
        $this->assertTrue( $result['requires_force'] );
    }

    public function test_execute_search_replace_with_dry_run_proceeds_to_handler(): void {
        $this->stubExecuteCommonMocks();
        // search-replace handler needs wpdb
        $wpdb            = \cdw_tests_reset_wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $result = $this->service->execute( 'search-replace old new --dry-run', 1 );

        $this->assertIsArray( $result );
        $this->assertArrayNotHasKey( 'requires_force', $result );
    }

    public function test_execute_plugin_list_does_not_require_force(): void {
        $this->stubExecuteCommonMocks();
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );
        Functions\when( 'is_plugin_active' )->justReturn( false );

        $result = $this->service->execute( 'plugin list', 1 );

        $this->assertIsArray( $result );
        $this->assertArrayNotHasKey( 'requires_force', $result );
    }

    // -----------------------------------------------------------------------
    // execute() — logging / history
    // -----------------------------------------------------------------------

    public function test_execute_successful_command_calls_add_to_history(): void {
        $this->stubExecuteCommonMocks();

        $updateMetaCalled = false;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$updateMetaCalled ) {
                if ( $key === \CDW_CLI_Service::HISTORY_META_KEY ) {
                    $updateMetaCalled = true;
                }
                return true;
            }
        );

        $this->service->execute( 'help', 1 );

        $this->assertTrue( $updateMetaCalled );
    }

    public function test_execute_successful_command_calls_log_cli_command(): void {
        $this->stubExecuteCommonMocks();

        $insertLog = false;
        $wpdb = new class {
            public $prefix  = 'wp_';
            public $queries = array();
            public bool $insertCalled = false;
            public function get_var( $sql ) { return 'wp_cdw_cli_logs'; }
            public function prepare( $sql, ...$args ) { return $sql; }
            public function query( $sql ) { $this->queries[] = $sql; }
            public function esc_like( $t ) { return $t; }
            public function insert( $table, $data, $format = null ) {
                $this->insertCalled = true;
                return 1;
            }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $this->service->execute( 'help', 1 );

        $this->assertTrue( $wpdb->insertCalled );
    }

    public function test_execute_failed_command_still_calls_add_to_history(): void {
        $this->stubExecuteCommonMocks();

        $historyWritten = false;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$historyWritten ) {
                if ( $key === \CDW_CLI_Service::HISTORY_META_KEY ) {
                    $historyWritten = true;
                }
                return true;
            }
        );

        // 'unknown-cmd' → success=false but still calls add_to_history
        $result = $this->service->execute( 'unknown-cmd', 1 );

        $this->assertFalse( $result['success'] );
        $this->assertTrue( $historyWritten );
    }

    public function test_execute_wp_error_from_guard_does_not_call_add_to_history(): void {
        Functions\when( 'get_option' )->justReturn( true );

        $historyWritten = false;
        Functions\when( 'update_user_meta' )->alias(
            function( $uid, $key, $value ) use ( &$historyWritten ) {
                if ( $key === \CDW_CLI_Service::HISTORY_META_KEY ) {
                    $historyWritten = true;
                }
            }
        );

        // Empty command → WP_Error returned → add_to_history NOT called
        $result = $this->service->execute( '', 1 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertFalse( $historyWritten );
    }
}

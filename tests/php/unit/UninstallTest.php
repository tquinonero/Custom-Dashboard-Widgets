<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/functions-uninstall.php';

class UninstallTest extends CDWTestCase {

    protected function setUp(): void {
        parent::setUp();
        // Fresh wpdb stub per test
        $GLOBALS['wpdb'] = new \wpdb();
    }

    // -----------------------------------------------------------------------
    // cdw_do_uninstall() — delete_on_uninstall = false
    // -----------------------------------------------------------------------

    public function test_do_uninstall_does_nothing_when_delete_option_is_false(): void {
        Functions\when( 'get_option' )->justReturn( false );

        $deleteOptionCalled = false;
        Functions\when( 'delete_option' )->alias( function() use ( &$deleteOptionCalled ) {
            $deleteOptionCalled = true;
        } );

        $wpdb            = \cdw_tests_reset_wpdb();

        \cdw_do_uninstall();

        $this->assertFalse( $deleteOptionCalled, 'delete_option should not be called' );
        $this->assertEmpty( $wpdb->queries, 'No DB queries should run' );
    }

    // -----------------------------------------------------------------------
    // cdw_do_uninstall() — delete_on_uninstall = true (default)
    // -----------------------------------------------------------------------

    public function test_do_uninstall_deletes_all_options_when_enabled(): void {
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );

        $deletedOptions = array();
        Functions\when( 'delete_option' )->alias( function( $key ) use ( &$deletedOptions ) {
            $deletedOptions[] = $key;
        } );

        $wpdb            = \cdw_tests_reset_wpdb();

        \cdw_do_uninstall();

        $expected = array(
            'cdw_support_email',
            'cdw_docs_url',
            'cdw_font_size',
            'cdw_bg_color',
            'cdw_header_bg_color',
            'cdw_header_text_color',
            'cdw_cli_enabled',
            'cdw_remove_default_widgets',
            'cdw_delete_on_uninstall',
            'cdw_db_version',
            'custom_dashboard_widget_email',
            'custom_dashboard_widget_docs_url',
            'custom_dashboard_widget_font_size',
            'custom_dashboard_widget_background_color',
            'custom_dashboard_widget_header_background_color',
            'custom_dashboard_widget_header_text_color',
        );

        foreach ( $expected as $opt ) {
            $this->assertContains( $opt, $deletedOptions, "Expected option $opt to be deleted" );
        }
    }

    public function test_do_uninstall_calls_delete_transient_for_stats_cache(): void {
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );

        $deletedTransients = array();
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deletedTransients ) {
            $deletedTransients[] = $key;
        } );

        $wpdb            = \cdw_tests_reset_wpdb();

        \cdw_do_uninstall();

        $this->assertContains( 'cdw_stats_cache', $deletedTransients );
    }

    public function test_do_uninstall_calls_wpdb_prepare_seven_times_for_patterns(): void {
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );

        $wpdb            = \cdw_tests_reset_wpdb();

        // Override prepare to track calls
        $prepareCalls = array();
        $wpdb_override = new class( $prepareCalls ) extends \wpdb {
            public $calls;
            public array $queriesCalledWith = array();
            public int   $prepareCalls      = 0;

            public function __construct( &$calls ) {
                $this->calls = &$calls;
            }

            public function prepare( $query, ...$args ) {
                $this->prepareCalls++;
                return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
            }

            public function query( $sql ) {
                $this->queries[] = $sql;
                return 1;
            }

            public function delete( $table, $where, $fmt = null ) { return 1; }
            public function esc_like( $t ) { return $t; }
        };
        $GLOBALS['wpdb'] = $wpdb_override;

        \cdw_do_uninstall();

        $this->assertSame( 7, $wpdb_override->prepareCalls,
            'Expected prepare() to be called exactly 7 times (one per LIKE pattern)' );
    }

    public function test_do_uninstall_calls_wpdb_query_for_drop_table(): void {
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );

        $wpdb = \cdw_tests_reset_wpdb();

        \cdw_do_uninstall();

        $dropQuery = null;
        foreach ( $wpdb->queries as $sql ) {
            if ( strpos( $sql, 'DROP TABLE IF EXISTS' ) !== false ) {
                $dropQuery = $sql;
                break;
            }
        }

        $this->assertNotNull( $dropQuery, 'Expected a DROP TABLE query' );
        $this->assertStringContainsString( 'cdw_cli_logs', $dropQuery );
    }

    public function test_do_uninstall_calls_wpdb_delete_twice_for_user_meta(): void {
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );

        $deleteCalls = array();

        $wpdb_override = new class extends \wpdb {
            public array $deleteCalls = array();
            public int   $queryCount  = 0;
            public function query( $sql )                              { $this->queries[] = $sql; return 1; }
            public function delete( $table, $where, $fmt = null )      { $this->deleteCalls[] = $where; return 1; }
            public function prepare( $query, ...$args )                { return $query; }
            public function esc_like( $t )                             { return $t; }
        };
        $GLOBALS['wpdb'] = $wpdb_override;

        \cdw_do_uninstall();

        $this->assertCount( 2, $wpdb_override->deleteCalls,
            'Expected $wpdb->delete to be called twice' );

        $metaKeys = array_column( $wpdb_override->deleteCalls, 'meta_key' );
        $this->assertContains( 'cdw_tasks',       $metaKeys );
        $this->assertContains( 'cdw_cli_history',  $metaKeys );
    }

    public function test_do_uninstall_default_true_runs_cleanup_even_when_option_never_set(): void {
        // get_option with second arg = true (the default) means option was never set → still true
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            // Simulate option never set → returns default
            return $default;
        } );

        $deleteOptionCalled = false;
        Functions\when( 'delete_option' )->alias( function() use ( &$deleteOptionCalled ) {
            $deleteOptionCalled = true;
        } );
        Functions\when( 'delete_transient' )->justReturn( true );

        $wpdb = \cdw_tests_reset_wpdb();

        \cdw_do_uninstall();

        // The default is true, so cleanup should have run
        $this->assertTrue( $deleteOptionCalled );
    }
}

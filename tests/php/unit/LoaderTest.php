<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-loader.php';

class LoaderTest extends CDWTestCase {

    protected function setUp(): void {
        parent::setUp();
        // Global wpdb stub
        $GLOBALS['wpdb'] = new \wpdb();

        // Stub register_rest_route so CDW_REST_API::register_controller_routes() doesn't blow up
        Functions\when( 'register_rest_route' )->justReturn( true );
        // Stub add_action / add_filter globally; we'll often replace per-test
        Functions\when( 'add_action' )->justReturn( true );
        Functions\when( 'add_filter' )->justReturn( true );
    }

    // -----------------------------------------------------------------------
    // run() — CDW_REST_API::register() always called
    // -----------------------------------------------------------------------

    public function test_run_always_calls_rest_api_register(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( \CDW_CLI_Service::DB_VERSION );

        $addActionCalls = array();
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$addActionCalls ) {
            $addActionCalls[] = $hook;
        } );

        $loader = new \CDW_Loader();
        $loader->run();

        // CDW_REST_API::register_routes() calls add_action('rest_api_init', ...)
        $this->assertContains( 'rest_api_init', $addActionCalls );
    }

    // -----------------------------------------------------------------------
    // run() — CDW_Widgets::register() only when is_admin()
    // -----------------------------------------------------------------------

    public function test_run_registers_widgets_when_is_admin_true(): void {
        Functions\when( 'is_admin' )->justReturn( true );
        Functions\when( 'get_option' )->justReturn( \CDW_CLI_Service::DB_VERSION );

        $addActionCalls = array();
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$addActionCalls ) {
            $addActionCalls[] = $hook;
        } );

        $loader = new \CDW_Loader();
        $loader->run();

        // CDW_Widgets::register() calls add_action('wp_dashboard_setup', ...)
        $this->assertContains( 'wp_dashboard_setup', $addActionCalls );
        // also hooked admin_enqueue_scripts
        $this->assertContains( 'admin_enqueue_scripts', $addActionCalls );
    }

    public function test_run_does_not_register_widgets_when_not_admin(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( \CDW_CLI_Service::DB_VERSION );

        $addActionCalls = array();
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$addActionCalls ) {
            $addActionCalls[] = $hook;
        } );

        $loader = new \CDW_Loader();
        $loader->run();

        $this->assertNotContains( 'wp_dashboard_setup',      $addActionCalls );
        $this->assertNotContains( 'admin_enqueue_scripts',   $addActionCalls );
    }

    // -----------------------------------------------------------------------
    // run() — cache/content hooks always registered
    // -----------------------------------------------------------------------

    public function test_run_hooks_content_cache_actions_unconditionally(): void {
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( \CDW_CLI_Service::DB_VERSION );

        $addActionCalls = array();
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$addActionCalls ) {
            $addActionCalls[] = $hook;
        } );

        $loader = new \CDW_Loader();
        $loader->run();

        foreach ( array( 'save_post', 'delete_post', 'add_attachment', 'edit_attachment' ) as $hook ) {
            $this->assertContains( $hook, $addActionCalls, "Expected $hook to be hooked" );
        }
    }

    // -----------------------------------------------------------------------
    // clear_content_cache()
    // -----------------------------------------------------------------------

    public function test_clear_content_cache_deletes_stats_transient(): void {
        $deleted = array();
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
            $deleted[] = $key;
        } );

        $wpdb = \cdw_tests_reset_wpdb();
        $loader = new \CDW_Loader();
        $loader->clear_content_cache();

        $this->assertContains( 'cdw_stats_cache', $deleted );
    }

    public function test_clear_content_cache_calls_wpdb_query(): void {
        Functions\when( 'delete_transient' )->justReturn( true );

        $wpdb            = \cdw_tests_reset_wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $loader = new \CDW_Loader();
        $loader->clear_content_cache();

        $this->assertNotEmpty( $wpdb->queries, 'Expected wpdb->query to be called' );
    }

    // -----------------------------------------------------------------------
    // enqueue_assets() — non-dashboard hook returns early
    // -----------------------------------------------------------------------

    public function test_enqueue_assets_returns_early_for_non_dashboard_hook(): void {
        $enqueueCalled = false;
        Functions\when( 'wp_enqueue_script' )->alias( function() use ( &$enqueueCalled ) {
            $enqueueCalled = true;
        } );
        Functions\when( 'wp_enqueue_style' )->alias( function() use ( &$enqueueCalled ) {
            $enqueueCalled = true;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'edit.php' );

        $this->assertFalse( $enqueueCalled );
    }

    // -----------------------------------------------------------------------
    // enqueue_assets() — build files missing
    // -----------------------------------------------------------------------

    public function test_enqueue_assets_hooks_admin_notice_when_files_missing(): void {
        Functions\when( 'file_exists' )->justReturn( false );

        $noticeCalled = false;
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$noticeCalled ) {
            if ( 'admin_notices' === $hook ) {
                $noticeCalled = true;
            }
        } );
        $enqueueCalled = false;
        Functions\when( 'wp_enqueue_script' )->alias( function() use ( &$enqueueCalled ) {
            $enqueueCalled = true;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertTrue( $noticeCalled, 'admin_notices should be hooked when build files missing' );
        $this->assertFalse( $enqueueCalled, 'wp_enqueue_script should NOT be called when files missing' );
    }

    // -----------------------------------------------------------------------
    // enqueue_assets() — files present, wp_enqueue_script + style called
    // -----------------------------------------------------------------------

    public function test_enqueue_assets_with_files_present_calls_enqueue_functions(): void {
        // Real build files exist; don't mock file_exists so the real check passes
        $scriptEnqueued = false;
        $styleEnqueued  = false;

        Functions\when( 'wp_enqueue_script' )->alias( function() use ( &$scriptEnqueued ) {
            $scriptEnqueued = true;
        } );
        Functions\when( 'wp_enqueue_style' )->alias( function() use ( &$styleEnqueued ) {
            $styleEnqueued = true;
        } );
        Functions\when( 'wp_add_inline_style' )->justReturn( true );
        Functions\when( 'wp_localize_script' )->justReturn( true );
        Functions\when( 'get_option' )->justReturn( '' );
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'abc123' );
        Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertTrue( $scriptEnqueued );
        $this->assertTrue( $styleEnqueued );
    }

    // -----------------------------------------------------------------------
    // enqueue_assets() — inline CSS from options
    // -----------------------------------------------------------------------

    private function stubEnqueueAssets(): void {
        Functions\when( 'wp_enqueue_script' )->justReturn( true );
        Functions\when( 'wp_enqueue_style' )->justReturn( true );
        Functions\when( 'wp_localize_script' )->justReturn( true );
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url_raw' )->returnArg();
        Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/' );
        Functions\when( 'wp_create_nonce' )->justReturn( 'nonce' );
        Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );
    }

    public function test_enqueue_assets_valid_font_size_generates_css(): void {
        $this->stubEnqueueAssets();

        $inlineCss = '';
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_font_size' === $key ) return '14';
            return $default;
        } );
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringContainsString( 'font-size: 14px', $inlineCss );
    }

    public function test_enqueue_assets_empty_font_size_no_font_css(): void {
        $this->stubEnqueueAssets();

        Functions\when( 'get_option' )->justReturn( '' );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringNotContainsString( 'font-size', $inlineCss );
    }

    public function test_enqueue_assets_non_numeric_font_size_no_font_css(): void {
        $this->stubEnqueueAssets();

        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_font_size' === $key ) return 'abc';
            return $default;
        } );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringNotContainsString( 'font-size', $inlineCss );
    }

    public function test_enqueue_assets_zero_font_size_no_font_css(): void {
        $this->stubEnqueueAssets();

        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_font_size' === $key ) return '0';
            return $default;
        } );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringNotContainsString( 'font-size', $inlineCss );
    }

    public function test_enqueue_assets_valid_bg_color_generates_css(): void {
        $this->stubEnqueueAssets();

        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_bg_color' === $key ) return '#fff';
            return $default;
        } );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringContainsString( '--cdw-bg: #fff', $inlineCss );
    }

    public function test_enqueue_assets_invalid_bg_color_no_background_css(): void {
        $this->stubEnqueueAssets();

        // 7 chars after # = invalid hex
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_bg_color' === $key ) return '#fffffff'; // 7 hex chars → invalid
            return $default;
        } );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringNotContainsString( 'background-color', $inlineCss );
    }

    public function test_enqueue_assets_wp_add_inline_style_not_called_when_all_options_empty(): void {
        $this->stubEnqueueAssets();

        Functions\when( 'get_option' )->justReturn( '' );

        $inlineStyleCalled = false;
        Functions\when( 'wp_add_inline_style' )->alias( function() use ( &$inlineStyleCalled ) {
            $inlineStyleCalled = true;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertFalse( $inlineStyleCalled );
    }

    // -----------------------------------------------------------------------
    // enqueue_assets() — isSettings flag
    // -----------------------------------------------------------------------

    public function test_enqueue_assets_is_settings_true_for_settings_page(): void {
        $this->stubEnqueueAssets();
        Functions\when( 'get_option' )->justReturn( '' );

        $localized = array();
        Functions\when( 'wp_localize_script' )->alias( function( $handle, $name, $data ) use ( &$localized ) {
            $localized = $data;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'settings_page_cdw-settings' );

        $this->assertTrue( $localized['isSettings'] );
    }

    public function test_enqueue_assets_is_settings_false_for_dashboard_hook(): void {
        $this->stubEnqueueAssets();
        Functions\when( 'get_option' )->justReturn( '' );

        $localized = array();
        Functions\when( 'wp_localize_script' )->alias( function( $handle, $name, $data ) use ( &$localized ) {
            $localized = $data;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertFalse( $localized['isSettings'] );
    }

    public function test_enqueue_assets_valid_header_bg_color_generates_css(): void {
        $this->stubEnqueueAssets();
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_header_bg_color' === $key ) return '#333333';
            return $default;
        } );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringContainsString( '--cdw-header-bg:', $inlineCss );
    }

    public function test_enqueue_assets_valid_header_text_color_generates_color_css(): void {
        $this->stubEnqueueAssets();
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_header_text_color' === $key ) return '#ffffff';
            return $default;
        } );
        $inlineCss = '';
        Functions\when( 'wp_add_inline_style' )->alias( function( $handle, $css ) use ( &$inlineCss ) {
            $inlineCss .= $css;
        } );

        $loader = new \CDW_Loader();
        $loader->enqueue_assets( 'index.php' );

        $this->assertStringContainsString( '--cdw-header-text:', $inlineCss );
    }
}

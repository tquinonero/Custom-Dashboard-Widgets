<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-widgets.php';

class WidgetsTest extends CDWTestCase {

    private \CDW_Widgets $widgets;

    protected function setUp(): void {
        parent::setUp();
        $this->widgets = new \CDW_Widgets();
    }

    // -----------------------------------------------------------------------
    // register()
    // -----------------------------------------------------------------------

    public function test_register_hooks_manage_dashboard_widgets_to_wp_dashboard_setup(): void {
        $hooked = array();
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$hooked ) {
            $hooked[] = $hook;
        } );

        $this->widgets->register();

        $this->assertContains( 'wp_dashboard_setup', $hooked );
    }

    public function test_register_hooks_add_settings_page_to_admin_menu(): void {
        $hooked = array();
        Functions\when( 'add_action' )->alias( function( $hook ) use ( &$hooked ) {
            $hooked[] = $hook;
        } );

        $this->widgets->register();

        $this->assertContains( 'admin_menu', $hooked );
    }

    // -----------------------------------------------------------------------
    // manage_dashboard_widgets() — default widget removal
    // -----------------------------------------------------------------------

    public function test_remove_default_widgets_when_option_is_true(): void {
        Functions\when( 'get_option' )->justReturn( true );
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( 'wp_add_dashboard_widget' )->justReturn( null );

        $removed = array();
        Functions\when( 'remove_meta_box' )->alias( function( $id ) use ( &$removed ) {
            $removed[] = $id;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertContains( 'dashboard_right_now', $removed );
        $this->assertContains( 'dashboard_activity', $removed );
        $this->assertContains( 'dashboard_site_health', $removed );
        $this->assertContains( 'dashboard_primary', $removed );
        $this->assertContains( 'dashboard_quick_press', $removed );
    }

    public function test_does_not_remove_default_widgets_when_option_is_false(): void {
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( 'wp_add_dashboard_widget' )->justReturn( null );

        $removeCalled = false;
        Functions\when( 'remove_meta_box' )->alias( function() use ( &$removeCalled ) {
            $removeCalled = true;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertFalse( $removeCalled );
    }

    // -----------------------------------------------------------------------
    // manage_dashboard_widgets() — widget registration by capability
    // -----------------------------------------------------------------------

    public function test_adds_editor_widgets_when_user_can_edit_posts(): void {
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'current_user_can' )->alias( function( $cap ) {
            return 'edit_posts' === $cap;
        } );

        $added = array();
        Functions\when( 'wp_add_dashboard_widget' )->alias( function( $id ) use ( &$added ) {
            $added[] = $id;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertContains( 'cdw_help', $added );
        $this->assertContains( 'cdw_stats', $added );
        $this->assertContains( 'cdw_media', $added );
        $this->assertContains( 'cdw_posts', $added );
    }

    public function test_does_not_add_editor_widgets_when_user_cannot_edit_posts(): void {
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'current_user_can' )->justReturn( false );

        $added = array();
        Functions\when( 'wp_add_dashboard_widget' )->alias( function( $id ) use ( &$added ) {
            $added[] = $id;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertNotContains( 'cdw_posts', $added );
        $this->assertNotContains( 'cdw_stats', $added );
    }

    public function test_adds_admin_widgets_when_user_can_manage_options(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_remove_default_widgets' === $key ) {
                return false;
            }
            if ( 'cdw_cli_enabled' === $key ) {
                return true;
            }
            return $default;
        } );
        Functions\when( 'current_user_can' )->alias( function( $cap ) {
            return 'manage_options' === $cap;
        } );

        $added = array();
        Functions\when( 'wp_add_dashboard_widget' )->alias( function( $id ) use ( &$added ) {
            $added[] = $id;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertContains( 'cdw_tasks', $added );
        $this->assertContains( 'cdw_updates', $added );
        $this->assertContains( 'cdw_quicklinks', $added );
        $this->assertContains( 'cdw_command', $added );
    }

    public function test_command_widget_not_added_when_cli_disabled(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_remove_default_widgets' === $key ) {
                return false;
            }
            if ( 'cdw_cli_enabled' === $key ) {
                return false;
            }
            return $default;
        } );
        Functions\when( 'current_user_can' )->alias( function( $cap ) {
            return 'manage_options' === $cap;
        } );

        $added = array();
        Functions\when( 'wp_add_dashboard_widget' )->alias( function( $id ) use ( &$added ) {
            $added[] = $id;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertNotContains( 'cdw_command', $added );
        $this->assertContains( 'cdw_tasks', $added );
    }

    public function test_no_admin_widgets_when_user_can_only_edit_posts(): void {
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'current_user_can' )->alias( function( $cap ) {
            return 'edit_posts' === $cap; // NOT manage_options
        } );

        $added = array();
        Functions\when( 'wp_add_dashboard_widget' )->alias( function( $id ) use ( &$added ) {
            $added[] = $id;
        } );

        $this->widgets->manage_dashboard_widgets();

        $this->assertNotContains( 'cdw_tasks', $added );
        $this->assertNotContains( 'cdw_command', $added );
    }

    // -----------------------------------------------------------------------
    // render_help_widget()
    // -----------------------------------------------------------------------

    public function test_render_help_widget_outputs_email_link_when_email_set(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_support_email' === $key ) {
                return 'help@example.com';
            }
            return $default;
        } );
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'get_admin_url' )->justReturn( 'http://example.com/wp-admin/' );

        ob_start();
        $this->widgets->render_help_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'help@example.com', $output );
        $this->assertStringContainsString( 'mailto:', $output );
    }

    public function test_render_help_widget_outputs_docs_link_when_docs_url_set(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_docs_url' === $key ) {
                return 'https://docs.example.com';
            }
            return $default;
        } );
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'get_admin_url' )->justReturn( 'http://example.com/wp-admin/' );

        ob_start();
        $this->widgets->render_help_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'https://docs.example.com', $output );
        $this->assertStringContainsString( 'documentation', $output );
    }

    public function test_render_help_widget_shows_no_info_configured_when_both_empty(): void {
        Functions\when( 'get_option' )->justReturn( '' );
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'get_admin_url' )->justReturn( 'http://example.com/wp-admin/' );

        ob_start();
        $this->widgets->render_help_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'No support information configured', $output );
    }

    public function test_render_help_widget_shows_edit_settings_button_when_email_set(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = '' ) {
            if ( 'cdw_support_email' === $key ) {
                return 'admin@example.com';
            }
            return $default;
        } );
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_url' )->returnArg();
        Functions\when( 'get_admin_url' )->justReturn( 'http://example.com/wp-admin/' );

        ob_start();
        $this->widgets->render_help_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Edit Widget Settings', $output );
    }

    // -----------------------------------------------------------------------
    // Simple render methods — output data-widget attribute
    // -----------------------------------------------------------------------

    public function test_render_stats_widget_outputs_stats_data_widget(): void {
        ob_start();
        $this->widgets->render_stats_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="stats"', $output );
    }

    public function test_render_media_widget_outputs_media_data_widget(): void {
        ob_start();
        $this->widgets->render_media_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="media"', $output );
    }

    public function test_render_posts_widget_outputs_posts_data_widget(): void {
        ob_start();
        $this->widgets->render_posts_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="posts"', $output );
    }

    public function test_render_tasks_widget_outputs_tasks_data_widget(): void {
        ob_start();
        $this->widgets->render_tasks_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="tasks"', $output );
    }

    public function test_render_updates_widget_outputs_updates_data_widget(): void {
        ob_start();
        $this->widgets->render_updates_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="updates"', $output );
    }

    public function test_render_quicklinks_widget_outputs_quicklinks_data_widget(): void {
        ob_start();
        $this->widgets->render_quicklinks_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="quicklinks"', $output );
    }

    public function test_render_command_widget_outputs_command_data_widget(): void {
        ob_start();
        $this->widgets->render_command_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-widget="command"', $output );
    }

    // -----------------------------------------------------------------------
    // add_settings_page()
    // -----------------------------------------------------------------------

    public function test_add_settings_page_registers_cdw_settings_slug(): void {
        $slug = null;
        Functions\when( 'add_options_page' )->alias(
            function( $page_title, $menu_title, $capability, $menu_slug ) use ( &$slug ) {
                $slug = $menu_slug;
            }
        );

        $this->widgets->add_settings_page();

        $this->assertSame( 'cdw-settings', $slug );
    }

    public function test_add_settings_page_requires_manage_options_capability(): void {
        $capability = null;
        Functions\when( 'add_options_page' )->alias(
            function( $page_title, $menu_title, $cap, $menu_slug ) use ( &$capability ) {
                $capability = $cap;
            }
        );

        $this->widgets->add_settings_page();

        $this->assertSame( 'manage_options', $capability );
    }

    // -----------------------------------------------------------------------
    // render_settings_page()
    // -----------------------------------------------------------------------

    public function test_render_settings_page_outputs_settings_root_div(): void {
        ob_start();
        $this->widgets->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cdw-settings-root', $output );
    }

    public function test_render_settings_page_outputs_wrap_div(): void {
        ob_start();
        $this->widgets->render_settings_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'class="wrap"', $output );
    }
}

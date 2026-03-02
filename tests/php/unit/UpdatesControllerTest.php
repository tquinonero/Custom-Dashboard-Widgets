<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-updates-controller.php';

// WP_Theme stub is defined in wp-stubs.php (global namespace)

class UpdatesControllerTest extends CDWTestCase {

    private \CDW_Updates_Controller $controller;

    protected function setUp(): void {
        parent::setUp();
        $this->controller = new \CDW_Updates_Controller();

        Functions\when( 'rest_ensure_response' )->alias( function( $data ) {
            return new \WP_REST_Response( $data, 200 );
        } );
    }

    // -----------------------------------------------------------------------
    // get_updates() — shape
    // -----------------------------------------------------------------------

    public function test_get_updates_returns_core_plugins_themes_shape(): void {
        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array( 'wordpress' => 0 ) ) );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $result = $this->controller->get_updates();
        $data   = $result->get_data();

        $this->assertArrayHasKey( 'core',    $data );
        $this->assertArrayHasKey( 'plugins', $data );
        $this->assertArrayHasKey( 'themes',  $data );
    }

    // -----------------------------------------------------------------------
    // core updates
    // -----------------------------------------------------------------------

    public function test_core_available_true_when_count_is_1(): void {
        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array( 'wordpress' => 1 ) ) );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $data = $this->controller->get_updates()->get_data();

        $this->assertTrue( $data['core']['available'] );
        $this->assertSame( 1, $data['core']['count'] );
    }

    public function test_core_count_is_0_when_wordpress_key_absent(): void {
        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $data = $this->controller->get_updates()->get_data();

        $this->assertSame( 0, $data['core']['count'] );
        $this->assertFalse( $data['core']['available'] );
    }

    // -----------------------------------------------------------------------
    // plugin updates
    // -----------------------------------------------------------------------

    public function test_empty_update_plugins_transient_returns_empty_plugins_array(): void {
        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $data = $this->controller->get_updates()->get_data();

        $this->assertSame( array(), $data['plugins'] );
    }

    public function test_plugin_update_includes_name_version_new_version(): void {
        $pluginUpdateData              = new \stdClass();
        $pluginUpdateData->new_version = '2.0';

        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->alias( function( $key ) use ( $pluginUpdateData ) {
            if ( 'update_plugins' === $key ) {
                return (object) array(
                    'response' => array( 'my-plugin/my-plugin.php' => $pluginUpdateData ),
                );
            }
            return (object) array( 'response' => array() );
        } );
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $data    = $this->controller->get_updates()->get_data();
        $plugins = $data['plugins'];

        $this->assertCount( 1, $plugins );
        $this->assertSame( 'my-plugin/my-plugin.php', $plugins[0]['file'] );
        $this->assertSame( 'My Plugin', $plugins[0]['name'] );
        $this->assertSame( '1.0',       $plugins[0]['version'] );
        $this->assertSame( '2.0',       $plugins[0]['new_version'] );
    }

    public function test_plugin_name_falls_back_to_dirname_when_not_in_get_plugins(): void {
        $pluginUpdateData              = new \stdClass();
        $pluginUpdateData->new_version = '2.0';

        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->alias( function( $key ) use ( $pluginUpdateData ) {
            if ( 'update_plugins' === $key ) {
                return (object) array(
                    'response' => array( 'unknown-plugin/unknown-plugin.php' => $pluginUpdateData ),
                );
            }
            return (object) array( 'response' => array() );
        } );
        Functions\when( 'get_plugins' )->justReturn( array() ); // plugin not in list
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $data    = $this->controller->get_updates()->get_data();
        $plugins = $data['plugins'];

        $this->assertCount( 1, $plugins );
        $this->assertSame( 'unknown-plugin', $plugins[0]['name'] );
    }

    public function test_plugin_version_defaults_to_empty_string_when_absent(): void {
        $pluginUpdateData              = new \stdClass();
        $pluginUpdateData->new_version = '2.0';

        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->alias( function( $key ) use ( $pluginUpdateData ) {
            if ( 'update_plugins' === $key ) {
                return (object) array(
                    'response' => array( 'myplugin/myplugin.php' => $pluginUpdateData ),
                );
            }
            return (object) array( 'response' => array() );
        } );
        // Plugin data doesn't have 'Version' key
        Functions\when( 'get_plugins' )->justReturn( array(
            'myplugin/myplugin.php' => array( 'Name' => 'My Plugin' ),
        ) );
        Functions\when( 'wp_get_themes' )->justReturn( array() );

        $data    = $this->controller->get_updates()->get_data();
        $plugins = $data['plugins'];

        $this->assertSame( '', $plugins[0]['version'] );
    }

    // -----------------------------------------------------------------------
    // theme updates
    // -----------------------------------------------------------------------

    public function test_theme_not_in_wp_get_themes_is_skipped(): void {
        $themeUpdateData                = array( 'new_version' => '2.0' );
        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->alias( function( $key ) use ( $themeUpdateData ) {
            if ( 'update_themes' === $key ) {
                return (object) array(
                    'response' => array( 'missing-theme' => $themeUpdateData ),
                );
            }
            return (object) array( 'response' => array() );
        } );
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'wp_get_themes' )->justReturn( array() ); // theme not present

        $data = $this->controller->get_updates()->get_data();

        $this->assertSame( array(), $data['themes'] );
    }

    public function test_theme_present_in_both_included_with_correct_data(): void {
        $themeStub   = new \WP_Theme( array( 'Name' => 'My Theme', 'Version' => '1.5' ) );
        $themeUpdate = array( 'new_version' => '2.0' );

        Functions\when( 'wp_get_update_data' )->justReturn( array( 'counts' => array() ) );
        Functions\when( 'get_site_transient' )->alias( function( $key ) use ( $themeUpdate ) {
            if ( 'update_themes' === $key ) {
                return (object) array( 'response' => array( 'my-theme' => $themeUpdate ) );
            }
            return (object) array( 'response' => array() );
        } );
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'wp_get_themes' )->justReturn( array( 'my-theme' => $themeStub ) );

        $data   = $this->controller->get_updates()->get_data();
        $themes = $data['themes'];

        $this->assertCount( 1, $themes );
        $this->assertSame( 'my-theme',  $themes[0]['slug'] );
        $this->assertSame( 'My Theme',  $themes[0]['name'] );
        $this->assertSame( '1.5',       $themes[0]['version'] );
        $this->assertSame( '2.0',       $themes[0]['new_version'] );
    }
}

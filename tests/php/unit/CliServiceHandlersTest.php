<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';
require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

/**
 * Tests for CDW_CLI_Service command handlers.
 * Handlers are accessed via execute() passing --force where required.
 */
class CliServiceHandlersTest extends CDWTestCase {

    private \CDW_CLI_Service $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new \CDW_CLI_Service();
        $this->resetAuditTableConfirmed();
        $this->resetUpgraderStatics();
    }

    private function resetAuditTableConfirmed(): void {
        $ref  = new \ReflectionClass( \CDW_CLI_Service::class );
        $prop = $ref->getProperty( 'audit_table_confirmed' );
        $prop->setAccessible( true );
        $prop->setValue( null, false );
    }

    /**
     * Reset static state on the Plugin_Upgrader stub between tests.
     * The stub class is loaded lazily (on first execute of a plugin handler),
     * so we guard with class_exists.
     */
    private function resetUpgraderStatics(): void {
        if ( class_exists( 'Plugin_Upgrader' ) ) {
            \Plugin_Upgrader::$upgrade_return = true;
            \Plugin_Upgrader::$install_return = true;
            \Plugin_Upgrader::$install_args   = null;
            \Plugin_Upgrader::$bulk_return    = array();
        }
    }

    /**
     * Set up the minimum common mocks so execute() passes all guards
     * and reaches the requested handler.
     * Uses bypass_rate_limit=true so rate mocks are unnecessary.
     */
    private function stubExecute(): void {
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_cli_enabled' === $key ) {
                return true;
            }
            if ( 'cdw_db_version' === $key ) {
                return \CDW_CLI_Service::DB_VERSION;
            }
            return $default;
        } );
        Functions\when( 'wp_unslash' )->returnArg();
        Functions\when( 'get_user_meta' )->justReturn( '' );
        Functions\when( 'update_user_meta' )->justReturn( true );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        \cdw_tests_reset_wpdb();
    }

    /** Shorthand: mock filesystem functions so init_filesystem() returns true. */
    private function stubFilesystemOk(): void {
        Functions\when( 'request_filesystem_credentials' )->justReturn( array( 'type' => 'direct' ) );
        Functions\when( 'WP_Filesystem' )->justReturn( true );
    }

    /** Shorthand: mock filesystem functions so init_filesystem() returns false. */
    private function stubFilesystemFail(): void {
        Functions\when( 'request_filesystem_credentials' )->justReturn( false );
    }

    /** Execute command with bypass_rate_limit=true for simplicity. */
    private function exec( string $command ): array {
        $result = $this->service->execute( $command, 1, true );
        $this->assertIsArray( $result, "execute() should return array, got WP_Error for: $command" );
        return $result;
    }

    // -----------------------------------------------------------------------
    // plugin list
    // -----------------------------------------------------------------------

    public function test_plugin_list_output_contains_both_plugin_names(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array(
            'hello/hello.php' => array( 'Name' => 'Hello Dolly', 'Version' => '1.6' ),
            'akismet/akismet.php' => array( 'Name' => 'Akismet', 'Version' => '5.0' ),
        ) );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );
        Functions\when( 'is_plugin_active' )->justReturn( false );

        $result = $this->exec( 'plugin list' );

        $this->assertStringContainsString( 'Hello Dolly', $result['output'] );
        $this->assertStringContainsString( 'Akismet', $result['output'] );
    }

    public function test_plugin_list_output_shows_no_plugins_message_for_empty(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );

        $result = $this->exec( 'plugin list' );

        $this->assertStringContainsStringIgnoringCase( 'no plugins', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // plugin status
    // -----------------------------------------------------------------------

    public function test_plugin_status_not_found_when_resolve_returns_false(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'plugin status nonexistent-plugin' );

        $this->assertStringContainsStringIgnoringCase( 'not found', $result['output'] );
    }

    public function test_plugin_status_shows_name_status_version_when_found(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '2.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_plugin_data' )->justReturn(
            array( 'Name' => 'My Plugin', 'Version' => '2.0' )
        );
        Functions\when( 'is_plugin_active' )->justReturn( false );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );

        $result = $this->exec( 'plugin status my-plugin' );

        $this->assertStringContainsString( 'My Plugin', $result['output'] );
        $this->assertStringContainsString( '2.0',       $result['output'] );
    }

    // -----------------------------------------------------------------------
    // plugin activate / deactivate
    // -----------------------------------------------------------------------

    public function test_plugin_activate_returns_activated_message_on_success(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'activate_plugin' )->justReturn( null ); // null = success

        $result = $this->exec( 'plugin activate my-plugin' );

        $this->assertStringContainsStringIgnoringCase( 'activated', $result['output'] );
        $this->assertTrue( $result['success'] );
    }

    public function test_plugin_activate_returns_failed_message_on_wp_error(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        $wpError = new \WP_Error( 'activation_error', 'Permission denied' );
        Functions\when( 'activate_plugin' )->justReturn( $wpError );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->exec( 'plugin activate my-plugin' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'fail', $result['output'] );
    }

    public function test_plugin_deactivate_calls_deactivate_plugins(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $calledWith = null;
        Functions\when( 'deactivate_plugins' )->alias( function( $p ) use ( &$calledWith ) {
            $calledWith = $p;
        } );

        $this->exec( 'plugin deactivate my-plugin' );

        $this->assertSame( 'my-plugin/my-plugin.php', $calledWith );
    }

    // -----------------------------------------------------------------------
    // plugin update (single)
    // -----------------------------------------------------------------------

    public function test_plugin_update_single_no_update_available(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );

        $result = $this->exec( 'plugin update my-plugin --force' );

        $this->assertStringContainsStringIgnoringCase( 'up to date', $result['output'] );
        $this->assertTrue( $result['success'] );
    }

    public function test_plugin_update_single_with_update_available_succeeds(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $updateData              = new \stdClass();
        $updateData->new_version = '2.0';
        Functions\when( 'get_site_transient' )->justReturn( (object) array(
            'response' => array( 'my-plugin/my-plugin.php' => $updateData ),
        ) );
        Functions\when( 'wp_cache_delete' )->justReturn( true );

        // Plugin_Upgrader stub: upgrade() returns true (default)
        $result = $this->exec( 'plugin update my-plugin --force' );

        $this->assertStringContainsStringIgnoringCase( 'updated', $result['output'] );
        $this->assertTrue( $result['success'] );
    }

    public function test_plugin_update_returns_filesystem_error_when_init_fails(): void {
        $this->stubExecute();
        $this->stubFilesystemFail();
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );

        $result = $this->exec( 'plugin update my-plugin --force' );

        $this->assertStringContainsStringIgnoringCase( 'filesystem', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    // -----------------------------------------------------------------------
    // plugin update --all
    // -----------------------------------------------------------------------

    public function test_plugin_update_all_outputs_up_to_date_when_no_updates(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();
        Functions\when( 'get_site_transient' )->justReturn( (object) array( 'response' => array() ) );

        $result = $this->exec( 'plugin update --all --force' );

        $this->assertStringContainsStringIgnoringCase( 'up to date', $result['output'] );
    }

    public function test_plugin_update_all_lists_both_plugins_when_updates_available(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();

        $upd1              = new \stdClass();
        $upd1->new_version = '2.0';
        $upd2              = new \stdClass();
        $upd2->new_version = '3.0';

        Functions\when( 'get_site_transient' )->justReturn( (object) array(
            'response' => array(
                'plugin-a/plugin-a.php' => $upd1,
                'plugin-b/plugin-b.php' => $upd2,
            ),
        ) );
        Functions\when( 'wp_cache_delete' )->justReturn( true );

        \Plugin_Upgrader::$bulk_return = array(
            'plugin-a/plugin-a.php' => true,
            'plugin-b/plugin-b.php' => true,
        );

        $result = $this->exec( 'plugin update --all --force' );

        $this->assertStringContainsString( 'plugin-a', $result['output'] );
        $this->assertStringContainsString( 'plugin-b', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // plugin delete (--force)
    // -----------------------------------------------------------------------

    public function test_plugin_delete_blocked_when_plugin_is_active(): void {
        $this->stubExecute();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'is_plugin_active' )->justReturn( true );

        $result = $this->exec( 'plugin delete my-plugin --force' );

        $this->assertStringContainsStringIgnoringCase( 'Cannot delete active', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_plugin_delete_succeeds_when_not_active(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'is_plugin_active' )->justReturn( false );
        Functions\when( 'delete_plugins' )->justReturn( true );

        $result = $this->exec( 'plugin delete my-plugin --force' );

        $this->assertTrue( $result['success'] );
    }

    // -----------------------------------------------------------------------
    // plugin install (--force)
    // -----------------------------------------------------------------------

    public function test_plugin_install_returns_not_found_when_plugins_api_returns_wp_error(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $apiError = new \WP_Error( 'plugin_not_found', 'Plugin not found.' );
        Functions\when( 'plugins_api' )->justReturn( $apiError );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->exec( 'plugin install new-plugin --force' );

        $this->assertStringContainsStringIgnoringCase( 'not found in repository', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_plugin_install_returns_filesystem_error_when_init_fails(): void {
        $this->stubExecute();
        $this->stubFilesystemFail();
        Functions\when( 'get_plugins' )->justReturn( array() );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'plugin install new-plugin --force' );

        $this->assertStringContainsStringIgnoringCase( 'filesystem', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_plugin_install_passes_overwrite_package_true_when_already_installed(): void {
        $this->stubExecute();
        $this->stubFilesystemOk();
        // Plugin is already installed
        Functions\when( 'get_plugins' )->justReturn( array(
            'my-plugin/my-plugin.php' => array( 'Name' => 'My Plugin', 'Version' => '1.0' ),
        ) );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $apiInfo                = new \stdClass();
        $apiInfo->download_link = 'https://example.com/my-plugin.zip';
        Functions\when( 'plugins_api' )->justReturn( $apiInfo );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );
        Functions\when( 'wp_cache_delete' )->justReturn( true );

        // Plugin_Upgrader is loaded after the first plugin command test,
        // reset statics (done in setUp) ensures clean state.
        $this->exec( 'plugin install my-plugin --force' );

        $this->assertNotNull( \Plugin_Upgrader::$install_args );
        $this->assertTrue( \Plugin_Upgrader::$install_args['overwrite_package'] );
    }

    // -----------------------------------------------------------------------
    // user create
    // -----------------------------------------------------------------------

    public function test_user_create_returns_already_exists_when_username_taken(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_user' )->returnArg();
        Functions\when( 'sanitize_email' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'username_exists' )->justReturn( true );

        $result = $this->exec( 'user create johndoe john@example.com administrator' );

        $this->assertStringContainsStringIgnoringCase( 'already exists', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_user_create_returns_invalid_email_error(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_user' )->returnArg();
        Functions\when( 'sanitize_email' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'username_exists' )->justReturn( false );
        Functions\when( 'is_email' )->justReturn( false );

        $result = $this->exec( 'user create johndoe notanemail administrator' );

        $this->assertStringContainsStringIgnoringCase( 'invalid email', $result['output'] );
    }

    public function test_user_create_returns_invalid_role_error(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_user' )->returnArg();
        Functions\when( 'sanitize_email' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'username_exists' )->justReturn( false );
        Functions\when( 'is_email' )->justReturn( true );
        Functions\when( 'get_role' )->justReturn( null );

        $result = $this->exec( 'user create johndoe john@example.com fakerole' );

        $this->assertStringContainsStringIgnoringCase( 'invalid role', $result['output'] );
    }

    public function test_user_create_succeeds_with_valid_inputs(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_user' )->returnArg();
        Functions\when( 'sanitize_email' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'username_exists' )->justReturn( false );
        Functions\when( 'is_email' )->justReturn( true );
        Functions\when( 'get_role' )->justReturn( (object) array( 'name' => 'editor' ) );
        Functions\when( 'wp_generate_password' )->justReturn( 'secret123' );
        Functions\when( 'wp_create_user' )->justReturn( 42 );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );
        Functions\when( 'wp_update_user' )->justReturn( 42 );
        Functions\when( 'wp_new_user_notification' )->justReturn( null );

        $result = $this->exec( 'user create johndoe john@example.com editor' );

        $this->assertTrue( $result['success'] );
    }

    // -----------------------------------------------------------------------
    // user delete (--force)
    // -----------------------------------------------------------------------

    public function test_user_delete_blocked_when_deleting_self(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $user     = new \stdClass();
        $user->ID = 1; // same as current user
        Functions\when( 'get_user_by' )->justReturn( $user );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );

        $result = $this->exec( 'user delete johndoe --delete-content --force' );

        $this->assertStringContainsStringIgnoringCase( 'Cannot delete yourself', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_user_delete_returns_error_when_reassign_target_not_found(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $user     = new \stdClass();
        $user->ID = 5;
        Functions\when( 'get_user_by' )->justReturn( $user );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'get_userdata' )->justReturn( false );

        $result = $this->exec( 'user delete johndoe --reassign=999 --force' );

        $this->assertStringContainsStringIgnoringCase( 'not found', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_user_delete_with_delete_content_calls_wp_delete_user(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $user     = new \stdClass();
        $user->ID = 5;
        Functions\when( 'get_user_by' )->justReturn( $user );
        Functions\when( 'get_current_user_id' )->justReturn( 1 );

        $capturedUserId = null;
        Functions\when( 'wp_delete_user' )->alias(
            function( $uid, $reassign = null ) use ( &$capturedUserId ) {
                $capturedUserId = $uid;
                return true;
            }
        );

        $this->exec( 'user delete johndoe --delete-content --force' );

        $this->assertSame( 5, $capturedUserId );
    }

    // -----------------------------------------------------------------------
    // option get / set / delete
    // -----------------------------------------------------------------------

    public function test_option_get_returns_value(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_cli_enabled' === $key ) return true;
            if ( 'cdw_db_version' === $key ) return \CDW_CLI_Service::DB_VERSION;
            if ( 'my_option' === $key ) return 'hello world';
            return $default;
        } );

        $result = $this->exec( 'option get my_option' );

        $this->assertStringContainsString( 'hello world', $result['output'] );
        $this->assertTrue( $result['success'] );
    }

    public function test_option_get_indicates_not_set_when_value_is_false(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_cli_enabled' === $key ) return true;
            if ( 'cdw_db_version' === $key ) return \CDW_CLI_Service::DB_VERSION;
            return false; // everything else → not found
        } );

        $result = $this->exec( 'option get missing_option' );

        $this->assertStringContainsStringIgnoringCase( 'not found', $result['output'] );
        $this->assertFalse( $result['success'] );
    }

    public function test_option_set_blocks_protected_option(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'option set siteurl https://example.com' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'protected', $result['output'] );
    }

    public function test_option_set_succeeds_when_update_option_returns_true(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_option' )->justReturn( true );

        $result = $this->exec( 'option set my_custom_opt my_value' );

        $this->assertTrue( $result['success'] );
    }

    public function test_option_set_succeeds_even_if_update_option_returns_false_but_value_matches(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'update_option' )->justReturn( false );
        Functions\when( 'get_option' )->alias( function( $key, $default = false ) {
            if ( 'cdw_cli_enabled' === $key ) return true;
            if ( 'cdw_db_version' === $key ) return \CDW_CLI_Service::DB_VERSION;
            return 'my_value'; // already same value
        } );

        $result = $this->exec( 'option set my_custom_opt my_value' );

        $this->assertTrue( $result['success'] );
    }

    public function test_option_delete_blocks_protected_option(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'option delete siteurl --force' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'protected', $result['output'] );
    }

    public function test_option_delete_succeeds_for_non_protected_option(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'delete_option' )->justReturn( true );

        $result = $this->exec( 'option delete my_custom_opt --force' );

        $this->assertTrue( $result['success'] );
    }

    // -----------------------------------------------------------------------
    // maintenance enable / disable / status
    // -----------------------------------------------------------------------

    public function test_maintenance_enable_returns_error_when_file_put_contents_fails(): void {
        $this->stubExecute();
        Functions\when( 'file_put_contents' )->justReturn( false );

        $result = $this->exec( 'maintenance enable' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'could not write', $result['output'] );
    }

    public function test_maintenance_enable_returns_success_when_file_written(): void {
        $this->stubExecute();
        Functions\when( 'file_put_contents' )->justReturn( 100 );

        $result = $this->exec( 'maintenance enable' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'maintenance mode enabled', $result['output'] );
    }

    public function test_maintenance_disable_success_when_file_does_not_exist(): void {
        $this->stubExecute();
        Functions\when( 'file_exists' )->justReturn( false );

        $result = $this->exec( 'maintenance disable' );

        $this->assertTrue( $result['success'] );
    }

    public function test_maintenance_disable_success_when_file_deleted(): void {
        $this->stubExecute();
        Functions\when( 'file_exists' )->justReturn( true );
        Functions\when( 'unlink' )->justReturn( true );

        $result = $this->exec( 'maintenance disable' );

        $this->assertTrue( $result['success'] );
    }

    public function test_maintenance_disable_error_when_unlink_fails(): void {
        $this->stubExecute();
        Functions\when( 'file_exists' )->justReturn( true );
        Functions\when( 'unlink' )->justReturn( false );

        $result = $this->exec( 'maintenance disable' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'could not delete', $result['output'] );
    }

    public function test_maintenance_on_alias_works_like_enable(): void {
        $this->stubExecute();
        Functions\when( 'file_put_contents' )->justReturn( 100 );

        $result = $this->exec( 'maintenance on' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'maintenance mode enabled', $result['output'] );
    }

    public function test_maintenance_off_alias_works_like_disable(): void {
        $this->stubExecute();
        Functions\when( 'file_exists' )->justReturn( false );

        $result = $this->exec( 'maintenance off' );

        $this->assertTrue( $result['success'] );
    }

    public function test_maintenance_status_shows_enabled_when_file_exists(): void {
        $this->stubExecute();
        Functions\when( 'file_exists' )->justReturn( true );

        $result = $this->exec( 'maintenance status' );

        $this->assertStringContainsStringIgnoringCase( 'enabled', $result['output'] );
    }

    public function test_maintenance_status_shows_disabled_when_file_missing(): void {
        $this->stubExecute();
        Functions\when( 'file_exists' )->justReturn( false );

        $result = $this->exec( 'maintenance status' );

        $this->assertStringContainsStringIgnoringCase( 'disabled', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // search-replace (accessed with --force or --dry-run)
    // -----------------------------------------------------------------------

    public function test_search_replace_fewer_than_2_args_shows_usage(): void {
        $this->stubExecute();
        // bypass force guard via --dry-run (1 real arg only)
        Functions\when( 'wp_unslash' )->returnArg();
        $wpdb            = \cdw_tests_reset_wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $result = $this->service->execute( 'search-replace onlyone --dry-run', 1, true );
        $this->assertIsArray( $result );
        $this->assertStringContainsStringIgnoringCase( 'usage', $result['output'] );
    }

    public function test_search_replace_dry_run_calls_get_results_not_update(): void {
        $this->stubExecute();

        $updateCalled = false;
        $wpdb = new class {
            public $prefix  = 'wp_';
            public $queries = array();
            public array $updateCalls = array();

            public function get_results( $sql, $type = 'OBJECT' ) {
                // Return a fake table with a text column.
                if ( strpos( $sql, 'SHOW TABLES' ) !== false ) {
                    // Service uses $table[0]; return a real array row, not stdClass.
                    return array( array( 0 => 'wp_options' ) );
                }
                if ( strpos( $sql, 'SHOW COLUMNS' ) !== false ) {
                    // One text column named 'option_value'
                    return array( array( 'option_value', 'text', 'YES', '', null, '' ) );
                }
                // SELECT COUNT: return 0 matches
                return array();
            }

            public function get_var( $sql ) { return null; }
            public function prepare( $sql, ...$args ) { return $sql; }
            public function query( $sql ) { $this->queries[] = $sql; return 0; }
            public function esc_like( $t ) { return $t; }
            public function update( ...$args ) { $this->updateCalls[] = $args; return 1; }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $result = $this->service->execute( 'search-replace old new --dry-run', 1, true );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'DRY RUN', $result['output'] );
        $this->assertEmpty( $wpdb->updateCalls, 'UPDATE should not be called in --dry-run mode' );
    }

    public function test_search_replace_force_executes_update_queries(): void {
        $this->stubExecute();

        $wpdb = new class {
            public $prefix        = 'wp_';
            public array $queries = array(); // captures query() calls

            public function get_results( $sql, $type = 'OBJECT' ) {
                if ( strpos( $sql, 'SHOW TABLES' ) !== false ) {
                    // Service uses $table[0]; return a real array row, not stdClass.
                    return array( array( 0 => 'wp_posts' ) );
                }
                if ( strpos( $sql, 'SHOW COLUMNS' ) !== false ) {
                    // post_content is longtext with no PK (4th element empty) → bulk UPDATE path.
                    return array( array( 'post_content', 'longtext', 'YES', '', null, '' ) );
                }
                return array();
            }

            public function get_var( $sql ) { return null; }
            public function prepare( $sql, ...$args ) { return $sql; }
            public function query( $sql ) { $this->queries[] = $sql; return 1; }
            public function esc_like( $t ) { return $t; }
            public function update( ...$args ) { return 1; }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $result = $this->service->execute( 'search-replace old new --force', 1, true );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'APPLYING CHANGES', $result['output'] );
        $this->assertNotEmpty( $wpdb->queries, 'Bulk UPDATE query should have been executed' );
        $this->assertStringContainsStringIgnoringCase( 'UPDATE', $wpdb->queries[0] );
        $this->assertStringContainsStringIgnoringCase( 'REPLACE', $wpdb->queries[0] );
    }

    // -----------------------------------------------------------------------
    // replace_in_value() / replace_in_data() — via reflection
    // -----------------------------------------------------------------------

    private function callReplaceInValue( $value, string $old, string $new ) {
        $handler = new \CDW_Search_Replace_Handler();
        $ref     = new \ReflectionClass( $handler );
        $method  = $ref->getMethod( 'replace_in_value' );
        $method->setAccessible( true );
        return $method->invoke( $handler, $value, $old, $new );
    }

    public function test_replace_in_value_plain_string(): void {
        Functions\when( 'is_serialized' )->justReturn( false );
        $result = $this->callReplaceInValue( 'hello world', 'hello', 'goodbye' );
        $this->assertSame( 'goodbye world', $result );
    }

    public function test_replace_in_value_serialized_string(): void {
        $serialized = serialize( 'https://old.com/path' );
        // Use real is_serialized (WP function); stub it to return true
        Functions\when( 'is_serialized' )->justReturn( true );
        $result = $this->callReplaceInValue( $serialized, 'https://old.com', 'https://new.com' );
        $this->assertStringContainsString( 'https://new.com', $result );
    }

    public function test_replace_in_value_nested_array(): void {
        Functions\when( 'is_serialized' )->justReturn( false );
        $input  = array( 'a' => array( 'b' => 'old value' ) );
        $result = $this->callReplaceInValue( $input, 'old', 'new' );
        $this->assertSame( 'new value', $result['a']['b'] );
    }

    public function test_replace_in_value_object_property(): void {
        Functions\when( 'is_serialized' )->justReturn( false );
        $obj      = new \stdClass();
        $obj->key = 'old text';
        $result   = $this->callReplaceInValue( $obj, 'old', 'new' );
        $this->assertSame( 'new text', $result->key );
    }

    public function test_replace_in_value_integer_scalar_unchanged(): void {
        Functions\when( 'is_serialized' )->justReturn( false );
        $result = $this->callReplaceInValue( 42, 'old', 'new' );
        $this->assertSame( 42, $result );
    }

    public function test_replace_in_value_boolean_scalar_unchanged(): void {
        Functions\when( 'is_serialized' )->justReturn( false );
        $result = $this->callReplaceInValue( true, 'old', 'new' );
        $this->assertTrue( $result );
    }

    public function test_replace_in_value_null_unchanged(): void {
        Functions\when( 'is_serialized' )->justReturn( false );
        $result = $this->callReplaceInValue( null, 'old', 'new' );
        $this->assertNull( $result );
    }

    // -----------------------------------------------------------------------
    // post create
    // -----------------------------------------------------------------------

    public function test_post_create_no_args_returns_usage_error(): void {
        $this->stubExecute();

        $result = $this->exec( 'post create' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'Usage: post create', $result['output'] );
    }

    public function test_post_create_single_word_title_succeeds(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_insert_post' )->justReturn( 99 );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->exec( 'post create Hello' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( '99', $result['output'] );
        $this->assertStringContainsStringIgnoringCase( 'draft', $result['output'] );
    }

    public function test_post_create_multi_word_title_joins_words_and_succeeds(): void {
        $this->stubExecute();
        $capturedArgs = null;
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_insert_post' )->alias(
            function( $postarr, $wp_error = false ) use ( &$capturedArgs ) {
                $capturedArgs = $postarr;
                return 7;
            }
        );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->exec( 'post create My New Post Title' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'My New Post Title', $capturedArgs['post_title'] );
        $this->assertSame( 'draft', $capturedArgs['post_status'] );
    }

    public function test_post_create_returns_error_on_wp_insert_post_failure(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_insert_post' )->justReturn(
            new \WP_Error( 'insert_failed', 'Could not insert post' )
        );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $result = $this->exec( 'post create Bad Post' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'Failed to create post', $result['output'] );
        $this->assertStringContainsString( 'Could not insert post', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // post count
    // -----------------------------------------------------------------------

    public function test_post_count_no_args_returns_all_public_types(): void {
        $this->stubExecute();

        // WordPress returns associative array: ['post' => 'post', 'page' => 'page', 'attachment' => 'attachment']
        Functions\when( 'get_post_types' )->justReturn( array(
            'post' => 'post',
            'page' => 'page',
            'attachment' => 'attachment',
        ) );
        Functions\when( 'wp_count_posts' )->justReturn(
            (object) array(
                'publish' => 10,
                'draft'   => 2,
                'pending' => 1,
                'trash'   => 0,
            )
        );
        Functions\when( 'get_post_type_object' )->alias(
            function( $type ) {
                // Always return an object with labels property
                $obj = new \stdClass();
                $obj->labels = new \stdClass();
                if ( 'post' === $type ) {
                    $obj->labels->singular_name = 'Post';
                } elseif ( 'page' === $type ) {
                    $obj->labels->singular_name = 'Page';
                }
                return $obj;
            }
        );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'post count' );

        $this->assertTrue( $result['success'], 'Output: ' . $result['output'] );
        $this->assertStringContainsString( 'Post counts by type', $result['output'] );
        $this->assertStringContainsString( 'publish:', $result['output'] );
    }

    public function test_post_count_specific_type_returns_counts(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_post_type_object' )->justReturn(
            (object) array( 'public' => true )
        );
        Functions\when( 'wp_count_posts' )->justReturn(
            (object) array(
                'publish' => 5,
                'draft'   => 3,
                'pending' => 0,
                'trash'   => 1,
            )
        );

        $result = $this->exec( 'post count page' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'Post counts for type: page', $result['output'] );
        $this->assertStringContainsString( 'publish:  5', $result['output'] );
        $this->assertStringContainsString( 'draft:    3', $result['output'] );
    }

    public function test_post_count_invalid_type_returns_error(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_post_type_object' )->justReturn( null );

        $result = $this->exec( 'post count invalid_type' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'Invalid or non-public post type', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // comment list
    // -----------------------------------------------------------------------

    public function test_comment_list_pending_returns_comments(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'get_comments' )->justReturn( array(
            (object) array(
                'comment_ID'      => 1,
                'comment_date'    => '2026-01-15 10:00:00',
                'comment_author'  => 'Alice',
                'comment_content' => 'This is a test comment',
            ),
        ) );
        Functions\when( 'date_i18n' )->alias( function( $format, $timestamp ) {
            return date( $format, $timestamp );
        } );
        Functions\when( 'wp_trim_words' )->alias( function( $text, $num, $more ) {
            return $text;
        } );

        $result = $this->exec( 'comment list' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'Alice', $result['output'] );
        $this->assertStringContainsString( '[1]', $result['output'] );
    }

    public function test_comment_list_approved_filters_by_status(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        $captured = null;
        Functions\when( 'get_comments' )->alias( function( $args ) use ( &$captured ) {
            $captured = $args;
            return array();
        } );

        $result = $this->exec( 'comment list approved' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'approve', $captured['status'] );
        $this->assertStringContainsStringIgnoringCase( 'no comments', $result['output'] );
    }

    public function test_comment_list_spam_filters_by_status(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();
        $captured = null;
        Functions\when( 'get_comments' )->alias( function( $args ) use ( &$captured ) {
            $captured = $args;
            return array();
        } );

        $result = $this->exec( 'comment list spam' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'spam', $captured['status'] );
    }

    // -----------------------------------------------------------------------
    // comment approve
    // -----------------------------------------------------------------------

    public function test_comment_approve_succeeds(): void {
        $this->stubExecute();
        $capturedArgs = array();
        Functions\when( 'wp_set_comment_status' )->alias(
            function ( $id, $status ) use ( &$capturedArgs ) {
                $capturedArgs = array( 'id' => $id, 'status' => $status );
                return true;
            }
        );

        $result = $this->exec( 'comment approve 42' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'Comment approved: 42', $result['output'] );
        $this->assertSame( 42, $capturedArgs['id'] );
        $this->assertSame( 'approve', $capturedArgs['status'] );
    }

    public function test_comment_approve_returns_error_when_no_id(): void {
        $this->stubExecute();

        $result = $this->exec( 'comment approve' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'usage', $result['output'] );
    }

    public function test_comment_approve_returns_error_when_comment_not_found(): void {
        $this->stubExecute();
        Functions\when( 'wp_set_comment_status' )->justReturn( false );

        $result = $this->exec( 'comment approve 999' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'not found', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // comment spam
    // -----------------------------------------------------------------------

    public function test_comment_spam_succeeds(): void {
        $this->stubExecute();
        $capturedArgs = array();
        Functions\when( 'wp_set_comment_status' )->alias(
            function ( $id, $status ) use ( &$capturedArgs ) {
                $capturedArgs = array( 'id' => $id, 'status' => $status );
                return true;
            }
        );

        $result = $this->exec( 'comment spam 47' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'Comment marked as spam: 47', $result['output'] );
        $this->assertSame( 47, $capturedArgs['id'] );
        $this->assertSame( 'spam', $capturedArgs['status'] );
    }

    public function test_comment_spam_returns_error_when_no_id(): void {
        $this->stubExecute();

        $result = $this->exec( 'comment spam' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'usage', $result['output'] );
    }

    public function test_comment_spam_returns_error_when_comment_not_found(): void {
        $this->stubExecute();
        Functions\when( 'wp_set_comment_status' )->justReturn( false );

        $result = $this->exec( 'comment spam 999' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'not found', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // comment delete
    // -----------------------------------------------------------------------

    public function test_comment_delete_succeeds_with_force(): void {
        $this->stubExecute();
        $capturedArgs = array();
        Functions\when( 'wp_delete_comment' )->alias(
            function ( $id, $force ) use ( &$capturedArgs ) {
                $capturedArgs = array( 'id' => $id, 'force' => $force );
                return true;
            }
        );

        $result = $this->exec( 'comment delete 53 --force' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'Comment deleted: 53', $result['output'] );
        $this->assertSame( 53, $capturedArgs['id'] );
        $this->assertTrue( $capturedArgs['force'] );
    }

    public function test_comment_delete_requires_force_flag(): void {
        $this->stubExecute();

        $result = $this->exec( 'comment delete 5' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( '--force', $result['output'] );
    }

    public function test_comment_delete_returns_error_when_no_id(): void {
        $this->stubExecute();

        $result = $this->exec( 'comment delete' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'usage', $result['output'] );
    }

    public function test_comment_delete_returns_error_when_comment_not_found(): void {
        $this->stubExecute();
        Functions\when( 'wp_delete_comment' )->justReturn( false );

        $result = $this->exec( 'comment delete 999 --force' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'not found', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // rewrite
    // -----------------------------------------------------------------------

    public function test_rewrite_flush_succeeds(): void {
        $this->stubExecute();
        // Use expect() so the test fails if flush_rewrite_rules is never called
        // or called with the wrong argument (true = hard flush, writes .htaccess).
        Functions\expect( 'flush_rewrite_rules' )->once()->with( true );

        $result = $this->exec( 'rewrite flush' );

        $this->assertTrue( $result['success'] );
        $this->assertSame( 'Rewrite rules flushed.', $result['output'] );
    }

    public function test_rewrite_unknown_subcmd_returns_help(): void {
        $this->stubExecute();

        $result = $this->exec( 'rewrite' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'rewrite flush', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // core
    // -----------------------------------------------------------------------

    public function test_core_version_shows_wp_and_php_version(): void {
        $this->stubExecute();
        Functions\when( 'get_bloginfo' )->alias(
            function ( $key ) {
                return 'version' === $key ? '6.7.1' : '';
            }
        );
        Functions\when( 'get_core_updates' )->justReturn( array() );

        $result = $this->exec( 'core version' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( '6.7.1', $result['output'] );
        $this->assertStringContainsString( PHP_VERSION, $result['output'] );
    }

    public function test_core_version_shows_up_to_date_when_no_updates(): void {
        $this->stubExecute();
        Functions\when( 'get_bloginfo' )->alias(
            function ( $key ) {
                return 'version' === $key ? '6.7.1' : '';
            }
        );
        $latest           = new \stdClass();
        $latest->response = 'latest';
        Functions\when( 'get_core_updates' )->justReturn( array( $latest ) );

        $result = $this->exec( 'core version' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'up to date', $result['output'] );
    }

    public function test_core_version_shows_update_available(): void {
        $this->stubExecute();
        Functions\when( 'get_bloginfo' )->alias(
            function ( $key ) {
                return 'version' === $key ? '6.7.1' : '';
            }
        );
        $latest           = new \stdClass();
        $latest->response = 'upgrade';
        $latest->version  = '6.8';
        Functions\when( 'get_core_updates' )->justReturn( array( $latest ) );

        $result = $this->exec( 'core version' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'available', $result['output'] );
        $this->assertStringContainsString( '6.8', $result['output'] );
    }

    public function test_core_unknown_subcmd_returns_help(): void {
        $this->stubExecute();

        $result = $this->exec( 'core' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'core version', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // media list
    // -----------------------------------------------------------------------

    public function test_media_list_returns_attachment_details(): void {
        $this->stubExecute();
        $att          = new \stdClass();
        $att->ID      = 42;
        $att->guid    = 'https://example.com/wp-content/uploads/photo.jpg';
        $att->post_mime_type = 'image/jpeg';
        $att->post_date      = '2026-01-10 12:00:00';
        Functions\when( 'get_posts' )->justReturn( array( $att ) );

        $result = $this->exec( 'media list' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( '[42]', $result['output'] );
        $this->assertStringContainsString( 'photo.jpg', $result['output'] );
        $this->assertStringContainsString( 'image/jpeg', $result['output'] );
        $this->assertStringContainsString( '2026-01-10', $result['output'] );
    }

    public function test_media_list_empty_returns_no_attachments_message(): void {
        $this->stubExecute();
        Functions\when( 'get_posts' )->justReturn( array() );

        $result = $this->exec( 'media list' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'no media', $result['output'] );
    }

    public function test_media_unknown_subcmd_returns_help(): void {
        $this->stubExecute();

        $result = $this->exec( 'media' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'media list', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // block-patterns list
    // -----------------------------------------------------------------------

    public function test_block_patterns_list_returns_pattern_names(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_text_field' )->returnArg();

        // Inject a stub registry directly — Brain\Monkey cannot mock static methods.
        $registry = new class extends \WP_Block_Patterns_Registry {
            public function get_all_registered(): array {
                return array(
                    array( 'name' => 'greenshift/hero',  'title' => 'Hero Section', 'categories' => array( 'featured' ) ),
                    array( 'name' => 'greenshift/cards', 'title' => 'Card Grid',    'categories' => array( 'columns' ) ),
                );
            }
        };
        \WP_Block_Patterns_Registry::$instance = $registry;

        $result = $this->exec( 'block-patterns list' );

        // Reset singleton so it does not bleed into other tests.
        \WP_Block_Patterns_Registry::$instance = null;

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'greenshift/hero',  $result['output'] );
        $this->assertStringContainsString( 'greenshift/cards', $result['output'] );
    }

    public function test_block_patterns_unknown_subcmd_returns_help(): void {
        $this->stubExecute();

        $result = $this->exec( 'block-patterns' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'block-patterns list', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // skill list
    // -----------------------------------------------------------------------

    public function test_skill_list_returns_no_skills_message_when_empty(): void {
        $this->stubExecute();
        Functions\when( 'glob' )->justReturn( array() );

        $result = $this->exec( 'skill list' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'No plugin skills found', $result['output'] );
    }

    public function test_skill_list_shows_plugin_and_skill_name(): void {
        $this->stubExecute();
        Functions\when( 'glob' )->justReturn( array(
            '/fake/plugins/greenshift-foo/skills/greenlight-vibe/SKILL.md',
        ) );

        $result = $this->exec( 'skill list' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'greenshift-foo', $result['output'] );
        $this->assertStringContainsString( 'greenlight-vibe', $result['output'] );
    }

    // -----------------------------------------------------------------------
    // skill get
    // -----------------------------------------------------------------------

    public function test_skill_get_missing_args_returns_usage_error(): void {
        $this->stubExecute();

        $result = $this->exec( 'skill get' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'Usage:', $result['output'] );
    }

    public function test_skill_get_rejects_non_md_file(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'skill get my-plugin my-skill config.php' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( '.md', $result['output'] );
    }

    public function test_skill_get_rejects_dotdot_traversal(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $result = $this->exec( 'skill get my-plugin my-skill ../../wp-config.md' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsStringIgnoringCase( 'access denied', $result['output'] );
    }

    public function test_skill_get_returns_not_found_when_realpath_fails(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'realpath' )->justReturn( false );

        $result = $this->exec( 'skill get my-plugin my-skill' );

        $this->assertFalse( $result['success'] );
        $this->assertStringContainsString( 'not found', $result['output'] );
    }

    public function test_skill_get_returns_file_contents_on_success(): void {
        $this->stubExecute();
        Functions\when( 'sanitize_key' )->returnArg();
        Functions\when( 'sanitize_text_field' )->returnArg();

        $plugins_dir = '/fake/plugins';
        Functions\when( 'realpath' )->alias( function ( $path ) use ( $plugins_dir ) {
            if ( $path === WP_PLUGIN_DIR ) {
                return $plugins_dir;
            }
            return $plugins_dir . '/my-plugin/skills/my-skill/SKILL.md';
        } );
        Functions\when( 'file_get_contents' )->justReturn( '# My Skill' );

        $result = $this->exec( 'skill get my-plugin my-skill' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( '# My Skill', $result['output'] );
    }

    public function test_skill_unknown_subcmd_returns_help(): void {
        $this->stubExecute();

        $result = $this->exec( 'skill' );

        $this->assertTrue( $result['success'] );
        $this->assertStringContainsString( 'skill list', $result['output'] );
        $this->assertStringContainsString( 'skill get', $result['output'] );
    }
}

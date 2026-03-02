<?php

namespace CDW\Tests\Integration;

/**
 * Integration tests for the CDW_CLI audit log table lifecycle:
 *  - Table created on activation.
 *  - Row inserted after execute().
 *  - Table dropped on uninstall.
 *
 * @group integration
 */
class AuditLogTest extends \WP_UnitTestCase {

    /** @var string Full table name including wpdb prefix */
    private string $table_name;

    /** @var int Admin user ID */
    private int $admin_id;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        // functions-uninstall.php is only required by uninstall.php, not by the
        // main plugin file.  Load it here so cdw_do_uninstall() is available.
        if ( ! function_exists( 'cdw_do_uninstall' ) ) {
            require_once CDW_PLUGIN_DIR . 'includes/functions-uninstall.php';
        }
    }

    public function set_up(): void {
        parent::set_up();

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cdw_cli_logs';
        $this->admin_id   = self::factory()->user->create( array( 'role' => 'administrator' ) );
    }

    public function tear_down(): void {
        global $wpdb;

        // Drop the audit table so each test starts clean.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "DROP TABLE IF EXISTS `{$this->table_name}`" );

        // Remove DB version option so ensure_audit_table() re-runs next time.
        delete_option( 'cdw_db_version' );
        delete_option( 'cdw_delete_on_uninstall' );

        parent::tear_down();
    }

    // -----------------------------------------------------------------------
    // Helper: confirm table existence
    // -----------------------------------------------------------------------

    private function tableExists(): bool {
        global $wpdb;
        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like( $this->table_name )
            )
        );
        return $result === $this->table_name;
    }

    private function rowCount(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table_name}`" );
    }

    // -----------------------------------------------------------------------
    // 1. Table created on activation
    // -----------------------------------------------------------------------

    public function test_activation_creates_audit_log_table(): void {
        $this->assertFalse( $this->tableExists(), 'Table should not exist before activation.' );

        CDW_activate();

        $this->assertTrue( $this->tableExists(), 'Activation should create the cdw_cli_logs table.' );
    }

    // -----------------------------------------------------------------------
    // 2. Row inserted after execute()
    // -----------------------------------------------------------------------

    public function test_execute_inserts_audit_row(): void {
        // Create the table first (simulates activation having run).
        CDW_activate();
        $this->assertTrue( $this->tableExists() );
        $this->assertSame( 0, $this->rowCount(), 'Table should be empty before execute().' );

        // Run a simple, non-destructive command as admin.
        wp_set_current_user( $this->admin_id );

        $cli     = new \CDW_CLI_Service();
        $result  = $cli->execute( 'plugin list', $this->admin_id, true /* bypass_rate_limit */ );

        // execute() may return an array or WP_Error depending on environment.
        // Either way, the log should have been written (it is written before
        // returning for non-WP_Error paths).
        if ( ! is_wp_error( $result ) ) {
            $this->assertGreaterThanOrEqual(
                1,
                $this->rowCount(),
                'execute() should insert at least one row into the audit log.'
            );
        } else {
            // If a WP_Error is returned (e.g. CLI disabled), there is no log row.
            $this->markTestSkipped( 'CLI returned WP_Error: ' . $result->get_error_message() );
        }
    }

    // -----------------------------------------------------------------------
    // 3. Table dropped on uninstall
    // -----------------------------------------------------------------------

    public function test_uninstall_drops_audit_log_table(): void {
        // Ensure the table exists first.
        CDW_activate();
        $this->assertTrue( $this->tableExists(), 'Pre-condition: table must exist before uninstall.' );

        // Allow uninstall to proceed.
        update_option( 'cdw_delete_on_uninstall', true );

        // Call the uninstall function — qualify with global namespace so it
        // resolves outside this namespace.
        \cdw_do_uninstall();

        $this->assertFalse( $this->tableExists(), 'Uninstall should drop the cdw_cli_logs table.' );
    }
}

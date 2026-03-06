<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;
use Brain\Monkey\Functions;

// WP_CLI stub is defined in the global namespace inside wp-stubs.php,
// so the "if ( ! class_exists( 'WP_CLI' ) ) { return; }" guard in the
// command class file will not bail out.
require_once CDW_PLUGIN_DIR . 'tests/php/stubs/wp-stubs.php';

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/cli/class-cdw-cli-command.php';

class CliCommandTest extends CDWTestCase {

    private \CDW_CLI_Command $command;
    private $mockStats;
    private $mockTask;
    private $mockCli;

    protected function setUp(): void {
        parent::setUp();

        // Provide minimum WP stubs for service construction.
        $GLOBALS['wpdb'] = new \wpdb();
        Functions\when( 'get_option' )->justReturn( false );
        Functions\when( 'get_transient' )->justReturn( 0 );
        Functions\when( 'get_user_meta' )->justReturn( '' );

        $this->command   = new \CDW_CLI_Command();
        $this->mockStats = \Mockery::mock( 'CDW_Stats_Service' );
        $this->mockTask  = \Mockery::mock( 'CDW_Task_Service' );
        $this->mockCli   = \Mockery::mock( 'CDW_CLI_Service' );

        $ref = new \ReflectionClass( $this->command );

        $prop = $ref->getProperty( 'stats_service' );
        $prop->setAccessible( true );
        $prop->setValue( $this->command, $this->mockStats );

        $prop = $ref->getProperty( 'task_service' );
        $prop->setAccessible( true );
        $prop->setValue( $this->command, $this->mockTask );

        $prop = $ref->getProperty( 'cli_service' );
        $prop->setAccessible( true );
        $prop->setValue( $this->command, $this->mockCli );

        \WP_CLI::reset();
    }

    // -----------------------------------------------------------------------
    // dispatch() — routing
    // -----------------------------------------------------------------------

    public function test_dispatch_with_empty_args_outputs_help(): void {
        $this->command->dispatch( array(), array() );

        $this->assertNotEmpty( \WP_CLI::$lines );
        $this->assertStringContainsString( 'CDW Commands', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_with_help_command_outputs_help(): void {
        $this->command->dispatch( array( 'help' ), array() );

        $this->assertStringContainsString( 'CDW Commands', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_with_unknown_command_records_error(): void {
        $this->command->dispatch( array( 'unknown-xyz' ), array() );

        $this->assertNotEmpty( \WP_CLI::$errors );
        $this->assertStringContainsString( 'unknown-xyz', \WP_CLI::$errors[0] );
    }

    // -----------------------------------------------------------------------
    // dispatch → stats
    // -----------------------------------------------------------------------

    public function test_dispatch_stats_outputs_site_statistics_header(): void {
        $this->mockStats->shouldReceive( 'get_stats' )->once()->andReturn( array(
            'posts' => 5, 'pages' => 2, 'comments' => 10, 'users' => 4,
            'media' => 3, 'categories' => 7, 'tags' => 8, 'plugins' => 2, 'themes' => 3,
        ) );

        $this->command->dispatch( array( 'stats' ), array() );

        $this->assertStringContainsString( 'Site Statistics', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_stats_outputs_all_numeric_values(): void {
        $this->mockStats->shouldReceive( 'get_stats' )->once()->andReturn( array(
            'posts' => 5, 'pages' => 2, 'comments' => 0, 'users' => 4,
            'media' => 3, 'categories' => 7, 'tags' => 8, 'plugins' => 2, 'themes' => 3,
        ) );

        $this->command->dispatch( array( 'stats' ), array() );

        $output = implode( "\n", \WP_CLI::$lines );
        $this->assertStringContainsString( 'Posts:', $output );
        $this->assertStringContainsString( 'Users:', $output );
        $this->assertStringContainsString( 'Plugins:', $output );
        $this->assertStringContainsString( 'Themes:', $output );
    }

    public function test_dispatch_stats_outputs_products_when_present(): void {
        $this->mockStats->shouldReceive( 'get_stats' )->once()->andReturn( array(
            'posts' => 1, 'pages' => 1, 'comments' => 1, 'users' => 1,
            'media' => 1, 'categories' => 1, 'tags' => 1, 'plugins' => 1, 'themes' => 1,
            'products' => 12,
        ) );

        $this->command->dispatch( array( 'stats' ), array() );

        $this->assertStringContainsString( 'Products:', implode( "\n", \WP_CLI::$lines ) );
    }

    // -----------------------------------------------------------------------
    // dispatch → tasks
    // -----------------------------------------------------------------------

    public function test_dispatch_tasks_list_with_no_tasks_outputs_no_tasks_found(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockTask->shouldReceive( 'get_tasks' )->once()->with( 1 )->andReturn( array() );

        $this->command->dispatch( array( 'tasks' ), array() );

        $this->assertStringContainsString( 'No tasks found', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_tasks_list_with_tasks_outputs_task_names(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'wp_date' )->justReturn( '2024-01-01 10:00' );
        $this->mockTask->shouldReceive( 'get_tasks' )->once()->andReturn( array(
            array( 'name' => 'Deploy hotfix', 'timestamp' => time() ),
        ) );

        $this->command->dispatch( array( 'tasks' ), array() );

        $this->assertStringContainsString( 'Deploy hotfix', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_tasks_list_uses_assoc_arg_user_when_provided(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockTask->shouldReceive( 'get_tasks' )->once()->with( 7 )->andReturn( array() );

        $this->command->dispatch( array( 'tasks', 'list' ), array( 'user' => '7' ) );

        $this->addToAssertionCount( 1 ); // Mockery expectation verified in tearDown.
    }

    public function test_dispatch_tasks_clear_calls_delete_tasks_and_outputs_success(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 3 );
        $this->mockTask->shouldReceive( 'delete_tasks' )->once()->with( 3 );

        $this->command->dispatch( array( 'tasks', 'clear' ), array() );

        $this->assertStringContainsString( 'Tasks cleared', implode( ' ', \WP_CLI::$successes ) );
    }

    public function test_dispatch_tasks_clear_uses_assoc_arg_user(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockTask->shouldReceive( 'delete_tasks' )->once()->with( 9 );

        $this->command->dispatch( array( 'tasks', 'clear' ), array( 'user' => '9' ) );

        $this->addToAssertionCount( 1 ); // Mockery expectation verified in tearDown.
    }

    public function test_dispatch_tasks_unknown_subcommand_records_error(): void {
        $this->command->dispatch( array( 'tasks', 'unknown-sub' ), array() );

        $this->assertNotEmpty( \WP_CLI::$errors );
    }

    // -----------------------------------------------------------------------
    // dispatch → cli execute
    // -----------------------------------------------------------------------

    public function test_dispatch_cli_execute_calls_service_and_outputs_result(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'is_wp_error' )->justReturn( false );
        $this->mockCli->shouldReceive( 'execute' )->once()
            ->with( 'plugin list', 1, true )
            ->andReturn( array( 'output' => 'Plugin list result', 'success' => true ) );

        $this->command->dispatch( array( 'cli', 'execute', 'plugin', 'list' ), array() );

        $this->assertStringContainsString( 'Plugin list result', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_cli_execute_with_empty_command_records_error(): void {
        $this->command->dispatch( array( 'cli', 'execute' ), array() );

        $this->assertNotEmpty( \WP_CLI::$errors );
    }

    public function test_dispatch_cli_execute_with_wp_error_result_records_error(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $error = new \WP_Error( 'cli_error', 'Something went wrong' );
        $this->mockCli->shouldReceive( 'execute' )->once()->andReturn( $error );
        Functions\when( 'is_wp_error' )->alias( function( $t ) { return $t instanceof \WP_Error; } );

        $this->command->dispatch( array( 'cli', 'execute', 'bad-cmd' ), array() );

        $this->assertNotEmpty( \WP_CLI::$errors );
        $this->assertStringContainsString( 'Something went wrong', \WP_CLI::$errors[0] );
    }

    // -----------------------------------------------------------------------
    // dispatch → cli history / clear
    // -----------------------------------------------------------------------

    public function test_dispatch_cli_history_with_no_history_outputs_no_history(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        $this->mockCli->shouldReceive( 'get_history' )->once()->with( 1 )->andReturn( array() );

        $this->command->dispatch( array( 'cli', 'history' ), array() );

        $this->assertStringContainsString( 'No command history', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_cli_history_with_entries_outputs_them(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 1 );
        Functions\when( 'wp_date' )->justReturn( '2024-01-01 12:00' );
        $this->mockCli->shouldReceive( 'get_history' )->once()->andReturn( array(
            array( 'command' => 'user list', 'success' => true, 'timestamp' => time() ),
        ) );

        $this->command->dispatch( array( 'cli', 'history' ), array() );

        $this->assertStringContainsString( 'user list', implode( ' ', \WP_CLI::$lines ) );
    }

    public function test_dispatch_cli_clear_calls_clear_history_and_outputs_success(): void {
        Functions\when( 'get_current_user_id' )->justReturn( 2 );
        $this->mockCli->shouldReceive( 'clear_history' )->once()->with( 2 );

        $this->command->dispatch( array( 'cli', 'clear' ), array() );

        $this->assertStringContainsString( 'CLI history cleared', implode( ' ', \WP_CLI::$successes ) );
    }

    public function test_dispatch_cli_unknown_subcommand_records_error(): void {
        $this->command->dispatch( array( 'cli', 'bad-sub' ), array() );

        $this->assertNotEmpty( \WP_CLI::$errors );
    }
}

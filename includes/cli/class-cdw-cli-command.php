<?php
/**
 * WP-CLI command handler for the CDW plugin.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-stats-service.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

/**
 * Registers and dispatches `wp cdw` sub-commands (stats, tasks, cli).
 *
 * @package CDW
 */
class CDW_CLI_Command {
	/**
	 * Stats service instance.
	 *
	 * @var CDW_Stats_Service
	 */
	private $stats_service;

	/**
	 * Task service instance.
	 *
	 * @var CDW_Task_Service
	 */
	private $task_service;

	/**
	 * CLI service instance.
	 *
	 * @var CDW_CLI_Service
	 */
	private $cli_service;

	/**
	 * Constructor — instantiates all required services.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->stats_service = new CDW_Stats_Service();
		$this->task_service  = new CDW_Task_Service();
		$this->cli_service   = new CDW_CLI_Service();
	}

	/**
	 * Registers the `wp cdw` command with WP-CLI.
	 *
	 * @return void
	 */
	public function register() {
		WP_CLI::add_command( 'cdw', array( $this, 'dispatch' ) );
	}

	/**
	 * Routes the first positional argument to the appropriate sub-command.
	 *
	 * @param string[]            $args       Indexed array of positional args.
	 * @param array<string,mixed> $assoc_args Associative array of flags and options.
	 * @return void
	 */
	public function dispatch( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			$this->show_help();
			return;
		}

		$command = array_shift( $args );

		switch ( $command ) {
			case 'stats':
				$this->stats( $args, $assoc_args );
				break;
			case 'tasks':
				$this->tasks( $args, $assoc_args );
				break;
			case 'cli':
				$this->cli( $args, $assoc_args );
				break;
			case 'help':
				$this->show_help();
				break;
			default:
				WP_CLI::error( "Unknown command: $command" );
		}
	}

	/**
	 * Outputs site statistics via WP-CLI.
	 *
	 * @param string[]            $args       Positional args (unused — required by WP-CLI dispatch signature).
	 * @param array<string,mixed> $assoc_args Flags (unused — required by WP-CLI dispatch signature).
	 * @return void
	 */
	private function stats( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$stats = $this->stats_service->get_stats();

		WP_CLI::line( '=== Site Statistics ===' );
		WP_CLI::line( 'Posts:     ' . $stats['posts'] );
		WP_CLI::line( 'Pages:     ' . $stats['pages'] );
		WP_CLI::line( 'Comments:  ' . $stats['comments'] );
		WP_CLI::line( 'Users:     ' . $stats['users'] );
		WP_CLI::line( 'Media:     ' . $stats['media'] );
		WP_CLI::line( 'Categories:' . $stats['categories'] );
		WP_CLI::line( 'Tags:      ' . $stats['tags'] );
		WP_CLI::line( 'Plugins:   ' . $stats['plugins'] );
		WP_CLI::line( 'Themes:    ' . $stats['themes'] );

		if ( isset( $stats['products'] ) ) {
			WP_CLI::line( 'Products:  ' . $stats['products'] );
		}
	}

	/**
	 * Lists or clears tasks via WP-CLI.
	 *
	 * @param string[]            $args       Positional args; first element is the sub-command.
	 * @param array<string,mixed> $assoc_args Flags; --user=<id> overrides the target user.
	 * @return void
	 */
	private function tasks( $args, $assoc_args ) {
		$subcommand = ! empty( $args[0] ) ? $args[0] : 'list';

		switch ( $subcommand ) {
			case 'list':
				$user_id = isset( $assoc_args['user'] ) ? intval( $assoc_args['user'] ) : get_current_user_id();
				$tasks   = $this->task_service->get_tasks( $user_id );

				if ( empty( $tasks ) ) {
					WP_CLI::line( 'No tasks found.' );
					return;
				}

				WP_CLI::line( '=== Tasks ===' );
				foreach ( $tasks as $index => $task ) {
					$date = wp_date( 'Y-m-d H:i', $task['timestamp'] );
					WP_CLI::line( sprintf( '%d. %s (created: %s)', $index + 1, $task['name'], $date ) );
				}
				break;

			case 'clear':
				$user_id = isset( $assoc_args['user'] ) ? intval( $assoc_args['user'] ) : get_current_user_id();
				$this->task_service->delete_tasks( $user_id );
				WP_CLI::success( 'Tasks cleared.' );
				break;

			default:
				WP_CLI::error( "Unknown tasks command: $subcommand" );
		}
	}

	/**
	 * Executes, lists or clears CLI history via WP-CLI.
	 *
	 * @param string[]            $args       Positional args; first element is the sub-command.
	 * @param array<string,mixed> $assoc_args Flags (unused at top level, forwarded to sub-commands).
	 * @return void
	 */
	private function cli( $args, $assoc_args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$subcommand = ! empty( $args[0] ) ? $args[0] : 'execute';

		switch ( $subcommand ) {
			case 'execute':
				if ( empty( $args[1] ) ) {
					WP_CLI::error( 'Usage: wp cdw cli execute <command>' );
					return;
				}
				$command = implode( ' ', array_slice( $args, 1 ) );
				$user_id = get_current_user_id();

				$result = $this->cli_service->execute( $command, $user_id, true );

				if ( is_wp_error( $result ) ) {
					WP_CLI::error( $result->get_error_message() );
					return;
				}

				WP_CLI::line( $result['output'] );
				break;

			case 'history':
				$user_id = get_current_user_id();
				$history = $this->cli_service->get_history( $user_id );

				if ( empty( $history ) ) {
					WP_CLI::line( 'No command history.' );
					return;
				}

				WP_CLI::line( '=== CLI Command History ===' );
				foreach ( $history as $index => $item ) {
					$date   = wp_date( 'Y-m-d H:i', $item['timestamp'] );
					$status = $item['success'] ? '[OK]' : '[FAIL]';
					WP_CLI::line( sprintf( '%d. %s %s - %s', $index + 1, $status, $date, $item['command'] ) );
				}
				break;

			case 'clear':
				$user_id = get_current_user_id();
				$this->cli_service->clear_history( $user_id );
				WP_CLI::success( 'CLI history cleared.' );
				break;

			default:
				WP_CLI::error( "Unknown cli command: $subcommand" );
		}
	}

	/**
	 * Prints usage information for all `wp cdw` sub-commands.
	 *
	 * @return void
	 */
	private function show_help() {
		WP_CLI::line( '=== CDW Commands ===' );
		WP_CLI::line( '' );
		WP_CLI::line( 'wp cdw stats              - Show site statistics' );
		WP_CLI::line( '' );
		WP_CLI::line( 'wp cdw tasks list         - List tasks' );
		WP_CLI::line( 'wp cdw tasks clear        - Clear tasks' );
		WP_CLI::line( '' );
		WP_CLI::line( 'wp cdw cli execute <cmd>  - Execute CLI command' );
		WP_CLI::line( 'wp cdw cli history        - Show command history' );
		WP_CLI::line( 'wp cdw cli clear          - Clear command history' );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$cdw_cli = new CDW_CLI_Command();
	$cdw_cli->register();
}

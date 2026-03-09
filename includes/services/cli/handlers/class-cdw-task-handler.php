<?php
/**
 * Task command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles task management commands (list, create, delete).
 */
class CDW_Task_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a task subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, create, delete).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-task-service.php';
		$task_service = new \CDW_Task_Service();

		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list( $task_service, $raw_args );

			case 'create':
				return $this->handle_create( $task_service, $args, $raw_args );

			case 'delete':
				return $this->handle_delete( $task_service, $raw_args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for task commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available task commands:\n  task list [--user_id=<id>]                                       - List pending tasks\n  task create <name> [--assignee_login=<user>|--assignee_id=<id>] - Create a task\n  task delete [--user_id=<id>]                                    - Delete all tasks",
			'success' => true,
		);
	}

	/**
	 * Check if subcommand requires --force flag.
	 *
	 * @param string $subcmd The subcommand to check.
	 * @return bool
	 */
	public function requires_force( string $subcmd ): bool {
		return 'delete' === $subcmd;
	}

	/**
	 * Handle task list.
	 *
	 * @param object $task_service Task service instance.
	 * @param array<int,string> $raw_args Raw arguments.
	 * @return array<string,mixed>
	 */
	private function handle_list( $task_service, array $raw_args ): array {
		$target_user_id = null;
		foreach ( $raw_args as $arg ) {
			if ( preg_match( '/^--user_id=(\d+)$/', $arg, $m ) ) {
				$target_user_id = (int) $m[1];
				break;
			}
		}

		$tasks = $task_service->get_tasks( $target_user_id );

		if ( empty( $tasks ) ) {
			$who = $target_user_id ? "user $target_user_id" : 'you';
			return $this->success( "No tasks found for $who." );
		}

		$output = "Tasks:\n";
		foreach ( $tasks as $i => $task ) {
			$n       = $i + 1;
			$output .= "$n. {$task['name']}\n";
		}

		return $this->success( rtrim( $output ) );
	}

	/**
	 * Handle task create.
	 *
	 * @param object $task_service Task service instance.
	 * @param array<int,string> $args Command arguments.
	 * @param array<int,string> $raw_args Raw arguments.
	 * @return array<string,mixed>
	 */
	private function handle_create( $task_service, array $args, array $raw_args ): array {
		$name_parts = array_values(
			array_filter(
				$args,
				function ( $a ) {
					return ! str_starts_with( $a, '--' );
				}
			)
		);

		if ( empty( $name_parts ) ) {
			return $this->failure( 'Usage: task create <name> [--assignee_login=<user>|--assignee_id=<id>]' );
		}

		$name            = sanitize_text_field( implode( ' ', $name_parts ) );
		$current_user_id = get_current_user_id();
		$target_user_id  = $current_user_id;

		foreach ( $raw_args as $arg ) {
			if ( preg_match( '/^--assignee_login=(.+)$/', $arg, $m ) ) {
				$login = sanitize_user( $m[1] );
				$user  = get_user_by( 'login', $login );
				if ( ! $user ) {
					return $this->failure( "User not found: $login" );
				}
				$target_user_id = $user->ID;
				break;
			}
			if ( preg_match( '/^--assignee_id=(\d+)$/', $arg, $m ) ) {
				$target_user_id = (int) $m[1];
				break;
			}
		}

		$result = $task_service->save_tasks(
			array( array( 'name' => $name ) ),
			$target_user_id,
			$current_user_id
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure( 'Failed to create task: ' . $result->get_error_message() );
		}

		$who = ( $target_user_id !== $current_user_id ) ? " for user ID $target_user_id" : '';

		return $this->success( "Task created: \"$name\"$who." );
	}

	/**
	 * Handle task delete.
	 *
	 * @param object $task_service Task service instance.
	 * @param array<int,string> $raw_args Raw arguments.
	 * @return array<string,mixed>
	 */
	private function handle_delete( $task_service, array $raw_args ): array {
		$target_user_id = null;
		foreach ( $raw_args as $arg ) {
			if ( preg_match( '/^--user_id=(\d+)$/', $arg, $m ) ) {
				$target_user_id = (int) $m[1];
				break;
			}
		}

		$who = $target_user_id ? "user $target_user_id" : 'you';

		if ( $task_service->delete_tasks( $target_user_id ) ) {
			return $this->success( "All tasks deleted for $who." );
		}

		return $this->success( "No tasks to delete for $who." );
	}
}

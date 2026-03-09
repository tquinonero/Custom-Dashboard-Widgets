<?php
/**
 * Cron command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles cron management commands (list, run).
 */
class CDW_Cron_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a cron subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, run).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list();

			case 'run':
				return $this->handle_run( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for cron commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available cron commands:\n  cron list              - List scheduled cron events\n  cron run <hook>       - Run a cron event now",
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
		return 'run' === $subcmd;
	}

	/**
	 * Handle cron list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		$crons = _get_cron_array();

		if ( empty( $crons ) ) {
			return $this->success( 'No scheduled cron events found.' );
		}

		$output = "Scheduled Cron Events:\n";
		foreach ( $crons as $timestamp => $hooks ) {
			$time = wp_date( 'Y-m-d H:i:s', $timestamp );
			foreach ( $hooks as $hook => $events ) {
				foreach ( $events as $key => $event ) {
					$schedule = isset( $event['schedule'] ) ? $event['schedule'] : 'one-time';
					$output  .= "  [$time] $hook  [$schedule]\n";
				}
			}
		}

		return $this->success( $output );
	}

	/**
	 * Handle cron run.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_run( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: cron run <hook>' );
		}

		$hook = sanitize_text_field( $args[0] );

		do_action( $hook );

		return $this->success( "Cron event '$hook' triggered." );
	}
}

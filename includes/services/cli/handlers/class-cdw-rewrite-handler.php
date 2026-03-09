<?php
/**
 * Rewrite command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles rewrite rule management commands (flush).
 */
class CDW_Rewrite_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a rewrite subcommand.
	 *
	 * @param string            $subcmd   Subcommand (flush).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'flush':
				return $this->handle_flush();

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for rewrite commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available rewrite commands:\n  rewrite flush  - Flush rewrite rules",
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
		return false;
	}

	/**
	 * Handle rewrite flush.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_flush(): array {
		flush_rewrite_rules( true );
		return $this->success( 'Rewrite rules flushed.' );
	}
}

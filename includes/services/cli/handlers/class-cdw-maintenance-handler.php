<?php
/**
 * Maintenance command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles maintenance mode commands (enable, disable, status).
 */
class CDW_Maintenance_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a maintenance subcommand.
	 *
	 * @param string            $subcmd   Subcommand (enable, disable, status).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'status':
				return $this->handle_status();

			case 'on':
			case 'enable':
				return $this->handle_enable();

			case 'off':
			case 'disable':
				return $this->handle_disable();

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for maintenance commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available maintenance commands:\n  maintenance enable [msg] - Enable maintenance mode\n  maintenance disable      - Disable maintenance mode\n  maintenance status       - Show current status",
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
	 * Handle maintenance status.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_status(): array {
		$is_active = file_exists( ABSPATH . '.maintenance' );
		return $this->success( 'Maintenance mode is ' . ( $is_active ? 'enabled' : 'disabled' ) . '.' );
	}

	/**
	 * Handle maintenance enable.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_enable(): array {
		$upgrading_time = time();
		$content        = "<?php \$upgrading = $upgrading_time; ?>";
		$written        = file_put_contents( ABSPATH . '.maintenance', $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem cannot write to ABSPATH root without admin credentials; direct write is intentional here.

		if ( false === $written ) {
			return $this->failure( 'Maintenance mode enable failed: could not write .maintenance file. Check file permissions.' );
		}

		return $this->success( 'Maintenance mode enabled' );
	}

	/**
	 * Handle maintenance disable.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_disable(): array {
		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			if ( ! unlink( ABSPATH . '.maintenance' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- wp_delete_file() wraps unlink but both require the file to exist; unlink gives direct return value needed here.
				return $this->failure( 'Maintenance mode disable failed: could not delete .maintenance file. Check file permissions.' );
			}
		}

		return $this->success( 'Maintenance mode disabled' );
	}
}

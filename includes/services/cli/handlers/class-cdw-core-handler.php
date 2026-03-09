<?php
/**
 * Core command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles WordPress core commands (version).
 */
class CDW_Core_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a core subcommand.
	 *
	 * @param string            $subcmd   Subcommand (version).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'version':
				return $this->handle_version();

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for core commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available core commands:\n  core version  - Show WP version, PHP version, and update status",
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
	 * Handle core version.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_version(): array {
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$wp_version  = get_bloginfo( 'version' );
		$php_version = PHP_VERSION;
		$updates     = get_core_updates();
		$update_info = 'Up to date';

		if ( is_array( $updates ) && ! empty( $updates ) ) {
			$latest = $updates[0];
			if ( isset( $latest->response ) && 'upgrade' === $latest->response ) {
				$update_info = isset( $latest->version )
					? 'Available (v' . $latest->version . ')'
					: 'Available';
			}
		}

		$output  = "WordPress Version Info:\n";
		$output .= 'WP Version:  ' . $wp_version . "\n";
		$output .= 'PHP Version: ' . $php_version . "\n";
		$output .= 'Core Update: ' . $update_info . "\n";

		return $this->success( $output );
	}
}

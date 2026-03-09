<?php
/**
 * Transient command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles transient management commands (list, delete).
 */
class CDW_Transient_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a transient subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, delete).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list();

			case 'delete':
				return $this->handle_delete( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for transient commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available transient commands:\n  transient list           - List transients\n  transient delete <name> - Delete transient",
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
	 * Handle transient list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		global $wpdb;
		$transients = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%' LIMIT 20",
			ARRAY_A
		);
		$output = "Transients (first 20):\n";
		foreach ( $transients as $t ) {
			$name    = str_replace( '_transient_', '', $t['option_name'] );
			$output .= "$name\n";
		}

		return $this->success( $output );
	}

	/**
	 * Handle transient delete.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: transient delete <name>' );
		}

		$key    = sanitize_text_field( $args[0] );
		$result = delete_transient( $key );

		return $result
			? $this->success( "Transient deleted: $key" )
			: $this->failure( "Transient not found: $key" );
	}
}

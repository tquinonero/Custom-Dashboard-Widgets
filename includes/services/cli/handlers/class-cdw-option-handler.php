<?php
/**
 * Option command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles option management commands (get, set, delete, list).
 */
class CDW_Option_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a option subcommand.
	 *
	 * @param string            $subcmd   Subcommand (get, set, delete, list).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'get':
				return $this->handle_get( $args );

			case 'set':
				return $this->handle_set( $args );

			case 'delete':
				return $this->handle_delete( $args );

			case 'list':
				return $this->handle_list();

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for option commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available option commands:\n  option get <name>        - Get option value\n  option set <name> <val> - Set option value\n  option delete <name>    - Delete option\n  option list             - List CDW options",
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
	 * Handle option get.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_get( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: option get <name>' );
		}

		$name  = sanitize_text_field( $args[0] );
		$value = get_option( $name );

		if ( false === $value ) {
			return $this->failure( "Option not found: $name" );
		}

		return $this->success( "$name = " . ( is_array( $value ) ? wp_json_encode( $value ) : $value ) );
	}

	/**
	 * Handle option set.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_set( array $args ): array {
		if ( count( $args ) < 2 ) {
			return $this->failure( 'Usage: option set <name> <value>' );
		}

		$name = sanitize_text_field( $args[0] );

		if ( $this->is_option_protected( $name ) ) {
			return $this->failure( "Cannot modify protected option: $name" );
		}

		$value   = sanitize_text_field( $args[1] );
		$updated = update_option( $name, $value );
		$success = $updated || ( get_option( $name ) === $value );

		return $success
			? $this->success( "Option updated: $name = $value" )
			: $this->failure( "Failed to update option: $name" );
	}

	/**
	 * Handle option delete.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: option delete <name>' );
		}

		$name = sanitize_text_field( $args[0] );

		if ( $this->is_option_protected( $name ) ) {
			return $this->failure( "Cannot delete protected option: $name" );
		}

		$deleted = delete_option( $name );

		return $deleted
			? $this->success( "Option deleted: $name" )
			: $this->failure( "Option not found: $name" );
	}

	/**
	 * Handle option list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		global $wpdb;
		$options = $wpdb->get_results(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'cdw_%' OR option_name LIKE 'custom_dashboard_widget_%'",
			ARRAY_A
		);
		$output = "CDW Options:\n";
		foreach ( $options as $opt ) {
			$output .= $opt['option_name'] . "\n";
		}

		return $this->success( $output );
	}

	/**
	 * Check if an option is protected.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	private function is_option_protected( string $name ): bool {
		if ( class_exists( 'CDW_Base_Controller' ) ) {
			return in_array( $name, \CDW_Base_Controller::$protected_options, true );
		}
		return false;
	}
}

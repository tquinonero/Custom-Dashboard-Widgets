<?php
/**
 * DB command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles database commands (size, tables).
 */
class CDW_DB_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a db subcommand.
	 *
	 * @param string            $subcmd   Subcommand (size, tables).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'size':
				return $this->handle_size();

			case 'tables':
				return $this->handle_tables();

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for db commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available db commands:\n  db size                 - Show database size\n  db tables               - List all tables",
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
		return true;
	}

	/**
	 * Handle db size.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_size(): array {
		global $wpdb;

		$result = $wpdb->get_row( 'SELECT ROUND( SUM( data_length + index_length ) / 1024 / 1024, 2 ) AS size FROM information_schema.tables WHERE table_schema = DATABASE()' );

		if ( ! $result || null === $result->size ) {
			return $this->failure( 'Could not determine database size.' );
		}

		return $this->success( "Database size: {$result->size} MB" );
	}

	/**
	 * Handle db tables.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_tables(): array {
		global $wpdb;

		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
		$output  = "Tables:\n";

		foreach ( $tables as $table ) {
			$output .= $table[0] . "\n";
		}

		return $this->success( $output );
	}
}

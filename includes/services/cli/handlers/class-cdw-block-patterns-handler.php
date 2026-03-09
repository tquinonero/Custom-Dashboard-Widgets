<?php
/**
 * Block Patterns command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles block patterns commands (list, get).
 */
class CDW_Block_Patterns_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a block-patterns subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, get).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list( $args );

			case 'get':
				return $this->handle_get( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for block-patterns commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available block-patterns commands:\n  block-patterns list [<category>]  - List registered patterns\n  block-patterns get <name>        - Get pattern content (base64)",
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
	 * Handle block-patterns list.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_list( array $args ): array {
		$category_filter = isset( $args[0] ) ? sanitize_text_field( $args[0] ) : '';
		$registry        = \WP_Block_Patterns_Registry::get_instance();
		$patterns        = $registry->get_all_registered();

		if ( ! empty( $category_filter ) ) {
			$patterns = array_values(
				array_filter(
					$patterns,
					function ( $pattern ) use ( $category_filter ) {
						$cats = isset( $pattern['categories'] ) ? (array) $pattern['categories'] : array();
						return in_array( $category_filter, $cats, true );
					}
				)
			);
		}

		if ( empty( $patterns ) ) {
			$msg = $category_filter ? "No block patterns found in category \"$category_filter\"." : 'No block patterns registered.';
			return $this->success( $msg );
		}

		$lines = array( 'Registered Block Patterns:' );
		foreach ( $patterns as $pattern ) {
			$cats    = isset( $pattern['categories'] ) ? implode( ', ', (array) $pattern['categories'] ) : '-';
			$lines[] = sprintf( '%s | %s | [%s]', $pattern['name'], $pattern['title'], $cats );
		}

		return $this->success( implode( "\n", $lines ) );
	}

	/**
	 * Handle block-patterns get.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_get( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: block-patterns get <pattern-name>' );
		}

		$pattern_name = sanitize_text_field( $args[0] );
		$registry     = \WP_Block_Patterns_Registry::get_instance();
		$patterns     = $registry->get_all_registered();

		$matched = null;
		foreach ( $patterns as $pattern ) {
			if ( $pattern['name'] === $pattern_name ) {
				$matched = $pattern;
				break;
			}
		}

		if ( ! $matched ) {
			return $this->failure( "Pattern not found: $pattern_name" );
		}

		$content = isset( $matched['content'] ) ? $matched['content'] : '';

		if ( empty( $content ) ) {
			return $this->failure( "Pattern \"$pattern_name\" has no content." );
		}

		$content_base64 = base64_encode( $content );

		return $this->success( "Pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes. Content: ' . substr( $content_base64, 0, 50 ) . '...' );
	}
}

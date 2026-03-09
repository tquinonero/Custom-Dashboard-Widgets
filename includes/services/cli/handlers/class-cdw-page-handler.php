<?php
/**
 * Page command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles page management commands (create).
 */
class CDW_Page_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a page subcommand.
	 *
	 * @param string            $subcmd   Subcommand (create).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'create':
				return $this->handle_create( $args, $raw_args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for page commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available page commands:\n  page create <title> [--publish]  - Create a page (draft or published)",
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
	 * Handle page create.
	 *
	 * @param array<int,string> $args    Command arguments.
	 * @param array<int,string> $raw_args Raw arguments including flags.
	 * @return array<string,mixed>
	 */
	private function handle_create( array $args, array $raw_args ): array {
		if ( empty( $args ) ) {
			return $this->failure( 'Usage: page create <page title> [--publish]' );
		}

		$title       = sanitize_text_field( implode( ' ', $args ) );
		$post_status = $this->has_publish_flag( $raw_args ) ? 'publish' : 'draft';

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $post_status,
				'post_type'   => 'page',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $this->failure( 'Failed to create page: ' . $post_id->get_error_message() );
		}

		$status_label = ( 'publish' === $post_status ) ? 'published' : 'draft';

		return $this->success( "Page created ($status_label): ID=$post_id, Title=\"$title\"" );
	}
}

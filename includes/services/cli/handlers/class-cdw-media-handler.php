<?php
/**
 * Media command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles media management commands (list).
 */
class CDW_Media_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a media subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for media commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available media commands:\n  media list [<count>]  - List recent media attachments (default 20)",
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
	 * Handle media list.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_list( array $args ): array {
		$count = isset( $args[0] ) && is_numeric( $args[0] ) ? (int) $args[0] : 20;
		$count = max( 1, min( 100, $count ) );

		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'numberposts' => $count,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		if ( empty( $attachments ) ) {
			return $this->success( 'No media attachments found.' );
		}

		$lines = array( 'Media Library:' );
		foreach ( $attachments as $att ) {
			$lines[] = sprintf(
				'[%d] %s | %s | %s',
				$att->ID,
				basename( $att->guid ),
				$att->post_mime_type,
				substr( $att->post_date, 0, 10 )
			);
		}

		return $this->success( implode( "\n", $lines ) );
	}
}

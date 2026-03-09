<?php
/**
 * Comment command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles comment management commands (list, approve, spam, delete).
 */
class CDW_Comment_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a comment subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, approve, spam, delete).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list( $args );

			case 'approve':
				return $this->handle_approve( $args );

			case 'spam':
				return $this->handle_spam( $args );

			case 'delete':
				return $this->handle_delete( $args, $raw_args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for comment commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available comment commands:\n  comment list [pending|approved|spam]  - List comments\n  comment approve <id>                  - Approve a comment\n  comment spam <id>                     - Mark as spam\n  comment delete <id> --force           - Permanently delete",
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
	 * Handle comment list.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_list( array $args ): array {
		$status = ! empty( $args[0] ) ? sanitize_text_field( $args[0] ) : 'hold';
		$map    = array(
			'pending'  => 'hold',
			'approved' => 'approve',
			'spam'     => 'spam',
			'hold'     => 'hold',
			'approve'  => 'approve',
		);
		$status = isset( $map[ $status ] ) ? $map[ $status ] : 'hold';

		$comments = get_comments(
			array(
				'status' => $status,
				'number' => 20,
			)
		);

		if ( empty( $comments ) ) {
			return $this->success( 'No comments found.' );
		}

		$label  = array(
			'hold'    => 'Pending',
			'approve' => 'Approved',
			'spam'    => 'Spam',
		);
		$output = $label[ $status ] . " Comments:\n";

		foreach ( $comments as $comment ) {
			$date    = date_i18n( 'Y-m-d', strtotime( $comment->comment_date ) );
			$author  = $comment->comment_author ? $comment->comment_author : '(anonymous)';
			$excerpt = wp_trim_words( $comment->comment_content, 10, '…' );
			$output .= sprintf( "  [%d] %s — %s: %s\n", $comment->comment_ID, $date, $author, $excerpt );
		}

		return $this->success( rtrim( $output ) );
	}

	/**
	 * Handle comment approve.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_approve( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: comment approve <id>' );
		}

		$id     = (int) $args[0];
		$result = wp_set_comment_status( $id, 'approve' );

		if ( ! $result ) {
			return $this->failure( "Comment not found: $id" );
		}

		return $this->success( "Comment approved: $id" );
	}

	/**
	 * Handle comment spam.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_spam( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: comment spam <id>' );
		}

		$id     = (int) $args[0];
		$result = wp_set_comment_status( $id, 'spam' );

		if ( ! $result ) {
			return $this->failure( "Comment not found: $id" );
		}

		return $this->success( "Comment marked as spam: $id" );
	}

	/**
	 * Handle comment delete.
	 *
	 * @param array<int,string> $args    Command arguments.
	 * @param array<int,string> $raw_args Raw arguments including flags.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args, array $raw_args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: comment delete <id> --force' );
		}

		if ( ! $this->has_force_flag( $raw_args ) ) {
			return $this->failure( 'Use --force to permanently delete a comment.' );
		}

		$id     = (int) $args[0];
		$result = wp_delete_comment( $id, true );

		if ( ! $result ) {
			return $this->failure( "Comment not found: $id" );
		}

		return $this->success( "Comment deleted: $id" );
	}
}

<?php
/**
 * Post command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles post management commands (list, get, delete, status, count).
 */
class CDW_Post_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a post subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, get, delete, status, count).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'create':
				return $this->handle_create( $args, $raw_args );

			case 'get':
				return $this->handle_get( $args );

			case 'list':
				return $this->handle_list( $args );

			case 'delete':
				return $this->handle_delete( $args );

			case 'status':
				return $this->handle_status( $args );

			case 'count':
				return $this->handle_count( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for post commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available post commands:\n  post create <title> [--publish]  - Create a post (draft or published)\n  post get <id>                    - Get post details\n  post list [<type>]               - List posts\n  post count [<type>]               - Count posts by status\n  post delete <id>                 - Delete post\n  post status <id> <status>        - Change post status",
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
	 * Handle post create.
	 *
	 * @param array<int,string> $args    Command arguments.
	 * @param array<int,string> $raw_args Raw arguments including flags.
	 * @return array<string,mixed>
	 */
	private function handle_create( array $args, array $raw_args ): array {
		if ( empty( $args ) ) {
			return $this->failure( 'Usage: post create <post title> [--publish]' );
		}

		$title       = sanitize_text_field( implode( ' ', $args ) );
		$post_status = $this->has_publish_flag( $raw_args ) ? 'publish' : 'draft';

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $post_status,
				'post_type'   => 'post',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $this->failure( 'Failed to create post: ' . $post_id->get_error_message() );
		}

		$status_label = ( 'publish' === $post_status ) ? 'published' : 'draft';

		return $this->success( "Post created ($status_label): ID=$post_id, Title=\"$title\"" );
	}

	/**
	 * Handle post get.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_get( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: post get <post-id>' );
		}

		$post_id = absint( $args[0] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->failure( "Post not found: $post_id" );
		}

		$author_name = get_the_author_meta( 'display_name', (int) $post->post_author );
		$permalink   = get_permalink( $post_id );
		$excerpt     = ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 20 );

		$output  = "Post ID:     {$post->ID}\n";
		$output .= "Title:       {$post->post_title}\n";
		$output .= "Type:        {$post->post_type}\n";
		$output .= "Status:      {$post->post_status}\n";
		$output .= "Author:      $author_name (ID: {$post->post_author})\n";
		$output .= "Date:        {$post->post_date}\n";
		$output .= "Modified:    {$post->post_modified}\n";
		$output .= "Slug:        {$post->post_name}\n";
		$output .= "URL:         $permalink\n";
		$output .= "Excerpt:     $excerpt\n";

		return $this->success( $output );
	}

	/**
	 * Handle post list.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_list( array $args ): array {
		$post_type = ! empty( $args[0] ) ? sanitize_text_field( $args[0] ) : 'post';

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => 20,
			)
		);

		if ( empty( $posts ) ) {
			return $this->success( "No posts found for type: $post_type" );
		}

		$output = "Posts (type: $post_type):\n";
		foreach ( $posts as $post ) {
			$status  = $post->post_status;
			$output .= "[$status] {$post->ID}: {$post->post_title}\n";
		}

		return $this->success( $output );
	}

	/**
	 * Handle post delete.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: post delete <post-id>' );
		}

		$post_id = intval( $args[0] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return $this->failure( "Post not found: $post_id" );
		}

		$result = wp_delete_post( $post_id, true );

		if ( ! $result ) {
			return $this->failure( 'Delete failed' );
		}

		return $this->success( "Post deleted: $post_id" );
	}

	/**
	 * Handle post status.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_status( array $args ): array {
		if ( empty( $args[0] ) || empty( $args[1] ) ) {
			return $this->failure( 'Usage: post status <post-id> <status>' );
		}

		$post_id    = intval( $args[0] );
		$new_status = sanitize_text_field( $args[1] );
		$post       = get_post( $post_id );

		if ( ! $post ) {
			return $this->failure( "Post not found: $post_id" );
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure( 'Failed to update post status: ' . $result->get_error_message() );
		}

		if ( ! $result ) {
			return $this->failure( "Failed to update post status: $post_id" );
		}

		return $this->success( "Post status updated: $post_id -> $new_status" );
	}

	/**
	 * Handle post count.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_count( array $args ): array {
		$post_type = ! empty( $args[0] ) ? sanitize_text_field( $args[0] ) : null;

		if ( $post_type ) {
			$type_obj = get_post_type_object( $post_type );
			if ( ! $type_obj || ! $type_obj->public ) {
				return $this->failure( "Invalid or non-public post type: $post_type" );
			}

			$counts = wp_count_posts( $post_type );
			$output  = "Post counts for type: $post_type\n";
			$output .= "  publish:  " . (int) $counts->publish . "\n";
			$output .= "  draft:    " . (int) $counts->draft . "\n";
			$output .= "  pending:  " . (int) $counts->pending . "\n";
			$output .= "  trash:    " . (int) $counts->trash . "\n";

			return $this->success( $output );
		}

		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );

		$output = "Post counts by type:\n";
		foreach ( $types as $type ) {
			$counts   = wp_count_posts( $type );
			$type_obj = get_post_type_object( $type );
			$label    = $type_obj ? $type_obj->labels->singular_name : $type;
			$output  .= "$label:\n";
			$output  .= "  publish:  " . (int) $counts->publish . "\n";
			$output  .= "  draft:    " . (int) $counts->draft . "\n";
			$output  .= "  pending:  " . (int) $counts->pending . "\n";
			$output  .= "  trash:    " . (int) $counts->trash . "\n";
		}

		return $this->success( $output );
	}
}

<?php
/**
 * Content-related ability registrations.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post content and page builder abilities.
 */
class CDW_Content_Abilities {

	/**
	 * Registers all content abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	public static function register( callable $permission_cb ) {
		wp_register_ability(
			'cdw/post-set-content',
			array(
				'label'               => __( 'Set Post Content', 'cdw' ),
				'description'         => __( 'Replaces the full post_content of an existing post or page with raw block markup. For design recommendations (colors, spacing, patterns), first use cdw/skill-list to find skills, then cdw/skill-get with skill_name: "gutenberg-design" to get design guidelines. Supply either content (plain string) or content_base64 (base64-encoded string — preferred for block markup because it avoids JSON escaping issues). For large pages: (1) call with content="" to clear, (2) use cdw/post-append-content to push sections.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					if ( isset( $input['content_base64'] ) && '' !== (string) $input['content_base64'] ) {
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 decoding with strict mode
						$content = base64_decode( (string) $input['content_base64'], true );
						if ( false === $content ) {
							return new \WP_Error( 'invalid_base64', 'content_base64 is not valid base64.' );
						}
					} else {
						$content = isset( $input['content'] ) ? (string) $input['content'] : '';
					}

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					if ( ! get_post( $post_id ) ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
					}

					$result = wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $content,
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$total_length = strlen( $content );
					return array( 'output' => "Post $post_id content set. Total content length: $total_length bytes." );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content'        => array(
							'type'        => 'string',
							'description' => 'Raw block markup to write to post_content. Use for short/plain content.',
						),
						'content_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded block markup. Preferred over content when the markup contains JSON block attributes (avoids double-escaping). Provide either content or content_base64, not both.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		wp_register_ability(
			'cdw/post-get-content',
			array(
				'label'               => __( 'Get Post Content', 'cdw' ),
				'description'         => __( 'Retrieves the raw post_content of a WordPress post or page, including all Gutenberg block markup. Use offset and limit for pagination on large content. Use this before editing a page with cdw/post-set-content.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					$offset  = isset( $input['offset'] ) ? (int) $input['offset'] : 0;
					$limit   = isset( $input['limit'] ) ? (int) $input['limit'] : 5000;

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					if ( $limit <= 0 || $limit > 20000 ) {
						$limit = 5000;
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
					}

					$content       = $post->post_content;
					$total_length  = strlen( $content );
					$has_more      = ( $offset + $limit ) < $total_length;
					$chunk_index   = intval( $offset / $limit );
					$chunk_content = substr( $content, $offset, $limit );

					return array(
						'output'       => $has_more
							? "Post $post_id content retrieved. Chunk $chunk_index (" . strlen( $chunk_content ) . ' bytes).'
							: "Post $post_id content retrieved. Length: $total_length bytes.",
						'post_id'      => $post_id,
						'title'        => $post->post_title,
						'content'      => $chunk_content,
						'total_length' => $total_length,
						'chunk_index'  => $chunk_index,
						'has_more'     => $has_more,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to get content from.',
						),
						'offset'  => array(
							'type'        => 'integer',
							'description' => 'Starting position for chunked retrieval (default: 0).',
							'default'     => 0,
						),
						'limit'   => array(
							'type'        => 'integer',
							'description' => 'Chunk size in characters (default: 5000, max: 20000).',
							'default'     => 5000,
						),
					),
					'required'   => array( 'post_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		wp_register_ability(
			'cdw/post-append-content',
			array(
				'label'               => __( 'Append Post Content', 'cdw' ),
				'description'         => __( 'Appends a raw block markup chunk to the existing post_content of a post or page. For design recommendations (colors, spacing, patterns), first use cdw/skill-list to find skills, then cdw/skill-get with skill_name: "gutenberg-design" to get design guidelines. Supply either content (plain string) or content_base64 (base64-encoded — preferred for block markup to avoid JSON escaping). Workflow: (1) call cdw/post-set-content with content="" to clear the post, (2) call this ability repeatedly with successive chunks. The response includes the running total byte count so you can confirm each chunk landed.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					if ( isset( $input['content_base64'] ) && '' !== (string) $input['content_base64'] ) {
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 decoding with strict mode
						$chunk = base64_decode( (string) $input['content_base64'], true );
						if ( false === $chunk ) {
							return new \WP_Error( 'invalid_base64', 'content_base64 is not valid base64.' );
						}
					} else {
						$chunk = isset( $input['content'] ) ? (string) $input['content'] : '';
					}

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
					}
					if ( '' === $chunk ) {
						return new \WP_Error( 'empty_content', 'content or content_base64 must not be empty.' );
					}

					$new_content = $post->post_content . $chunk;

					$result = wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $new_content,
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$total_length = strlen( $new_content );
					return array( 'output' => "Chunk appended to post $post_id. Total content length: $total_length bytes." );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content'        => array(
							'type'        => 'string',
							'description' => 'Block markup chunk to append. Use for plain/short content.',
						),
						'content_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded block markup chunk to append. Preferred over content when the markup contains JSON block attributes (avoids double-escaping). Provide either content or content_base64, not both.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		wp_register_ability(
			'cdw/build-page',
			array(
				'label'               => __( 'Build Page', 'cdw' ),
				'description'         => __( 'Creates a new page or updates an existing one with Gutenberg block markup generated from structured JSON. For design recommendations (colors, spacing, patterns), first use cdw/skill-list to find skills, then cdw/skill-get with skill_name: "gutenberg-design" to get design guidelines. Input: {"title": "Page Title", "sections": [{"type": "cover", "title": "Hero", "image": "url"}, {"type": "two-column", "left": {...}, "right": {...}}, {"type": "footer", "columns": [...]}]}. Supported section types: cover, two-column, three-column, footer. Returns post_id, title, and section_count.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$title    = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
					$sections = isset( $input['sections'] ) ? (array) $input['sections'] : array();
					$post_id  = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					if ( empty( $title ) ) {
						return new \WP_Error( 'missing_title', 'title is required.' );
					}

					if ( empty( $sections ) ) {
						return new \WP_Error( 'missing_sections', 'sections array is required.' );
					}

					require_once CDW_PLUGIN_DIR . 'includes/renderers/class-cdw-section-renderers.php';

					$content = \CDW_Section_Renderers::render_sections( $sections );

					if ( $post_id > 0 ) {
						$post = get_post( $post_id );
						if ( ! $post ) {
							return new \WP_Error( 'post_not_found', "Post $post_id not found." );
						}
						if ( ! current_user_can( 'edit_post', $post_id ) ) {
							return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
						}

						$result = wp_update_post(
							array(
								'ID'           => $post_id,
								'post_content' => $content,
							),
							true
						);

						if ( is_wp_error( $result ) ) {
							return $result;
						}

						return array(
							'output'         => "Page $post_id updated with " . count( $sections ) . ' sections.',
							'post_id'        => $post_id,
							'title'          => $title,
							'section_count'  => count( $sections ),
							'content_length' => strlen( $content ),
						);
					}

					$result = wp_insert_post(
						array(
							'post_title'   => $title,
							'post_content' => $content,
							'post_status'  => 'draft',
							'post_type'    => 'page',
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					return array(
						'output'         => "Page created (draft): ID=$result, Title=\"$title\"",
						'post_id'        => $result,
						'title'          => $title,
						'section_count'  => count( $sections ),
						'content_length' => strlen( $content ),
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array(
							'type'        => 'string',
							'description' => 'Title of the page to create or update.',
						),
						'sections' => array(
							'type'        => 'array',
							'description' => 'Array of section objects. Supported types: cover, two-column, three-column, footer.',
						),
						'post_id'  => array(
							'type'        => 'integer',
							'description' => 'Optional. ID of existing page to update. If omitted, creates a new draft page.',
						),
					),
					'required'   => array( 'title', 'sections' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);
	}
}

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
				'label'       => __( 'Set Post Content', 'cdw' ),
				'description' => __(
					'Replaces the full post_content of an existing post or page with raw block markup. For design guidelines use cdw/skill-get with skill_name: "gutenberg-design". For exact block attributes and validation rules use cdw/skill-get with skill_name: "block-schemas". For large pages: (1) call with content="" to clear, (2) use cdw/post-append-content to push sections.',
					'cdw'
				),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {

					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					$content = isset( $input['content'] ) ? (string) $input['content'] : '';

					if ( ! $post_id ) {
						return new \WP_Error(
							'invalid_post_id',
							'post_id is required and must be a positive integer.'
						);
					}

					if ( ! mb_check_encoding( $content, 'UTF-8' ) ) {
						return new \WP_Error(
							'invalid_encoding',
							'content must be valid UTF-8.'
						);
					}

					$post = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error(
							'post_not_found',
							sprintf( 'Post %d not found.', $post_id )
						);
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error(
							'forbidden',
							'You do not have permission to edit this post.'
						);
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
						'output' => sprintf(
							'Post %d content set. Total content length: %d bytes.',
							$post_id,
							strlen( $content )
						),
					);
				},
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Raw block markup to write to post_content.',
						),
					),
					'required'   => array( 'post_id', 'content' ),
				),
				'meta'         => array(
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

					$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					$offset  = isset( $input['offset'] ) ? (int) $input['offset'] : 0;
					$limit   = isset( $input['limit'] ) ? (int) $input['limit'] : 5000;

					if ( ! $post_id ) {
						return new \WP_Error(
							'invalid_post_id',
							'post_id is required and must be a positive integer.'
						);
					}

					if ( $limit <= 0 || $limit > 20000 ) {
						$limit = 5000;
					}

					$post = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error(
							'post_not_found',
							sprintf( 'Post %d not found.', $post_id )
						);
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error(
							'forbidden',
							'You do not have permission to edit this post.'
						);
					}

					$content       = $post->post_content;
					$total_length  = strlen( $content );
					$chunk_content = substr( $content, $offset, $limit );
					$chunk_length  = strlen( $chunk_content );
					$has_more      = ( $offset + $limit ) < $total_length;
					$chunk_index   = (int) floor( $offset / $limit );
					$next_offset   = $has_more ? $offset + $limit : null;

					return array(
						'output'       => $has_more
							? sprintf(
								'Post %d content retrieved. Chunk %d (%d bytes). %d bytes remaining.',
								$post_id,
								$chunk_index,
								$chunk_length,
								$total_length - ( $offset + $chunk_length )
							)
							: sprintf(
								'Post %d content retrieved. Length: %d bytes.',
								$post_id,
								$total_length
							),
						'content'      => $chunk_content,
						'total_length' => $total_length,
						'chunk_index'  => $chunk_index,
						'has_more'     => $has_more,
						'next_offset'  => $next_offset,
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
				'description'         => __( 'Appends a raw block markup chunk to the existing post_content of a post or page. For design guidelines use cdw/skill-get with skill_name: "gutenberg-design". For exact block attributes and validation rules use cdw/skill-get with skill_name: "block-schemas". Workflow: (1) call cdw/post-set-content with content="" to clear the post, (2) call this ability repeatedly with successive chunks. The response includes the running total byte count so you can confirm each chunk landed.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {

					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					$chunk   = isset( $input['content'] ) ? (string) $input['content'] : '';

					if ( $post_id <= 0 ) {
						return new \WP_Error(
							'invalid_post_id',
							'post_id is required and must be a positive integer.'
						);
					}

					if ( '' === $chunk ) {
						return new \WP_Error( 'empty_content', 'content must not be empty.' );
					}

					if ( ! mb_check_encoding( $chunk, 'UTF-8' ) ) {
						return new \WP_Error(
							'invalid_encoding',
							'content must be valid UTF-8.'
						);
					}

					$post = get_post( $post_id );

					if ( ! $post ) {
						return new \WP_Error(
							'post_not_found',
							sprintf( 'Post %d not found.', $post_id )
						);
					}

					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
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

					return array(
						'output' => sprintf(
							'Chunk appended to post %d. Total content length: %d bytes.',
							$post_id,
							strlen( $new_content )
						),
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Block markup chunk to append.',
						),
					),
					'required'   => array( 'post_id', 'content' ),
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
				'description'         => __(
					'Creates a new page or updates an existing one with Gutenberg block markup generated from structured JSON. Before building, always fetch both skills in order: (1) cdw/skill-get with skill_name: "gutenberg-design" — design decisions, personality vibe, section structure patterns, and colour rules. (2) cdw/skill-get with skill_name: "block-schemas" — exact block attributes, defaults, and validation rules for each block type. Use "page_template" to set template (e.g. "default", "blank"). Get available templates with cdw/list-page-templates. Section types: cover, two-column, three-column, footer, block. Use "type": "block" for individual Gutenberg blocks. Returns post_id, title, and section_count.',
					'cdw'
				),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {

					$title         = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
					$sections      = isset( $input['sections'] ) ? (array) $input['sections'] : array();
					$post_id       = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
					$page_template = isset( $input['page_template'] ) ? sanitize_text_field( $input['page_template'] ) : '';

					if ( empty( $title ) ) {
						return new \WP_Error( 'missing_title', 'title is required.' );
					}

					if ( empty( $sections ) ) {
						return new \WP_Error( 'missing_sections', 'sections array is required.' );
					}

					require_once CDW_PLUGIN_DIR . 'includes/renderers/class-cdw-section-renderers.php';

					$content       = \CDW_Section_Renderers::render_sections( $sections );
					$section_count = count( $sections );

					// Update path.
					if ( $post_id ) {

						$post = get_post( $post_id );

						if ( ! $post ) {
							return new \WP_Error(
								'post_not_found',
								sprintf( 'Post %d not found.', $post_id )
							);
						}

						if ( ! current_user_can( 'edit_post', $post_id ) ) {
							return new \WP_Error(
								'forbidden',
								'You do not have permission to edit this post.'
							);
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

						if ( $page_template ) {
							update_post_meta( $post_id, '_wp_page_template', $page_template );
						}

						return array(
							'output'         => sprintf(
								'Page %d updated with %d sections.',
								$post_id,
								$section_count
							),
							'post_id'        => $post_id,
							'title'          => $title,
							'section_count'  => $section_count,
							'content_length' => strlen( $content ),
						);
					}

					// Insert path.
					if ( ! current_user_can( 'edit_pages' ) ) {
						return new \WP_Error(
							'forbidden',
							'You do not have permission to create pages.'
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

					if ( $page_template ) {
						update_post_meta( $result, '_wp_page_template', $page_template );
					}

					return array(
						'output'         => sprintf(
							'Page created (draft): ID=%d, Title="%s"',
							$result,
							$title
						),
						'post_id'        => $result,
						'title'          => $title,
						'section_count'  => $section_count,
						'content_length' => strlen( $content ),
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => 'ID of an existing page to update. Omit to create a new draft page.',
						),
						'title'         => array(
							'type'        => 'string',
							'description' => 'Page title.',
						),
						'sections'      => array(
							'type'        => 'array',
							'description' => 'Array of section definition objects.',
							'items'       => array(
								'type' => 'object',
							),
						),
						'page_template' => array(
							'type'        => 'string',
							'description' => 'Page template slug (e.g. "default", "blank"). Use cdw/list-page-templates to enumerate options.',
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

		wp_register_ability(
			'cdw/list-page-templates',
			array(
				'label'               => __( 'List Page Templates', 'cdw' ),
				'description'         => __( 'Returns a list of available page templates from the active theme. For classic themes, returns get_page_templates(). For FSE/block themes, returns available template slugs. Use cdw/theme-info to check if the active theme is a block theme.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {

					$templates = array();

					if ( wp_is_block_theme() ) {
						$block_templates = get_block_templates( array( 'post_type' => 'page' ) );
						foreach ( $block_templates as $template ) {
							$templates[ $template->slug ] = $template->title;
						}
						if ( empty( $templates ) ) {
							$templates = array(
								'page'         => 'Page',
								'page--custom' => 'Page (Custom)',
								'blank'        => 'Blank',
								'single'       => 'Single',
							);
						}
					} else {
						$wp_templates = get_page_templates();
						foreach ( $wp_templates as $name => $file ) {
							$templates[ $file ] = $name;
						}
					}

					if ( empty( $templates ) ) {
						$templates = array( 'default' => 'Default Template' );
					}

					return array(
						'output'     => 'Available page templates: ' . count( $templates ),
						'templates'  => $templates,
						'theme_type' => wp_is_block_theme() ? 'block' : 'classic',
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
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
	}
}

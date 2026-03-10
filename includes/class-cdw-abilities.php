<?php
/**
 * WordPress Abilities API registration.
 *
 * Registers all 32 CDW tools as WP_Ability objects so they are discoverable
 * by the WordPress Abilities API (WP 6.9+) and, optionally, by external AI
 * clients via the MCP Adapter plugin.
 *
 * This class is purely additive — the existing REST API and agentic loop are
 * untouched.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers CDW abilities via the WordPress Abilities API.
 *
 * @package CDW
 */
class CDW_Abilities {

	/**
	 * Registers the ability category and all CDW abilities.
	 *
	 * Called unconditionally from CDW_Loader::run(). Bails silently on
	 * WordPress versions older than 6.9 that lack the Abilities API.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( static::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( static::class, 'register_abilities' ) );

		// Opt-in MCP exposure — applied before abilities are finalised.
		if ( get_option( 'cdw_mcp_public', false ) ) {
			add_filter(
				'wp_register_ability_args',
				function ( $args, $ability_name ) {
					if ( str_starts_with( $ability_name, 'cdw/' ) ) {
						$args['meta']['mcp']['public'] = true;
					}
					return $args;
				},
				10,
				2
			);
		}
	}

	/**
	 * Registers the `cdw-admin-tools` ability category.
	 *
	 * Hooked to `wp_abilities_api_categories_init`.
	 *
	 * @return void
	 */
	public static function register_category() {
		wp_register_ability_category(
			'cdw-admin-tools',
			array(
				'label'       => __( 'CDW Admin Tools', 'cdw' ),
				'description' => __( 'WordPress admin management tools provided by CDW.', 'cdw' ),
			)
		);
	}

	/**
	 * Registers all CDW abilities from config plus inline abilities.
	 *
	 * Hooked to `wp_abilities_api_init`.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

		$permission_cb = function () {
			return current_user_can( 'manage_options' );
		};

		// Load abilities from config file.
		// Note: We use include instead of require_once because PHPUnit runs all tests
		// in the same process, and require_once returns true after first include.
		// The config file returns an array, so we check for that.
		$config_abilities = include CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-abilities-config.php';

		foreach ( $config_abilities as $ability_name => $ability ) {
			// Add name to the ability array for register_one().
			$ability['name'] = $ability_name;
			self::register_one( $ability, $permission_cb );
		}

		// ---------------------------------------------------------------
		// Role management (abilities-only, no CLI equivalent).
		// Registered with inline execute_callbacks since the CLI service
		// does not handle role operations.
		// ---------------------------------------------------------------

		$built_in_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

		// role-list: Returns all roles with their capabilities.
		wp_register_ability(
			'cdw/role-list',
			array(
				'label'               => __( 'List Roles', 'cdw' ),
				'description'         => __( 'Returns a list of all WordPress roles with their display names and capabilities. Use this to see what capabilities each role has before creating or updating roles.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) use ( $built_in_roles ) {
					$filter_role = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';
					$wp_roles    = wp_roles();

					$roles = array();
					foreach ( $wp_roles->roles as $role_key => $role_data ) {
						// Filter by role if specified.
						if ( $filter_role && $role_key !== $filter_role ) {
							continue;
						}

						$capabilities = isset( $role_data['capabilities'] ) ? $role_data['capabilities'] : array();
						$roles[] = array(
							'role'         => $role_key,
							'display_name' => isset( $role_data['name'] ) ? $role_data['name'] : $role_key,
							'is_builtin'   => in_array( $role_key, $built_in_roles, true ),
							'capabilities' => array_keys( $capabilities ),
							'cap_count'    => count( $capabilities ),
						);
					}

					return array(
						'output' => 'Found ' . count( $roles ) . ' roles.',
						'roles'  => $roles,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'Optional. Filter by role slug to return only that role.',
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		// role-create: Creates a new role with capabilities, optionally cloning from existing role.
		wp_register_ability(
			'cdw/role-create',
			array(
				'label'               => __( 'Create Role', 'cdw' ),
				'description'         => __( 'Creates a new WordPress role with specified display name and capabilities. Optionally clone capabilities from an existing role. Cannot override built-in roles (administrator, editor, author, contributor, subscriber).', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) use ( $built_in_roles ) {
					$role         = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';
					$display_name = isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : '';
					$capabilities = isset( $input['capabilities'] ) ? (array) $input['capabilities'] : array();
					$clone_from   = isset( $input['clone_from'] ) ? sanitize_key( $input['clone_from'] ) : '';

					if ( empty( $role ) ) {
						return new \WP_Error( 'missing_role', 'role (slug) is required.' );
					}
					if ( empty( $display_name ) ) {
						return new \WP_Error( 'missing_display_name', 'display_name is required.' );
					}
					if ( in_array( $role, $built_in_roles, true ) ) {
						return new \WP_Error( 'builtin_role', "Cannot override built-in role: $role" );
					}

					$wp_roles = wp_roles();

					// Check if role already exists.
					if ( isset( $wp_roles->roles[ $role ] ) ) {
						return new \WP_Error( 'role_exists', "Role '$role' already exists. Use role-update to modify it." );
					}

					// If cloning, get capabilities from source role.
					if ( ! empty( $clone_from ) ) {
						if ( ! isset( $wp_roles->roles[ $clone_from ] ) ) {
							return new \WP_Error( 'clone_source_not_found', "Source role '$clone_from' does not exist." );
						}
						$source_caps = isset( $wp_roles->roles[ $clone_from ]['capabilities'] )
							? $wp_roles->roles[ $clone_from ]['capabilities']
							: array();
						$capabilities = array_merge( $capabilities, $source_caps );
					}

					// If no capabilities provided and not cloning, start with empty array.
					if ( empty( $capabilities ) ) {
						$capabilities = array( 'read' => true );
					}

					$result = $wp_roles->add_role( $role, $display_name, $capabilities );

					if ( ! $result ) {
						return new \WP_Error( 'add_role_failed', "Failed to create role '$role'." );
					}

					return array(
						'output'       => "Role '$role' created successfully with " . count( $capabilities ) . ' capabilities.',
						'role'         => $role,
						'display_name' => $display_name,
						'cap_count'    => count( $capabilities ),
						'cloned_from'  => ! empty( $clone_from ) ? $clone_from : null,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'         => array(
							'type'        => 'string',
							'description' => 'The role slug (e.g., manager, consultant).',
						),
						'display_name' => array(
							'type'        => 'string',
							'description' => 'The role display name shown in the admin.',
						),
						'capabilities' => array(
							'type'        => 'array',
							'description' => 'Array of capability keys (e.g., ["read", "edit_posts", "manage_options"]).',
						),
						'clone_from'   => array(
							'type'        => 'string',
							'description' => 'Optional. Role slug to clone capabilities from (e.g., editor).',
						),
					),
					'required'   => array( 'role', 'display_name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		// role-update: Adds or removes capabilities from a role.
		wp_register_ability(
			'cdw/role-update',
			array(
				'label'               => __( 'Update Role', 'cdw' ),
				'description'         => __( 'Updates a role by adding or removing capabilities. Use add_caps to grant new capabilities, remove_caps to revoke capabilities. Cannot modify built-in roles (administrator, editor, author, contributor, subscriber).', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) use ( $built_in_roles ) {
					$role        = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';
					$add_caps    = isset( $input['add_caps'] ) ? (array) $input['add_caps'] : array();
					$remove_caps = isset( $input['remove_caps'] ) ? (array) $input['remove_caps'] : array();

					if ( empty( $role ) ) {
						return new \WP_Error( 'missing_role', 'role (slug) is required.' );
					}

					if ( in_array( $role, $built_in_roles, true ) ) {
						return new \WP_Error( 'builtin_role', "Cannot modify built-in role: $role. Use role-create to make a custom copy instead." );
					}

					$wp_roles = wp_roles();

					if ( ! isset( $wp_roles->roles[ $role ] ) ) {
						return new \WP_Error( 'role_not_found', "Role '$role' does not exist. Use role-create to create it." );
					}

					$role_object = $wp_roles->get_role( $role );
					if ( ! $role_object ) {
						return new \WP_Error( 'role_get_failed', "Failed to get role '$role'." );
					}

					$added   = array();
					$removed = array();

					// Add capabilities.
					foreach ( $add_caps as $cap ) {
						$cap = sanitize_key( $cap );
						if ( ! empty( $cap ) ) {
							$role_object->add_cap( $cap );
							$added[] = $cap;
						}
					}

					// Remove capabilities.
					foreach ( $remove_caps as $cap ) {
						$cap = sanitize_key( $cap );
						if ( ! empty( $cap ) ) {
							$role_object->remove_cap( $cap );
							$removed[] = $cap;
						}
					}

					// Get updated capability count.
					$current_caps = isset( $wp_roles->roles[ $role ]['capabilities'] )
						? $wp_roles->roles[ $role ]['capabilities']
						: array();

					return array(
						'output'    => "Role '$role' updated.",
						'role'      => $role,
						'added'     => $added,
						'removed'   => $removed,
						'cap_count' => count( $current_caps ),
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'        => array(
							'type'        => 'string',
							'description' => 'The role slug to update (must be a custom role, not built-in).',
						),
						'add_caps'    => array(
							'type'        => 'array',
							'description' => 'Array of capability keys to add.',
						),
						'remove_caps' => array(
							'type'        => 'array',
							'description' => 'Array of capability keys to remove.',
						),
					),
					'required'   => array( 'role' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		// role-delete: Deletes a custom role.
		wp_register_ability(
			'cdw/role-delete',
			array(
				'label'               => __( 'Delete Role', 'cdw' ),
				'description'         => __( 'Deletes a custom WordPress role. Cannot delete built-in roles (administrator, editor, author, contributor, subscriber). Users currently assigned to the deleted role will be moved to subscriber.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) use ( $built_in_roles ) {
					$role = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';

					if ( empty( $role ) ) {
						return new \WP_Error( 'missing_role', 'role (slug) is required.' );
					}

					if ( in_array( $role, $built_in_roles, true ) ) {
						return new \WP_Error( 'builtin_role', "Cannot delete built-in role: $role" );
					}

					$wp_roles = wp_roles();

					if ( ! isset( $wp_roles->roles[ $role ] ) ) {
						return new \WP_Error( 'role_not_found', "Role '$role' does not exist." );
					}

					// Get count of users with this role before deletion.
					$users_with_role = count(
						get_users(
							array(
								'role'   => $role,
								'fields' => 'ID',
								'number' => -1,
							)
						)
					);

					// remove_role() returns void, throws on failure.
					$wp_roles->remove_role( $role );

					return array(
						'output'         => "Role '$role' deleted successfully.",
						'role'           => $role,
						'users_affected' => $users_with_role,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'The role slug to delete (must be a custom role, not built-in).',
						),
					),
					'required'   => array( 'role' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);

		// ---------------------------------------------------------------
		// block-patterns-get: abilities-only (no CLI equivalent).
		// Returns raw block markup for a specific pattern by name.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/block-patterns-get',
			array(
				'label'               => __( 'Get Block Pattern Content', 'cdw' ),
				'description'         => __( 'Returns the raw block markup for a specific block pattern by name. Returns base64-encoded content to preserve special characters. Use this to retrieve a pattern before appending it to a page.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$pattern_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					if ( empty( $pattern_name ) ) {
						return new \WP_Error( 'invalid_pattern_name', 'pattern name is required.' );
					}

					$registry = \WP_Block_Patterns_Registry::get_instance();
					$patterns = $registry->get_all_registered();

					$matched = null;
					foreach ( $patterns as $pattern ) {
						if ( $pattern['name'] === $pattern_name ) {
							$matched = $pattern;
							break;
						}
					}

					if ( ! $matched ) {
						return new \WP_Error( 'pattern_not_found', "Pattern not found: $pattern_name" );
					}

					$content = isset( $matched['content'] ) ? $matched['content'] : '';
					if ( empty( $content ) ) {
						return new \WP_Error( 'empty_pattern', "Pattern \"$pattern_name\" has no content." );
					}

					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 encoding for safe transfer
					$content_base64 = base64_encode( $content );

					return array(
						'output'         => "Pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes.',
						'name'           => $pattern_name,
						'title'          => isset( $matched['title'] ) ? $matched['title'] : '',
						'content_length' => strlen( $content ),
						'content_base64' => $content_base64,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Name of the block pattern to retrieve (e.g., blockbase/footer-simple).',
						),
					),
					'required'   => array( 'name' ),
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

		// ---------------------------------------------------------------
		// post-set-content: abilities-only (no CLI equivalent).
		// Block markup contains quotes, newlines, and angle brackets that
		// cannot survive the whitespace-tokenised CLI command parser.
		// ---------------------------------------------------------------
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

			// ---------------------------------------------------------------
			// post-get-content: abilities-only. Returns raw post_content
			// (block markup) so AI can edit it. Supports pagination via
			// offset and limit parameters for large content.
			// ---------------------------------------------------------------
			wp_register_ability(
				'cdw/post-get-content',
				array(
					'label'               => __( 'Get Post Content', 'cdw' ),
					'description'         => __( 'Retrieves the raw post_content of a WordPress post or page, including all Gutenberg block markup. Use offset and limit for pagination on large content. Use this before editing a page with cdw/post-set-content.', 'cdw' ),
					'category'            => 'cdw-admin-tools',
					'permission_callback' => $permission_cb,
					'execute_callback'    => function ( $input = array() ) {
						$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
						$offset   = isset( $input['offset'] ) ? (int) $input['offset'] : 0;
						$limit    = isset( $input['limit'] ) ? (int) $input['limit'] : 5000;

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

						$content        = $post->post_content;
						$total_length   = strlen( $content );
						$has_more       = ( $offset + $limit ) < $total_length;
						$chunk_index    = intval( $offset / $limit );
						$chunk_content  = substr( $content, $offset, $limit );

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

			// ---------------------------------------------------------------
			// post-append-content: abilities-only (no CLI equivalent).
			// Appends a block markup chunk to the existing post_content so large
			// pages can be built in multiple smaller tool calls.
			// ---------------------------------------------------------------
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

		// ---------------------------------------------------------------
		// build-page: abilities-only (no CLI equivalent).
		// Accepts structured JSON to generate Gutenberg block markup.
		// ---------------------------------------------------------------
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
					} else {
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
					}
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

		// ---------------------------------------------------------------
		// custom-patterns-list: Lists custom patterns from cdw/patterns/ folder.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/custom-patterns-list',
			array(
				'label'               => __( 'List Custom Patterns', 'cdw' ),
				'description'         => __( 'Returns a list of all custom block patterns stored in the cdw/patterns/ folder. Each pattern includes name, title, description, and category.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$patterns_dir = CDW_PLUGIN_DIR . 'patterns';

					if ( ! is_dir( $patterns_dir ) ) {
						return array(
							'output'   => 'No custom patterns found.',
							'patterns' => array(),
						);
					}

					$patterns = array();
					$files    = glob( $patterns_dir . '/**/*.json', GLOB_BRACE );

					foreach ( $files as $file ) {
						$content = file_get_contents( $file );
						$data    = json_decode( $content, true );

						if ( $data && isset( $data['name'] ) ) {
							$patterns[] = array(
								'name'        => $data['name'],
								'title'       => isset( $data['title'] ) ? $data['title'] : '',
								'description' => isset( $data['description'] ) ? $data['description'] : '',
								'category'    => isset( $data['category'] ) ? $data['category'] : 'general',
							);
						}
					}

					return array(
						'output'   => 'Found ' . count( $patterns ) . ' custom patterns.',
						'patterns' => $patterns,
					);
				},
				'input_schema'        => array(),
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

		// ---------------------------------------------------------------
		// custom-patterns-get: Gets a specific custom pattern by name.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/custom-patterns-get',
			array(
				'label'               => __( 'Get Custom Pattern', 'cdw' ),
				'description'         => __( 'Returns the raw block markup for a specific custom pattern by name. Searches in cdw/patterns/ folder. Returns base64-encoded content to preserve special characters.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$pattern_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					if ( empty( $pattern_name ) ) {
						return new \WP_Error( 'invalid_pattern_name', 'pattern name is required.' );
					}

					$patterns_dir = CDW_PLUGIN_DIR . 'patterns';

					if ( ! is_dir( $patterns_dir ) ) {
						return new \WP_Error( 'patterns_dir_not_found', 'Patterns directory not found.' );
					}

					// Search for the pattern file (supports subdirectories).
					$files = glob( $patterns_dir . '/**/*.json', GLOB_BRACE );
					$matched = null;

					foreach ( $files as $file ) {
						$content = file_get_contents( $file );
						$data    = json_decode( $content, true );

						if ( $data && isset( $data['name'] ) && $data['name'] === $pattern_name ) {
							$matched = $data;
							break;
						}
					}

					if ( ! $matched ) {
						return new \WP_Error( 'pattern_not_found', "Custom pattern not found: $pattern_name" );
					}

					$content = isset( $matched['content'] ) ? $matched['content'] : '';
					if ( empty( $content ) ) {
						return new \WP_Error( 'empty_pattern', "Pattern \"$pattern_name\" has no content." );
					}

					$content_base64 = base64_encode( $content );

					return array(
						'output'         => "Custom pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes.',
						'name'           => $pattern_name,
						'title'          => isset( $matched['title'] ) ? $matched['title'] : '',
						'description'    => isset( $matched['description'] ) ? $matched['description'] : '',
						'category'       => isset( $matched['category'] ) ? $matched['category'] : 'general',
						'content_length' => strlen( $content ),
						'content_base64' => $content_base64,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Name of the custom pattern to retrieve (e.g., hero-luxury, gallery-masonry).',
						),
					),
					'required'   => array( 'name' ),
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

	/**
	 * Registers a single ability.
	 *
	 * Builds the execute_callback by mapping the ability name to the correct
	 * CLI command string, then calls wp_register_ability().
	 *
	 * @param array<string, mixed> $ability       Ability definition (name, label, input, cli).
	 * @param callable             $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_one( array $ability, callable $permission_cb ) {
		$ability_name = $ability['name'];
		$static_cli   = $ability['cli'];

		$execute_cb = function ( $input = null ) use ( $ability_name, $static_cli ) {
			// Convert stdClass to array recursively if needed (MCP sends JSON objects).
			if ( is_object( $input ) ) {
				$input = json_decode( json_encode( $input ), true );
			}
			$cli_command = $static_cli ?? self::build_cli_command( $ability_name, $input );
			$service     = new CDW_CLI_Service();
			$result      = $service->execute_as_ai( $cli_command, get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'output' => $result );
		};

		$args = array(
			'label'               => $ability['label'],
			'description'         => $ability['desc'],
			'category'            => 'cdw-admin-tools',
			'permission_callback' => $permission_cb,
			'execute_callback'    => $execute_cb,
			'meta'                => array(
				'show_in_rest' => true,
				'readonly'     => $ability['readonly'],
				'idempotent'   => $ability['readonly'],
				'annotations'  => array(
					'destructive' => $ability['destructive'],
				),
			),
		);

		// Register input_schema based on the ability's parameter requirements.
		//
		// - No params at all: omit schema entirely for MCP compatibility.
		// - Has params: include schema with type='object'.
		if ( ! empty( $ability['input'] ) ) {
			$args['input_schema'] = array(
				'type'       => 'object',
				'properties' => $ability['input'],
			);
		}

		wp_register_ability( $ability_name, $args );
	}

	/**
	 * Strips whitespace from a CLI argument to prevent token injection.
	 *
	 * The CLI service splits commands on whitespace, so any spaces inside a
	 * user-supplied value would be parsed as separate tokens (extra flags or
	 * arguments). Removing them closes that injection vector.
	 *
	 * @param string $value Raw user input.
	 * @return string Sanitized single-token value.
	 */
	private static function sanitize_cli_arg( string $value ): string {
		return preg_replace( '/\s+/', '', trim( $value ) );
	}

	/**
	 * Builds a CLI command string from an ability name and user-supplied input.
	 *
	 * Only called for abilities whose CLI string depends on runtime input params.
	 *
	 * @param string               $ability_name Fully-qualified ability name, e.g. `cdw/plugin-activate`.
	 * @param array<string, mixed> $input        Validated input params from the caller.
	 * @return string
	 */
	private static function build_cli_command( string $ability_name, ?array $input ): string {
		$input = $input ?? array();
		switch ( $ability_name ) {
			case 'cdw/plugin-status':
				return 'plugin status ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/plugin-activate':
				return 'plugin activate ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/plugin-deactivate':
				return 'plugin deactivate ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/plugin-install':
				return 'plugin install ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/plugin-update':
				return 'plugin update ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/plugin-delete':
				return 'plugin delete ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/theme-activate':
				return 'theme activate ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/theme-install':
				return 'theme install ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/theme-update':
				return 'theme update ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/theme-status':
				return 'theme status ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/user-create':
				return 'user create '
					. self::sanitize_cli_arg( $input['username'] ) . ' '
					. self::sanitize_cli_arg( $input['email'] ) . ' '
					. self::sanitize_cli_arg( $input['role'] );
			case 'cdw/user-delete':
				return 'user delete ' . (int) $input['user_id'] . ' --force';
			case 'cdw/user-get':
				return 'user get ' . self::sanitize_cli_arg( $input['identifier'] );
			case 'cdw/option-get':
				return 'option get ' . self::sanitize_cli_arg( $input['name'] );
			case 'cdw/option-set':
				return 'option set ' . self::sanitize_cli_arg( $input['name'] ) . ' ' . self::sanitize_cli_arg( $input['value'] );
			case 'cdw/search-replace':
				$flag = ! empty( $input['dry_run'] ) ? '--dry-run' : '--force';
				return 'search-replace ' . self::sanitize_cli_arg( $input['search'] ) . ' ' . self::sanitize_cli_arg( $input['replace'] ) . ' ' . $flag;
			case 'cdw/post-get':
				return 'post get ' . (int) $input['post_id'];
			case 'cdw/post-create':
				return 'post create ' . sanitize_text_field( $input['title'] );
			case 'cdw/page-create':
				return 'page create ' . sanitize_text_field( $input['title'] );
			case 'cdw/task-list':
				$uid = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
				$cmd = 'task list';
				if ( $uid > 0 ) {
					$cmd .= ' --user_id=' . $uid;
				}
				return $cmd;
			case 'cdw/task-create':
				$cmd = 'task create ' . sanitize_text_field( $input['name'] );
				if ( ! empty( $input['assignee_login'] ) ) {
					$cmd .= ' --assignee_login=' . self::sanitize_cli_arg( $input['assignee_login'] );
				} elseif ( ! empty( $input['assignee_id'] ) ) {
					$cmd .= ' --assignee_id=' . (int) $input['assignee_id'];
				}
				return $cmd;
			case 'cdw/task-delete':
				$uid = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
				$cmd = 'task delete';
				if ( $uid > 0 ) {
					$cmd .= ' --user_id=' . $uid;
				}
				return $cmd;
			case 'cdw/comment-list':
				$status = isset( $input['status'] ) ? self::sanitize_cli_arg( (string) $input['status'] ) : 'pending';
				return 'comment list ' . $status;
			case 'cdw/comment-approve':
				return 'comment approve ' . (int) $input['id'];
			case 'cdw/comment-spam':
				return 'comment spam ' . (int) $input['id'];
			case 'cdw/comment-delete':
				return 'comment delete ' . (int) $input['id'] . ' --force';
			case 'cdw/post-list':
				$type = isset( $input['type'] ) ? self::sanitize_cli_arg( (string) $input['type'] ) : 'post';
				return 'post list ' . $type;
			case 'cdw/post-count':
				$type = isset( $input['type'] ) ? self::sanitize_cli_arg( (string) $input['type'] ) : '';
				return $type ? 'post count ' . $type : 'post count';
			case 'cdw/post-status':
				return 'post status ' . (int) $input['post_id'] . ' ' . self::sanitize_cli_arg( (string) $input['status'] );
			case 'cdw/post-delete':
				return 'post delete ' . (int) $input['post_id'] . ' --force';
			case 'cdw/user-role':
				return 'user role ' . self::sanitize_cli_arg( (string) $input['identifier'] ) . ' ' . self::sanitize_cli_arg( (string) $input['role'] );
			case 'cdw/option-delete':
				return 'option delete ' . self::sanitize_cli_arg( (string) $input['name'] );
			case 'cdw/theme-delete':
				return 'theme delete ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
			case 'cdw/transient-delete':
				return 'transient delete ' . self::sanitize_cli_arg( (string) $input['name'] );
			case 'cdw/cron-run':
				return 'cron run ' . self::sanitize_cli_arg( (string) $input['hook'] );
			case 'cdw/media-list':
				$count = isset( $input['count'] ) ? (int) $input['count'] : 20;
				return 'media list ' . $count;
			case 'cdw/block-patterns-list':
				if ( ! empty( $input['category'] ) ) {
					return 'block-patterns list ' . self::sanitize_cli_arg( (string) $input['category'] );
				}
				return 'block-patterns list';
			case 'cdw/skill-get':
				$cmd = 'skill get '
					. self::sanitize_cli_arg( (string) $input['plugin_slug'] ) . ' '
					. self::sanitize_cli_arg( (string) $input['skill_name'] );
				if ( ! empty( $input['file'] ) ) {
					$cmd .= ' ' . self::sanitize_cli_arg( (string) $input['file'] );
				}
				return $cmd;
			default:
				return '';
		}
	}
}

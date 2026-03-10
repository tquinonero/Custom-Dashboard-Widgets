<?php
/**
 * Meta-related ability registrations.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post, user, and term meta abilities.
 */
class CDW_Meta_Abilities {

	/**
	 * Registers all meta abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	public static function register( callable $permission_cb ) {
		self::register_post_meta( $permission_cb );
		self::register_user_meta( $permission_cb );
		self::register_term_meta( $permission_cb );
	}

	/**
	 * Registers post meta abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_post_meta( callable $permission_cb ) {
		wp_register_ability(
			'cdw/post-meta-get',
			array(
				'label'               => __( 'Get Post Meta', 'cdw' ),
				'description'         => __( 'Retrieves metadata for a specific post. If key is omitted, returns all meta for the post.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to view meta for this post.' );
					}

					if ( '' !== $key ) {
						$value = get_post_meta( $post_id, $key, true );
						return array(
							'output'   => "Meta '$key' for post $post_id: " . ( is_scalar( $value ) ? $value : json_encode( $value ) ),
							'post_id'  => $post_id,
							'key'      => $key,
							'value'    => $value,
						);
					}

					$all_meta = get_post_meta( $post_id );
					$meta     = array();
					foreach ( $all_meta as $k => $v ) {
						$meta[ $k ] = is_array( $v ) && count( $v ) === 1 ? $v[0] : $v;
					}

					return array(
						'output'  => 'Retrieved ' . count( $meta ) . ' meta items for post ' . $post_id,
						'post_id' => $post_id,
						'meta'    => $meta,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post.',
						),
						'key'     => array(
							'type'        => 'string',
							'description' => 'Optional. Specific meta key to retrieve. If omitted, returns all meta.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/post-meta-set',
			array(
				'label'               => __( 'Set Post Meta', 'cdw' ),
				'description'         => __( 'Sets metadata for a post. For complex values (arrays, objects), provide value_base64 with base64-encoded JSON.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( isset( $input['value_base64'] ) && '' !== (string) $input['value_base64'] ) {
						$decoded = base64_decode( (string) $input['value_base64'], true );
						if ( false === $decoded ) {
							return new \WP_Error( 'invalid_base64', 'value_base64 is not valid base64.' );
						}
						$value = json_decode( $decoded, true );
						if ( json_last_error() !== JSON_ERROR_NONE ) {
							$value = $decoded;
						}
					} else {
						$value = isset( $input['value'] ) ? $input['value'] : '';
					}

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					if ( empty( $key ) ) {
						return new \WP_Error( 'invalid_key', 'key is required.' );
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit meta for this post.' );
					}

					$result = update_post_meta( $post_id, $key, $value );

					return array(
						'output'   => "Meta '$key' set on post $post_id.",
						'post_id'  => $post_id,
						'key'      => $key,
						'previous' => $result,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => 'ID of the post.',
						),
						'key'         => array(
							'type'        => 'string',
							'description' => 'Meta key to set.',
						),
						'value'        => array(
							'type'        => 'mixed',
							'description' => 'Meta value (string, number, or boolean). For arrays/objects, use value_base64.',
						),
						'value_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded JSON value for arrays/objects. Provide either value or value_base64, not both.',
						),
					),
					'required'   => array( 'post_id', 'key' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/post-meta-delete',
			array(
				'label'               => __( 'Delete Post Meta', 'cdw' ),
				'description'         => __( 'Deletes metadata for a specific post by key.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					if ( empty( $key ) ) {
						return new \WP_Error( 'invalid_key', 'key is required.' );
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to delete meta for this post.' );
					}

					$result = delete_post_meta( $post_id, $key );

					return array(
						'output'   => $result ? "Meta '$key' deleted from post $post_id." : "Meta '$key' not found on post $post_id.",
						'post_id'  => $post_id,
						'key'      => $key,
						'deleted'  => $result,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post.',
						),
						'key'     => array(
							'type'        => 'string',
							'description' => 'Meta key to delete.',
						),
					),
					'required'   => array( 'post_id', 'key' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);
	}

	/**
	 * Registers user meta abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_user_meta( callable $permission_cb ) {
		wp_register_ability(
			'cdw/user-meta-get',
			array(
				'label'               => __( 'Get User Meta', 'cdw' ),
				'description'         => __( 'Retrieves metadata for a specific user. If key is omitted, returns all meta for the user.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( $user_id <= 0 ) {
						return new \WP_Error( 'invalid_user_id', 'user_id is required and must be a positive integer.' );
					}
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						return new \WP_Error( 'user_not_found', "User $user_id not found." );
					}
					if ( ! current_user_can( 'edit_user', $user_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to view meta for this user.' );
					}

					if ( '' !== $key ) {
						$value = get_user_meta( $user_id, $key, true );
						return array(
							'output'   => "Meta '$key' for user $user_id: " . ( is_scalar( $value ) ? $value : json_encode( $value ) ),
							'user_id'  => $user_id,
							'key'      => $key,
							'value'    => $value,
						);
					}

					$all_meta = get_user_meta( $user_id );
					$meta     = array();
					foreach ( $all_meta as $k => $v ) {
						$meta[ $k ] = is_array( $v ) && count( $v ) === 1 ? $v[0] : $v;
					}

					return array(
						'output'  => 'Retrieved ' . count( $meta ) . ' meta items for user ' . $user_id,
						'user_id' => $user_id,
						'meta'    => $meta,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the user.',
						),
						'key'     => array(
							'type'        => 'string',
							'description' => 'Optional. Specific meta key to retrieve. If omitted, returns all meta.',
						),
					),
					'required'   => array( 'user_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/user-meta-set',
			array(
				'label'               => __( 'Set User Meta', 'cdw' ),
				'description'         => __( 'Sets metadata for a user. For complex values (arrays, objects), provide value_base64 with base64-encoded JSON.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( isset( $input['value_base64'] ) && '' !== (string) $input['value_base64'] ) {
						$decoded = base64_decode( (string) $input['value_base64'], true );
						if ( false === $decoded ) {
							return new \WP_Error( 'invalid_base64', 'value_base64 is not valid base64.' );
						}
						$value = json_decode( $decoded, true );
						if ( json_last_error() !== JSON_ERROR_NONE ) {
							$value = $decoded;
						}
					} else {
						$value = isset( $input['value'] ) ? $input['value'] : '';
					}

					if ( $user_id <= 0 ) {
						return new \WP_Error( 'invalid_user_id', 'user_id is required and must be a positive integer.' );
					}
					if ( empty( $key ) ) {
						return new \WP_Error( 'invalid_key', 'key is required.' );
					}
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						return new \WP_Error( 'user_not_found', "User $user_id not found." );
					}
					if ( ! current_user_can( 'edit_user', $user_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit meta for this user.' );
					}

					$result = update_user_meta( $user_id, $key, $value );

					return array(
						'output'   => "Meta '$key' set on user $user_id.",
						'user_id'  => $user_id,
						'key'      => $key,
						'previous' => $result,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'user_id'     => array(
							'type'        => 'integer',
							'description' => 'ID of the user.',
						),
						'key'         => array(
							'type'        => 'string',
							'description' => 'Meta key to set.',
						),
						'value'        => array(
							'type'        => 'mixed',
							'description' => 'Meta value (string, number, or boolean). For arrays/objects, use value_base64.',
						),
						'value_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded JSON value for arrays/objects. Provide either value or value_base64, not both.',
						),
					),
					'required'   => array( 'user_id', 'key' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/user-meta-delete',
			array(
				'label'               => __( 'Delete User Meta', 'cdw' ),
				'description'         => __( 'Deletes metadata for a specific user by key.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( $user_id <= 0 ) {
						return new \WP_Error( 'invalid_user_id', 'user_id is required and must be a positive integer.' );
					}
					if ( empty( $key ) ) {
						return new \WP_Error( 'invalid_key', 'key is required.' );
					}
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						return new \WP_Error( 'user_not_found', "User $user_id not found." );
					}
					if ( ! current_user_can( 'edit_user', $user_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to delete meta for this user.' );
					}

					$result = delete_user_meta( $user_id, $key );

					return array(
						'output'   => $result ? "Meta '$key' deleted from user $user_id." : "Meta '$key' not found on user $user_id.",
						'user_id'  => $user_id,
						'key'      => $key,
						'deleted'  => $result,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the user.',
						),
						'key'     => array(
							'type'        => 'string',
							'description' => 'Meta key to delete.',
						),
					),
					'required'   => array( 'user_id', 'key' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);
	}

	/**
	 * Registers term list ability (needed to get term IDs before working with term meta).
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_term_list( callable $permission_cb ) {
		wp_register_ability(
			'cdw/term-list',
			array(
				'label'               => __( 'List Terms', 'cdw' ),
				'description'         => __( 'Returns a list of terms (categories, tags, or custom taxonomy) with their IDs, names, and counts. Use this to find term IDs before working with term meta.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$taxonomy = isset( $input['taxonomy'] ) ? (string) $input['taxonomy'] : 'category';
					$number   = isset( $input['number'] ) ? (int) $input['number'] : 100;

					if ( ! taxonomy_exists( $taxonomy ) ) {
						return new \WP_Error( 'invalid_taxonomy', "Taxonomy '$taxonomy' does not exist." );
					}
					if ( ! current_user_can( 'manage_options' ) && ! current_user_can( "manage_{$taxonomy}" ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to list terms.' );
					}

					$terms = get_terms(
						array(
							'taxonomy'   => $taxonomy,
							'number'     => $number,
							'hide_empty' => false,
						)
					);

					if ( is_wp_error( $terms ) ) {
						return $terms;
					}

					$items = array();
					foreach ( $terms as $term ) {
						$items[] = array(
							'term_id'    => $term->term_id,
							'name'       => $term->name,
							'slug'       => $term->slug,
							'count'      => $term->count,
							'taxonomy'   => $term->taxonomy,
						);
					}

					return array(
						'output'    => "Found " . count( $items ) . " terms in '$taxonomy'",
						'taxonomy'  => $taxonomy,
						'terms'     => $items,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array(
							'type'        => 'string',
							'description' => 'Taxonomy slug (category, post_tag, or custom). Default: category.',
							'default'     => 'category',
						),
						'number'   => array(
							'type'        => 'integer',
							'description' => 'Maximum number of terms to return. Default: 100.',
							'default'     => 100,
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
	}

	/**
	 * Registers term meta abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_term_meta( callable $permission_cb ) {
		self::register_term_list( $permission_cb );

		wp_register_ability(
			'cdw/term-meta-get',
			array(
				'label'               => __( 'Get Term Meta', 'cdw' ),
				'description'         => __( 'Retrieves metadata for a specific term (category, tag, or custom taxonomy). If key is omitted, returns all meta for the term.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$term_id = isset( $input['term_id'] ) ? (int) $input['term_id'] : 0;
					$key     = isset( $input['key'] ) ? (string) $input['key'] : '';
					$taxonomy = isset( $input['taxonomy'] ) ? (string) $input['taxonomy'] : '';

					if ( $term_id <= 0 ) {
						return new \WP_Error( 'invalid_term_id', 'term_id is required and must be a positive integer.' );
					}

					$term = $term_id > 0 ? get_term( $term_id, $taxonomy ) : null;
					if ( ! $term || is_wp_error( $term ) ) {
						return new \WP_Error( 'term_not_found', "Term $term_id not found." );
					}
					if ( ! current_user_can( 'manage_terms' ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to view meta for this term.' );
					}

					if ( '' !== $key ) {
						$value = get_term_meta( $term_id, $key, true );
						return array(
							'output'    => "Meta '$key' for term $term_id ({$term->name}): " . ( is_scalar( $value ) ? $value : json_encode( $value ) ),
							'term_id'   => $term_id,
							'term_name' => $term->name,
							'taxonomy'  => $term->taxonomy,
							'key'       => $key,
							'value'     => $value,
						);
					}

					$all_meta = get_term_meta( $term_id );
					$meta     = array();
					foreach ( $all_meta as $k => $v ) {
						$meta[ $k ] = is_array( $v ) && count( $v ) === 1 ? $v[0] : $v;
					}

					return array(
						'output'    => 'Retrieved ' . count( $meta ) . ' meta items for term ' . $term->name,
						'term_id'   => $term_id,
						'term_name' => $term->name,
						'taxonomy'  => $term->taxonomy,
						'meta'      => $meta,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'  => array(
							'type'        => 'integer',
							'description' => 'ID of the term.',
						),
						'taxonomy' => array(
							'type'        => 'string',
							'description' => 'Optional. Taxonomy slug (category, post_tag, or custom).',
						),
						'key'      => array(
							'type'        => 'string',
							'description' => 'Optional. Specific meta key to retrieve. If omitted, returns all meta.',
						),
					),
					'required'   => array( 'term_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/term-meta-set',
			array(
				'label'               => __( 'Set Term Meta', 'cdw' ),
				'description'         => __( 'Sets metadata for a term (category, tag, or custom taxonomy). For complex values (arrays, objects), provide value_base64 with base64-encoded JSON.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$term_id  = isset( $input['term_id'] ) ? (int) $input['term_id'] : 0;
					$taxonomy = isset( $input['taxonomy'] ) ? (string) $input['taxonomy'] : '';
					$key      = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( isset( $input['value_base64'] ) && '' !== (string) $input['value_base64'] ) {
						$decoded = base64_decode( (string) $input['value_base64'], true );
						if ( false === $decoded ) {
							return new \WP_Error( 'invalid_base64', 'value_base64 is not valid base64.' );
						}
						$value = json_decode( $decoded, true );
						if ( json_last_error() !== JSON_ERROR_NONE ) {
							$value = $decoded;
						}
					} else {
						$value = isset( $input['value'] ) ? $input['value'] : '';
					}

					if ( $term_id <= 0 ) {
						return new \WP_Error( 'invalid_term_id', 'term_id is required and must be a positive integer.' );
					}
					if ( empty( $key ) ) {
						return new \WP_Error( 'invalid_key', 'key is required.' );
					}

					$term = $term_id > 0 ? get_term( $term_id, $taxonomy ) : null;
					if ( ! $term || is_wp_error( $term ) ) {
						return new \WP_Error( 'term_not_found', "Term $term_id not found." );
					}
					if ( ! current_user_can( 'manage_terms' ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit meta for this term.' );
					}

					$result = update_term_meta( $term_id, $key, $value );

					return array(
						'output'    => "Meta '$key' set on term {$term->name} (ID: $term_id).",
						'term_id'   => $term_id,
						'term_name' => $term->name,
						'taxonomy'  => $term->taxonomy,
						'key'       => $key,
						'previous'  => $result,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'     => array(
							'type'        => 'integer',
							'description' => 'ID of the term.',
						),
						'taxonomy'    => array(
							'type'        => 'string',
							'description' => 'Optional. Taxonomy slug (category, post_tag, or custom).',
						),
						'key'         => array(
							'type'        => 'string',
							'description' => 'Meta key to set.',
						),
						'value'        => array(
							'type'        => 'mixed',
							'description' => 'Meta value (string, number, or boolean). For arrays/objects, use value_base64.',
						),
						'value_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded JSON value for arrays/objects. Provide either value or value_base64, not both.',
						),
					),
					'required'   => array( 'term_id', 'key' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/term-meta-delete',
			array(
				'label'               => __( 'Delete Term Meta', 'cdw' ),
				'description'         => __( 'Deletes metadata for a specific term (category, tag, or custom taxonomy) by key.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$term_id  = isset( $input['term_id'] ) ? (int) $input['term_id'] : 0;
					$taxonomy = isset( $input['taxonomy'] ) ? (string) $input['taxonomy'] : '';
					$key      = isset( $input['key'] ) ? (string) $input['key'] : '';

					if ( $term_id <= 0 ) {
						return new \WP_Error( 'invalid_term_id', 'term_id is required and must be a positive integer.' );
					}
					if ( empty( $key ) ) {
						return new \WP_Error( 'invalid_key', 'key is required.' );
					}

					$term = $term_id > 0 ? get_term( $term_id, $taxonomy ) : null;
					if ( ! $term || is_wp_error( $term ) ) {
						return new \WP_Error( 'term_not_found', "Term $term_id not found." );
					}
					if ( ! current_user_can( 'manage_terms' ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to delete meta for this term.' );
					}

					$result = delete_term_meta( $term_id, $key );

					return array(
						'output'    => $result ? "Meta '$key' deleted from term {$term->name} (ID: $term_id)." : "Meta '$key' not found on term {$term->name}.",
						'term_id'   => $term_id,
						'term_name' => $term->name,
						'taxonomy'  => $term->taxonomy,
						'key'       => $key,
						'deleted'   => $result,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'term_id'  => array(
							'type'        => 'integer',
							'description' => 'ID of the term.',
						),
						'taxonomy' => array(
							'type'        => 'string',
							'description' => 'Optional. Taxonomy slug (category, post_tag, or custom).',
						),
						'key'      => array(
							'type'        => 'string',
							'description' => 'Meta key to delete.',
						),
					),
					'required'   => array( 'term_id', 'key' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);
	}
}

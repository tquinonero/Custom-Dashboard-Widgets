<?php
/**
 * Posts REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Handles GET /cdw/v1/posts — returns recent posts of a given type/status.
 *
 * @package CDW
 */
class CDW_Posts_Controller extends CDW_Base_Controller {
	/**
	 * Registers the /posts REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_posts' ),
				'permission_callback' => array( $this, 'check_contributor_permission' ),
				'args'                => array(
					'per_page'  => array(
						'type'    => 'integer',
						'default' => 10,
						'minimum' => 1,
						'maximum' => 50,
					),
					'status'    => array(
						'type'    => 'string',
						'default' => 'publish',
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => 'post',
					),
				),
			)
		);
	}

	/**
	 * Returns recent posts.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_posts( WP_REST_Request $request ) {
		$per_page  = (int) $request->get_param( 'per_page' );
		$status    = $request->get_param( 'status' );
		$post_type = $request->get_param( 'post_type' );

		// Non-admins may only see published content.
		if ( 'publish' !== $status && ! current_user_can( 'manage_options' ) ) {
			$status = 'publish';
		}

		// Reject unregistered or non-public post types to prevent data leakage
		// (e.g. post_type=revision) and avoid confusing empty results.
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! $post_type_obj || ! $post_type_obj->public ) {
			return $this->error_response( 'Invalid or non-public post type.', 400 );
		}

		$cache_key = "cdw_posts_cache_{$per_page}_{$status}_{$post_type}";
		$formatted = $this->get_transient_with_cache(
			$cache_key,
			function () use ( $per_page, $status, $post_type ) {
				$posts = get_posts(
					array(
						'numberposts' => $per_page,
						'post_status' => $status,
						'post_type'   => $post_type,
					)
				);

				$items = array();
				foreach ( $posts as $post ) {
					$items[] = array(
						'id'        => $post->ID,
						'title'     => $post->post_title,
						'status'    => $post->post_status,
						'date'      => $post->post_date,
						'author'    => $post->post_author,
						'permalink' => get_permalink( $post->ID ),
					);
				}
				return $items;
			},
			300
		);

		return rest_ensure_response( $formatted );
	}
}

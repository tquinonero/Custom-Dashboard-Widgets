<?php
/**
 * Media REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Handles GET /cdw/v1/media — returns recently uploaded files.
 *
 * @package CDW
 */
class CDW_Media_Controller extends CDW_Base_Controller {
	/**
	 * Registers the /media REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/media',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_media' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'args'                => array(
					'per_page' => array(
						'type'    => 'integer',
						'default' => 10,
						'minimum' => 1,
						'maximum' => 50,
					),
				),
			)
		);
	}

	/**
	 * Returns recent media items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_media( WP_REST_Request $request ) {
		$per_page  = (int) $request->get_param( 'per_page' );
		$cache_key = 'cdw_media_cache_' . $per_page;
		$media     = $this->get_transient_with_cache(
			$cache_key,
			function () use ( $per_page ) {
				$args  = array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => $per_page,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);
				$query = new WP_Query( $args );
				$items = array();

				if ( $query->have_posts() ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						$items[] = array(
							'id'    => get_the_ID(),
							'title' => get_the_title(),
							'url'   => wp_get_attachment_url( get_the_ID() ),
							'date'  => get_the_date( 'c' ),
						);
					}
					wp_reset_postdata();
				}

				return $items;
			},
			300
		);

		return rest_ensure_response( $media );
	}
}

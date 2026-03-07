<?php
/**
 * Users REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Handles GET /cdw/v1/users — returns a summary user list for the admin interface.
 *
 * @package CDW
 */
class CDW_Users_Controller extends CDW_Base_Controller {
	/**
	 * Registers the /users REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/users',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_users' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Returns a list of users with basic profile data.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_users() {
		$rate_check = $this->check_rate_limit( 'users_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$users     = get_users( array( 'number' => 200 ) );
		$formatted = array();

		foreach ( $users as $user ) {
			$formatted[] = array(
				'id'           => $user->ID,
				'username'     => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
			);
		}

		return rest_ensure_response( $formatted );
	}
}

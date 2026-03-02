<?php
/**
 * Base REST controller class.
 *
 * All CDW controllers extend this class to inherit shared helpers.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for all CDW REST API controllers.
 *
 * Provides shared helpers for permission checks, standard JSON responses,
 * transient cache management, and the protected-option list.
 *
 * @package CDW
 */
abstract class CDW_Base_Controller {
	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'cdw/v1';

	/**
	 * WordPress option names that may not be modified or deleted via the CLI.
	 *
	 * @var string[]
	 */
	public static $protected_options = array(
		'siteurl',
		'home',
		'admin_email',
		'blogname',
		'blogdescription',
		'wp_user_roles',
		'active_plugins',
		'template',
		'stylesheet',
		'auth_key',
		'secure_auth_key',
		'logged_in_key',
		'nonce_key',
		'auth_salt',
		'secure_auth_salt',
		'logged_in_salt',
		'nonce_salt',
		'db_version',
		'initial_db_version',
		'wordpress_db_version',
		'cron',
		'sidebars_widgets',
		'widget_block',
		'widget_pages',
		'widget_calendar',
		'widget_archives',
		'widget_meta',
		'widget_search',
		'widget_recent-posts',
		'widget_recent-comments',
		'widget_rss',
		'widget_tag_cloud',
		'widget_nav_menu',
		'widget_text',
		'widget_categories',
		'users_can_register',
		'default_role',
	);

	/**
	 * Returns true when the current user can read (subscriber level or higher).
	 *
	 * @return bool
	 */
	public function check_read_permission() {
		return current_user_can( 'read' );
	}

	/**
	 * Returns true when the current user has manage_options capability.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns true when the given option name is in the protected list.
	 *
	 * @param string $option_name Option name to check.
	 * @return bool
	 */
	protected function is_option_protected( $option_name ) {
		return in_array( $option_name, self::$protected_options, true );
	}

	/**
	 * Wraps $data in a standard {success:true, data:...} REST response.
	 *
	 * @param mixed $data   Response payload.
	 * @param int   $status HTTP status code (default 200).
	 * @return WP_REST_Response
	 */
	protected function success_response( $data, $status = 200 ) {
		$response = rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
		$response->set_status( $status );
		return $response;
	}

	/**
	 * Returns a WP_Error with a cdw_error code and given message/status.
	 *
	 * @param string $message Human-readable error message.
	 * @param int    $status  HTTP status code (default 400).
	 * @return WP_Error
	 */
	protected function error_response( $message, $status = 400 ) {
		return new WP_Error(
			'cdw_error',
			$message,
			array( 'status' => $status )
		);
	}

	/**
	 * Returns cached data from a transient or calls $callback and stores the result.
	 *
	 * @param string   $transient_name Transient key.
	 * @param callable $callback       Produces the value when the cache is empty.
	 * @param int      $expiration     Cache TTL in seconds (default 300).
	 * @return mixed
	 */
	protected function get_transient_with_cache( $transient_name, $callback, $expiration = 300 ) {
		$cached = get_transient( $transient_name );
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $callback();
		set_transient( $transient_name, $data, $expiration );
		return $data;
	}

	/**
	 * Deletes all transients (value + timeout) whose key starts with $prefix.
	 *
	 * @param string $prefix Transient key prefix, e.g. '_transient_cdw_posts_cache_'.
	 * @return void
	 */
	protected function delete_transients_by_prefix( $prefix ) {
		global $wpdb;

		// Derive the timeout prefix: _transient_foo_ -> _transient_timeout_foo_.
		$timeout_prefix = str_replace( '_transient_', '_transient_timeout_', $prefix );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $timeout_prefix ) . '%'
			)
		);
	}
}

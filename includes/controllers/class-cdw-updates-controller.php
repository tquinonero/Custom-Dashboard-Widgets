<?php
/**
 * Updates REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Handles GET /cdw/v1/updates — aggregates available core, plugin, and theme updates.
 *
 * @package CDW
 */
class CDW_Updates_Controller extends CDW_Base_Controller {
	/**
	 * Registers the /updates REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/updates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_updates' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Returns available updates for core, plugins, and themes.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_updates() {
		$updates = array(
			'core'    => $this->get_core_updates(),
			'plugins' => $this->get_plugin_updates(),
			'themes'  => $this->get_theme_updates(),
		);

		return rest_ensure_response( $updates );
	}

	/**
	 * Returns core update info: count and whether any update is available.
	 *
	 * @return array{count: int, available: bool}
	 */
	private function get_core_updates() {
		$updates    = wp_get_update_data();
		$core_count = isset( $updates['counts']['wordpress'] ) ? (int) $updates['counts']['wordpress'] : 0;
		return array(
			'count'     => $core_count,
			'available' => $core_count > 0,
		);
	}

	/**
	 * Returns an array of plugins that have available updates.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_plugin_updates() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$updates = get_site_transient( 'update_plugins' );
		$plugins = get_plugins();
		$upgrade = array();

		if ( ! empty( $updates->response ) ) {
			foreach ( $updates->response as $plugin_file => $plugin_data ) {
				$plugin_name = isset( $plugins[ $plugin_file ]['Name'] )
					? $plugins[ $plugin_file ]['Name']
					: dirname( $plugin_file );
				$upgrade[]   = array(
					'file'        => $plugin_file,
					'name'        => $plugin_name,
					'version'     => isset( $plugins[ $plugin_file ]['Version'] ) ? $plugins[ $plugin_file ]['Version'] : '',
					'new_version' => $plugin_data->new_version,
				);
			}
		}

		return $upgrade;
	}

	/**
	 * Returns an array of themes that have available updates.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_theme_updates() {
		$updates = get_site_transient( 'update_themes' );
		$themes  = wp_get_themes();
		$upgrade = array();

		if ( ! empty( $updates->response ) ) {
			foreach ( $updates->response as $theme_slug => $theme_data ) {
				$theme = isset( $themes[ $theme_slug ] ) ? $themes[ $theme_slug ] : null;
				if ( $theme ) {
					$upgrade[] = array(
						'slug'        => $theme_slug,
						'name'        => $theme->get( 'Name' ),
						'version'     => $theme->get( 'Version' ),
						'new_version' => $theme_data['new_version'],
					);
				}
			}
		}

		return $upgrade;
	}
}

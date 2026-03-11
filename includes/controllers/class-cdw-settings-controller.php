<?php
/**
 * Settings REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

/**
 * Handles GET and POST /cdw/v1/settings.
 *
 * @package CDW
 */
class CDW_Settings_Controller extends CDW_Base_Controller {
	/**
	 * Registers the GET and POST /settings REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'settings' => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Returns all CDW plugin settings.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_settings() {
		$email                   = get_option( 'cdw_support_email', get_option( 'custom_dashboard_widget_email', '' ) );
		$docs_url                = get_option( 'cdw_docs_url', get_option( 'custom_dashboard_widget_docs_url', '' ) );
		$font_size               = get_option( 'cdw_font_size', get_option( 'custom_dashboard_widget_font_size', '' ) );
		$bg_color                = get_option( 'cdw_bg_color', get_option( 'custom_dashboard_widget_background_color', '' ) );
		$header_bg_color         = get_option( 'cdw_header_bg_color', get_option( 'custom_dashboard_widget_header_background_color', '' ) );
		$header_text_color       = get_option( 'cdw_header_text_color', get_option( 'custom_dashboard_widget_header_text_color', '' ) );
		$cli_enabled             = get_option( 'cdw_cli_enabled', true );
		$floating_enabled        = get_option( 'cdw_floating_enabled', true );
		$remove_default_widgets  = get_option( 'cdw_remove_default_widgets', true );
		$delete_on_uninstall     = get_option( 'cdw_delete_on_uninstall', true );
		$ai_enabled              = get_option( 'cdw_ai_enabled', false );
		$ai_execution_mode       = get_option( 'cdw_ai_execution_mode', 'confirm' );
		$ai_custom_system_prompt = get_option( 'cdw_ai_custom_system_prompt', '' );
		$mcp_public              = get_option( 'cdw_mcp_public', false );
		$user_type               = get_option( 'cdw_user_type', null );

		return rest_ensure_response(
			array(
				'email'                   => $email,
				'docs_url'                => $docs_url,
				'font_size'               => $font_size,
				'bg_color'                => $bg_color,
				'header_bg_color'         => $header_bg_color,
				'header_text_color'       => $header_text_color,
				'cli_enabled'             => $cli_enabled,
				'floating_enabled'        => $floating_enabled,
				'remove_default_widgets'  => $remove_default_widgets,
				'delete_on_uninstall'     => $delete_on_uninstall,
				'ai_enabled'              => $ai_enabled,
				'ai_execution_mode'       => $ai_execution_mode,
				'ai_custom_system_prompt' => $ai_custom_system_prompt,
				'mcp_public'              => (bool) $mcp_public,
				'user_type'               => $user_type,
			)
		);
	}

	/**
	 * Validates and persists submitted settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'settings_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$settings = $request->get_json_params();

		if ( isset( $settings['email'] ) ) {
			$email = sanitize_email( $settings['email'] );
			if ( ! empty( $email ) && ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', 'Invalid email address', array( 'status' => 400 ) );
			}
			update_option( 'cdw_support_email', $email, false );
			update_option( 'custom_dashboard_widget_email', $email );
		}

		if ( isset( $settings['docs_url'] ) ) {
			$url = esc_url_raw( $settings['docs_url'] );
			if ( ! empty( $url ) && ! preg_match( '#^https?://#i', $url ) ) {
				$url = '';
			}
			update_option( 'cdw_docs_url', $url, false );
			update_option( 'custom_dashboard_widget_docs_url', $url );
		}

		if ( isset( $settings['font_size'] ) ) {
			$size = sanitize_text_field( $settings['font_size'] );
			update_option( 'cdw_font_size', $size, false );
			update_option( 'custom_dashboard_widget_font_size', $size );
		}

		if ( isset( $settings['bg_color'] ) ) {
			$color = sanitize_hex_color( $settings['bg_color'] );
			update_option( 'cdw_bg_color', $color, false );
			update_option( 'custom_dashboard_widget_background_color', $color );
		}

		if ( isset( $settings['header_bg_color'] ) ) {
			$color = sanitize_hex_color( $settings['header_bg_color'] );
			update_option( 'cdw_header_bg_color', $color, false );
			update_option( 'custom_dashboard_widget_header_background_color', $color );
		}

		if ( isset( $settings['header_text_color'] ) ) {
			$color = sanitize_hex_color( $settings['header_text_color'] );
			update_option( 'cdw_header_text_color', $color, false );
			update_option( 'custom_dashboard_widget_header_text_color', $color );
		}

		if ( isset( $settings['cli_enabled'] ) ) {
			update_option( 'cdw_cli_enabled', (bool) $settings['cli_enabled'], false );
		}

		if ( isset( $settings['floating_enabled'] ) ) {
			update_option( 'cdw_floating_enabled', (bool) $settings['floating_enabled'], false );
		}

		if ( isset( $settings['remove_default_widgets'] ) ) {
			update_option( 'cdw_remove_default_widgets', (bool) $settings['remove_default_widgets'], false );
		}

		if ( isset( $settings['delete_on_uninstall'] ) ) {
			update_option( 'cdw_delete_on_uninstall', (bool) $settings['delete_on_uninstall'], false );
		}

		if ( isset( $settings['ai_enabled'] ) ) {
			update_option( 'cdw_ai_enabled', (bool) $settings['ai_enabled'], false );
		}

		if ( isset( $settings['ai_execution_mode'] ) ) {
			$mode = sanitize_text_field( $settings['ai_execution_mode'] );
			if ( in_array( $mode, array( 'auto', 'confirm' ), true ) ) {
				update_option( 'cdw_ai_execution_mode', $mode, false );
			}
		}

		if ( isset( $settings['ai_custom_system_prompt'] ) ) {
			update_option( 'cdw_ai_custom_system_prompt', sanitize_textarea_field( $settings['ai_custom_system_prompt'] ), false );
		}

		if ( isset( $settings['mcp_public'] ) ) {
			update_option( 'cdw_mcp_public', (bool) $settings['mcp_public'], false );
		}

		if ( isset( $settings['user_type'] ) ) {
			$user_type = sanitize_text_field( $settings['user_type'] );
			if ( in_array( $user_type, array( 'developer', 'user' ), true ) ) {
				update_option( 'cdw_user_type', $user_type, false );
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}

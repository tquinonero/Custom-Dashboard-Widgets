<?php
/**
 * Main class for CDW Abilities Explorer.
 *
 * Registers the admin page and handles AJAX requests.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abilities Explorer main class.
 *
 * @package CDW
 */
class CDW_Abilities_Explorer {

	/**
	 * Initializes the explorer.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'admin_menu', array( static::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue_assets' ) );
		add_action( 'wp_ajax_cdw_ability_explorer_invoke', array( static::class, 'handle_ajax_invoke' ) );
	}

	/**
	 * Registers the admin menu page.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_management_page(
			__( 'CDW Abilities Explorer', 'cdw' ),
			__( 'Abilities Explorer', 'cdw' ),
			'manage_options',
			'cdw-abilities-explorer',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueues assets on the explorer page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ) {
		if ( 'tools_page_cdw-abilities-explorer' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'cdw-explorer',
			CDW_PLUGIN_URL . 'includes/abilities/explorer/explorer.css',
			array(),
			CDW_VERSION
		);
	}

	/**
	 * Renders the admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cdw' ) );
		}

		CDW_Abilities_Admin_Page::render();
	}

	/**
	 * Handles AJAX requests to invoke abilities.
	 *
	 * @return void
	 */
	public static function handle_ajax_invoke() {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		check_ajax_referer( 'cdw_ability_explorer_invoke', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'cdw' ),
				),
				403
			);
		}

		$ability_name = isset( $_POST['ability_name'] ) ? sanitize_text_field( wp_unslash( $_POST['ability_name'] ) ) : '';
		// Input is JSON string - sanitized via json_decode, not text sanitization.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$input_json = isset( $_POST['input'] ) ? wp_unslash( $_POST['input'] ) : '{}';

		if ( empty( $ability_name ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Ability name is required.', 'cdw' ),
				),
				400
			);
		}

		$ability = CDW_Ability_Handler::get_ability( $ability_name );

		if ( ! $ability ) {
			wp_send_json_error(
				array(
					'message' => __( 'Ability not found.', 'cdw' ),
				),
				404
			);
		}

		$input = array();
		if ( $input_json ) {
			$input = json_decode( $input_json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: JSON error message */
							__( 'Invalid JSON: %s', 'cdw' ),
							json_last_error_msg()
						),
					),
					400
				);
			}
		}

		$validation = CDW_Ability_Handler::validate_input( $ability['input_schema'] ?? null, $input );

		if ( ! $validation['valid'] ) {
			wp_send_json_error(
				array(
					'message' => implode( "\n", $validation['errors'] ),
				),
				400
			);
		}

		$result = CDW_Ability_Handler::invoke_ability( $ability_name, $input );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] );
		} else {
			wp_send_json_error(
				array(
					'message' => $result['error'] ?? __( 'Unknown error occurred.', 'cdw' ),
				),
				500
			);
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	}
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

class CDW_Settings_Controller extends CDW_Base_Controller {
    public function register_routes() {
        register_rest_route( $this->namespace, '/settings', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_settings' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/settings', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'save_settings' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    public function get_settings() {
        $email             = get_option( 'cdw_support_email', get_option( 'custom_dashboard_widget_email', '' ) );
        $docs_url          = get_option( 'cdw_docs_url', get_option( 'custom_dashboard_widget_docs_url', '' ) );
        $font_size         = get_option( 'cdw_font_size', get_option( 'custom_dashboard_widget_font_size', '' ) );
        $bg_color          = get_option( 'cdw_bg_color', get_option( 'custom_dashboard_widget_background_color', '' ) );
        $header_bg_color   = get_option( 'cdw_header_bg_color', get_option( 'custom_dashboard_widget_header_background_color', '' ) );
        $header_text_color = get_option( 'cdw_header_text_color', get_option( 'custom_dashboard_widget_header_text_color', '' ) );
        $cli_enabled            = get_option( 'cdw_cli_enabled', true );
        $remove_default_widgets = get_option( 'cdw_remove_default_widgets', true );
        $delete_on_uninstall    = get_option( 'cdw_delete_on_uninstall', true );

        return rest_ensure_response( array(
            'email'                  => $email,
            'docs_url'               => $docs_url,
            'font_size'              => $font_size,
            'bg_color'               => $bg_color,
            'header_bg_color'        => $header_bg_color,
            'header_text_color'      => $header_text_color,
            'cli_enabled'            => $cli_enabled,
            'remove_default_widgets' => $remove_default_widgets,
            'delete_on_uninstall'    => $delete_on_uninstall,
        ) );
    }

    public function save_settings( WP_REST_Request $request ) {
        $settings = $request->get_json_params();

        if ( ! is_array( $settings ) ) {
            return new WP_Error( 'invalid_data', 'Invalid settings data', array( 'status' => 400 ) );
        }

        if ( isset( $settings['email'] ) ) {
            $email = sanitize_email( $settings['email'] );
            if ( ! empty( $email ) && ! is_email( $email ) ) {
                return new WP_Error( 'invalid_email', 'Invalid email address', array( 'status' => 400 ) );
            }
            update_option( 'cdw_support_email', $email );
            update_option( 'custom_dashboard_widget_email', $email );
        }

        if ( isset( $settings['docs_url'] ) ) {
            $url = esc_url_raw( $settings['docs_url'] );
            if ( ! empty( $url ) && ! preg_match( '#^https?://#i', $url ) ) {
                $url = '';
            }
            update_option( 'cdw_docs_url', $url );
            update_option( 'custom_dashboard_widget_docs_url', $url );
        }

        if ( isset( $settings['font_size'] ) ) {
            $size = sanitize_text_field( $settings['font_size'] );
            update_option( 'cdw_font_size', $size );
            update_option( 'custom_dashboard_widget_font_size', $size );
        }

        if ( isset( $settings['bg_color'] ) ) {
            $color = sanitize_hex_color( $settings['bg_color'] );
            update_option( 'cdw_bg_color', $color );
            update_option( 'custom_dashboard_widget_background_color', $color );
        }

        if ( isset( $settings['header_bg_color'] ) ) {
            $color = sanitize_hex_color( $settings['header_bg_color'] );
            update_option( 'cdw_header_bg_color', $color );
            update_option( 'custom_dashboard_widget_header_background_color', $color );
        }

        if ( isset( $settings['header_text_color'] ) ) {
            $color = sanitize_hex_color( $settings['header_text_color'] );
            update_option( 'cdw_header_text_color', $color );
            update_option( 'custom_dashboard_widget_header_text_color', $color );
        }

        if ( isset( $settings['cli_enabled'] ) ) {
            update_option( 'cdw_cli_enabled', (bool) $settings['cli_enabled'] );
        }

        if ( isset( $settings['remove_default_widgets'] ) ) {
            update_option( 'cdw_remove_default_widgets', (bool) $settings['remove_default_widgets'] );
        }

        if ( isset( $settings['delete_on_uninstall'] ) ) {
            update_option( 'cdw_delete_on_uninstall', (bool) $settings['delete_on_uninstall'] );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }
}

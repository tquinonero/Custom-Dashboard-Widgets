<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CDW_PLUGIN_DIR . 'includes/class-cdw-rest-api.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-widgets.php';
require_once CDW_PLUGIN_DIR . 'includes/cli/class-cdw-cli-command.php';

class CDW_Loader {
    private $rest_api;
    private $widgets;

    public function run() {
        // REST API routes must be registered unconditionally: REST_REQUEST is
        // not yet defined at plugins_loaded (it is set later at parse_request),
        // so any runtime check here would incorrectly skip registration.
        $this->rest_api = new CDW_REST_API();
        $this->rest_api->register();

        if ( is_admin() ) {
            $this->widgets = new CDW_Widgets();
            $this->widgets->register();

            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        }

        // Cache hooks must fire on all contexts (REST API saves, CLI, admin).
        add_action( 'save_post',       array( $this, 'clear_content_cache' ) );
        add_action( 'delete_post',     array( $this, 'clear_content_cache' ) );
        add_action( 'add_attachment',  array( $this, 'clear_content_cache' ) );
        add_action( 'edit_attachment', array( $this, 'clear_content_cache' ) );
    }

    public function clear_content_cache() {
        delete_transient( 'cdw_stats_cache' );
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s
             OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_cdw_posts_cache_' ) . '%',
            $wpdb->esc_like( '_transient_cdw_media_cache_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_cdw_posts_cache_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_cdw_media_cache_' ) . '%'
        ) );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( ! in_array( $hook_suffix, array( 'index.php', 'settings_page_cdw-settings' ), true ) ) {
            return;
        }

        $asset_file = CDW_PLUGIN_DIR . 'build/index.asset.php';
        $js_file = CDW_PLUGIN_DIR . 'build/index.js';

        if ( ! file_exists( $asset_file ) || ! file_exists( $js_file ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>CDW Plugin:</strong> Build files not found. Please run <code>npm install && npm run build</code>.</p>';
                echo '</div>';
            } );
            return;
        }

        $asset = require $asset_file;
        $dependencies = array_merge( $asset['dependencies'], array( 'wp-api-fetch' ) );
        wp_enqueue_script(
            'cdw-script',
            CDW_PLUGIN_URL . 'build/index.js',
            $dependencies,
            $asset['version'],
            true
        );
        wp_enqueue_style(
            'cdw-style',
            CDW_PLUGIN_URL . 'build/index.css',
            array(),
            $asset['version']
        );

        $font_size         = get_option( 'cdw_font_size', '' );
        $bg_color          = get_option( 'cdw_bg_color', '' );
        $header_bg_color   = get_option( 'cdw_header_bg_color', '' );
        $header_text_color = get_option( 'cdw_header_text_color', '' );

        $css = '';
        if ( is_numeric( $font_size ) && (int) $font_size > 0 ) {
            $css .= '.cdw-widget { font-size: ' . (int) $font_size . 'px; }' . "\n";
        }
        if ( ! empty( $bg_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $bg_color ) ) {
            $css .= '.cdw-widget { background-color: ' . esc_attr( $bg_color ) . '; }' . "\n";
        }
        if ( ! empty( $header_bg_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $header_bg_color ) ) {
            $css .= '.cdw-widget .cdw-widget-header, .postbox .hndle { background: ' . esc_attr( $header_bg_color ) . ' !important; background-image: none !important; }' . "\n";
        }
        if ( ! empty( $header_text_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $header_text_color ) ) {
            $css .= '.cdw-widget .cdw-widget-header, .postbox .hndle { color: ' . esc_attr( $header_text_color ) . ' !important; }' . "\n";
        }
        if ( ! empty( $css ) ) {
            wp_add_inline_style( 'cdw-style', $css );
        }

        $is_settings_page = 'settings_page_cdw-settings' === $hook_suffix;

        wp_localize_script(
            'cdw-script',
            'cdwData',
            array(
                'root'         => esc_url_raw( rest_url() ),
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'pluginUrl'    => CDW_PLUGIN_URL,
                'adminUrl'     => admin_url(),
                'isSettings'   => $is_settings_page,
            )
        );
    }
}

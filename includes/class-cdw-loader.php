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
        $this->rest_api = new CDW_REST_API();
        $this->rest_api->register();

        $this->widgets = new CDW_Widgets();
        $this->widgets->register();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

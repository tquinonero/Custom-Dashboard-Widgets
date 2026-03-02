<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

class CDW_Updates_Controller extends CDW_Base_Controller {
    public function register_routes() {
        register_rest_route( $this->namespace, '/updates', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_updates' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    public function get_updates() {
        $updates = array(
            'core'    => $this->get_core_updates(),
            'plugins' => $this->get_plugin_updates(),
            'themes'  => $this->get_theme_updates(),
        );

        return rest_ensure_response( $updates );
    }

    private function get_core_updates() {
        $updates = wp_get_update_data();
        return array(
            'count' => $updates['counts']['total'],
            'available' => $updates['update'],
        );
    }

    private function get_plugin_updates() {
        $updates = get_site_transient( 'update_plugins' );
        $plugins = get_plugins();
        $upgrade = array();

        if ( ! empty( $updates->response ) ) {
            foreach ( $updates->response as $plugin_file => $plugin_data ) {
                $plugin_name = isset( $plugins[ $plugin_file ]['Name'] )
                    ? $plugins[ $plugin_file ]['Name']
                    : dirname( $plugin_file );
                $upgrade[] = array(
                    'file'    => $plugin_file,
                    'name'    => $plugin_name,
                    'version' => $plugins[ $plugin_file ]['Version'],
                    'new_version' => $plugin_data->new_version,
                );
            }
        }

        return $upgrade;
    }

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

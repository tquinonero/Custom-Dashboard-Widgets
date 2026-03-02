<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class CDW_Base_Controller {
    protected $namespace = 'cdw/v1';

    protected $protected_options = array(
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
    );

    public function check_read_permission() {
        return current_user_can( 'read' );
    }

    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    protected function is_option_protected( $option_name ) {
        return in_array( $option_name, $this->protected_options, true );
    }

    protected function success_response( $data, $status = 200 ) {
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $data,
        ), $status );
    }

    protected function error_response( $message, $status = 400 ) {
        return new WP_Error(
            'cdw_error',
            $message,
            array( 'status' => $status )
        );
    }

    protected function get_transient_with_cache( $transient_name, $callback, $expiration = 300 ) {
        $cached = get_transient( $transient_name );
        if ( false !== $cached ) {
            return $cached;
        }

        $data = $callback();
        set_transient( $transient_name, $data, $expiration );
        return $data;
    }

    protected function delete_transients_by_prefix( $prefix ) {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( $prefix ) . '%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( $prefix . '_timeout_' ) . '%'
            )
        );
    }
}

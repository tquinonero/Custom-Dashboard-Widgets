<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';

class CDW_Users_Controller extends CDW_Base_Controller {
    public function register_routes() {
        register_rest_route( $this->namespace, '/users', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_users' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );
    }

    public function get_users() {
        $users = get_users();
        $formatted = array();

        foreach ( $users as $user ) {
            $formatted[] = array(
                'id'         => $user->ID,
                'username'   => $user->user_login,
                'email'      => $user->user_email,
                'display_name' => $user->display_name,
                'roles'      => $user->roles,
            );
        }

        return rest_ensure_response( $formatted );
    }
}

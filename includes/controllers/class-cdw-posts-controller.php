<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CDW_Posts_Controller extends CDW_Base_Controller {
    public function register_routes() {
        register_rest_route( $this->namespace, '/posts', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_posts' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
            'args'                => array(
                'per_page' => array(
                    'type'    => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ),
                'status'   => array(
                    'type'    => 'string',
                    'default' => 'publish',
                ),
                'post_type' => array(
                    'type'    => 'string',
                    'default' => 'post',
                ),
            ),
        ) );
    }

    public function get_posts( WP_REST_Request $request ) {
        $per_page  = $request->get_param( 'per_page' ) ?: 10;
        $status    = $request->get_param( 'status' ) ?: 'publish';
        $post_type = $request->get_param( 'post_type' ) ?: 'post';

        $cache_key = "cdw_posts_cache_{$per_page}_{$status}_{$post_type}";
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $posts = get_posts( array(
            'numberposts'      => $per_page,
            'post_status'      => $status,
            'post_type'        => $post_type,
            'suppress_filters' => true,
        ) );

        $formatted = array();
        foreach ( $posts as $post ) {
            $formatted[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'status'    => $post->post_status,
                'date'      => $post->post_date,
                'author'    => $post->post_author,
                'permalink' => get_permalink( $post->ID ),
            );
        }

        set_transient( $cache_key, $formatted, 300 );

        return rest_ensure_response( $formatted );
    }
}

<?php
/**
 * Stub for wp-admin/includes/file.php
 * Defines filesystem-related functions used in unit tests.
 */

if ( ! function_exists( 'request_filesystem_credentials' ) ) {
    function request_filesystem_credentials( $form_post, $type = '', $error = false, $context = false, $extra_fields = null, $allow_relaxed_file_ownership = false ) {
        return array( 'hostname' => '', 'username' => '', 'password' => '', 'connection_type' => 'direct' );
    }
}

if ( ! function_exists( 'WP_Filesystem' ) ) {
    function WP_Filesystem( $args = false, $context = false, $allow_relaxed_file_ownership = false ) {
        return true;
    }
}

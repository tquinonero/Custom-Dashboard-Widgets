<?php
/**
 * Stub for wp-admin/includes/plugin-install.php
 * plugins_api() is conditionally defined so Brain\Monkey mocks take priority.
 */

if ( ! function_exists( 'plugins_api' ) ) {
    function plugins_api( $action, $args = array() ) {
        $obj                = new \stdClass();
        $obj->download_link = 'https://downloads.wordpress.org/plugin/fake.zip';
        return $obj;
    }
}

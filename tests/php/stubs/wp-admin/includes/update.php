<?php
/**
 * Stub for wp-admin/includes/update.php
 * Declares get_core_updates() so the require_once in handle_core_command() succeeds.
 * Brain\Monkey / Patchwork will replace the implementation in individual tests.
 */

if ( ! function_exists( 'get_core_updates' ) ) {
	function get_core_updates() {
		return array();
	}
}

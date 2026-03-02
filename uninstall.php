<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This file should be at the root of your plugin.
 *
 * - Two methods exist for uninstalling: `register_uninstall_hook()`
 *   and `uninstall.php`. See the documentation below for choosing.
 *
 * - For `register_uninstall_hook()`, the plugin should have a
 *   function attached to this hook to perform cleanup.
 *
 * - For `uninstall.php`, all cleanup tasks should be performed
 *   directly in that file.
 *
 * @link    https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 * @package CDW
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/functions-uninstall.php';
cdw_do_uninstall();

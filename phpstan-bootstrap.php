<?php
/**
 * PHPStan bootstrap file — defines plugin-specific constants so static analysis
 * can resolve them without loading WordPress.
 *
 * This file is only loaded during PHPStan analysis; it is never shipped with the
 * plugin release (see .distignore).
 *
 * @package CDW
 */

// Plugin-specific constants defined in CDW.php at runtime.
if ( ! defined( 'CDW_PLUGIN_DIR' ) ) {
	define( 'CDW_PLUGIN_DIR', __DIR__ . '/' );
}
if ( ! defined( 'CDW_PLUGIN_URL' ) ) {
	define( 'CDW_PLUGIN_URL', 'http://example.com/wp-content/plugins/cdw/' );
}
if ( ! defined( 'CDW_VERSION' ) ) {
	define( 'CDW_VERSION', '3.0.0' );
}
if ( ! defined( 'CDW_MIN_PHP' ) ) {
	define( 'CDW_MIN_PHP', '8.0' );
}
if ( ! defined( 'CDW_MIN_WP' ) ) {
	define( 'CDW_MIN_WP', '6.0' );
}

// WordPress core constants that the WP stubs may not define.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ . '/../../' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'cdw/CDW.php' );
}

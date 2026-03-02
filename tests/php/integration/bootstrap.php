<?php
/**
 * Bootstrap for CDW integration tests.
 *
 * Requires a running WordPress test environment.
 * Set the WP_PHPUNIT__TESTS_CONFIG env variable (or define it below) to point
 * to your wp-tests-config.php file.
 *
 * In DDEV run:
 *   WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-content/plugins/CDW/wp-tests-config.php \
 *   vendor/bin/phpunit --config phpunit-integration.xml
 *
 * Or simply:
 *   ddev exec bash -c 'export WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-content/plugins/CDW/wp-tests-config.php && vendor/bin/phpunit --config phpunit-integration.xml'
 */

// Provide the config path if not already set in the environment.
if ( false === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) || '' === getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
    putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . dirname( __DIR__, 3 ) . '/wp-tests-config.php' );
}

$_tests_dir = dirname( __DIR__, 3 ) . '/vendor/wp-phpunit/wp-phpunit';

// PHPUnit Polyfills — required by the WP Core test bootstrap.
// Point to the installed Composer package so the bootstrap can load them.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define(
        'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
        dirname( __DIR__, 3 ) . '/vendor/yoast/phpunit-polyfills'
    );
}

// Load the WP test helper functions so tests_add_filter() is available.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the CDW plugin during the muplugins_loaded action — exactly as WordPress
 * would load a plugin from disk — so every hook registered in CDW.php fires.
 */
tests_add_filter(
    'muplugins_loaded',
    function () {
        $plugin_dir = dirname( __DIR__, 3 ) . '/';

        if ( ! defined( 'CDW_PLUGIN_DIR' ) ) {
            define( 'CDW_PLUGIN_DIR', $plugin_dir );
        }
        if ( ! defined( 'CDW_PLUGIN_URL' ) ) {
            define( 'CDW_PLUGIN_URL', 'http://example.com/wp-content/plugins/CDW/' );
        }
        if ( ! defined( 'CDW_VERSION' ) ) {
            define( 'CDW_VERSION', '3.0.0' );
        }

        require $plugin_dir . 'CDW.php';
    }
);

// Bootstrap WordPress — installs tables into the test DB and sets up the env.
require $_tests_dir . '/includes/bootstrap.php';

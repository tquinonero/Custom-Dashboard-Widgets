<?php
/**
 * WordPress test configuration — sample file.
 *
 * Copy this file to wp-tests-config.php and fill in your local DB credentials.
 * wp-tests-config.php is git-ignored and must never be committed.
 *
 * Usage:
 *   cp wp-tests-config-sample.php wp-tests-config.php
 *   # edit wp-tests-config.php with your local database credentials
 *
 * Run integration tests (DDEV):
 *   ddev exec bash -c 'export WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-content/plugins/CDW/wp-tests-config.php \
 *     && cd /var/www/html/wp-content/plugins/CDW \
 *     && vendor/bin/phpunit --config phpunit-integration.xml'
 *
 * Or via npm script:
 *   ddev exec bash -c 'export WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-content/plugins/CDW/wp-tests-config.php \
 *     && npm run test:integration'
 */

// ---------------------------------------------------------------------------
// Database credentials for the WordPress test environment.
// The test runner will DROP and recreate tables on each run — use a dedicated
// test database, never your production or development database.
// ---------------------------------------------------------------------------

define( 'ABSPATH',    '/path/to/wordpress/' ); // Absolute path to WordPress root (with trailing slash).
define( 'DB_NAME',    'wordpress_test' );       // Test database name.
define( 'DB_USER',    'root' );                 // Database user.
define( 'DB_PASSWORD', '' );                    // Database password.
define( 'DB_HOST',    '127.0.0.1' );            // Database host. Use 'db' inside DDEV.
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // Prefix for test tables (must differ from your real table prefix).

// ---------------------------------------------------------------------------
// WordPress test environment settings.
// ---------------------------------------------------------------------------

define( 'WP_TESTS_DOMAIN',  'example.com' );
define( 'WP_TESTS_EMAIL',   'admin@example.com' );
define( 'WP_TESTS_TITLE',   'CDW Test Site' );
define( 'WP_DEFAULT_THEME', 'twentytwentyfive' );
define( 'WP_PHP_BINARY',    'php' );
define( 'WPLANG',           '' );

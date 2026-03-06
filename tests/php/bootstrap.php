<?php
/**
 * Bootstrap for CDW unit tests.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load Patchwork early — before any test class files are required — so that
// when the source files (e.g. class-cdw-cli-service.php) are later included
// via require_once at the top of each test file, Patchwork's stream wrapper
// can preprocess them and insert call-rerouting for both user-defined
// functions (is_wp_error, etc.) and redefinable internals (file_exists, etc.).
require_once __DIR__ . '/../../vendor/antecedent/patchwork/Patchwork.php';

define( 'ABSPATH',        __DIR__ . '/stubs/' );
define( 'CDW_PLUGIN_DIR',  dirname( __DIR__, 2 ) . '/' );
define( 'CDW_PLUGIN_URL', 'http://example.com/wp-content/plugins/CDW/' );
define( 'CDW_VERSION',    '3.0.0' );
define( 'WP_CONTENT_DIR', __DIR__ . '/stubs/wp-content' );
define( 'WP_PLUGIN_DIR',  __DIR__ . '/stubs/plugins' );

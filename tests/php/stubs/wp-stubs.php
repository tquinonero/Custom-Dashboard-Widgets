<?php
/**
 * Minimal WordPress class/function stubs for unit tests.
 * Guard every declaration so this file can be safely included multiple times.
 */

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();

        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( '' !== $code ) {
                $this->errors[ $code ][] = $message;
                if ( '' !== $data ) {
                    $this->error_data[ $code ] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys( $this->errors );
            return reset( $codes );
        }

        public function get_error_message( $code = '' ) {
            if ( '' === $code ) {
                $code = $this->get_error_code();
            }
            return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
        }

        public function get_error_data( $code = '' ) {
            if ( '' === $code ) {
                $code = $this->get_error_code();
            }
            return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
        }

        public function has_errors() {
            return ! empty( $this->errors );
        }

        public function add( $code, $message, $data = '' ) {
            $this->errors[ $code ][] = $message;
            if ( '' !== $data ) {
                $this->error_data[ $code ] = $data;
            }
        }
    }
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
    class WP_REST_Response {
        public $data;
        public $status;
        public $headers = array();

        public function __construct( $data = null, $status = 200 ) {
            $this->data   = $data;
            $this->status = $status;
        }

        public function set_status( $status ) {
            $this->status = (int) $status;
        }

        public function get_status() {
            return $this->status;
        }

        public function get_data() {
            return $this->data;
        }
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $params  = array();
        private $json    = null;
        private $body    = null;

        public function __construct( $method = 'GET', $route = '', $attributes = array() ) {}

        public function get_param( $key ) {
            return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
        }

        public function set_param( $key, $value ) {
            $this->params[ $key ] = $value;
        }

        public function get_json_params() {
            return $this->json;
        }

        public function set_json_params( $json ) {
            $this->json = $json;
        }

        public function get_body() {
            return $this->body;
        }
    }
}

if ( ! class_exists( 'WP_Theme' ) ) {
    class WP_Theme {
        private $data;
        public function __construct( array $data = array() ) { $this->data = $data; }
        public function get( $key ) { return isset( $this->data[ $key ] ) ? $this->data[ $key ] : ''; }
    }
}

/**
 * Minimal wpdb stub.
 */
if ( ! class_exists( 'wpdb' ) ) {
    class wpdb {
        public $options  = 'wp_options';
        public $usermeta = 'wp_usermeta';
        public $prefix   = 'wp_';
        public $queries  = array();

        public function prepare( $query, ...$args ) {
            // Very simplified — just for test assertions.
            return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
        }

        public function query( $sql ) {
            $this->queries[] = $sql;
            return 1;
        }

        public function get_var( $sql ) {
            return null;
        }

        public function get_results( $sql, $output = OBJECT ) {
            return array();
        }

        public function insert( $table, $data, $format = null ) {
            return 1;
        }

        public function delete( $table, $where, $where_format = null ) {
            return 1;
        }

        public function esc_like( $text ) {
            // No real SQL escaping needed in unit tests — keep values readable
            // so test assertions can use plain strings.
            return $text;
        }

        public function get_charset_collate() {
            return "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        }
    }
}

if ( ! class_exists( 'WP_Query' ) ) {
    /**
     * Minimal WP_Query stub for unit tests.
     *
     * Set WP_Query::$mock_posts to an array of stdClass objects before
     * instantiating so the query loop returns those items.
     */
    class WP_Query {
        /** @var object[] Configurable post list; set in each test. */
        public static $mock_posts = array();

        /** @var object[] Internal copy held by this instance. */
        private $posts;

        /** @var int Current loop position (starts before first item). */
        private $current = -1;

        public function __construct( array $args = array() ) {
            $this->posts = self::$mock_posts;
        }

        public function have_posts() {
            return isset( $this->posts[ $this->current + 1 ] );
        }

        public function the_post() {
            ++$this->current;
        }
    }
}

if ( ! class_exists( 'WP_User' ) ) {
    /**
     * Minimal WP_User stub.
     */
    class WP_User {
        public $user_login = 'testuser';
        public $ID         = 1;

        public function __construct( $user_login = 'testuser', $id = 1 ) {
            $this->user_login = $user_login;
            $this->ID         = $id;
        }
    }
}

/**
 * Helper: initialise a fresh global $wpdb stub for tests that need one.
 */
function cdw_tests_reset_wpdb() {
    $GLOBALS['wpdb'] = new wpdb();
    return $GLOBALS['wpdb'];
}

/**
 * is_wp_error() stub.
 */
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

/**
 * sanitize_text_field() stub — trims and strips slashes, mirroring the
 * whitespace-removal behaviour of the real WP function.
 */
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( wp_unslash( (string) $str ) );
    }
}

/**
 * wp_unslash() stub — mirrors the real WP implementation.
 */
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
    }
}

/**
 * update_option() stub — always returns true (no real DB in unit tests).
 */
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $option, $value, $autoload = null ) {
        return true;
    }
}

/**
 * i18n stubs — return text as-is.
 */
if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( '_e' ) ) {
    function _e( $text, $domain = 'default' ) {
        echo $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return htmlspecialchars( $text );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES );
    }
}

/**
 * WordPress output-type constants for wpdb query results.
 */
if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
    define( 'ARRAY_N', 'ARRAY_N' );
}

/**
 * esc_html_e stub — echo escaped text as-is.
 */
if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) {
        echo htmlspecialchars( $text );
    }
}

/**
 * WP_CLI stub for CLI command unit tests.
 *
 * Captures output in static arrays so tests can assert on messages
 * without any real WP-CLI infrastructure.
 */
if ( ! class_exists( 'WP_CLI' ) ) {
    class WP_CLI {
        /** @var string[] */
        public static $lines     = array();
        /** @var string[] */
        public static $successes = array();
        /** @var string[] */
        public static $errors    = array();

        public static function line( $msg ) {
            self::$lines[] = $msg;
        }

        public static function success( $msg ) {
            self::$successes[] = $msg;
        }

        /** Does NOT exit — tests keep running after an expected error. */
        public static function error( $msg ) {
            self::$errors[] = $msg;
        }

        public static function add_command( $cmd, $callback ) {}

        /** Reset all captured output between tests. */
        public static function reset() {
            self::$lines     = array();
            self::$successes = array();
            self::$errors    = array();
        }
    }
}

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

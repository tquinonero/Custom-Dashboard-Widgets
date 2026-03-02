<?php
/**
 * Stub for wp-admin/includes/class-wp-upgrader.php
 * Defines minimal upgrader stubs so handlers can instantiate them.
 */

if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
    class WP_Upgrader_Skin {
        public function __construct( $args = array() ) {}
        public function get_errors() { return new WP_Error(); }
    }
}

if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
    class WP_Ajax_Upgrader_Skin extends WP_Upgrader_Skin {
        public function __construct( $args = array() ) {}
        public function get_errors() { return new WP_Error(); }
    }
}

if ( ! class_exists( 'WP_Upgrader' ) ) {
    class WP_Upgrader {
        protected $skin;
        public function __construct( $skin = null ) { $this->skin = $skin; }
    }
}

if ( ! class_exists( 'Plugin_Upgrader' ) ) {
    class Plugin_Upgrader extends WP_Upgrader {
        /** Set by test: return value for upgrade() */
        public static $upgrade_return  = true;
        /** Set by test: return value for install() */
        public static $install_return  = true;
        /** Captured by test: args passed to install() */
        public static $install_args    = null;
        /** Captured by test: args passed to bulk_upgrade() */
        public static $bulk_return     = array();

        public function upgrade( $plugin, $args = array() ) {
            return self::$upgrade_return;
        }

        public function install( $package, $args = array() ) {
            self::$install_args = $args;
            return self::$install_return;
        }

        public function bulk_upgrade( $plugins, $args = array() ) {
            return self::$bulk_return;
        }
    }
}

if ( ! class_exists( 'Theme_Upgrader' ) ) {
    class Theme_Upgrader extends WP_Upgrader {
        public function upgrade( $theme, $args = array() ) {
            return true;
        }
        public function install( $package, $args = array() ) {
            return true;
        }
    }
}

if ( ! class_exists( 'Theme_Installer_Skin' ) ) {
    class Theme_Installer_Skin extends WP_Upgrader_Skin {
        public function __construct( $args = array() ) {}
    }
}

if ( ! class_exists( 'Plugin_Installer_Skin' ) ) {
    class Plugin_Installer_Skin extends WP_Upgrader_Skin {
        public function __construct( $args = array() ) {}
    }
}

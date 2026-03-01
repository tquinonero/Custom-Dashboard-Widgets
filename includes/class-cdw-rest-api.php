<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CDW_REST_API {
    private $namespace = 'cdw/v1';
    private $rate_limit_count = 20;
    private $rate_limit_window = 60;

    /**
     * Options that must never be written or deleted via the CLI to prevent
     * accidental site lockout or data loss.
     */
    private $protected_options = array(
        'siteurl',
        'home',
        'admin_email',
        'blogname',
        'blogdescription',
        'wp_user_roles',
        'active_plugins',
        'template',
        'stylesheet',
        'auth_key',
        'secure_auth_key',
        'logged_in_key',
        'nonce_key',
        'auth_salt',
        'secure_auth_salt',
        'logged_in_salt',
        'nonce_salt',
        'db_version',
        'initial_db_version',
        'wordpress_db_version',
    );

    public function register() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'init', array( $this, 'ensure_audit_table' ), 5 );
    }

    public function create_audit_log_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'cdw_cli_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            command varchar(500) NOT NULL,
            success tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function ensure_audit_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdw_cli_logs';

        $table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '" . $wpdb->esc_like( $table_name ) . "'"
        );

        if ( $table_exists !== $table_name ) {
            $this->create_audit_log_table();
        }
    }

    private function log_cli_command( $user_id, $command, $success ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cdw_cli_logs';

        $table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '" . $wpdb->esc_like( $table_name ) . "'"
        );

        if ( $table_exists !== $table_name ) {
            return;
        }

        try {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'command' => substr( $command, 0, 500 ),
                    'success' => $success ? 1 : 0,
                ),
                array( '%d', '%s', '%d' )
            );

            $wpdb->query(
                "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
        } catch ( Exception $e ) {
            error_log( sprintf(
                '[CDW Plugin] Failed to log CLI command: %s | User: %d | Error: %s',
                $command,
                $user_id,
                $e->getMessage()
            ) );
        }
    }

    private function check_rate_limit( $user_id ) {
        try {
            $key   = 'cdw_cli_rate_' . $user_id;
            $count = get_transient( $key );

            if ( false === $count ) {
                $count = 0;
            }

            if ( $count >= $this->rate_limit_count ) {
                return false;
            }

            set_transient( $key, $count + 1, $this->rate_limit_window );
            return true;
        } catch ( Exception $e ) {
            error_log( sprintf(
                '[CDW Plugin] Rate limit check failed: %s',
                $e->getMessage()
            ) );
            return false;
        }
    }

    private function command_requires_force( $cmd, $subcmd ) {
        if ( $cmd === 'search-replace' ) {
            return true;
        }
        $dangerous_commands = array(
            'plugin delete',
            'theme delete',
            'user delete',
            'option delete',
            'post delete',
        );
        return in_array( $cmd . ' ' . $subcmd, $dangerous_commands );
    }

    private function has_force_flag( $args ) {
        foreach ( $args as $arg ) {
            if ( strtolower( $arg ) === '--force' ) {
                return true;
            }
        }
        return false;
    }

    private function has_dry_run_flag( $args ) {
        foreach ( $args as $arg ) {
            if ( strtolower( $arg ) === '--dry-run' ) {
                return true;
            }
        }
        return false;
    }

    private function has_all_flag( $args ) {
        foreach ( $args as $arg ) {
            if ( strtolower( $arg ) === '--all' ) {
                return true;
            }
        }
        return false;
    }

    private function validate_hex_color( $color ) {
        if ( empty( $color ) ) {
            return '';
        }
        $color = trim( $color );
        if ( ! preg_match( '/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $color ) ) {
            return '';
        }
        if ( strpos( $color, '#' ) !== 0 ) {
            $color = '#' . $color;
        }
        return $color;
    }

    private function validate_email( $email ) {
        if ( empty( $email ) ) {
            return false;
        }
        return is_email( $email );
    }

    private function validate_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }
        if ( preg_match( '/^(javascript|data|vbscript):/i', $url ) ) {
            return false;
        }
        return esc_url_raw( $url );
    }

    /**
     * Initialize the WP_Filesystem abstraction layer.
     * Required before running any upgrader or filesystem operation.
     */
    private function init_filesystem() {
        global $wp_filesystem;

        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $creds = request_filesystem_credentials( '', '', false, false, null );
        if ( ! WP_Filesystem( $creds ) ) {
            return false;
        }

        return true;
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
        ) );

        register_rest_route( $this->namespace, '/media', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_media' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
            'args'                => array(
                'per_page' => array(
                    'type'    => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/posts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_posts' ),
            'permission_callback' => array( $this, 'check_read_permission' ),
            'args'                => array(
                'per_page' => array(
                    'type'    => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50,
                ),
            ),
        ) );

        register_rest_route( $this->namespace, '/users', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_users' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/updates', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_updates' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/tasks', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_tasks' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_tasks' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_settings' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/cli/execute', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'execute_cli_command' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( $this->namespace, '/cli/history', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_cli_history' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'clear_cli_history' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
        ) );
    }

    public function check_read_permission( $request = null ) {
        if ( $request instanceof WP_REST_Request ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return false;
            }
        }
        return current_user_can( 'read' );
    }

    public function check_admin_permission( $request = null ) {
        if ( $request instanceof WP_REST_Request ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );
            if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return false;
            }
        }
        return current_user_can( 'manage_options' );
    }

    public function get_stats() {
        $cached = get_transient( 'cdw_stats_cache' );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $page_counts   = wp_count_posts( 'page' );
        $page_total    = 0;
        $page_statuses = array( 'publish', 'draft', 'pending', 'private' );
        foreach ( $page_statuses as $status ) {
            if ( isset( $page_counts->$status ) ) {
                $page_total += $page_counts->$status;
            }
        }

        $stats = array(
            'posts'      => wp_count_posts()->publish,
            'pages'      => $page_total,
            'comments'   => wp_count_comments()->approved,
            'users'      => count_users()['total_users'],
            'media'      => wp_count_posts( 'attachment' )->inherit,
            'categories' => wp_count_terms( 'category' ),
            'tags'       => wp_count_terms( 'post_tag' ),
            'plugins'    => count( get_plugins() ),
            'themes'     => count( wp_get_themes() ),
        );

        if ( class_exists( 'WooCommerce' ) ) {
            $product_counts   = wp_count_posts( 'product' );
            $product_total    = 0;
            $product_statuses = array( 'publish', 'draft', 'pending', 'private' );
            foreach ( $product_statuses as $status ) {
                if ( isset( $product_counts->$status ) ) {
                    $product_total += $product_counts->$status;
                }
            }
            $stats['products'] = $product_total;
        }

        set_transient( 'cdw_stats_cache', $stats, 60 );

        return rest_ensure_response( $stats );
    }

    public function get_media( WP_REST_Request $request ) {
        $per_page  = $request->get_param( 'per_page' ) ?: 10;
        $cache_key = 'cdw_media_cache_' . $per_page;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $args  = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        $query = new WP_Query( $args );
        $media = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $media[] = array(
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'url'   => wp_get_attachment_url( get_the_ID() ),
                    'date'  => get_the_date( 'c' ),
                );
            }
            wp_reset_postdata();
        }

        set_transient( $cache_key, $media, 300 );

        return rest_ensure_response( $media );
    }

    public function get_posts( WP_REST_Request $request ) {
        $per_page  = $request->get_param( 'per_page' ) ?: 10;
        $cache_key = 'cdw_posts_cache_' . $per_page;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $posts     = wp_get_recent_posts( array( 'numberposts' => $per_page, 'post_status' => 'publish' ) );
        $formatted = array();

        foreach ( $posts as $post ) {
            $formatted[] = array(
                'id'    => $post['ID'],
                'title' => $post['post_title'],
                'date'  => $post['post_date'],
                'link'  => get_permalink( $post['ID'] ),
            );
        }

        set_transient( $cache_key, $formatted, 300 );

        return rest_ensure_response( $formatted );
    }

    public function get_users() {
        $users              = get_users();
        $formatted          = array();
        $can_manage_options = current_user_can( 'manage_options' );

        foreach ( $users as $user ) {
            $formatted[] = array(
                'id'     => $user->ID,
                'name'   => $user->display_name,
                'email'  => $can_manage_options ? $user->user_email : '',
                'avatar' => get_avatar_url( $user->ID ),
            );
        }

        return rest_ensure_response( $formatted );
    }

    public function get_updates() {
        $updates = get_site_transient( 'update_plugins' );
        $plugins = array();

        if ( ! empty( $updates ) && ! empty( $updates->response ) ) {
            foreach ( $updates->response as $plugin_file => $update ) {
                if ( ! function_exists( 'get_plugin_data' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
                $plugins[]   = array(
                    'name'    => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'file'    => $plugin_file,
                );
            }
        }

        return rest_ensure_response( $plugins );
    }

    public function get_tasks() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return rest_ensure_response( array() );
        }

        $tasks_json = get_user_meta( $user_id, 'cdw_tasks', true );
        $tasks      = $tasks_json ? json_decode( $tasks_json, true ) : array();

        return rest_ensure_response( $tasks );
    }

    public function save_tasks( WP_REST_Request $request ) {
        $current_user_id = get_current_user_id();

        if ( ! $current_user_id ) {
            return new WP_Error( 'no_user', 'User not logged in', array( 'status' => 401 ) );
        }

        $tasks       = $request->get_param( 'tasks' );
        $assignee_id = $request->get_param( 'assignee_id' );

        if ( ! is_array( $tasks ) ) {
            return new WP_Error( 'invalid_data', 'Invalid tasks data', array( 'status' => 400 ) );
        }

        $target_user_id = $current_user_id;

        if ( $assignee_id && current_user_can( 'manage_options' ) ) {
            $target_user_id = intval( $assignee_id );
            if ( ! get_userdata( $target_user_id ) ) {
                return new WP_Error( 'invalid_user', 'Invalid user', array( 'status' => 400 ) );
            }
        }

        $sanitized_tasks = array();
        $now             = time();

        foreach ( $tasks as $task ) {
            $name = isset( $task['name'] ) ? sanitize_text_field( wp_unslash( $task['name'] ) ) : '';

            if ( empty( $name ) ) {
                continue;
            }

            $timestamp = isset( $task['timestamp'] ) ? intval( $task['timestamp'] ) : 0;
            if ( $timestamp <= 0 || $timestamp > $now ) {
                $timestamp = $now;
            }

            $sanitized_tasks[] = array(
                'name'       => $name,
                'timestamp'  => $timestamp,
                'created_by' => $current_user_id,
            );
        }

        update_user_meta( $target_user_id, 'cdw_tasks', wp_json_encode( $sanitized_tasks ) );

        return rest_ensure_response( array(
            'success' => true,
            'tasks'   => $sanitized_tasks,
        ) );
    }

    public function get_settings() {
        $email             = get_option( 'cdw_support_email', get_option( 'custom_dashboard_widget_email', '' ) );
        $docs_url          = get_option( 'cdw_docs_url', get_option( 'custom_dashboard_widget_docs_url', '' ) );
        $font_size         = get_option( 'cdw_font_size', get_option( 'custom_dashboard_widget_font_size', '' ) );
        $bg_color          = get_option( 'cdw_bg_color', get_option( 'custom_dashboard_widget_background_color', '' ) );
        $header_bg_color   = get_option( 'cdw_header_bg_color', get_option( 'custom_dashboard_widget_header_background_color', '' ) );
        $header_text_color = get_option( 'cdw_header_text_color', get_option( 'custom_dashboard_widget_header_text_color', '' ) );
        $cli_enabled            = get_option( 'cdw_cli_enabled', true );
        $remove_default_widgets = get_option( 'cdw_remove_default_widgets', true );

        return rest_ensure_response( array(
            'email'                  => $email,
            'docs_url'               => $docs_url,
            'font_size'              => $font_size,
            'bg_color'               => $bg_color,
            'header_bg_color'        => $header_bg_color,
            'header_text_color'      => $header_text_color,
            'cli_enabled'            => $cli_enabled,
            'remove_default_widgets' => $remove_default_widgets,
        ) );
    }

    public function save_settings( WP_REST_Request $request ) {
        $settings = $request->get_json_params();

        if ( isset( $settings['email'] ) && ! empty( $settings['email'] ) ) {
            $email = $this->validate_email( $settings['email'] );
            if ( $email ) {
                update_option( 'cdw_support_email', $email );
                update_option( 'custom_dashboard_widget_email', $email );
            }
        }

        if ( isset( $settings['docs_url'] ) && ! empty( $settings['docs_url'] ) ) {
            $url = $this->validate_url( $settings['docs_url'] );
            if ( $url ) {
                update_option( 'cdw_docs_url', $url );
                update_option( 'custom_dashboard_widget_docs_url', $url );
            }
        }

        if ( isset( $settings['font_size'] ) ) {
            $size = absint( $settings['font_size'] );
            if ( $size >= 10 && $size <= 40 ) {
                update_option( 'cdw_font_size', $size );
                update_option( 'custom_dashboard_widget_font_size', $size );
            }
        }

        if ( isset( $settings['bg_color'] ) ) {
            $color = $this->validate_hex_color( $settings['bg_color'] );
            if ( $color !== '' ) {
                update_option( 'cdw_bg_color', $color );
                update_option( 'custom_dashboard_widget_background_color', $color );
            }
        }

        if ( isset( $settings['header_bg_color'] ) ) {
            $color = $this->validate_hex_color( $settings['header_bg_color'] );
            if ( $color !== '' ) {
                update_option( 'cdw_header_bg_color', $color );
                update_option( 'custom_dashboard_widget_header_background_color', $color );
            }
        }

        if ( isset( $settings['header_text_color'] ) ) {
            $color = $this->validate_hex_color( $settings['header_text_color'] );
            if ( $color !== '' ) {
                update_option( 'cdw_header_text_color', $color );
                update_option( 'custom_dashboard_widget_header_text_color', $color );
            }
        }

        if ( isset( $settings['cli_enabled'] ) ) {
            $cli_enabled = filter_var( $settings['cli_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
            if ( $cli_enabled !== null ) {
                update_option( 'cdw_cli_enabled', $cli_enabled );
            }
        }

        if ( isset( $settings['remove_default_widgets'] ) ) {
            $remove_default_widgets = filter_var( $settings['remove_default_widgets'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
            if ( $remove_default_widgets !== null ) {
                update_option( 'cdw_remove_default_widgets', $remove_default_widgets );
            }
        }

        delete_transient( 'cdw_stats_cache' );

        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . $wpdb->esc_like( '_transient_cdw_media_cache_' ) . "%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . $wpdb->esc_like( '_transient_cdw_posts_cache_' ) . "%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . $wpdb->esc_like( '_transient_timeout_cdw_media_cache_' ) . "%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . $wpdb->esc_like( '_transient_timeout_cdw_posts_cache_' ) . "%'" );

        return rest_ensure_response( array( 'success' => true ) );
    }

    public function get_cli_history() {
        global $wpdb;

        $user_id = get_current_user_id();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'cdw_cli_history'",
                $user_id
            )
        );

        if ( ! empty( $results ) && ! empty( $results[0]->meta_value ) ) {
            return rest_ensure_response( json_decode( $results[0]->meta_value, true ) );
        }

        return rest_ensure_response( array() );
    }

    public function clear_cli_history() {
        global $wpdb;

        $user_id = get_current_user_id();

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'cdw_cli_history'",
                $user_id
            )
        );

        return rest_ensure_response( array( 'success' => true, 'cleared' => true ) );
    }

    public function execute_cli_command( WP_REST_Request $request ) {
        $command = $request->get_param( 'command' );
        $user_id = get_current_user_id();

        $cli_enabled = get_option( 'cdw_cli_enabled', true );
        if ( ! $cli_enabled ) {
            return new WP_Error(
                'cli_disabled',
                __( 'CLI feature is disabled in settings.', 'cdw' ),
                array( 'status' => 403 )
            );
        }

        if ( empty( $command ) ) {
            return new WP_Error( 'empty_command', 'Command cannot be empty', array( 'status' => 400 ) );
        }

        if ( ! $this->check_rate_limit( $user_id ) ) {
            return new WP_Error( 'rate_limited', 'Rate limit exceeded. Max 20 commands per minute.', array( 'status' => 429 ) );
        }

        $this->ensure_audit_table();

        $command = sanitize_text_field( wp_unslash( $command ) );
        $parts   = preg_split( '/\s+/', trim( $command ) );
        $cmd     = strtolower( $parts[0] ?? '' );
        $subcmd  = strtolower( $parts[1] ?? '' );
        // For search-replace all parts after the command name are args.
        $raw_args = $cmd === 'search-replace' ? array_slice( $parts, 1 ) : array_slice( $parts, 2 );

        // Allow --dry-run to bypass the --force requirement for search-replace.
        $force_bypass = $this->has_dry_run_flag( $raw_args );

        if ( $this->command_requires_force( $cmd, $subcmd ) && ! $this->has_force_flag( $raw_args ) && ! $force_bypass ) {
            if ( $cmd === 'search-replace' ) {
                $error  = "This command requires --force or --dry-run.\n";
                $error .= "Usage: search-replace <old> <new> [--dry-run] [--force]\n";
                $error .= "Tip: use --dry-run first to preview matches without making changes.";
            } else {
                $error  = "This command requires the --force flag for safety.\n";
                $error .= "Usage: $cmd $subcmd --force\n";
                $error .= "Example: plugin delete woocommerce --force";
            }

            $this->log_cli_command( $user_id, $command, false );

            return rest_ensure_response( array(
                'success'        => false,
                'output'         => $error,
                'command'        => $command,
                'requires_force' => true,
            ) );
        }

        // Clean args: strip flag tokens before passing to handlers.
        $clean_args = array_values( array_filter( $raw_args, function( $arg ) {
            return ! in_array( strtolower( $arg ), array( '--force', '--all' ), true );
        } ) );

        $output  = '';
        $success = true;
        $error   = '';

        try {
            $result = null;
            switch ( $cmd ) {
                case 'plugin':
                    $result = $this->handle_plugin_command( $subcmd, $clean_args, $raw_args );
                    break;

                case 'theme':
                    $result = $this->handle_theme_command( $subcmd, $clean_args, $raw_args );
                    break;

                case 'user':
                    $result = $this->handle_user_command( $subcmd, $clean_args );
                    break;

                case 'post':
                    $result = $this->handle_post_command( $subcmd, $clean_args );
                    break;

                case 'site':
                    $result = $this->handle_site_command( $subcmd, $clean_args );
                    break;

                case 'cache':
                    $result = $this->handle_cache_command( $subcmd, $clean_args );
                    break;

                case 'db':
                    $result = $this->handle_db_command( $subcmd, $clean_args );
                    break;

                case 'option':
                    $result = $this->handle_option_command( $subcmd, $clean_args );
                    break;

                case 'transient':
                    $result = $this->handle_transient_command( $subcmd, $clean_args );
                    break;

                case 'cron':
                    $result = $this->handle_cron_command( $subcmd, $clean_args );
                    break;

                case 'maintenance':
                    $result = $this->handle_maintenance_command( $subcmd, $clean_args );
                    break;

                case 'search-replace':
                    // Pass raw_args so the handler can inspect --dry-run / --force itself.
                    $result = $this->handle_search_replace_command( $raw_args );
                    break;

                case 'help':
                    $result = $this->handle_help_command();
                    break;

                default:
                    $success = false;
                    $error   = "Unknown command: $cmd. Type 'help' for available commands.";
            }

            if ( $result && is_array( $result ) && isset( $result['output'] ) ) {
                $output  = $result['output'];
                $success = isset( $result['success'] ) ? $result['success'] : true;
            }
        } catch ( Error $e ) {
            $success = false;
            $error   = 'Error: ' . $e->getMessage();
            error_log( '[CDW CLI] Error: ' . $e->getMessage() );
        } catch ( Exception $e ) {
            $success = false;
            $error   = $e->getMessage();
        }

        $this->log_cli_command( $user_id, $command, $success );

        delete_transient( 'cdw_stats_cache' );

        $final_output = $success ? $output : $error;

        $history_item = array(
            'command'   => $command,
            'output'    => $final_output,
            'success'   => $success,
            'timestamp' => time(),
        );

        $history = get_user_meta( $user_id, 'cdw_cli_history', true );
        $history = $history ? json_decode( $history, true ) : array();
        array_unshift( $history, $history_item );
        $history = array_slice( $history, 0, 50 );
        update_user_meta( $user_id, 'cdw_cli_history', wp_json_encode( $history ) );

        return rest_ensure_response( array(
            'success' => $success,
            'output'  => $final_output,
            'command' => $command,
        ) );
    }

    // =========================================================================
    // Plugin commands
    // =========================================================================

    private function handle_plugin_command( $subcmd, $args, $raw_args = array() ) {
        switch ( $subcmd ) {
            case 'list':
                $plugins = get_plugins();
                $updates = get_site_transient( 'update_plugins' );
                $output  = "Installed Plugins:\n";
                foreach ( $plugins as $path => $plugin ) {
                    $status     = is_plugin_active( $path ) ? '[Active]  ' : '[Inactive]';
                    $has_update = ! empty( $updates->response[ $path ] ) ? ' [Update available]' : '';
                    $output    .= "$status " . $plugin['Name'] . " v" . $plugin['Version'] . " (" . dirname( $path ) . ")$has_update\n";
                }
                return array( 'output' => $output, 'success' => true );

            case 'status':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: plugin status <plugin-slug>', 'success' => false );
                }
                $plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
                if ( ! $plugin_file ) {
                    return array( 'output' => "Plugin not found: " . $args[0], 'success' => false );
                }
                if ( ! function_exists( 'get_plugin_data' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }
                $data       = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
                $updates    = get_site_transient( 'update_plugins' );
                $has_update = ! empty( $updates->response[ $plugin_file ] );
                $new_ver    = $has_update ? $updates->response[ $plugin_file ]->new_version : null;
                $output     = "Plugin:  " . $data['Name'] . "\n";
                $output    .= "Status:  " . ( is_plugin_active( $plugin_file ) ? 'Active' : 'Inactive' ) . "\n";
                $output    .= "Version: " . $data['Version'] . "\n";
                $output    .= "Update:  " . ( $has_update ? "Available (v$new_ver)" : 'Up to date' ) . "\n";
                $output    .= "File:    $plugin_file\n";
                return array( 'output' => $output, 'success' => true );

            case 'activate':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: plugin activate <plugin-slug>', 'success' => false );
                }
                $plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
                if ( ! $plugin_file ) {
                    return array( 'output' => "Plugin not found: " . $args[0], 'success' => false );
                }
                $result = activate_plugin( $plugin_file );
                if ( is_wp_error( $result ) ) {
                    return array( 'output' => 'Activation failed: ' . $result->get_error_message(), 'success' => false );
                }
                return array( 'output' => "Plugin activated: " . dirname( $plugin_file ), 'success' => true );

            case 'deactivate':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: plugin deactivate <plugin-slug>', 'success' => false );
                }
                $plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
                if ( ! $plugin_file ) {
                    return array( 'output' => "Plugin not found: " . $args[0], 'success' => false );
                }
                deactivate_plugins( $plugin_file );
                return array( 'output' => "Plugin deactivated: " . dirname( $plugin_file ), 'success' => true );

            case 'update':
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/misc.php';
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }

                $updates = get_site_transient( 'update_plugins' );

                if ( $this->has_all_flag( $raw_args ) ) {
                    if ( empty( $updates->response ) ) {
                        return array( 'output' => 'All plugins are up to date.', 'success' => true );
                    }
                    $skin     = new WP_Ajax_Upgrader_Skin();
                    $upgrader = new Plugin_Upgrader( $skin );
                    $results  = $upgrader->bulk_upgrade( array_keys( $updates->response ) );
                    $output   = "Plugin updates:\n";
                    foreach ( $results as $plugin_file => $result ) {
                        if ( is_wp_error( $result ) ) {
                            $output .= "  FAILED  $plugin_file: " . $result->get_error_message() . "\n";
                        } elseif ( false === $result ) {
                            $output .= "  FAILED  $plugin_file\n";
                        } else {
                            $output .= "  Updated $plugin_file\n";
                        }
                    }
                    wp_cache_delete( 'plugins', 'plugins' );
                    return array( 'output' => rtrim( $output ), 'success' => true );
                }

                if ( empty( $args[0] ) ) {
                    return array( 'output' => "Usage: plugin update <slug>  |  plugin update --all", 'success' => false );
                }
                $plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
                if ( ! $plugin_file ) {
                    return array( 'output' => "Plugin not found: " . $args[0], 'success' => false );
                }
                if ( empty( $updates->response[ $plugin_file ] ) ) {
                    return array( 'output' => "Plugin is already up to date: " . $args[0], 'success' => true );
                }
                $skin     = new WP_Ajax_Upgrader_Skin();
                $upgrader = new Plugin_Upgrader( $skin );
                $result   = $upgrader->upgrade( $plugin_file );
                if ( false === $result ) {
                    $skin_errors = $skin->get_errors();
                    $error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
                        ? $skin_errors->get_error_message()
                        : 'Could not connect to the filesystem.';
                    return array( 'output' => 'Update failed: ' . $error_msg, 'success' => false );
                }
                if ( is_wp_error( $result ) ) {
                    return array( 'output' => 'Update failed: ' . $result->get_error_message(), 'success' => false );
                }
                wp_cache_delete( 'plugins', 'plugins' );
                return array( 'output' => "Plugin updated: " . $args[0], 'success' => true );

            case 'delete':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: plugin delete <plugin-slug> --force', 'success' => false );
                }
                $plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
                if ( ! $plugin_file ) {
                    return array( 'output' => "Plugin not found: " . $args[0], 'success' => false );
                }
                if ( is_plugin_active( $plugin_file ) ) {
                    return array( 'output' => 'Cannot delete active plugin. Deactivate first.', 'success' => false );
                }
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }
                $result = delete_plugins( array( $plugin_file ) );
                if ( is_wp_error( $result ) ) {
                    return array( 'output' => 'Delete failed: ' . $result->get_error_message(), 'success' => false );
                }
                return array( 'output' => "Plugin deleted: " . dirname( $plugin_file ), 'success' => true );

            case 'install':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: plugin install <plugin-slug>', 'success' => false );
                }

                $slug = sanitize_text_field( $args[0] );

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/misc.php';
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }

                $existing_plugins  = get_plugins();
                $already_installed = false;
                foreach ( $existing_plugins as $plugin_file => $plugin_data ) {
                    if ( strpos( $plugin_file, $slug ) !== false ) {
                        $already_installed = true;
                        break;
                    }
                }

                $api = plugins_api( 'plugin_information', array(
                    'slug'   => $slug,
                    'fields' => array( 'sections' => false ),
                ) );

                if ( is_wp_error( $api ) ) {
                    return array( 'output' => 'Plugin not found in repository: ' . $slug . ' - ' . $api->get_error_message(), 'success' => false );
                }

                $skin     = new WP_Ajax_Upgrader_Skin();
                $upgrader = new Plugin_Upgrader( $skin );
                $result   = $upgrader->install( $api->download_link, array(
                    'overwrite_package' => $already_installed,
                ) );

                if ( false === $result ) {
                    $skin_errors = $skin->get_errors();
                    $error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
                        ? $skin_errors->get_error_message()
                        : 'Could not connect to the filesystem. Check file permissions.';
                    return array( 'output' => 'Install failed: ' . $error_msg, 'success' => false );
                }

                if ( is_wp_error( $result ) ) {
                    return array( 'output' => 'Install failed: ' . $result->get_error_message(), 'success' => false );
                }

                wp_cache_delete( 'plugins', 'plugins' );
                $installed_plugins = get_plugins();
                $plugin_found      = false;
                foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
                    if ( strpos( $plugin_file, $slug ) !== false ) {
                        $plugin_found = true;
                        break;
                    }
                }

                if ( ! $plugin_found ) {
                    return array( 'output' => 'Plugin installed but could not verify. Check plugins list.', 'success' => true );
                }

                $verb = $already_installed ? 're-installed' : 'installed';
                return array( 'output' => "Plugin $verb successfully: $slug", 'success' => true );

            default:
                return array(
                    'output'  => "Available plugin commands:\n  plugin list              - List all plugins\n  plugin status <slug>     - Show status for a plugin\n  plugin install <slug>    - Install a plugin\n  plugin activate <slug>   - Activate a plugin\n  plugin deactivate <slug> - Deactivate a plugin\n  plugin update <slug>     - Update a plugin\n  plugin update --all      - Update all plugins\n  plugin delete <slug>     - Delete a plugin (requires --force)",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Theme commands
    // =========================================================================

    private function handle_theme_command( $subcmd, $args, $raw_args = array() ) {
        switch ( $subcmd ) {
            case 'list':
                $themes  = wp_get_themes();
                $current = wp_get_theme();
                $updates = get_site_transient( 'update_themes' );
                $output  = "Installed Themes:\n";
                foreach ( $themes as $theme ) {
                    $status     = ( $theme->get_stylesheet() === $current->get_stylesheet() ) ? '[Active]  ' : '[Inactive]';
                    $has_update = ! empty( $updates->response[ $theme->get_stylesheet() ] ) ? ' [Update available]' : '';
                    $output    .= "$status " . $theme->get( 'Name' ) . " v" . $theme->get( 'Version' ) . " (" . $theme->get_stylesheet() . ")$has_update\n";
                }
                return array( 'output' => $output, 'success' => true );

            case 'status':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: theme status <theme-slug>', 'success' => false );
                }
                $slug   = sanitize_text_field( $args[0] );
                $themes = wp_get_themes();
                if ( ! isset( $themes[ $slug ] ) ) {
                    return array( 'output' => "Theme not found: $slug", 'success' => false );
                }
                $theme      = $themes[ $slug ];
                $current    = wp_get_theme();
                $updates    = get_site_transient( 'update_themes' );
                $has_update = ! empty( $updates->response[ $slug ] );
                $new_ver    = $has_update ? $updates->response[ $slug ]['new_version'] : null;
                $output     = "Theme:   " . $theme->get( 'Name' ) . "\n";
                $output    .= "Status:  " . ( $theme->get_stylesheet() === $current->get_stylesheet() ? 'Active' : 'Inactive' ) . "\n";
                $output    .= "Version: " . $theme->get( 'Version' ) . "\n";
                $output    .= "Update:  " . ( $has_update ? "Available (v$new_ver)" : 'Up to date' ) . "\n";
                return array( 'output' => $output, 'success' => true );

            case 'activate':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: theme activate <theme-slug>', 'success' => false );
                }
                $slug   = sanitize_text_field( $args[0] );
                $themes = wp_get_themes();
                if ( ! isset( $themes[ $slug ] ) ) {
                    return array( 'output' => "Theme not found: $slug", 'success' => false );
                }
                switch_theme( $slug );
                return array( 'output' => "Theme activated: $slug", 'success' => true );

            case 'deactivate':
                if ( ! empty( $args[0] ) ) {
                    $theme_to_activate = sanitize_text_field( $args[0] );
                    switch_theme( $theme_to_activate );
                    return array( 'output' => "Switched to theme: $theme_to_activate", 'success' => true );
                }
                foreach ( wp_get_themes() as $theme ) {
                    if ( ! $theme->is_active() && $theme->exists() ) {
                        switch_theme( $theme->get_stylesheet() );
                        return array( 'output' => "Switched to: " . $theme->get_stylesheet(), 'success' => true );
                    }
                }
                return array( 'output' => 'No other theme available to switch to.', 'success' => false );

            case 'update':
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/misc.php';
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }

                $updates = get_site_transient( 'update_themes' );

                if ( $this->has_all_flag( $raw_args ) ) {
                    if ( empty( $updates->response ) ) {
                        return array( 'output' => 'All themes are up to date.', 'success' => true );
                    }
                    $skin     = new WP_Ajax_Upgrader_Skin();
                    $upgrader = new Theme_Upgrader( $skin );
                    $results  = $upgrader->bulk_upgrade( array_keys( $updates->response ) );
                    $output   = "Theme updates:\n";
                    foreach ( $results as $theme_slug => $result ) {
                        if ( is_wp_error( $result ) ) {
                            $output .= "  FAILED  $theme_slug: " . $result->get_error_message() . "\n";
                        } elseif ( false === $result ) {
                            $output .= "  FAILED  $theme_slug\n";
                        } else {
                            $output .= "  Updated $theme_slug\n";
                        }
                    }
                    search_theme_directories( true );
                    return array( 'output' => rtrim( $output ), 'success' => true );
                }

                if ( empty( $args[0] ) ) {
                    return array( 'output' => "Usage: theme update <slug>  |  theme update --all", 'success' => false );
                }
                $slug = sanitize_text_field( $args[0] );
                if ( empty( $updates->response[ $slug ] ) ) {
                    return array( 'output' => "Theme is already up to date: $slug", 'success' => true );
                }
                $skin     = new WP_Ajax_Upgrader_Skin();
                $upgrader = new Theme_Upgrader( $skin );
                $result   = $upgrader->upgrade( $slug );
                if ( false === $result ) {
                    $skin_errors = $skin->get_errors();
                    $error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
                        ? $skin_errors->get_error_message()
                        : 'Could not connect to the filesystem.';
                    return array( 'output' => 'Update failed: ' . $error_msg, 'success' => false );
                }
                if ( is_wp_error( $result ) ) {
                    return array( 'output' => 'Update failed: ' . $result->get_error_message(), 'success' => false );
                }
                search_theme_directories( true );
                return array( 'output' => "Theme updated: $slug", 'success' => true );

            case 'install':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: theme install <theme-slug>', 'success' => false );
                }

                $slug = sanitize_text_field( $args[0] );

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/misc.php';
                require_once ABSPATH . 'wp-admin/includes/theme.php';
                require_once ABSPATH . 'wp-admin/includes/theme-install.php';
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }

                $existing_themes   = wp_get_themes();
                $already_installed = isset( $existing_themes[ $slug ] );

                $api = themes_api( 'theme_information', array(
                    'slug'   => $slug,
                    'fields' => array( 'sections' => false ),
                ) );

                if ( is_wp_error( $api ) ) {
                    return array( 'output' => 'Theme not found in repository: ' . $slug . ' - ' . $api->get_error_message(), 'success' => false );
                }

                $skin     = new WP_Ajax_Upgrader_Skin();
                $upgrader = new Theme_Upgrader( $skin );
                $result   = $upgrader->install( $api->download_link, array(
                    'overwrite_package' => $already_installed,
                ) );

                if ( false === $result ) {
                    $skin_errors = $skin->get_errors();
                    $error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
                        ? $skin_errors->get_error_message()
                        : 'Could not connect to the filesystem. Check file permissions.';
                    return array( 'output' => 'Install failed: ' . $error_msg, 'success' => false );
                }

                if ( is_wp_error( $result ) ) {
                    return array( 'output' => 'Install failed: ' . $result->get_error_message(), 'success' => false );
                }

                search_theme_directories( true );
                $installed_themes = wp_get_themes();
                if ( ! isset( $installed_themes[ $slug ] ) ) {
                    return array( 'output' => 'Theme installed but could not verify. Check themes list.', 'success' => true );
                }

                $verb = $already_installed ? 're-installed' : 'installed';
                return array( 'output' => "Theme $verb successfully: $slug", 'success' => true );

            case 'delete':
                return array( 'output' => 'Theme deletion is disabled for safety. Use WP-CLI: wp theme delete <slug>', 'success' => false );

            default:
                return array(
                    'output'  => "Available theme commands:\n  theme list              - List all themes\n  theme status <slug>     - Show status for a theme\n  theme install <slug>    - Install a theme from wordpress.org\n  theme activate <slug>   - Activate a theme\n  theme deactivate [slug] - Switch to another theme\n  theme update <slug>     - Update a theme\n  theme update --all      - Update all themes",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // User commands
    // =========================================================================

    private function handle_user_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'list':
                $users  = get_users();
                $output = "Users:\n";
                foreach ( $users as $user ) {
                    $roles   = implode( ', ', $user->roles );
                    $output .= "#{$user->ID} {$user->display_name} <{$user->user_email}> ($roles)\n";
                }
                return array( 'output' => $output, 'success' => true );

            case 'get':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: user get <id|username>', 'success' => false );
                }
                $identifier = sanitize_text_field( $args[0] );
                $user       = is_numeric( $identifier )
                    ? get_userdata( intval( $identifier ) )
                    : get_user_by( 'login', $identifier );
                if ( ! $user ) {
                    return array( 'output' => "User not found: $identifier", 'success' => false );
                }
                $output  = "ID:           {$user->ID}\n";
                $output .= "Username:     {$user->user_login}\n";
                $output .= "Display Name: {$user->display_name}\n";
                $output .= "Email:        {$user->user_email}\n";
                $output .= "Roles:        " . implode( ', ', $user->roles ) . "\n";
                $output .= "Registered:   {$user->user_registered}\n";
                return array( 'output' => $output, 'success' => true );

            case 'update':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: user update <id|username> --role <role>', 'success' => false );
                }
                $identifier = sanitize_text_field( $args[0] );
                $user       = is_numeric( $identifier )
                    ? get_userdata( intval( $identifier ) )
                    : get_user_by( 'login', $identifier );
                if ( ! $user ) {
                    return array( 'output' => "User not found: $identifier", 'success' => false );
                }
                $new_role = null;
                for ( $i = 1; $i < count( $args ); $i++ ) {
                    if ( strtolower( $args[ $i ] ) === '--role' && isset( $args[ $i + 1 ] ) ) {
                        $new_role = sanitize_text_field( $args[ $i + 1 ] );
                        break;
                    }
                }
                if ( ! $new_role ) {
                    return array( 'output' => 'Nothing to update. Supported flags: --role <role>', 'success' => false );
                }
                if ( ! function_exists( 'get_editable_roles' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }
                $valid_roles = array_keys( get_editable_roles() );
                if ( ! in_array( $new_role, $valid_roles, true ) ) {
                    return array( 'output' => 'Invalid role. Valid roles: ' . implode( ', ', $valid_roles ), 'success' => false );
                }
                $wp_user = new WP_User( $user->ID );
                $wp_user->set_role( $new_role );
                return array( 'output' => "User {$user->user_login} role updated to: $new_role", 'success' => true );

            case 'delete':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: user delete <id|username> --force', 'success' => false );
                }
                $identifier = sanitize_text_field( $args[0] );
                $user       = is_numeric( $identifier )
                    ? get_userdata( intval( $identifier ) )
                    : get_user_by( 'login', $identifier );
                if ( ! $user ) {
                    return array( 'output' => "User not found: $identifier", 'success' => false );
                }
                if ( $user->ID === get_current_user_id() ) {
                    return array( 'output' => 'Cannot delete your own account.', 'success' => false );
                }
                if ( ! function_exists( 'wp_delete_user' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }
                $result = wp_delete_user( $user->ID );
                if ( ! $result ) {
                    return array( 'output' => "Could not delete user: {$user->user_login}", 'success' => false );
                }
                return array( 'output' => "User deleted: {$user->user_login} (ID: {$user->ID})", 'success' => true );

            case 'create':
                if ( count( $args ) < 3 ) {
                    return array( 'output' => 'Usage: user create <username> <email> <role> [password]', 'success' => false );
                }
                $username = sanitize_user( $args[0] );
                $email    = sanitize_email( $args[1] );

                if ( ! is_email( $email ) ) {
                    return array( 'output' => 'Error: Invalid email address format', 'success' => false );
                }

                if ( ! function_exists( 'get_editable_roles' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                }

                $role        = sanitize_text_field( $args[2] );
                $valid_roles = array_keys( get_editable_roles() );
                if ( ! in_array( $role, $valid_roles, true ) ) {
                    return array( 'output' => 'Error: Invalid role. Valid roles: ' . implode( ', ', $valid_roles ), 'success' => false );
                }

                $auto_generate_password = ! isset( $args[3] );
                if ( $auto_generate_password ) {
                    $password = wp_generate_password( 12, true );
                } else {
                    $password = $args[3];
                    if ( strlen( $password ) < 8 ) {
                        return array( 'output' => 'Error: Password must be at least 8 characters', 'success' => false );
                    }
                }

                $user_id = wp_create_user( $username, $password, $email );
                if ( is_wp_error( $user_id ) ) {
                    return array( 'output' => 'Error: ' . $user_id->get_error_message(), 'success' => false );
                }

                $wp_user = new WP_User( $user_id );
                $wp_user->set_role( $role );

                if ( $auto_generate_password ) {
                    $message    = "A new user account has been created on " . get_bloginfo( 'name' ) . ".\n\n";
                    $message   .= "Username: $username\nPassword: $password\nEmail: $email\n\n";
                    $message   .= "Login URL: " . wp_login_url() . "\n";
                    $email_sent = @wp_mail( $email, 'Your new account on ' . get_bloginfo( 'name' ), $message );
                    if ( $email_sent ) {
                        return array( 'output' => "User created: $username (ID: $user_id) - Password sent to user's email", 'success' => true );
                    } else {
                        return array( 'output' => "User created: $username (ID: $user_id) - WARNING: Email could not be sent. Please reset the password to log in.", 'success' => true );
                    }
                }

                return array( 'output' => "User created: $username (ID: $user_id)", 'success' => true );

            default:
                return array(
                    'output'  => "Available user commands:\n  user list                          - List all users\n  user get <id|username>             - Get user details\n  user create <user> <email> <role>  - Create a user\n  user update <id|user> --role <r>   - Change a user's role\n  user delete <id|username>          - Delete a user (requires --force)",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Post commands
    // =========================================================================

    private function handle_post_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'list':
                $posts  = wp_get_recent_posts( array( 'numberposts' => 10, 'post_status' => 'any' ) );
                $output = "Recent Posts:\n";
                foreach ( $posts as $post ) {
                    $output .= "[{$post['post_status']}] #{$post['ID']} {$post['post_title']}\n";
                }
                return array( 'output' => $output, 'success' => true );

            case 'get':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: post get <id>', 'success' => false );
                }
                $post_id = intval( $args[0] );
                $post    = get_post( $post_id );
                if ( ! $post ) {
                    return array( 'output' => "Post not found: #$post_id", 'success' => false );
                }
                $author  = get_the_author_meta( 'display_name', $post->post_author );
                $output  = "ID:     {$post->ID}\n";
                $output .= "Title:  {$post->post_title}\n";
                $output .= "Status: {$post->post_status}\n";
                $output .= "Type:   {$post->post_type}\n";
                $output .= "Author: $author\n";
                $output .= "Date:   {$post->post_date}\n";
                $output .= "URL:    " . get_permalink( $post->ID ) . "\n";
                return array( 'output' => $output, 'success' => true );

            case 'create':
                if ( count( $args ) < 1 ) {
                    return array( 'output' => 'Usage: post create <title>', 'success' => false );
                }
                $title   = implode( ' ', $args );
                $post_id = wp_insert_post( array(
                    'post_title'  => $title,
                    'post_status' => 'draft',
                    'post_type'   => 'post',
                ) );
                if ( is_wp_error( $post_id ) ) {
                    return array( 'output' => 'Error: ' . $post_id->get_error_message(), 'success' => false );
                }
                return array( 'output' => "Post created: #$post_id - $title", 'success' => true );

            case 'publish':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: post publish <id>', 'success' => false );
                }
                $post_id = intval( $args[0] );
                $post    = get_post( $post_id );
                if ( ! $post ) {
                    return array( 'output' => "Post not found: #$post_id", 'success' => false );
                }
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
                return array( 'output' => "Post published: #$post_id - {$post->post_title}", 'success' => true );

            case 'unpublish':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: post unpublish <id>', 'success' => false );
                }
                $post_id = intval( $args[0] );
                $post    = get_post( $post_id );
                if ( ! $post ) {
                    return array( 'output' => "Post not found: #$post_id", 'success' => false );
                }
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
                return array( 'output' => "Post set to draft: #$post_id - {$post->post_title}", 'success' => true );

            case 'delete':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: post delete <id> --force', 'success' => false );
                }
                $post_id = intval( $args[0] );
                $post    = get_post( $post_id );
                if ( ! $post ) {
                    return array( 'output' => "Post not found: #$post_id", 'success' => false );
                }
                $result = wp_delete_post( $post_id, true );
                if ( ! $result ) {
                    return array( 'output' => "Could not delete post: #$post_id", 'success' => false );
                }
                return array( 'output' => "Post permanently deleted: #$post_id", 'success' => true );

            default:
                return array(
                    'output'  => "Available post commands:\n  post list            - List recent posts\n  post get <id>        - Get post details\n  post create <title>  - Create a draft post\n  post publish <id>    - Publish a post\n  post unpublish <id>  - Set a post back to draft\n  post delete <id>     - Permanently delete a post (requires --force)",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Site commands
    // =========================================================================

    private function handle_site_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'info':
                global $wpdb;
                $db_size = $wpdb->get_var( "SELECT SUM(ROUND((data_length + index_length) / 1024 / 1024, 2)) FROM information_schema.tables WHERE table_schema = DATABASE()" );
                $output  = "Site Information:\n";
                $output .= "URL:     " . get_site_url() . "\n";
                $output .= "Admin:   " . admin_url() . "\n";
                $output .= "Version: " . get_bloginfo( 'version' ) . "\n";
                $output .= "DB Size: " . size_format( $db_size ) . "\n";
                return array( 'output' => $output, 'success' => true );

            case 'status':
                $output  = "Site Status:\n";
                $output .= "Active Plugins: " . count( get_option( 'active_plugins' ) ) . "\n";
                $output .= "Theme:          " . wp_get_theme()->get( 'Name' ) . "\n";
                $output .= "Users:          " . count_users()['total_users'] . "\n";
                $output .= "Posts:          " . wp_count_posts()->publish . "\n";
                $output .= "Pages:          " . wp_count_posts( 'page' )->publish . "\n";
                return array( 'output' => $output, 'success' => true );

            default:
                return array(
                    'output'  => "Available site commands:\n  site info   - Show site information\n  site status - Show site status",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Cache commands
    // =========================================================================

    private function handle_cache_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'flush':
            case 'clear':
                wp_cache_flush();
                return array( 'output' => 'Object cache flushed successfully.', 'success' => true );

            default:
                return array(
                    'output'  => "Available cache commands:\n  cache flush - Flush the object cache",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // DB commands
    // =========================================================================

    private function handle_db_command( $subcmd, $args ) {
        global $wpdb;

        switch ( $subcmd ) {
            case 'optimize':
                $tables  = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
                $output  = "Optimizing tables:\n";
                foreach ( $tables as $table ) {
                    $result  = $wpdb->query( "OPTIMIZE TABLE `$table`" );
                    $output .= "  " . ( $result !== false ? 'OK  ' : 'FAIL' ) . "  $table\n";
                }
                return array( 'output' => rtrim( $output ), 'success' => true );

            case 'repair':
                $tables  = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
                $output  = "Repairing tables:\n";
                foreach ( $tables as $table ) {
                    $result  = $wpdb->query( "REPAIR TABLE `$table`" );
                    $output .= "  " . ( $result !== false ? 'OK  ' : 'FAIL' ) . "  $table\n";
                }
                return array( 'output' => rtrim( $output ), 'success' => true );

            default:
                return array(
                    'output'  => "Available db commands:\n  db optimize - Optimize all WordPress tables\n  db repair   - Repair all WordPress tables",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Option commands
    // =========================================================================

    private function handle_option_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'get':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: option get <key>', 'success' => false );
                }
                $key   = sanitize_text_field( $args[0] );
                $value = get_option( $key );
                if ( false === $value ) {
                    return array( 'output' => "Option not found: $key", 'success' => false );
                }
                if ( is_array( $value ) || is_object( $value ) ) {
                    $value = wp_json_encode( $value, JSON_PRETTY_PRINT );
                }
                return array( 'output' => "$key:\n$value", 'success' => true );

            case 'set':
                if ( count( $args ) < 2 ) {
                    return array( 'output' => 'Usage: option set <key> <value>', 'success' => false );
                }
                $key = sanitize_text_field( $args[0] );
                if ( in_array( $key, $this->protected_options, true ) ) {
                    return array( 'output' => "Protected option — cannot be modified via CLI: $key", 'success' => false );
                }
                $value = sanitize_text_field( $args[1] );
                update_option( $key, $value );
                return array( 'output' => "Option updated: $key = $value", 'success' => true );

            case 'delete':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: option delete <key> --force', 'success' => false );
                }
                $key = sanitize_text_field( $args[0] );
                if ( in_array( $key, $this->protected_options, true ) ) {
                    return array( 'output' => "Protected option — cannot be deleted via CLI: $key", 'success' => false );
                }
                if ( false === get_option( $key ) ) {
                    return array( 'output' => "Option not found: $key", 'success' => false );
                }
                delete_option( $key );
                return array( 'output' => "Option deleted: $key", 'success' => true );

            default:
                return array(
                    'output'  => "Available option commands:\n  option get <key>          - Get an option value\n  option set <key> <value>  - Set an option value\n  option delete <key>       - Delete an option (requires --force)",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Transient commands
    // =========================================================================

    private function handle_transient_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'get':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: transient get <key>', 'success' => false );
                }
                $key   = sanitize_text_field( $args[0] );
                $value = get_transient( $key );
                if ( false === $value ) {
                    return array( 'output' => "Transient not found or expired: $key", 'success' => false );
                }
                if ( is_array( $value ) || is_object( $value ) ) {
                    $value = wp_json_encode( $value, JSON_PRETTY_PRINT );
                }
                return array( 'output' => "$key:\n$value", 'success' => true );

            case 'delete':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: transient delete <key>', 'success' => false );
                }
                $key    = sanitize_text_field( $args[0] );
                $result = delete_transient( $key );
                if ( ! $result ) {
                    return array( 'output' => "Transient not found: $key", 'success' => false );
                }
                return array( 'output' => "Transient deleted: $key", 'success' => true );

            case 'flush':
                global $wpdb;
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'" );
                wp_cache_flush();
                return array( 'output' => 'All transients flushed.', 'success' => true );

            default:
                return array(
                    'output'  => "Available transient commands:\n  transient get <key>    - Get a transient value\n  transient delete <key> - Delete a specific transient\n  transient flush        - Delete all transients",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Cron commands
    // =========================================================================

    private function handle_cron_command( $subcmd, $args ) {
        switch ( $subcmd ) {
            case 'list':
                $crons = _get_cron_array();
                if ( empty( $crons ) ) {
                    return array( 'output' => 'No cron events scheduled.', 'success' => true );
                }
                $output = "Scheduled Cron Events:\n";
                $now    = time();
                foreach ( $crons as $timestamp => $hooks ) {
                    $time = date( 'Y-m-d H:i:s', $timestamp );
                    $due  = $timestamp <= $now ? '(overdue)' : 'in ' . human_time_diff( $now, $timestamp );
                    foreach ( $hooks as $hook => $events ) {
                        foreach ( $events as $event ) {
                            $schedule = isset( $event['schedule'] ) && $event['schedule'] ? $event['schedule'] : 'single';
                            $output  .= "  [$time] $hook  [$schedule] $due\n";
                        }
                    }
                }
                return array( 'output' => rtrim( $output ), 'success' => true );

            case 'run':
                if ( empty( $args[0] ) ) {
                    return array( 'output' => 'Usage: cron run <hook>', 'success' => false );
                }
                $hook  = sanitize_key( $args[0] );
                $crons = _get_cron_array();
                $found = false;
                foreach ( $crons as $timestamp => $hooks ) {
                    if ( isset( $hooks[ $hook ] ) ) {
                        $found = true;
                        foreach ( $hooks[ $hook ] as $event ) {
                            do_action_ref_array( $hook, $event['args'] );
                        }
                        break;
                    }
                }
                if ( ! $found ) {
                    return array( 'output' => "Cron hook not found in schedule: $hook", 'success' => false );
                }
                return array( 'output' => "Cron hook executed: $hook", 'success' => true );

            default:
                return array(
                    'output'  => "Available cron commands:\n  cron list        - List all scheduled cron events\n  cron run <hook>  - Manually trigger a cron hook",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Maintenance mode commands
    // =========================================================================

    private function handle_maintenance_command( $subcmd, $args ) {
        $maintenance_file = ABSPATH . '.maintenance';

        switch ( $subcmd ) {
            case 'on':
            case 'enable':
                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }
                global $wp_filesystem;
                $content = '<?php $upgrading = ' . time() . '; ?>';
                if ( ! $wp_filesystem->put_contents( $maintenance_file, $content, FS_CHMOD_FILE ) ) {
                    return array( 'output' => 'Could not write .maintenance file. Check file permissions.', 'success' => false );
                }
                return array( 'output' => 'Maintenance mode ENABLED. Your site is now showing the maintenance screen to visitors.', 'success' => true );

            case 'off':
            case 'disable':
                if ( ! file_exists( $maintenance_file ) ) {
                    return array( 'output' => 'Maintenance mode is already off.', 'success' => true );
                }
                if ( ! $this->init_filesystem() ) {
                    return array( 'output' => 'Could not initialize filesystem. Check file permissions.', 'success' => false );
                }
                global $wp_filesystem;
                if ( ! $wp_filesystem->delete( $maintenance_file ) ) {
                    return array( 'output' => 'Could not delete .maintenance file. Check file permissions.', 'success' => false );
                }
                return array( 'output' => 'Maintenance mode DISABLED. Your site is now publicly accessible.', 'success' => true );

            case 'status':
                $is_active = file_exists( $maintenance_file );
                return array( 'output' => 'Maintenance mode: ' . ( $is_active ? 'ON' : 'OFF' ), 'success' => true );

            default:
                return array(
                    'output'  => "Available maintenance commands:\n  maintenance on     - Enable maintenance mode\n  maintenance off    - Disable maintenance mode\n  maintenance status - Check current status",
                    'success' => true,
                );
        }
    }

    // =========================================================================
    // Search-replace command
    // =========================================================================

    private function handle_search_replace_command( $args ) {
        $positional = array_values( array_filter( $args, function( $arg ) {
            return strpos( $arg, '--' ) !== 0;
        } ) );

        if ( count( $positional ) < 2 ) {
            return array( 'output' => "Usage: search-replace <old> <new> [--dry-run] [--force]", 'success' => false );
        }

        $search     = $positional[0];
        $replace    = $positional[1];
        $is_dry_run = $this->has_dry_run_flag( $args );

        if ( empty( $search ) ) {
            return array( 'output' => 'Search string cannot be empty.', 'success' => false );
        }

        global $wpdb;

        $tables = array(
            $wpdb->posts         => array( 'post_content', 'post_excerpt', 'post_title', 'guid' ),
            $wpdb->postmeta      => array( 'meta_value' ),
            $wpdb->options       => array( 'option_value' ),
            $wpdb->comments      => array( 'comment_content', 'comment_author_url' ),
            $wpdb->commentmeta   => array( 'meta_value' ),
            $wpdb->usermeta      => array( 'meta_value' ),
            $wpdb->term_taxonomy => array( 'description' ),
            $wpdb->links         => array( 'link_url', 'link_name', 'link_description' ),
        );

        $total_replaced = 0;
        $output         = $is_dry_run
            ? "DRY RUN — no changes will be made.\nSearching for: '$search'\n"
            : "Search-replace: '$search' → '$replace'\n";

        foreach ( $tables as $table => $columns ) {
            foreach ( $columns as $column ) {
                $count = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM `$table` WHERE `$column` LIKE %s",
                    '%' . $wpdb->esc_like( $search ) . '%'
                ) );

                if ( ! $count ) {
                    continue;
                }

                // Detect the primary key so we can update row by row (serialized-safe).
                $pk_col = $wpdb->get_var( "SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'" );

                if ( ! $pk_col ) {
                    // No PK: simple SQL replace, no serialized handling.
                    if ( ! $is_dry_run ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE `$table` SET `$column` = REPLACE(`$column`, %s, %s) WHERE `$column` LIKE %s",
                            $search,
                            $replace,
                            '%' . $wpdb->esc_like( $search ) . '%'
                        ) );
                    }
                    $total_replaced += $count;
                    $output         .= "  $table.$column  $count match(es)\n";
                    continue;
                }

                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT `$pk_col`, `$column` FROM `$table` WHERE `$column` LIKE %s",
                    '%' . $wpdb->esc_like( $search ) . '%'
                ), ARRAY_A );

                $row_count = 0;
                foreach ( $rows as $row ) {
                    $original = $row[ $column ];
                    $updated  = $this->recursive_replace( $search, $replace, $original );

                    if ( $updated === $original ) {
                        continue;
                    }

                    $row_count++;
                    if ( ! $is_dry_run ) {
                        $wpdb->update(
                            $table,
                            array( $column   => $updated ),
                            array( $pk_col   => $row[ $pk_col ] ),
                            array( '%s' ),
                            array( '%s' )
                        );
                    }
                }

                if ( $row_count > 0 ) {
                    $total_replaced += $row_count;
                    $output         .= "  $table.$column  $row_count row(s)\n";
                }
            }
        }

        if ( $total_replaced === 0 ) {
            $output .= "No matches found for: '$search'";
        } else {
            $verb    = $is_dry_run ? 'Would replace' : 'Replaced';
            $output .= "\n$verb $total_replaced row(s) total.";
            if ( $is_dry_run ) {
                $output .= "\nRe-run with --force to apply changes.";
            }
        }

        return array( 'output' => $output, 'success' => true );
    }

    /**
     * Recursively replace a string inside a value, handling serialized data safely.
     */
    private function recursive_replace( $search, $replace, $value ) {
        if ( is_string( $value ) && is_serialized( $value ) ) {
            $unserialized = @unserialize( $value );
            if ( false !== $unserialized || $value === 'b:0;' ) {
                return serialize( $this->recursive_replace( $search, $replace, $unserialized ) );
            }
        }

        if ( is_string( $value ) ) {
            return str_replace( $search, $replace, $value );
        }

        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) {
                $value[ $k ] = $this->recursive_replace( $search, $replace, $v );
            }
            return $value;
        }

        if ( is_object( $value ) ) {
            foreach ( get_object_vars( $value ) as $k => $v ) {
                $value->$k = $this->recursive_replace( $search, $replace, $v );
            }
            return $value;
        }

        return $value;
    }

    // =========================================================================
    // Help
    // =========================================================================

    private function handle_help_command() {
        return array(
            'output'  => $this->get_help_text(),
            'success' => true,
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function resolve_plugin_file( $slug ) {
        $plugins = get_plugins();
        $slug    = sanitize_text_field( $slug );

        if ( isset( $plugins[ $slug ] ) ) {
            return $slug;
        }

        foreach ( $plugins as $plugin_file => $plugin_data ) {
            $plugin_folder = dirname( $plugin_file );
            if ( $plugin_folder === '.' ) {
                $filename = basename( $plugin_file, '.php' );
                if ( $filename === $slug || $plugin_file === $slug . '.php' ) {
                    return $plugin_file;
                }
            } elseif ( $plugin_folder === $slug ) {
                return $plugin_file;
            }
        }

        return null;
    }

    private function get_help_text() {
        return "Available commands:
  help                                  - Show this help message

  plugin list                           - List all plugins (with update status)
  plugin status <slug>                  - Show version, status, update info
  plugin install <slug>                 - Install a plugin from wordpress.org
  plugin activate <slug>                - Activate a plugin
  plugin deactivate <slug>              - Deactivate a plugin
  plugin update <slug>                  - Update a specific plugin
  plugin update --all                   - Update all plugins
  plugin delete <slug>                  - Delete a plugin (requires --force)

  theme list                            - List all themes (with update status)
  theme status <slug>                   - Show version, status, update info
  theme install <slug>                  - Install a theme from wordpress.org
  theme activate <slug>                 - Activate a theme
  theme deactivate [slug]               - Switch to another theme
  theme update <slug>                   - Update a specific theme
  theme update --all                    - Update all themes

  user list                             - List all users
  user get <id|username>                - Get details for a user
  user create <user> <email> <role>     - Create a user (password emailed)
  user update <id|user> --role <role>   - Change a user's role
  user delete <id|username>             - Delete a user (requires --force)

  post list                             - List recent posts
  post get <id>                         - Get details for a post
  post create <title>                   - Create a draft post
  post publish <id>                     - Publish a post
  post unpublish <id>                   - Set a post back to draft
  post delete <id>                      - Permanently delete a post (requires --force)

  db optimize                           - Optimize all WordPress database tables
  db repair                             - Repair all WordPress database tables

  option get <key>                      - Get an option value
  option set <key> <value>              - Set an option value
  option delete <key>                   - Delete an option (requires --force)

  transient get <key>                   - Get a transient value
  transient delete <key>                - Delete a specific transient
  transient flush                       - Delete ALL transients

  cron list                             - List all scheduled cron events
  cron run <hook>                       - Manually trigger a cron hook

  maintenance on                        - Enable maintenance mode
  maintenance off                       - Disable maintenance mode
  maintenance status                    - Check maintenance mode status

  search-replace <old> <new> --dry-run  - Preview matches without making changes
  search-replace <old> <new> --force    - Replace a string sitewide

  cache flush                           - Flush the object cache
  site info                             - Show site information
  site status                           - Show site status

Security notes:
  - Destructive commands require --force
  - search-replace supports --dry-run to safely preview before committing
  - Critical options (siteurl, admin_email, auth keys, etc.) are protected
  - user delete cannot target your own account

Examples:
  plugin update --all
  theme install twentytwentyfive
  user update john --role editor
  search-replace https://old.com https://new.com --dry-run
  maintenance on
  cron list
  option get blogname
  transient flush
  post publish 42";
    }
}

<?php
/**
 * CDW CLI service - command execution, history, and audit logging.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CLI command execution, audit logging, and command history.
 */
/**
 * Handles CLI command execution, audit logging, and command history.
 */
class CDW_CLI_Service {
	const DB_VERSION        = '1.1';
	const TABLE_NAME        = 'cdw_cli_logs';
	const HISTORY_META_KEY  = 'cdw_cli_history';
	const RATE_LIMIT_COUNT  = 20;
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Whether the audit log table has been confirmed to exist.
	 *
	 * @var bool
	 */
	/**
	 * Whether the audit log table has been confirmed to exist.
	 *
	 * @var bool
	 */
	private static $audit_table_confirmed = false;

	/**
	 * Ensure the audit log table exists, creating it if needed.
	 *
	 * @return void
	 */
	/**
	 * Ensure the audit log table exists, creating it if needed.
	 *
	 * @return void
	 */
	public function ensure_audit_table() {
		if ( get_option( 'cdw_db_version' ) === self::DB_VERSION ) {
			return;
		}
		$this->create_audit_log_table();
		update_option( 'cdw_db_version', self::DB_VERSION );
	}

	/**
	 * Create the CDW CLI audit log database table.
	 *
	 * @return void
	 */
	/**
	 * Create the CDW CLI audit log database table.
	 *
	 * @return void
	 */
	public function create_audit_log_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$create_sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			command varchar(500) NOT NULL,
			success tinyint(1) NOT NULL DEFAULT 1,
			outcome varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user_id (user_id),
			KEY idx_created_at (created_at)
		) {$charset_collate}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- CREATE TABLE cannot use placeholders; all interpolated values come from trusted $wpdb properties.
		$result = $wpdb->query( $create_sql );

		// DIAGNOSTIC: emit to PHP error log when running on a real DB (not Brain\Monkey stub).
		if ( isset( $wpdb->ready ) ) {
			$diag_last_error = isset( $wpdb->last_error ) ? $wpdb->last_error : '';
			// Verify table existence immediately using the same $wpdb handle.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
			$diag_current_db  = $wpdb->get_var( 'SELECT DATABASE()' );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$diag_show_tables = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_var_export
			error_log( '[CDW-DIAG] table=' . $table_name . ' current_db=' . $diag_current_db . ' result=' . var_export( $result, true ) . ' last_error=' . $diag_last_error . ' show_tables=' . var_export( $diag_show_tables, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Log a CLI command to the audit table.
	 *
	 * @param int    $user_id  User ID who ran the command.
	 * @param string $command  The command string.
	 * @param bool   $success  Whether the command succeeded.
	 * @param string $outcome  Optional outcome message.
	 * @return void
	 */
	/**
	 * Log a CLI command to the audit table.
	 *
	 * @param int    $user_id  User ID who ran the command.
	 * @param string $command  The command string.
	 * @param bool   $success  Whether the command succeeded.
	 * @param string $outcome  Optional outcome message.
	 * @return void
	 */
	private function log_cli_command( $user_id, $command, $success, $outcome = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( ! self::$audit_table_confirmed ) {
			$exists = $wpdb->get_var(
				"SHOW TABLES LIKE '" . $wpdb->esc_like( $table_name ) . "'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- esc_like sanitizes the value; SHOW TABLES does not support placeholders.
			);
			if ( $table_name !== $exists ) {
				return;
			}
			self::$audit_table_confirmed = true;
		}

		$wpdb->insert(
			$table_name,
			array(
				'user_id' => $user_id,
				'command' => substr( $command, 0, 500 ),
				'success' => $success ? 1 : 0,
				'outcome' => $outcome ? substr( $outcome, 0, 500 ) : null,
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	/**
	 * Retrieve the command history for a user.
	 *
	 * @param int|null $user_id User ID, defaults to current user.
	 * @return array<int,array<string,mixed>> Command history items.
	 */
	/**
	 * Retrieve the command history for a user.
	 *
	 * @param int|null $user_id User ID, defaults to current user.
	 * @return array<int,array<string,mixed>> Command history items.
	 */
	public function get_history( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$history_json = get_user_meta( $user_id, self::HISTORY_META_KEY, true );
		$history      = $history_json ? json_decode( $history_json, true ) : array();

		return is_array( $history ) ? $history : array();
	}

	/**
	 * Clear the command history for a user.
	 *
	 * @param int|null $user_id User ID, defaults to current user.
	 * @return bool True on success, false on failure.
	 */
	/**
	 * Clear the command history for a user.
	 *
	 * @param int|null $user_id User ID, defaults to current user.
	 * @return bool True on success, false on failure.
	 */
	public function clear_history( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		return delete_user_meta( $user_id, self::HISTORY_META_KEY );
	}

	/**
	 * Add a command result to the user's history (max 50 entries).
	 *
	 * @param int    $user_id User ID.
	 * @param string $command Command that was run.
	 * @param string $output  Command output.
	 * @param bool   $success Whether the command succeeded.
	 * @return int|bool Meta ID on creation, true on update, false on failure.
	 */
	/**
	 * Add a command result to the user's history (max 50 entries).
	 *
	 * @param int    $user_id User ID.
	 * @param string $command Command that was run.
	 * @param string $output  Command output.
	 * @param bool   $success Whether the command succeeded.
	 * @return int|bool Meta ID on creation, true on update, false on failure.
	 */
	public function add_to_history( $user_id, $command, $output, $success ) {
		$history_item = array(
			'command'   => $command,
			'output'    => $output,
			'success'   => $success,
			'timestamp' => time(),
		);

		$history = $this->get_history( $user_id );
		array_unshift( $history, $history_item );
		$history = array_slice( $history, 0, 50 );

		return update_user_meta( $user_id, self::HISTORY_META_KEY, wp_json_encode( $history ) );
	}

	/**
	 * Check whether the user has exceeded the CLI rate limit.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if within limit, false if exceeded.
	 */
	/**
	 * Check whether the user has exceeded the CLI rate limit.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if within limit, false if exceeded.
	 */
	public function check_rate_limit( $user_id ) {
		$key   = 'cdw_cli_rate_' . $user_id;
		$count = get_transient( $key );

		// get_transient returns false when not set; (int) false === 0.
		if ( (int) $count >= self::RATE_LIMIT_COUNT ) {
			return false;
		}

		set_transient( $key, (int) $count + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Check whether the CLI feature is enabled.
	 *
	 * @return mixed Option value (truthy when enabled).
	 */
	/**
	 * Check whether the CLI feature is enabled.
	 *
	 * @return mixed Option value (truthy when enabled).
	 */
	public function is_cli_enabled() {
		return get_option( 'cdw_cli_enabled', true );
	}

	/**
	 * Check whether a WordPress option is protected from CLI modification.
	 *
	 * @param string $option_name Option name to check.
	 * @return bool True if the option is protected.
	 */
	/**
	 * Check whether a WordPress option is protected from CLI modification.
	 *
	 * @param string $option_name Option name to check.
	 * @return bool True if the option is protected.
	 */
	public function is_option_protected( $option_name ) {
		// Ensure the base controller is loaded (it is, via class-cdw-rest-api.php load chain).
		if ( class_exists( 'CDW_Base_Controller' ) ) {
			return in_array( $option_name, CDW_Base_Controller::$protected_options, true );
		}
		return false;
	}

	/**
	 * Execute a CLI command string on behalf of a user.
	 *
	 * @param string $command           The command string to execute.
	 * @param int    $user_id           User ID running the command.
	 * @param bool   $bypass_rate_limit Whether to skip rate-limit enforcement.
	 * @return array<string,mixed>|WP_Error Result array or WP_Error on failure.
	 */
	/**
	 * Execute a CLI command string on behalf of a user.
	 *
	 * @param string $command           The command string to execute.
	 * @param int    $user_id           User ID running the command.
	 * @param bool   $bypass_rate_limit Whether to skip rate-limit enforcement.
	 * @return array<string,mixed>|WP_Error Result array or WP_Error on failure.
	 */
	public function execute( $command, $user_id, $bypass_rate_limit = false ) {
		if ( ! $this->is_cli_enabled() ) {
			return new WP_Error(
				'cli_disabled',
				__( 'CLI feature is disabled in settings.', 'cdw' ),
				array( 'status' => 403 )
			);
		}

		if ( '' === trim( (string) $command ) ) {
			return new WP_Error( 'empty_command', 'Command cannot be empty', array( 'status' => 400 ) );
		}

		if ( ! $bypass_rate_limit && ! $this->check_rate_limit( $user_id ) ) {
			return new WP_Error( 'rate_limited', 'Rate limit exceeded. Max 20 commands per minute.', array( 'status' => 429 ) );
		}

		$this->ensure_audit_table();

		$command  = wp_unslash( $command );
		$parts    = preg_split( '/\s+/', trim( $command ) );
		$cmd      = strtolower( $parts[0] ?? '' );
		$subcmd   = strtolower( $parts[1] ?? '' );
		$raw_args = 'search-replace' === $cmd ? array_slice( $parts, 1 ) : array_slice( $parts, 2 );

		$force_bypass = $this->has_dry_run_flag( $raw_args );

		if ( $this->command_requires_force( $cmd, $subcmd ) && ! $this->has_force_flag( $raw_args ) && ! $force_bypass ) {
			if ( 'search-replace' === $cmd ) {
				$error  = "This command requires --force or --dry-run.\n";
				$error .= "Usage: search-replace <old> <new> [--dry-run] [--force]\n";
				$error .= 'Tip: use --dry-run first to preview matches without making changes.';
			} else {
				$error  = "This command requires the --force flag for safety.\n";
				$error .= "Usage: $cmd $subcmd --force\n";
				$error .= 'Example: plugin delete woocommerce --force';
			}

			$this->log_cli_command( $user_id, $command, false );

			return array(
				'success'        => false,
				'output'         => $error,
				'command'        => $command,
				'requires_force' => true,
			);
		}

		$clean_args = array_values(
			array_filter(
				$raw_args,
				function ( $arg ) {
					return ! in_array( strtolower( $arg ), array( '--force', '--all', '--dry-run', '--delete-content' ), true )
					&& ! preg_match( '/^--reassign=\d+$/', $arg );
				}
			)
		);

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
					$result = $this->handle_user_command( $subcmd, $clean_args, $raw_args );
					break;
				case 'post':
					$result = $this->handle_post_command( $subcmd, $clean_args, $raw_args );
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
					$result = $this->handle_search_replace_command( $clean_args, $raw_args );
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
			} elseif ( ! $success ) {
				$output = $error;
			}
		} catch ( Error $e ) {
			$success = false;
			$error   = 'Error: ' . $e->getMessage();
			error_log( '[CDW CLI] Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional production error logging for CLI. // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional production error logging for CLI.
		} catch ( Exception $e ) {
			$success = false;
			$error   = $e->getMessage();
		}

		// Use $output when non-empty (it holds the handler's message for both
		// success and failure), falling back to $error which is set by the
		// catch blocks or the "unknown command" / default branch.
		$final_output = ! empty( $output ) ? $output : $error;

		$this->log_cli_command( $user_id, $command, $success, $final_output );
		$this->add_to_history( $user_id, $command, $final_output, $success );

		return array(
			'success' => $success,
			'output'  => $final_output,
			'command' => $command,
		);
	}

	/**
	 * Check whether the --force flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	/**
	 * Check whether the --force flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	private function has_force_flag( $args ) {
		return in_array( '--force', $args, true );
	}

	/**
	 * Check whether the --dry-run flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	/**
	 * Check whether the --dry-run flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	private function has_dry_run_flag( $args ) {
		return in_array( '--dry-run', $args, true );
	}

	/**
	 * Check whether the --all flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	/**
	 * Check whether the --all flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	private function has_all_flag( $args ) {
		return in_array( '--all', $args, true );
	}

	/**
	 * Check whether a command/subcommand combination requires the --force flag.
	 *
	 * @param string $cmd    Top-level command (e.g. "plugin").
	 * @param string $subcmd Subcommand (e.g. "delete").
	 * @return bool
	 */
	/**
	 * Check whether a command/subcommand combination requires the --force flag.
	 *
	 * @param string $cmd    Top-level command (e.g. "plugin").
	 * @param string $subcmd Subcommand (e.g. "delete").
	 * @return bool
	 */
	private function command_requires_force( $cmd, $subcmd ) {
		if ( 'search-replace' === $cmd ) {
			return true;
		}

		$dangerous_commands = array(
			'plugin' => array( 'delete', 'update', 'install' ),
			'theme'  => array( 'delete', 'update', 'install' ),
			'user'   => array( 'delete' ),
			'post'   => array( 'delete' ),
			'db'     => array( 'export', 'import' ),
		);

		return isset( $dangerous_commands[ $cmd ] ) && in_array( $subcmd, $dangerous_commands[ $cmd ], true );
	}

	/**
	 * Initialise the WP_Filesystem API.
	 *
	 * @return bool True on success, false on failure.
	 */
	/**
	 * Initialise the WP_Filesystem API.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function init_filesystem() {
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$credentials = request_filesystem_credentials( '', '', false, '', null );

		if ( false === $credentials ) {
			return false;
		}

		if ( ! WP_Filesystem( $credentials ) ) {
			request_filesystem_credentials( '', '', true, '', null );
			return false;
		}

		return true;
	}

	/**
	 * Resolve a plugin slug to its main plugin file path.
	 *
	 * @param string $slug Plugin slug (folder name or base filename).
	 * @return string|false Plugin file relative path, or false if not found.
	 */
	/**
	 * Resolve a plugin slug to its main plugin file path.
	 *
	 * @param string $slug Plugin slug (folder name or base filename).
	 * @return string|false Plugin file relative path, or false if not found.
	 */
	private function resolve_plugin_file( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		foreach ( $plugins as $file => $plugin ) {
			$plugin_slug = dirname( $file );
			if ( '.' === $plugin_slug ) {
				$plugin_slug = basename( $file, '.php' );
			}
			if ( $plugin_slug === $slug || basename( $file, '.php' ) === $slug ) {
				return $file;
			}
		}

		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		if ( file_exists( $wp_content_dir . '/plugins/' . $slug ) ) {
			$files = scandir( $wp_content_dir . '/plugins/' . $slug );
			if ( ! is_array( $files ) ) {
				return false;
			}
			foreach ( $files as $file ) {
				if ( substr( $file, -4 ) === '.php' ) {
					return $slug . '/' . $file;
				}
			}
		}

		return false;
	}

	/**
	 * Handle plugin management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, install, activate, etc.).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle plugin management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, install, activate, etc.).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_plugin_command( $subcmd, $args, $raw_args = array() ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		switch ( $subcmd ) {
			case 'list':
				$plugins = get_plugins();
				$updates = get_site_transient( 'update_plugins' );
				$output  = "Installed Plugins:\n";
				if ( empty( $plugins ) ) {
					$output .= '(no plugins installed)';
				}
				foreach ( $plugins as $path => $plugin ) {
					$status     = is_plugin_active( $path ) ? '[Active]  ' : '[Inactive]';
					$has_update = ! empty( $updates->response[ $path ] ) ? ' [Update available]' : '';
					$output    .= "$status " . $plugin['Name'] . ' v' . $plugin['Version'] . ' (' . dirname( $path ) . ")$has_update\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'status':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: plugin status <plugin-slug>',
						'success' => false,
					);
				}
				$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
				if ( ! $plugin_file ) {
					return array(
						'output'  => 'Plugin not found: ' . $args[0],
						'success' => false,
					);
				}
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$data       = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
				$updates    = get_site_transient( 'update_plugins' );
				$has_update = ! empty( $updates->response[ $plugin_file ] );
				$new_ver    = $has_update ? $updates->response[ $plugin_file ]->new_version : null;
				$output     = 'Plugin:  ' . $data['Name'] . "\n";
				$output    .= 'Status:  ' . ( is_plugin_active( $plugin_file ) ? 'Active' : 'Inactive' ) . "\n";
				$output    .= 'Version: ' . $data['Version'] . "\n";
				$output    .= 'Update:  ' . ( $has_update ? "Available (v$new_ver)" : 'Up to date' ) . "\n";
				$output    .= "File:    $plugin_file\n";
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'activate':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: plugin activate <plugin-slug>',
						'success' => false,
					);
				}
				$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
				if ( ! $plugin_file ) {
					return array(
						'output'  => 'Plugin not found: ' . $args[0],
						'success' => false,
					);
				}
				$result = activate_plugin( $plugin_file );
				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Activation failed: ' . $result->get_error_message(),
						'success' => false,
					);
				}
				return array(
					'output'  => 'Plugin activated: ' . dirname( $plugin_file ),
					'success' => true,
				);

			case 'deactivate':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: plugin deactivate <plugin-slug>',
						'success' => false,
					);
				}
				$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
				if ( ! $plugin_file ) {
					return array(
						'output'  => 'Plugin not found: ' . $args[0],
						'success' => false,
					);
				}
				deactivate_plugins( $plugin_file );
				return array(
					'output'  => 'Plugin deactivated: ' . dirname( $plugin_file ),
					'success' => true,
				);

			case 'update':
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				if ( ! $this->init_filesystem() ) {
					return array(
						'output'  => 'Could not initialize filesystem. Check file permissions.',
						'success' => false,
					);
				}

				$updates = get_site_transient( 'update_plugins' );

				if ( $this->has_all_flag( $raw_args ) ) {
					if ( empty( $updates->response ) ) {
						return array(
							'output'  => 'All plugins are up to date.',
							'success' => true,
						);
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
					return array(
						'output'  => rtrim( $output ),
						'success' => true,
					);
				}

				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: plugin update <slug>  |  plugin update --all',
						'success' => false,
					);
				}
				$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
				if ( ! $plugin_file ) {
					return array(
						'output'  => 'Plugin not found: ' . $args[0],
						'success' => false,
					);
				}
				if ( empty( $updates->response[ $plugin_file ] ) ) {
					return array(
						'output'  => 'Plugin is already up to date: ' . $args[0],
						'success' => true,
					);
				}
				$skin     = new WP_Ajax_Upgrader_Skin();
				$upgrader = new Plugin_Upgrader( $skin );
				$result   = $upgrader->upgrade( $plugin_file );
				if ( false === $result ) {
					$skin_errors = $skin->get_errors();
					$error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
						? $skin_errors->get_error_message()
						: 'Could not connect to the filesystem.';
					return array(
						'output'  => 'Update failed: ' . $error_msg,
						'success' => false,
					);
				}
				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Update failed: ' . $result->get_error_message(),
						'success' => false,
					);
				}
				wp_cache_delete( 'plugins', 'plugins' );
				return array(
					'output'  => 'Plugin updated: ' . $args[0],
					'success' => true,
				);

			case 'delete':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: plugin delete <plugin-slug> --force',
						'success' => false,
					);
				}
				$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
				if ( ! $plugin_file ) {
					return array(
						'output'  => 'Plugin not found: ' . $args[0],
						'success' => false,
					);
				}
				if ( is_plugin_active( $plugin_file ) ) {
					return array(
						'output'  => 'Cannot delete active plugin. Deactivate first.',
						'success' => false,
					);
				}
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				if ( ! $this->init_filesystem() ) {
					return array(
						'output'  => 'Could not initialize filesystem. Check file permissions.',
						'success' => false,
					);
				}
				$result = delete_plugins( array( $plugin_file ) );
				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Delete failed: ' . $result->get_error_message(),
						'success' => false,
					);
				}
				return array(
					'output'  => 'Plugin deleted: ' . dirname( $plugin_file ),
					'success' => true,
				);

			case 'install':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: plugin install <plugin-slug>',
						'success' => false,
					);
				}

				$slug = sanitize_text_field( $args[0] );

				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				if ( ! $this->init_filesystem() ) {
					return array(
						'output'  => 'Could not initialize filesystem. Check file permissions.',
						'success' => false,
					);
				}

				$existing_plugins  = get_plugins();
				$already_installed = false;
				foreach ( $existing_plugins as $existing_file => $plugin_data ) {
					$existing_slug = dirname( $existing_file );
					if ( '.' === $existing_slug ) {
						$existing_slug = basename( $existing_file, '.php' );
					}
					if ( $existing_slug === $slug || basename( $existing_file, '.php' ) === $slug ) {
						$already_installed = true;
						break;
					}
				}

				$api = plugins_api(
					'plugin_information',
					array(
						'slug'   => $slug,
						'fields' => array( 'sections' => false ),
					)
				);

				if ( is_wp_error( $api ) ) {
					return array(
						'output'  => 'Plugin not found in repository: ' . $slug . ' - ' . $api->get_error_message(),
						'success' => false,
					);
				}

				$skin     = new WP_Ajax_Upgrader_Skin();
				$upgrader = new Plugin_Upgrader( $skin );
				$result   = $upgrader->install(
					$api->download_link,
					array(
						'overwrite_package' => $already_installed,
					)
				);

				if ( false === $result ) {
					$skin_errors = $skin->get_errors();
					$error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
						? $skin_errors->get_error_message()
						: 'Could not connect to the filesystem. Check permissions.';
					return array(
						'output'  => 'Install failed: ' . $error_msg,
						'success' => false,
					);
				}

				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Install failed: ' . $result->get_error_message(),
						'success' => false,
					);
				}

				wp_cache_delete( 'plugins', 'plugins' );
				$verb = $already_installed ? 're-installed' : 'installed';
				return array(
					'output'  => "Plugin $verb successfully: $slug",
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available plugin commands:\n  plugin list              - List all plugins\n  plugin status <slug>     - Show status for a plugin\n  plugin install <slug>    - Install a plugin\n  plugin activate <slug>   - Activate a plugin\n  plugin deactivate <slug> - Deactivate a plugin\n  plugin update <slug>     - Update a plugin\n  plugin update --all      - Update all plugins\n  plugin delete <slug>     - Delete a plugin (requires --force)",
					'success' => true,
				);
		}
	}

	/**
	 * Handle theme management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, install, activate, etc.).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle theme management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, install, activate, etc.).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
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
					$output    .= "$status " . $theme->get( 'Name' ) . ' v' . $theme->get( 'Version' ) . ' (' . $theme->get_stylesheet() . ")$has_update\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'status':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: theme status <theme-slug>',
						'success' => false,
					);
				}
				$slug   = sanitize_text_field( $args[0] );
				$themes = wp_get_themes();
				if ( ! isset( $themes[ $slug ] ) ) {
					return array(
						'output'  => "Theme not found: $slug",
						'success' => false,
					);
				}
				$theme      = $themes[ $slug ];
				$current    = wp_get_theme();
				$updates    = get_site_transient( 'update_themes' );
				$has_update = ! empty( $updates->response[ $slug ] );
				$new_ver    = $has_update ? $updates->response[ $slug ]['new_version'] : null;
				$output     = 'Theme:   ' . $theme->get( 'Name' ) . "\n";
				$output    .= 'Status:  ' . ( $theme->get_stylesheet() === $current->get_stylesheet() ? 'Active' : 'Inactive' ) . "\n";
				$output    .= 'Version: ' . $theme->get( 'Version' ) . "\n";
				$output    .= 'Update:  ' . ( $has_update ? "Available (v$new_ver)" : 'Up to date' ) . "\n";
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'activate':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: theme activate <theme-slug>',
						'success' => false,
					);
				}
				$slug = sanitize_text_field( $args[0] );
				if ( ! wp_get_theme( $slug )->exists() ) {
					return array(
						'output'  => "Theme not found: $slug",
						'success' => false,
					);
				}
				switch_theme( $slug );
				return array(
					'output'  => "Theme activated: $slug",
					'success' => true,
				);

			case 'delete':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: theme delete <theme-slug> --force',
						'success' => false,
					);
				}
				$slug  = sanitize_text_field( $args[0] );
				$theme = wp_get_theme( $slug );
				if ( ! $theme->exists() ) {
					return array(
						'output'  => "Theme not found: $slug",
						'success' => false,
					);
				}
				if ( $theme->get_stylesheet() === wp_get_theme()->get_stylesheet() ) {
					return array(
						'output'  => 'Cannot delete active theme.',
						'success' => false,
					);
				}
				require_once ABSPATH . 'wp-admin/includes/file.php';
				if ( ! $this->init_filesystem() ) {
					return array(
						'output'  => 'Could not initialize filesystem.',
						'success' => false,
					);
				}
				global $wp_filesystem;
				$theme_dir = $theme->get_theme_root() . '/' . $slug;
				$result    = $wp_filesystem->delete( $theme_dir, true );
				if ( ! $result ) {
					return array(
						'output'  => 'Delete failed: could not remove theme directory. Check file permissions.',
						'success' => false,
					);
				}
				return array(
					'output'  => "Theme deleted: $slug",
					'success' => true,
				);

			case 'install':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: theme install <theme-slug>',
						'success' => false,
					);
				}
				$slug = sanitize_text_field( $args[0] );

				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';
				require_once ABSPATH . 'wp-admin/includes/theme.php';
				require_once ABSPATH . 'wp-admin/includes/theme-install.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				if ( ! $this->init_filesystem() ) {
					return array(
						'output'  => 'Could not initialize filesystem.',
						'success' => false,
					);
				}

				$api = themes_api(
					'theme_information',
					array(
						'slug'   => $slug,
						'fields' => array( 'sections' => false ),
					)
				);

				if ( is_wp_error( $api ) ) {
					return array(
						'output'  => 'Theme not found in repository: ' . $slug,
						'success' => false,
					);
				}

				$skin     = new WP_Ajax_Upgrader_Skin();
				$upgrader = new Theme_Upgrader( $skin );
				$result   = $upgrader->install( $api->download_link );

				if ( false === $result ) {
					$skin_errors = $skin->get_errors();
					$error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
						? $skin_errors->get_error_message()
						: 'Could not connect to the filesystem. Check permissions.';
					return array(
						'output'  => 'Install failed: ' . $error_msg,
						'success' => false,
					);
				}

				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Install failed: ' . $result->get_error_message(),
						'success' => false,
					);
				}

				return array(
					'output'  => "Theme installed: $slug",
					'success' => true,
				);

			case 'update':
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';
				require_once ABSPATH . 'wp-admin/includes/theme.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

				if ( ! $this->init_filesystem() ) {
					return array(
						'output'  => 'Could not initialize filesystem.',
						'success' => false,
					);
				}

				$updates = get_site_transient( 'update_themes' );

				if ( $this->has_all_flag( $raw_args ) ) {
					if ( empty( $updates->response ) ) {
						return array(
							'output'  => 'All themes are up to date.',
							'success' => true,
						);
					}
					$skin     = new WP_Ajax_Upgrader_Skin();
					$upgrader = new Theme_Upgrader( $skin );
					$results  = $upgrader->bulk_upgrade( array_keys( $updates->response ) );
					$output   = "Theme updates:\n";
					foreach ( $results as $theme_stylesheet => $result ) {
						if ( is_wp_error( $result ) ) {
							$output .= "  FAILED  $theme_stylesheet: " . $result->get_error_message() . "\n";
						} elseif ( false === $result ) {
							$output .= "  FAILED  $theme_stylesheet\n";
						} else {
							$output .= "  Updated $theme_stylesheet\n";
						}
					}
					wp_cache_delete( 'themes', 'themes' );
					return array(
						'output'  => rtrim( $output ),
						'success' => true,
					);
				}

				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: theme update <slug>  |  theme update --all',
						'success' => false,
					);
				}
				$slug  = sanitize_text_field( $args[0] );
				$theme = wp_get_theme( $slug );
				if ( ! $theme->exists() ) {
					return array(
						'output'  => "Theme not found: $slug",
						'success' => false,
					);
				}
				$theme_stylesheet = $theme->get_stylesheet();
				if ( empty( $updates->response[ $theme_stylesheet ] ) ) {
					return array(
						'output'  => "Theme is already up to date: $slug",
						'success' => true,
					);
				}
				$skin     = new WP_Ajax_Upgrader_Skin();
				$upgrader = new Theme_Upgrader( $skin );
				$result   = $upgrader->upgrade( $theme_stylesheet );
				if ( false === $result ) {
					$skin_errors = $skin->get_errors();
					$error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
						? $skin_errors->get_error_message()
						: 'Could not connect to the filesystem.';
					return array(
						'output'  => 'Update failed: ' . $error_msg,
						'success' => false,
					);
				}
				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Update failed: ' . $result->get_error_message(),
						'success' => false,
					);
				}
				wp_cache_delete( 'themes', 'themes' );
				return array(
					'output'  => 'Theme updated: ' . $slug,
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available theme commands:\n  theme list               - List all themes\n  theme status <slug>     - Show status for a theme\n  theme install <slug>   - Install a theme\n  theme activate <slug>  - Activate a theme\n  theme update <slug>    - Update a theme\n  theme update --all    - Update all themes\n  theme delete <slug>    - Delete a theme (requires --force)",
					'success' => true,
				);
		}
	}

	/**
	 * Handle user management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, create, delete, role).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle user management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, create, delete, role).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_user_command( $subcmd, $args, $raw_args = array() ) {
		switch ( $subcmd ) {
			case 'list':
				$users  = get_users( array( 'number' => 200 ) );
				$output = "Users:\n";
				foreach ( $users as $user ) {
					$roles   = implode( ', ', $user->roles );
					$output .= $user->user_login . " (ID: {$user->ID}, $roles)\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'create':
				if ( count( $args ) < 3 ) {
					return array(
						'output'  => 'Usage: user create <username> <email> <role>',
						'success' => false,
					);
				}
				$username = sanitize_user( $args[0] );
				$email    = sanitize_email( $args[1] );
				$role     = sanitize_text_field( $args[2] );

				if ( username_exists( $username ) ) {
					return array(
						'output'  => "User already exists: $username",
						'success' => false,
					);
				}
				if ( ! is_email( $email ) ) {
					return array(
						'output'  => "Invalid email: $email",
						'success' => false,
					);
				}
				if ( ! get_role( $role ) && 'administrator' !== $role ) {
					return array(
						'output'  => "Invalid role: $role",
						'success' => false,
					);
				}

				$user_id = wp_create_user( $username, wp_generate_password(), $email );
				if ( is_wp_error( $user_id ) ) {
					return array(
						'output'  => 'User creation failed: ' . $user_id->get_error_message(),
						'success' => false,
					);
				}

				wp_update_user(
					array(
						'ID'   => $user_id,
						'role' => $role,
					)
				);
				wp_new_user_notification( $user_id, null, 'both' );
				return array(
					'output'  => "User created: $username (ID: $user_id, role: $role). Password reset email sent to $email.",
					'success' => true,
				);

			case 'delete':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: user delete <username> --force',
						'success' => false,
					);
				}
				$identifier = sanitize_text_field( $args[0] );
				$user       = get_user_by( 'login', $identifier );
				if ( ! $user ) {
					$user = get_user_by( 'id', intval( $identifier ) );
				}
				if ( ! $user ) {
					return array(
						'output'  => "User not found: $identifier",
						'success' => false,
					);
				}
				if ( get_current_user_id() === $user->ID ) {
					return array(
						'output'  => 'Cannot delete yourself.',
						'success' => false,
					);
				}
				require_once ABSPATH . 'wp-admin/includes/user.php';
				$reassign_id    = null;
				$delete_content = in_array( '--delete-content', $raw_args, true );
				foreach ( $raw_args as $arg ) {
					if ( preg_match( '/^--reassign=(\d+)$/', $arg, $m ) ) {
						$reassign_id = (int) $m[1];
						break;
					}
				}
				if ( null === $reassign_id && ! $delete_content ) {
					return array(
						'output'  => "user delete requires one of:\n  --reassign=<user_id>  Reassign content to another user\n  --delete-content      Permanently delete all their content\nExample: user delete $identifier --reassign=1 --force",
						'success' => false,
					);
				}
				if ( $reassign_id && ! get_userdata( $reassign_id ) ) {
					return array(
						'output'  => "Reassign target not found: $reassign_id",
						'success' => false,
					);
				}
				$result = wp_delete_user( $user->ID, $reassign_id );
				if ( ! $result ) {
					return array(
						'output'  => 'Delete failed: could not delete user.',
						'success' => false,
					);
				}
				$note = $reassign_id ? " (content reassigned to user $reassign_id)" : ' (content deleted)';
				return array(
					'output'  => "User deleted: $identifier$note",
					'success' => true,
				);

			case 'role':
				if ( count( $args ) < 2 ) {
					return array(
						'output'  => 'Usage: user role <username> <role>',
						'success' => false,
					);
				}
				$identifier = sanitize_text_field( $args[0] );
				$new_role   = sanitize_text_field( $args[1] );

				if ( ! get_role( $new_role ) ) {
					return array(
						'output'  => "Invalid role: $new_role",
						'success' => false,
					);
				}

				$user = get_user_by( 'login', $identifier );
				if ( ! $user ) {
					$user = get_user_by( 'id', intval( $identifier ) );
				}
				if ( ! $user ) {
					return array(
						'output'  => "User not found: $identifier",
						'success' => false,
					);
				}

				$user->set_role( $new_role );
				return array(
					'output'  => "User role updated: $identifier -> $new_role",
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available user commands:\n  user list                - List all users\n  user create <user> <email> <role> - Create user\n  user delete <user>      - Delete user\n  user role <user> <role>  - Change user role",
					'success' => true,
				);
		}
	}

	/**
	 * Handle post management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, delete, status).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle post management commands.
	 *
	 * @param string            $subcmd  Subcommand (list, delete, status).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_post_command( $subcmd, $args, $raw_args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		switch ( $subcmd ) {
			case 'list':
				$post_type = ! empty( $args[0] ) ? sanitize_text_field( $args[0] ) : 'post';
				$posts     = get_posts(
					array(
						'post_type'      => $post_type,
						'posts_per_page' => 20,
					)
				);
				if ( empty( $posts ) ) {
					return array(
						'output'  => "No posts found for type: $post_type",
						'success' => true,
					);
				}
				$output = "Posts (type: $post_type):\n";
				foreach ( $posts as $post ) {
					$status  = $post->post_status;
					$output .= "[$status] {$post->ID}: {$post->post_title}\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'delete':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: post delete <post-id>',
						'success' => false,
					);
				}
				$post_id = intval( $args[0] );
				$post    = get_post( $post_id );
				if ( ! $post ) {
					return array(
						'output'  => "Post not found: $post_id",
						'success' => false,
					);
				}
				$result = wp_delete_post( $post_id, true );
				if ( ! $result ) {
					return array(
						'output'  => 'Delete failed',
						'success' => false,
					);
				}
				return array(
					'output'  => "Post deleted: $post_id",
					'success' => true,
				);

			case 'status':
				if ( empty( $args[0] ) || empty( $args[1] ) ) {
					return array(
						'output'  => 'Usage: post status <post-id> <status>',
						'success' => false,
					);
				}
				$post_id    = intval( $args[0] );
				$new_status = sanitize_text_field( $args[1] );
				$post       = get_post( $post_id );
				if ( ! $post ) {
					return array(
						'output'  => "Post not found: $post_id",
						'success' => false,
					);
				}
				$result = wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $new_status,
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					return array(
						'output'  => 'Failed to update post status: ' . $result->get_error_message(),
						'success' => false,
					);
				}
				if ( ! $result ) {
					return array(
						'output'  => "Failed to update post status: $post_id",
						'success' => false,
					);
				}
				return array(
					'output'  => "Post status updated: $post_id -> $new_status",
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available post commands:\n  post list [<type>]       - List posts\n  post delete <id>        - Delete post\n  post status <id> <status> - Change post status",
					'success' => true,
				);
		}
	}

	/**
	 * Handle site management commands.
	 *
	 * @param string            $subcmd Subcommand (info, status, empty).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle site management commands.
	 *
	 * @param string            $subcmd Subcommand (info, status, empty).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_site_command( $subcmd, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		switch ( $subcmd ) {
			case 'info':
				$output  = "Site Info:\n";
				$output .= 'Name:    ' . get_bloginfo( 'name' ) . "\n";
				$output .= 'URL:     ' . get_bloginfo( 'url' ) . "\n";
				$output .= 'Admin:   ' . get_admin_url() . "\n";
				$output .= 'Version: ' . get_bloginfo( 'version' ) . "\n";
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'status':
				$output  = "Site Status:\n";
				$output .= 'Multisite: ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";
				$output .= 'Debug:     ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled' ) . "\n";
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'empty':
				if ( ! class_exists( 'WP_Optimize' ) ) {
					return array(
						'output'  => 'WP-Optimize plugin is required for this command.',
						'success' => false,
					);
				}
				wp_optimize()->database->clean_all();
				return array(
					'output'  => 'Database optimized',
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available site commands:\n  site info                - Show site info\n  site status             - Show site status\n  site empty              - Optimize database",
					'success' => true,
				);
		}
	}

	/**
	 * Handle cache management commands.
	 *
	 * @param string            $subcmd Subcommand (flush).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle cache management commands.
	 *
	 * @param string            $subcmd Subcommand (flush).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_cache_command( $subcmd, $args ) {
		switch ( $subcmd ) {
			case 'clear':
			case 'flush':
				$type = ! empty( $args[0] ) ? strtolower( sanitize_text_field( $args[0] ) ) : 'all';

				if ( in_array( $type, array( 'all', 'object' ), true ) ) {
					wp_cache_flush();
					return array(
						'output'  => 'Object cache fully flushed.',
						'success' => true,
					);
				}

				$can_flush_group = function_exists( 'wp_cache_flush_group' );
				$output          = "Cleared cache:\n";

				switch ( $type ) {
					case 'posts':
						if ( $can_flush_group ) {
							wp_cache_flush_group( 'posts' );
							wp_cache_flush_group( 'post_meta' );
						} else {
							wp_cache_flush();
						}
						$output .= "  - Post cache\n";
						break;
					case 'terms':
						if ( $can_flush_group ) {
							wp_cache_flush_group( 'terms' );
							wp_cache_flush_group( 'term_meta' );
						} else {
							wp_cache_flush();
						}
						$output .= "  - Term cache\n";
						break;
					case 'comments':
						if ( $can_flush_group ) {
							wp_cache_flush_group( 'comment' );
							wp_cache_flush_group( 'comment_meta' );
						} else {
							wp_cache_flush();
						}
						$output .= "  - Comment cache\n";
						break;
					case 'transients':
						global $wpdb;
						$wpdb->query(
							"DELETE FROM {$wpdb->options} WHERE
                 option_name LIKE '_transient_cdw_%'
                 OR option_name LIKE '_transient_timeout_cdw_%'
                 OR option_name LIKE '_site_transient_cdw_%'
                 OR option_name LIKE '_site_transient_timeout_cdw_%'"
						);
						$output .= "  - CDW transients\n";
						break;
					default:
						return array(
							'output'  => "Unknown cache type: $type\nAvailable: all, object, posts, terms, comments, transients",
							'success' => false,
						);
				}
				return array(
					'output'  => rtrim( $output ),
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available cache commands:\n  cache flush [type]       - Flush cache (all, object, posts, terms, comments, transients)",
					'success' => true,
				);
		}
	}

	/**
	 * Handle database commands.
	 *
	 * @param string            $subcmd Subcommand (size, tables).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle database commands.
	 *
	 * @param string            $subcmd Subcommand (size, tables).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_db_command( $subcmd, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		switch ( $subcmd ) {
			case 'size':
				global $wpdb;
				$result = $wpdb->get_row( 'SELECT ROUND( SUM( data_length + index_length ) / 1024 / 1024, 2 ) AS size FROM information_schema.tables WHERE table_schema = DATABASE()' );
				if ( ! $result || null === $result->size ) {
					return array(
						'output'  => 'Could not determine database size.',
						'success' => false,
					);
				}
				return array(
					'output'  => "Database size: {$result->size} MB",
					'success' => true,
				);

			case 'tables':
				global $wpdb;
				$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );
				$output = "Tables:\n";
				foreach ( $tables as $table ) {
					$output .= $table[0] . "\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available db commands:\n  db size                 - Show database size\n  db tables               - List all tables",
					'success' => true,
				);
		}
	}

	/**
	 * Handle option get/set/delete/list commands.
	 *
	 * @param string            $subcmd Subcommand (get, set, delete, list).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle option get/set/delete/list commands.
	 *
	 * @param string            $subcmd Subcommand (get, set, delete, list).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_option_command( $subcmd, $args ) {
		switch ( $subcmd ) {
			case 'get':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: option get <name>',
						'success' => false,
					);
				}
				$name  = sanitize_text_field( $args[0] );
				$value = get_option( $name );
				if ( false === $value ) {
					return array(
						'output'  => "Option not found: $name",
						'success' => false,
					);
				}
				return array(
					'output'  => "$name = " . ( is_array( $value ) ? wp_json_encode( $value ) : $value ),
					'success' => true,
				);

			case 'set':
				if ( count( $args ) < 2 ) {
					return array(
						'output'  => 'Usage: option set <name> <value>',
						'success' => false,
					);
				}
				$name = sanitize_text_field( $args[0] );
				if ( $this->is_option_protected( $name ) ) {
					return array(
						'output'  => "Cannot modify protected option: $name",
						'success' => false,
					);
				}
				$value   = sanitize_text_field( $args[1] );
				$updated = update_option( $name, $value );
				// update_option() returns false both on DB failure AND when the value is unchanged.
				// Check that the stored value matches what was requested.
				$success = $updated || ( get_option( $name ) === $value );
				return array(
					'output'  => $success ? "Option updated: $name = $value" : "Failed to update option: $name",
					'success' => $success,
				);

			case 'delete':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: option delete <name>',
						'success' => false,
					);
				}
				$name = sanitize_text_field( $args[0] );
				if ( $this->is_option_protected( $name ) ) {
					return array(
						'output'  => "Cannot delete protected option: $name",
						'success' => false,
					);
				}
				$deleted = delete_option( $name );
				return array(
					'output'  => $deleted ? "Option deleted: $name" : "Option not found: $name",
					'success' => $deleted,
				);

			case 'list':
				global $wpdb;
				$options = $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'cdw_%' OR option_name LIKE 'custom_dashboard_widget_%'", ARRAY_A );
				$output  = "CDW Options:\n";
				foreach ( $options as $opt ) {
					$output .= $opt['option_name'] . "\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available option commands:\n  option get <name>        - Get option value\n  option set <name> <val> - Set option value\n  option delete <name>    - Delete option\n  option list             - List CDW options",
					'success' => true,
				);
		}
	}

	/**
	 * Handle transient list/delete commands.
	 *
	 * @param string            $subcmd Subcommand (list, delete).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle transient list/delete commands.
	 *
	 * @param string            $subcmd Subcommand (list, delete).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_transient_command( $subcmd, $args ) {
		switch ( $subcmd ) {
			case 'list':
				global $wpdb;
				$transients = $wpdb->get_results(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%' LIMIT 20",
					ARRAY_A
				);
				$output     = "Transients (first 20):\n";
				foreach ( $transients as $t ) {
					$name    = str_replace( '_transient_', '', $t['option_name'] );
					$output .= "$name\n";
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'delete':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: transient delete <name>',
						'success' => false,
					);
				}
				$key    = sanitize_text_field( $args[0] );
				$result = delete_transient( $key );
				return array(
					'output'  => $result ? "Transient deleted: $key" : "Transient not found: $key",
					'success' => $result,
				);

			default:
				return array(
					'output'  => "Available transient commands:\n  transient list           - List transients\n  transient delete <name> - Delete transient",
					'success' => true,
				);
		}
	}

	/**
	 * Handle cron list/run commands.
	 *
	 * @param string            $subcmd Subcommand (list, run).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle cron list/run commands.
	 *
	 * @param string            $subcmd Subcommand (list, run).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_cron_command( $subcmd, $args ) {
		switch ( $subcmd ) {
			case 'list':
				$crons = _get_cron_array();
				if ( empty( $crons ) ) {
					return array(
						'output'  => 'No scheduled cron events found.',
						'success' => true,
					);
				}
				$output = "Scheduled Cron Events:\n";
				foreach ( $crons as $timestamp => $hooks ) {
					$time = wp_date( 'Y-m-d H:i:s', $timestamp );
					foreach ( $hooks as $hook => $events ) {
						foreach ( $events as $key => $event ) {
							$schedule = isset( $event['schedule'] ) ? $event['schedule'] : 'one-time';
							$output  .= "  [$time] $hook  [$schedule]\n";
						}
					}
				}
				return array(
					'output'  => $output,
					'success' => true,
				);

			case 'run':
				if ( empty( $args[0] ) ) {
					return array(
						'output'  => 'Usage: cron run <hook>',
						'success' => false,
					);
				}
				$hook  = sanitize_key( $args[0] );
				$crons = _get_cron_array();
				if ( empty( $crons ) ) {
					return array(
						'output'  => "Cron hook not found: $hook",
						'success' => false,
					);
				}
				$hook_found = false;
				foreach ( $crons as $timestamp => $hooks ) {
					if ( isset( $hooks[ $hook ] ) ) {
						$hook_found = true;
						foreach ( $hooks[ $hook ] as $event ) {
							$event_args = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
							do_action_ref_array( $hook, $event_args );
						}
					}
				}
				if ( ! $hook_found ) {
					return array(
						'output'  => "Cron hook not found: $hook",
						'success' => false,
					);
				}
				return array(
					'output'  => "Cron hook executed: $hook",
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available cron commands:\n  cron list               - List scheduled cron events\n  cron run <hook>        - Run a cron hook now",
					'success' => true,
				);
		}
	}

	/**
	 * Handle maintenance mode enable/disable commands.
	 *
	 * @param string            $subcmd Subcommand (enable, disable, status).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle maintenance mode enable/disable commands.
	 *
	 * @param string            $subcmd Subcommand (enable, disable, status).
	 * @param array<int,string> $args   Positional arguments.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_maintenance_command( $subcmd, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		switch ( $subcmd ) {
			case 'status':
				$is_active = file_exists( ABSPATH . '.maintenance' );
				return array(
					'output'  => 'Maintenance mode is ' . ( $is_active ? 'enabled' : 'disabled' ) . '.',
					'success' => true,
				);

			case 'enable':
				// WordPress checks .maintenance by including it as PHP and reading $upgrading.
				// The file must define: $upgrading = time().
				$upgrading_time = time();
				$content        = "<?php \$upgrading = $upgrading_time; ?>";
				$written        = file_put_contents( ABSPATH . '.maintenance', $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem cannot write to ABSPATH root without admin credentials; direct write is intentional here. // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem cannot write to ABSPATH root without admin credentials; direct write is intentional here.
				if ( false === $written ) {
					return array(
						'output'  => 'Maintenance mode enable failed: could not write .maintenance file. Check file permissions.',
						'success' => false,
					);
				}
				return array(
					'output'  => 'Maintenance mode enabled',
					'success' => true,
				);

			case 'disable':
				if ( file_exists( ABSPATH . '.maintenance' ) ) {
					if ( ! unlink( ABSPATH . '.maintenance' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- wp_delete_file() wraps unlink but both require the file to exist; unlink gives direct return value needed here.
						return array(
							'output'  => 'Maintenance mode disable failed: could not delete .maintenance file. Check file permissions.',
							'success' => false,
						);
					}
				}
				return array(
					'output'  => 'Maintenance mode disabled',
					'success' => true,
				);

			default:
				return array(
					'output'  => "Available maintenance commands:\n  maintenance enable [msg] - Enable maintenance mode\n  maintenance disable      - Disable maintenance mode",
					'success' => true,
				);
		}
	}

	/**
	 * Handle the search-replace command across all database tables.
	 *
	 * @param array<int,string> $args     Positional arguments [old, new].
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle the search-replace command across all database tables.
	 *
	 * @param array<int,string> $args     Positional arguments [old, new].
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	private function handle_search_replace_command( $args, $raw_args = array() ) {
		if ( count( $args ) < 2 ) {
			return array(
				'output'  => "Usage: search-replace <old> <new> [--dry-run] [--force]\n  old  - Text to search for\n  new  - Replacement text\n  --dry-run - Preview changes without applying",
				'success' => false,
			);
		}

		$old = wp_unslash( $args[0] );
		$new = wp_unslash( $args[1] );
		// Check --dry-run in the original $raw_args (flags are stripped from $args).
		$dry_run = $this->has_dry_run_flag( $raw_args );

		global $wpdb;
		$tables = $wpdb->get_results(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ),
			ARRAY_N
		);

		$output  = "Search & Replace: '$old' -> '$new'\n";
		$output .= $dry_run ? "(DRY RUN - no changes made)\n" : "(APPLYING CHANGES)\n";

		$count = 0;
		foreach ( $tables as $table ) {
			$table_name         = $table[0];
			$table_name_escaped = preg_replace( '/[^a-zA-Z0-9_]/', '', $table_name );
			if ( empty( $table_name_escaped ) ) {
				continue;
			}
			$columns = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name_escaped`", ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name_escaped is sanitized via preg_replace to alphanumeric+underscore only. // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name_escaped is sanitized via preg_replace to alphanumeric+underscore only.

			foreach ( $columns as $column ) {
				$col_name         = $column[0];
				$col_name_escaped = preg_replace( '/[^a-zA-Z0-9_]/', '', $col_name );
				if ( empty( $col_name_escaped ) ) {
					continue;
				}
				$col_type = $column[1];

				if ( stripos( $col_type, 'char' ) === false && stripos( $col_type, 'text' ) === false ) {
					continue;
				}

				if ( $dry_run ) {
					$result = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT COUNT(*) as cnt FROM `$table_name_escaped` WHERE `$col_name_escaped` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backtick-quoted identifiers sanitized via preg_replace.
							'%' . $wpdb->esc_like( $old ) . '%'
						)
					);
					if ( $result && $result[0]->cnt > 0 ) {
						$output .= "  $table_name.$col_name: {$result[0]->cnt} matches\n";
						$count  += $result[0]->cnt;
					}
				} else {
					// Find the primary key column for this table.
					$pk_col = null;
					foreach ( $columns as $col_def ) {
						if ( strtoupper( $col_def[3] ) === 'PRI' ) {
							$pk_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $col_def[0] );
							break;
						}
					}

					if ( ! $pk_col ) {
						// No PK — use bulk SQL REPLACE as best-effort fallback.
						$affected = (int) $wpdb->query(
							$wpdb->prepare(
								"UPDATE `$table_name_escaped` SET `$col_name_escaped` = REPLACE(`$col_name_escaped`, %s, %s) WHERE `$col_name_escaped` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backtick-quoted identifiers sanitized via preg_replace.
								$old,
								$new,
								'%' . $wpdb->esc_like( $old ) . '%'
							)
						);
						if ( $affected > 0 ) {
							$output .= "  $table_name.$col_name: $affected changes (no PK, bulk)\n";
							$count  += $affected;
						}
						continue;
					}

					$rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT `$pk_col`, `$col_name_escaped` FROM `$table_name_escaped` WHERE `$col_name_escaped` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backtick-quoted identifiers sanitized via preg_replace.
							'%' . $wpdb->esc_like( $old ) . '%'
						),
						ARRAY_N
					);

					$changed = 0;
					foreach ( (array) $rows as $row ) {
						$pk_val   = $row[0];
						$original = $row[1];
						$replaced = $this->replace_in_value( $original, $old, $new );
						if ( $replaced !== $original ) {
							$wpdb->update(
								$table_name_escaped,
								array( $col_name_escaped => $replaced ),
								array( $pk_col => $pk_val ),
								array( '%s' ),
								array( '%s' )
							);
							++$changed;
						}
					}

					if ( $changed > 0 ) {
						$output .= "  $table_name.$col_name: $changed changes\n";
						$count  += $changed;
					}
				}
			}
		}

		$output .= "Total: $count replacements";
		return array(
			'output'  => $output,
			'success' => true,
		);
	}

	/**
	 * Replace a string within a value, handling serialized data.
	 *
	 * @param mixed  $value The value to process.
	 * @param string $old   String to search for.
	 * @param string $new   Replacement string.
	 * @return mixed The processed value.
	 */
	/**
	 * Replace a string within a value, handling serialized data.
	 *
	 * @param mixed  $value The value to process.
	 * @param string $old   String to search for.
	 * @param string $new   Replacement string.
	 * @return mixed The processed value.
	 */
	private function replace_in_value( $value, $old, $new ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.newFound -- "new" is the natural English term for the replacement value. // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.newFound -- "new" is the natural English term for the replacement value.
		if ( is_serialized( $value ) ) {
			$data = unserialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- required for DB search-replace of serialized WP data.
			return serialize( $this->replace_in_data( $data, $old, $new ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- required for DB search-replace of serialized WP data.
		}
		return $this->replace_in_data( $value, $old, $new );
	}

	/**
	 * Recursively replace a string within data structures.
	 *
	 * @param mixed  $data The data to process.
	 * @param string $old  String to search for.
	 * @param string $new  Replacement string.
	 * @return mixed The processed data.
	 */
	/**
	 * Recursively replace a string within data structures.
	 *
	 * @param mixed  $data The data to process.
	 * @param string $old  String to search for.
	 * @param string $new  Replacement string.
	 * @return mixed The processed data.
	 */
	private function replace_in_data( $data, $old, $new ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.newFound -- "new" is the natural English term for the replacement value. // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.newFound -- "new" is the natural English term for the replacement value.
		if ( is_string( $data ) ) {
			return str_replace( $old, $new, $data );
		}
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				$new_key         = is_string( $k ) ? str_replace( $old, $new, $k ) : $k;
				$out[ $new_key ] = $this->replace_in_data( $v, $old, $new );
			}
			return $out;
		}
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $prop => $val ) {
				$data->$prop = $this->replace_in_data( $val, $old, $new );
			}
			return $data;
		}
		return $data;
	}

	/**
	 * Handle the help command — return full command reference.
	 *
	 * @return array<string,mixed> Result array.
	 */
	/**
	 * Handle the help command — return full command reference.
	 *
	 * @return array<string,mixed> Result array.
	 */
	private function handle_help_command() {
		return array(
			'output'  => $this->get_help_text(),
			'success' => true,
		);
	}

	/**
	 * Return the full CLI help text listing all available commands.
	 *
	 * @return string Help text.
	 */
	/**
	 * Return the full CLI help text listing all available commands.
	 *
	 * @return string Help text.
	 */
	private function get_help_text() {
		return <<<'HELP'
Available Commands:

Plugin Management:
  plugin list              - List all plugins
  plugin status <slug>    - Show plugin status
  plugin install <slug>   - Install a plugin
  plugin activate <slug>  - Activate a plugin
  plugin deactivate <slug> - Deactivate a plugin
  plugin update <slug>    - Update a plugin
  plugin update --all     - Update all plugins
  plugin delete <slug>    - Delete a plugin

Theme Management:
  theme list              - List all themes
  theme status <slug>     - Show theme status
  theme install <slug>   - Install a theme
  theme activate <slug>  - Activate a theme
  theme delete <slug>    - Delete a theme

User Management:
  user list               - List all users
  user create <user> <email> <role> - Create user
  user delete <user>     - Delete user
  user role <user> <role> - Change user role

Post Management:
  post list [<type>]      - List posts
  post delete <id>        - Delete post
  post status <id> <status> - Change post status

Site Management:
  site info               - Show site info
  site status            - Show site status
  site empty             - Optimize database

Cache:
  cache flush [type]     - Flush cache

Database:
  db size                - Show database size
  db tables              - List all tables

Options:
  option get <name>       - Get option
  option set <name> <val> - Set option
  option delete <name>    - Delete option
  option list             - List CDW options

Transients:
  transient list         - List transients
  transient delete <name> - Delete transient

Cron:
  cron list              - List scheduled cron events
  cron run <hook>        - Run cron hook

Maintenance:
  maintenance enable [msg] - Enable maintenance mode
  maintenance disable    - Disable maintenance mode

Database Search & Replace:
  search-replace <old> <new> [--dry-run] [--force]
HELP;
	}
}

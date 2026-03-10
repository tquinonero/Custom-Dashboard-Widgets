<?php
/**
 * CDW CLI service - command execution, history, and audit logging.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-plugin-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-theme-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-user-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-post-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-page-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-cache-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-media-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-site-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-option-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-transient-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-cron-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-task-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-block-patterns-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-skill-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-comment-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-maintenance-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-rewrite-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-core-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-db-handler.php';
require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/class-cdw-search-replace-handler.php';

/**
 * Handles CLI command execution, audit logging, and command history.
 */
class CDW_CLI_Service {
	const DB_VERSION        = '1.2';
	const TABLE_NAME        = 'cdw_cli_logs';
	const HISTORY_META_KEY  = 'cdw_cli_history';
	const RATE_LIMIT_COUNT  = 20;
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Commands that are always blocked when executed via the AI agentic loop,
	 * regardless of the current user's role or execution mode.
	 *
	 * Format: [ [cmd, subcmd], … ]. An empty subcmd ('') matches any subcmd.
	 *
	 * @var array<int,array<int,string>>
	 */
	const BLOCKED_AI_COMMANDS = array(
		array( 'db', 'export' ),
		array( 'db', 'import' ),
	);

	/**
	 * Whether the audit log table has been confirmed to exist.
	 *
	 * @var bool
	 */
	private static $audit_table_confirmed = false;

	/**
	 * Get a command handler instance for a given command.
	 *
	 * @param string $cmd The command name (e.g., 'plugin', 'theme').
	 * @return CDW_Command_Handler_Interface|null Handler instance or null if not found.
	 */
	private function get_handler( string $cmd ): ?CDW_Command_Handler_Interface {
		$handlers = array(
			'plugin'         => 'CDW_Plugin_Handler',
			'theme'          => 'CDW_Theme_Handler',
			'user'           => 'CDW_User_Handler',
			'post'           => 'CDW_Post_Handler',
			'page'           => 'CDW_Page_Handler',
			'cache'          => 'CDW_Cache_Handler',
			'media'          => 'CDW_Media_Handler',
			'site'           => 'CDW_Site_Handler',
			'option'         => 'CDW_Option_Handler',
			'transient'      => 'CDW_Transient_Handler',
			'cron'           => 'CDW_Cron_Handler',
			'task'           => 'CDW_Task_Handler',
			'block-patterns' => 'CDW_Block_Patterns_Handler',
			'skill'          => 'CDW_Skill_Handler',
			'comment'        => 'CDW_Comment_Handler',
			'maintenance'    => 'CDW_Maintenance_Handler',
			'rewrite'        => 'CDW_Rewrite_Handler',
			'core'           => 'CDW_Core_Handler',
			'db'             => 'CDW_DB_Handler',
			'search-replace' => 'CDW_Search_Replace_Handler',
		);

		$handler_class = $handlers[ $cmd ] ?? null;

		if ( ! $handler_class || ! class_exists( $handler_class ) ) {
			return null;
		}

		return new $handler_class();
	}

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
	 * Create or upgrade the CDW CLI audit log database table.
	 *
	 * Uses dbDelta() so that new columns are added to pre-existing tables
	 * without losing any existing rows.
	 *
	 * @return void
	 */
	public function create_audit_log_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$create_sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			command varchar(500) NOT NULL,
			success tinyint(1) NOT NULL DEFAULT 1,
			outcome varchar(500) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_id (user_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta is the canonical WP way to create/upgrade tables.
		dbDelta( $create_sql );
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
	public function is_cli_enabled() {
		return get_option( 'cdw_cli_enabled', true );
	}

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
	 * Check whether a command is permitted in the AI agentic context.
	 *
	 * Consults BLOCKED_AI_COMMANDS — an allow-all list minus explicitly blocked
	 * operations (e.g. db export/import) that are too risky to execute autonomously.
	 *
	 * @param string $cmd    Top-level command token.
	 * @param string $subcmd Subcommand token.
	 * @return bool True if the command may be executed; false if blocked.
	 */
	public function is_safe_for_ai( $cmd, $subcmd ) {
		foreach ( self::BLOCKED_AI_COMMANDS as $blocked ) {
			if ( $cmd === $blocked[0] && $subcmd === $blocked[1] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Execute a CLI command on behalf of the AI agentic loop.
	 *
	 * Identical to execute() but:
	 * - Checks is_safe_for_ai() and rejects blocked commands with a 403 WP_Error.
	 * - Bypasses the CLI rate limit (the AI layer has its own per-user rate limit).
	 *
	 * @param string $command The command string to execute.
	 * @param int    $user_id WordPress user ID running the command.
	 * @return array<string,mixed>|WP_Error Result array or WP_Error.
	 */
	public function execute_as_ai( $command, $user_id ) {
		$parts  = preg_split( '/\s+/', trim( (string) $command ) );
		$cmd    = strtolower( $parts[0] ?? '' );
		$subcmd = strtolower( $parts[1] ?? '' );

		if ( ! $this->is_safe_for_ai( $cmd, $subcmd ) ) {
			return new WP_Error(
				'ai_blocked_command',
				/* translators: %1$s: command token, %2$s: subcommand token */
				sprintf( __( 'Command "%1$s %2$s" is not permitted in AI mode.', 'cdw' ), $cmd, $subcmd ),
				array( 'status' => 403 )
			);
		}

		// AI has its own rate limit; bypass the CLI-level rate limit here.
		return $this->execute( $command, $user_id, true );
	}

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
		$raw_args = array_map( 'rawurldecode', $raw_args );

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
					return ! in_array( strtolower( $arg ), array( '--force', '--all', '--dry-run', '--delete-content', '--publish' ), true )
					&& ! preg_match( '/^--reassign=\d+$/', $arg );
				}
			)
		);

		$output  = '';
		$success = true;
		$error   = '';

		try {
			$result = null;

			$handler = $this->get_handler( $cmd );
			if ( $handler ) {
				$result = $handler->execute( $subcmd, $clean_args, $raw_args );
			} else {
				switch ( $cmd ) {
					case 'help':
						$result = $this->handle_help_command();
						break;
					default:
						$success = false;
						$error   = "Unknown command: $cmd. Type 'help' for available commands.";
				}
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
			error_log( '[CDW CLI] Error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional production error logging for CLI.
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
	private function has_force_flag( $args ) {
		return in_array( '--force', $args, true );
	}

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
	 * Handle the help command — returns full CLI help text.
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
  theme info              - Show active theme details
  theme list              - List all themes
  theme status <slug>     - Show theme status
  theme install <slug>   - Install a theme
  theme update <slug>    - Update a theme
  theme update --all     - Update all themes
  theme activate <slug>  - Activate a theme
  theme delete <slug>    - Delete a theme

User Management:
  user get <username|id>  - Get user details
  user list               - List all users
  user create <user> <email> <role> - Create user
  user delete <user>     - Delete user
  user role <user> <role> - Change user role

Post Management:
  post create <title>    - Create a draft post
  post get <id>           - Get post details
  post list [<type>]      - List posts
  post delete <id>        - Delete post
  post status <id> <status> - Change post status

Page Management:
  page create <title>    - Create a draft page

Site Management:
  site info               - Show site info
  site settings           - Show WordPress settings
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
  maintenance status     - Show maintenance mode status
  maintenance enable     - Enable maintenance mode
  maintenance disable    - Disable maintenance mode

Rewrite:
  rewrite flush          - Flush rewrite rules

Core:
  core version           - Show WP version, PHP version, and update status

Comments:
  comment list [pending|approved|spam]  - List comments (default: pending)
  comment approve <id>                  - Approve a comment
  comment spam <id>                     - Mark a comment as spam
  comment delete <id> --force           - Permanently delete a comment

Database Search & Replace:
  search-replace <old> <new> [--dry-run] [--force]

Tasks:
  task list [--user_id=<id>]                                       - List pending tasks
  task create <name> [--assignee_login=<user>|--assignee_id=<id>] - Create a task
  task delete [--user_id=<id>]                                     - Delete all tasks

Media:
  media list [<count>]              - List recent media attachments (default 20)

Block Patterns:
  block-patterns list [<category>]  - List registered block patterns
HELP;
	}
}

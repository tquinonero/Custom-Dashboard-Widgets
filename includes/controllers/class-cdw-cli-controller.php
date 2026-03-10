<?php
/**
 * CLI REST controller.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/controllers/class-cdw-base-controller.php';
require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

/**
 * Handles CDW REST CLI endpoints: execute, history, and command definitions.
 *
 * @package CDW
 */
class CDW_CLI_Controller extends CDW_Base_Controller {
	/**
	 * CLI service instance.
	 *
	 * @var CDW_CLI_Service
	 */
	private $cli_service;

	/**
	 * Constructor — instantiates the CLI service.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->cli_service = new CDW_CLI_Service();
	}

	/**
	 * Registers CLI REST routes (history GET/DELETE, commands GET, execute POST).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/cli/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cli_history' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cli/history',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'clear_cli_history' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cli/commands',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cli_commands' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cli/execute',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'execute_cli_command' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'command' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Returns the CLI command history for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cli_history() {
		$rate_check = $this->check_rate_limit( 'cli_history_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id = get_current_user_id();
		$history = $this->cli_service->get_history( $user_id );

		return new WP_REST_Response( is_array( $history ) ? $history : array(), 200 );
	}

	/**
	 * Clears the CLI command history for the current user.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_cli_history() {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$rate_check = $this->check_rate_limit( 'cli_history_write', true );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id = get_current_user_id();
		$this->cli_service->clear_history( $user_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'cleared' => true,
			),
			200
		);
	}

	/**
	 * Returns the available CLI command definitions for the autocomplete UI.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cli_commands() {
		$rate_check = $this->check_rate_limit( 'cli_commands_read' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		return new WP_REST_Response( $this->get_command_definitions(), 200 );
	}

	/**
	 * Executes a CLI command and returns the result.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function execute_cli_command( WP_REST_Request $request ) {
		$nonce_check = $this->verify_nonce();
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$command = $request->get_param( 'command' );
		$user_id = get_current_user_id();

		$ob_level = ob_get_level();
		ob_start();

		try {
			$result = $this->cli_service->execute( $command, $user_id );

			ob_end_clean();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			delete_transient( 'cdw_stats_cache' );

			return new WP_REST_Response( $result, 200 );
		} catch ( Exception $e ) {
			while ( ob_get_level() > $ob_level ) {
				ob_end_clean();
			}
			return new WP_Error(
				'cli_error',
				'Command execution failed: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Returns the grouped command definitions used for autocomplete and help text.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_command_definitions() {
		return array(
			array(
				'category' => 'Plugin Management',
				'commands' => array(
					array(
						'name'        => 'plugin list',
						'description' => 'List all plugins',
					),
					array(
						'name'        => 'plugin status <slug>',
						'description' => 'Show plugin status',
					),
					array(
						'name'        => 'plugin install <slug>',
						'description' => 'Install a plugin',
					),
					array(
						'name'        => 'plugin activate <slug>',
						'description' => 'Activate a plugin',
					),
					array(
						'name'        => 'plugin deactivate <slug>',
						'description' => 'Deactivate a plugin',
					),
					array(
						'name'        => 'plugin update <slug>',
						'description' => 'Update a plugin',
					),
					array(
						'name'        => 'plugin update --all',
						'description' => 'Update all plugins',
					),
					array(
						'name'        => 'plugin delete <slug>',
						'description' => 'Delete a plugin (requires --force)',
					),
				),
			),
			array(
				'category' => 'Theme Management',
				'commands' => array(
					array(
						'name'        => 'theme info',
						'description' => 'Show active theme details',
					),
					array(
						'name'        => 'theme list',
						'description' => 'List all themes',
					),
					array(
						'name'        => 'theme status <slug>',
						'description' => 'Show theme status',
					),
					array(
						'name'        => 'theme activate <slug>',
						'description' => 'Activate a theme',
					),
					array(
						'name'        => 'theme install <slug>',
						'description' => 'Install a theme',
					),
					array(
						'name'        => 'theme update <slug>',
						'description' => 'Update a theme',
					),
					array(
						'name'        => 'theme update --all',
						'description' => 'Update all themes',
					),
					array(
						'name'        => 'theme delete <slug>',
						'description' => 'Delete a theme',
					),
				),
			),
			array(
				'category' => 'User Management',
				'commands' => array(
					array(
						'name'        => 'user get <username|id>',
						'description' => 'Get user details',
					),
					array(
						'name'        => 'user list',
						'description' => 'List all users',
					),
					array(
						'name'        => 'user create <user> <email> <role>',
						'description' => 'Create user',
					),
					array(
						'name'        => 'user role <user> <role>',
						'description' => 'Change user role',
					),
					array(
						'name'        => 'user delete <id>',
						'description' => 'Delete a user',
					),
				),
			),
			array(
				'category' => 'Post Management',
				'commands' => array(
					array(
						'name'        => 'post create <title>',
						'description' => 'Create a draft post',
					),
					array(
						'name'        => 'post create <title> --publish',
						'description' => 'Create and publish a post',
					),
					array(
						'name'        => 'post get <id>',
						'description' => 'Get post details',
					),
					array(
						'name'        => 'post list [<type>]',
						'description' => 'List posts',
					),
					array(
						'name'        => 'post count [<type>]',
						'description' => 'Count posts by status',
					),
					array(
						'name'        => 'post status <id> <status>',
						'description' => 'Change post status',
					),
					array(
						'name'        => 'post delete <id>',
						'description' => 'Delete a post',
					),
				),
			),
			array(
				'category' => 'Page Management',
				'commands' => array(
					array(
						'name'        => 'page create <title>',
						'description' => 'Create a draft page',
					),
					array(
						'name'        => 'page create <title> --publish',
						'description' => 'Create and publish a page',
					),
				),
			),
			array(
				'category' => 'Media',
				'commands' => array(
					array(
						'name'        => 'media list',
						'description' => 'List recent media attachments (20)',
					),
					array(
						'name'        => 'media list <count>',
						'description' => 'List N most recent media attachments',
					),
				),
			),
			array(
				'category' => 'Block Patterns',
				'commands' => array(
					array(
						'name'        => 'block-patterns list',
						'description' => 'List all registered block patterns',
					),
					array(
						'name'        => 'block-patterns list <category>',
						'description' => 'List block patterns in a category',
					),
				),
			),
			array(
				'category' => 'Cache',
				'commands' => array(
					array(
						'name'        => 'cache flush',
						'description' => 'Flush all cache',
					),
				),
			),
			array(
				'category' => 'Database',
				'commands' => array(
					array(
						'name'        => 'db size',
						'description' => 'Show database size',
					),
					array(
						'name'        => 'db tables',
						'description' => 'List all tables',
					),
					array(
						'name'        => 'search-replace <old> <new>',
						'description' => 'Search and replace in the database',
					),
				),
			),
			array(
				'category' => 'Options',
				'commands' => array(
					array(
						'name'        => 'option get <name>',
						'description' => 'Get option value',
					),
					array(
						'name'        => 'option list',
						'description' => 'List CDW options',
					),
					array(
						'name'        => 'option set <name> <value>',
						'description' => 'Set option value',
					),
					array(
						'name'        => 'option delete <name>',
						'description' => 'Delete an option',
					),
				),
			),
			array(
				'category' => 'Rewrite',
				'commands' => array(
					array(
						'name'        => 'rewrite flush',
						'description' => 'Flush rewrite rules',
					),
				),
			),
			array(
				'category' => 'Core',
				'commands' => array(
					array(
						'name'        => 'core version',
						'description' => 'Show WP version, PHP version, and update status',
					),
				),
			),
			array(
				'category' => 'Skills',
				'commands' => array(
					array(
						'name'        => 'skill list',
						'description' => 'List all agent skill docs available in installed plugins',
					),
					array(
						'name'        => 'skill get <plugin-slug> <skill-name>',
						'description' => 'Read a plugin skill overview (SKILL.md)',
					),
					array(
						'name'        => 'skill get <plugin-slug> <skill-name> <file>',
						'description' => 'Read a specific file within a plugin skill (e.g. instructions/attributes.md)',
					),
				),
			),
			array(
				'category' => 'Comments',
				'commands' => array(
					array(
						'name'        => 'comment list [pending|approved|spam]',
						'description' => 'List comments (default: pending)',
					),
					array(
						'name'        => 'comment approve <id>',
						'description' => 'Approve a comment',
					),
					array(
						'name'        => 'comment spam <id>',
						'description' => 'Mark a comment as spam',
					),
					array(
						'name'        => 'comment delete <id> --force',
						'description' => 'Permanently delete a comment',
					),
				),
			),
			array(
				'category' => 'Transients',
				'commands' => array(
					array(
						'name'        => 'transient list',
						'description' => 'List all transients',
					),
					array(
						'name'        => 'transient delete <name>',
						'description' => 'Delete a transient',
					),
				),
			),
			array(
				'category' => 'Cron',
				'commands' => array(
					array(
						'name'        => 'cron list',
						'description' => 'List scheduled cron events',
					),
					array(
						'name'        => 'cron run <hook>',
						'description' => 'Run a cron hook immediately',
					),
				),
			),
			array(
				'category' => 'Site',
				'commands' => array(
					array(
						'name'        => 'site info',
						'description' => 'Show site info',
					),
					array(
						'name'        => 'site settings',
						'description' => 'Show WordPress settings',
					),
					array(
						'name'        => 'site status',
						'description' => 'Show site status',
					),
					array(
						'name'        => 'site empty',
						'description' => 'Optimize database',
					),
				),
			),
			array(
				'category' => 'Maintenance',
				'commands' => array(
					array(
						'name'        => 'maintenance status',
						'description' => 'Show maintenance mode status',
					),
					array(
						'name'        => 'maintenance enable',
						'description' => 'Enable maintenance mode',
					),
					array(
						'name'        => 'maintenance disable',
						'description' => 'Disable maintenance mode',
					),
				),
			),
		);
	}
}

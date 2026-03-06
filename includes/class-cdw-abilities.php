<?php
/**
 * WordPress Abilities API registration.
 *
 * Registers all 31 CDW tools as WP_Ability objects so they are discoverable
 * by the WordPress Abilities API (WP 6.9+) and, optionally, by external AI
 * clients via the MCP Adapter plugin.
 *
 * This class is purely additive — the existing REST API and agentic loop are
 * untouched.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers CDW abilities via the WordPress Abilities API.
 *
 * @package CDW
 */
class CDW_Abilities {

	/**
	 * Registers the ability category and all 31 CDW abilities.
	 *
	 * Called unconditionally from CDW_Loader::run(). Bails silently on
	 * WordPress versions older than 6.9 that lack the Abilities API.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( static::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( static::class, 'register_abilities' ) );

		// Opt-in MCP exposure — applied before abilities are finalised.
		if ( get_option( 'cdw_mcp_public', false ) ) {
			add_filter(
				'wp_register_ability_args',
				function ( $args, $ability_name ) {
					if ( str_starts_with( $ability_name, 'cdw/' ) ) {
						$args['meta']['mcp']['public'] = true;
					}
					return $args;
				},
				10,
				2
			);
		}
	}

	/**
	 * Registers the `cdw-admin-tools` ability category.
	 *
	 * Hooked to `wp_abilities_api_categories_init`.
	 *
	 * @return void
	 */
	public static function register_category() {
		wp_register_ability_category(
			'cdw-admin-tools',
			array(
				'label'       => __( 'CDW Admin Tools', 'cdw' ),
				'description' => __( 'WordPress admin management tools provided by CDW.', 'cdw' ),
			)
		);
	}

	/**
	 * Registers all 31 CDW abilities.
	 *
	 * Hooked to `wp_abilities_api_init`.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

		$permission_cb = function () {
			return current_user_can( 'manage_options' );
		};

		$abilities = array(

			// ---------------------------------------------------------------
			// Plugin management
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/plugin-list',
				'label'       => __( 'List Plugins', 'cdw' ),
				'desc'        => __( 'Returns a list of all installed plugins with their activation status, version, and description.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'plugin list',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/plugin-status',
				'label'       => __( 'Plugin Status', 'cdw' ),
				'desc'        => __( 'Returns the activation status and version of a specific plugin identified by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null, // Built dynamically.
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/plugin-activate',
				'label'       => __( 'Activate Plugin', 'cdw' ),
				'desc'        => __( 'Activates an installed plugin by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/plugin-deactivate',
				'label'       => __( 'Deactivate Plugin', 'cdw' ),
				'desc'        => __( 'Deactivates an active plugin by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/plugin-install',
				'label'       => __( 'Install Plugin', 'cdw' ),
				'desc'        => __( 'Downloads and installs a plugin from the WordPress.org repository by slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/plugin-update',
				'label'       => __( 'Update Plugin', 'cdw' ),
				'desc'        => __( 'Updates an installed plugin to the latest available version by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/plugin-delete',
				'label'       => __( 'Delete Plugin', 'cdw' ),
				'desc'        => __( 'Permanently deletes an installed plugin by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => true,
			),

			// ---------------------------------------------------------------
			// Theme management
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/theme-list',
				'label'       => __( 'List Themes', 'cdw' ),
				'desc'        => __( 'Returns a list of all installed themes with their activation status and version.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'theme list',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/theme-activate',
				'label'       => __( 'Activate Theme', 'cdw' ),
				'desc'        => __( 'Activates an installed theme by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/theme-install',
				'label'       => __( 'Install Theme', 'cdw' ),
				'desc'        => __( 'Downloads and installs a theme from the WordPress.org repository by slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/theme-update',
				'label'       => __( 'Update Theme', 'cdw' ),
				'desc'        => __( 'Updates an installed theme to the latest available version by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/theme-info',
				'label'       => __( 'Theme Info', 'cdw' ),
				'desc'        => __( 'Returns details about the currently active theme including name, version, and author.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'theme info',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/theme-status',
				'label'       => __( 'Theme Status', 'cdw' ),
				'desc'        => __( 'Returns the activation status and version of a specific theme identified by its slug.', 'cdw' ),
				'input'       => array(
					'slug' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// User management
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/user-list',
				'label'       => __( 'List Users', 'cdw' ),
				'desc'        => __( 'Returns a list of all WordPress users with their IDs, usernames, roles, and email addresses.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'user list',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/user-create',
				'label'       => __( 'Create User', 'cdw' ),
				'desc'        => __( 'Creates a new WordPress user with the specified username, email address, and role.', 'cdw' ),
				'input'       => array(
					'username' => array(
						'type'     => 'string',
						'required' => true,
					),
					'email'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'role'     => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/user-delete',
				'label'       => __( 'Delete User', 'cdw' ),
				'desc'        => __( 'Permanently deletes a WordPress user identified by their numeric user ID.', 'cdw' ),
				'input'       => array(
					'user_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => true,
			),
			array(
				'name'        => 'cdw/user-get',
				'label'       => __( 'Get User', 'cdw' ),
				'desc'        => __( 'Retrieves details about a specific WordPress user by their ID, username, or email address.', 'cdw' ),
				'input'       => array(
					'identifier' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Cache / options / cron
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/cache-flush',
				'label'       => __( 'Flush Cache', 'cdw' ),
				'desc'        => __( 'Flushes the WordPress object cache, clearing all cached data.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'cache flush',
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/option-get',
				'label'       => __( 'Get Option', 'cdw' ),
				'desc'        => __( 'Retrieves the current value of a WordPress option from the database by its name.', 'cdw' ),
				'input'       => array(
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/option-list',
				'label'       => __( 'List Options', 'cdw' ),
				'desc'        => __( 'Returns all WordPress options stored in the database with their values.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'option list',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/option-set',
				'label'       => __( 'Set Option', 'cdw' ),
				'desc'        => __( 'Creates or updates a WordPress option in the database with the given name and value.', 'cdw' ),
				'input'       => array(
					'name'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'value' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/cron-list',
				'label'       => __( 'List Cron Events', 'cdw' ),
				'desc'        => __( 'Returns a list of all scheduled WordPress cron events with their next run time and recurrence interval.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'cron list',
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Site information
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/site-info',
				'label'       => __( 'Site Info', 'cdw' ),
				'desc'        => __( 'Returns general information about the WordPress site including its name, URL, WordPress version, and active theme.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'site info',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/site-status',
				'label'       => __( 'Site Status', 'cdw' ),
				'desc'        => __( 'Returns the current health and configuration status of the WordPress site.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'site status',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/site-settings',
				'label'       => __( 'Site Settings', 'cdw' ),
				'desc'        => __( 'Returns the configured WordPress site settings such as timezone, date format, and reading/writing options.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'site settings',
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Database
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/db-size',
				'label'       => __( 'Database Size', 'cdw' ),
				'desc'        => __( 'Returns the total size of the WordPress database in bytes.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'db size',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/db-tables',
				'label'       => __( 'Database Tables', 'cdw' ),
				'desc'        => __( 'Returns a list of all WordPress database tables with their sizes and row counts.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'db tables',
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Search-replace
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/search-replace',
				'label'       => __( 'Search & Replace', 'cdw' ),
				'desc'        => __( 'Performs a search and replace across all database tables. Set dry_run to true to preview changes without committing them.', 'cdw' ),
				'input'       => array(
					'search'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'replace' => array(
						'type'     => 'string',
						'required' => true,
					),
					'dry_run' => array(
						'type'     => 'boolean',
						'required' => false,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Maintenance
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/maintenance-on',
				'label'       => __( 'Enable Maintenance Mode', 'cdw' ),
				'desc'        => __( 'Enables WordPress maintenance mode, making the site temporarily unavailable to visitors while showing a maintenance message.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'maintenance on',
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/maintenance-off',
				'label'       => __( 'Disable Maintenance Mode', 'cdw' ),
				'desc'        => __( 'Disables WordPress maintenance mode, restoring normal public access to the site.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'maintenance off',
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Posts
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/post-get',
				'label'       => __( 'Get Post', 'cdw' ),
				'desc'        => __( 'Retrieves the title, content, status, and metadata of a specific WordPress post by its numeric ID.', 'cdw' ),
				'input'       => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
		);

		foreach ( $abilities as $ability ) {
			self::register_one( $ability, $permission_cb );
		}
	}

	/**
	 * Registers a single ability.
	 *
	 * Builds the execute_callback by mapping the ability name to the correct
	 * CLI command string, then calls wp_register_ability().
	 *
	 * @param array<string, mixed> $ability       Ability definition (name, label, input, cli).
	 * @param callable             $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_one( array $ability, callable $permission_cb ) {
		$ability_name = $ability['name'];
		$static_cli   = $ability['cli'];

		$execute_cb = function ( $input = null ) use ( $ability_name, $static_cli ) {
			$cli_command = $static_cli ?? self::build_cli_command( $ability_name, $input );
			$service     = new CDW_CLI_Service();
			$result      = $service->execute_as_ai( $cli_command, get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'output' => $result );
		};

		$args = array(
			'label'               => $ability['label'],
			'description'         => $ability['desc'],
			'category'            => 'cdw-admin-tools',
			'permission_callback' => $permission_cb,
			'execute_callback'    => $execute_cb,
			'show_in_rest'        => true,
			'readonly'            => $ability['readonly'],
			'destructive'         => $ability['destructive'],
			'idempotent'          => $ability['readonly'],
		);

		// Only add input_schema when the ability actually accepts parameters.
		if ( ! empty( $ability['input'] ) ) {
			$args['input_schema'] = array(
				'type'       => 'object',
				'properties' => $ability['input'],
			);
		}

		wp_register_ability( $ability_name, $args );
	}

	/**
	 * Builds a CLI command string from an ability name and user-supplied input.
	 *
	 * Only called for abilities whose CLI string depends on runtime input params.
	 *
	 * @param string               $ability_name Fully-qualified ability name, e.g. `cdw/plugin-activate`.
	 * @param array<string, mixed> $input        Validated input params from the caller.
	 * @return string
	 */
	/**
	 * Strips whitespace from a CLI argument to prevent token injection.
	 *
	 * The CLI service splits commands on whitespace, so any spaces inside a
	 * user-supplied value would be parsed as separate tokens (extra flags or
	 * arguments). Removing them closes that injection vector.
	 *
	 * @param string $value Raw user input.
	 * @return string Sanitized single-token value.
	 */
	private static function sanitize_cli_arg( string $value ): string {
		return preg_replace( '/\s+/', '', trim( $value ) );
	}

	private static function build_cli_command( string $ability_name, array $input ): string {
		switch ( $ability_name ) {
			case 'cdw/plugin-status':
				return 'plugin status ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/plugin-activate':
				return 'plugin activate ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/plugin-deactivate':
				return 'plugin deactivate ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/plugin-install':
				return 'plugin install ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/plugin-update':
				return 'plugin update ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/plugin-delete':
				return 'plugin delete ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/theme-activate':
				return 'theme activate ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/theme-install':
				return 'theme install ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/theme-update':
				return 'theme update ' . self::sanitize_cli_arg( $input['slug'] ) . ' --force';
			case 'cdw/theme-status':
				return 'theme status ' . self::sanitize_cli_arg( $input['slug'] );
			case 'cdw/user-create':
				return 'user create '
					. self::sanitize_cli_arg( $input['username'] ) . ' '
					. self::sanitize_cli_arg( $input['email'] ) . ' '
					. self::sanitize_cli_arg( $input['role'] );
			case 'cdw/user-delete':
				return 'user delete ' . (int) $input['user_id'] . ' --force';
			case 'cdw/user-get':
				return 'user get ' . self::sanitize_cli_arg( $input['identifier'] );
			case 'cdw/option-get':
				return 'option get ' . self::sanitize_cli_arg( $input['name'] );
			case 'cdw/option-set':
				return 'option set ' . self::sanitize_cli_arg( $input['name'] ) . ' ' . self::sanitize_cli_arg( $input['value'] );
			case 'cdw/search-replace':
				$flag = ! empty( $input['dry_run'] ) ? '--dry-run' : '--force';
				return 'search-replace ' . self::sanitize_cli_arg( $input['search'] ) . ' ' . self::sanitize_cli_arg( $input['replace'] ) . ' ' . $flag;
			case 'cdw/post-get':
				return 'post get ' . (int) $input['post_id'];
			default:
				return '';
		}
	}
}

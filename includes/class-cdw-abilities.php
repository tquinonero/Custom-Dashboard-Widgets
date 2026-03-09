<?php
/**
 * WordPress Abilities API registration.
 *
 * Registers all 32 CDW tools as WP_Ability objects so they are discoverable
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
	 * Registers all 32 CDW abilities.
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
			array(
				'name'        => 'cdw/plugin-update-all',
				'label'       => __( 'Update All Plugins', 'cdw' ),
				'desc'        => __( 'Updates all installed plugins that have pending updates.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'plugin update --all',
				'readonly'    => false,
				'destructive' => false,
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
				'name'        => 'cdw/core-version',
				'label'       => __( 'Core Version', 'cdw' ),
				'desc'        => __( 'Returns the current WordPress version, PHP version, and whether a core update is available.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'core version',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/comment-list',
				'label'       => __( 'List Comments', 'cdw' ),
				'desc'        => __( 'Lists comments filtered by status: pending (default), approved, or spam.', 'cdw' ),
				'input'       => array(
					'status' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/comment-approve',
				'label'       => __( 'Approve Comment', 'cdw' ),
				'desc'        => __( 'Approves a comment by ID.', 'cdw' ),
				'input'       => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/comment-spam',
				'label'       => __( 'Spam Comment', 'cdw' ),
				'desc'        => __( 'Marks a comment as spam by ID.', 'cdw' ),
				'input'       => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/comment-delete',
				'label'       => __( 'Delete Comment', 'cdw' ),
				'desc'        => __( 'Permanently deletes a comment by ID. Requires --force.', 'cdw' ),
				'input'       => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => true,
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
						'required' => true,
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
			array(
				'name'        => 'cdw/post-create',
				'label'       => __( 'Create Post', 'cdw' ),
				'desc'        => __( 'Creates a new WordPress post as a draft with the specified title.', 'cdw' ),
				'input'       => array(
					'title' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/page-create',
				'label'       => __( 'Create Page', 'cdw' ),
				'desc'        => __( 'Creates a new WordPress page as a draft with the specified title.', 'cdw' ),
				'input'       => array(
					'title' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Task management
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/task-list',
				'label'       => __( 'List Tasks', 'cdw' ),
				'desc'        => __( 'Lists pending tasks for a user. Omit user_id to list tasks for the current user.', 'cdw' ),
				'input'       => array(
					'user_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/task-create',
				'label'       => __( 'Create Task', 'cdw' ),
				'desc'        => __( 'Creates a new pending task. Optionally assigns it to another user by username (assignee_login) or user ID (assignee_id). Assigning to another user requires administrator privileges.', 'cdw' ),
				'input'       => array(
					'name'           => array(
						'type'     => 'string',
						'required' => true,
					),
					'assignee_login' => array(
						'type'     => 'string',
						'required' => true,
					),
					'assignee_id'    => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/task-delete',
				'label'       => __( 'Delete Tasks', 'cdw' ),
				'desc'        => __( 'Deletes all tasks for a user. Omit user_id to delete tasks for the current user.', 'cdw' ),
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

			// ---------------------------------------------------------------
			// Posts (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/post-list',
				'label'       => __( 'List Posts', 'cdw' ),
				'desc'        => __( 'Returns a list of recent posts, optionally filtered by post type (default: post).', 'cdw' ),
				'input'       => array(
					'type' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/post-count',
				'label'       => __( 'Count Posts', 'cdw' ),
				'desc'        => __( 'Returns the count of posts by status (publish, draft, pending, trash) for each public post type. Excludes attachments.', 'cdw' ),
				'input'       => array(
					'type' => array(
						'type'     => 'string',
						'required' => true,
						'desc'     => __( 'Post type to count (e.g. post, page). Omit for all public post types.', 'cdw' ),
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/post-status',
				'label'       => __( 'Change Post Status', 'cdw' ),
				'desc'        => __( 'Changes the status of an existing post (e.g. draft, publish, trash).', 'cdw' ),
				'input'       => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'status'  => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/post-delete',
				'label'       => __( 'Delete Post', 'cdw' ),
				'desc'        => __( 'Permanently deletes a WordPress post by its numeric ID.', 'cdw' ),
				'input'       => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => true,
			),

			// ---------------------------------------------------------------
			// Users (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/user-role',
				'label'       => __( 'Change User Role', 'cdw' ),
				'desc'        => __( 'Changes the role of an existing WordPress user identified by username or user ID.', 'cdw' ),
				'input'       => array(
					'identifier' => array(
						'type'     => 'string',
						'required' => true,
					),
					'role'       => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Options (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/option-delete',
				'label'       => __( 'Delete Option', 'cdw' ),
				'desc'        => __( 'Deletes a WordPress option from the database by name. Protected core options cannot be deleted.', 'cdw' ),
				'input'       => array(
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => true,
			),

			// ---------------------------------------------------------------
			// Themes (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/theme-delete',
				'label'       => __( 'Delete Theme', 'cdw' ),
				'desc'        => __( 'Permanently deletes an installed theme by its slug. The theme must not be currently active.', 'cdw' ),
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
			array(
				'name'        => 'cdw/theme-update-all',
				'label'       => __( 'Update All Themes', 'cdw' ),
				'desc'        => __( 'Updates all installed themes that have pending updates.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'theme update --all',
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Transients
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/transient-list',
				'label'       => __( 'List Transients', 'cdw' ),
				'desc'        => __( 'Returns the first 20 WordPress transients currently stored in the database.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'transient list',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/transient-delete',
				'label'       => __( 'Delete Transient', 'cdw' ),
				'desc'        => __( 'Deletes a specific WordPress transient by name.', 'cdw' ),
				'input'       => array(
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Rewrite
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/rewrite-flush',
				'label'       => __( 'Flush Rewrite Rules', 'cdw' ),
				'desc'        => __( 'Flushes WordPress rewrite rules, equivalent to saving the permalink settings.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'rewrite flush',
				'readonly'    => false,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Maintenance (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/maintenance-status',
				'label'       => __( 'Maintenance Mode Status', 'cdw' ),
				'desc'        => __( 'Returns whether WordPress maintenance mode is currently enabled or disabled.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'maintenance status',
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Cron (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/cron-run',
				'label'       => __( 'Run Cron Hook', 'cdw' ),
				'desc'        => __( 'Manually triggers a scheduled WordPress cron hook immediately.', 'cdw' ),
				'input'       => array(
					'hook' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => false,
				'destructive' => false,
			),
			// ---------------------------------------------------------------
			// Media
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/media-list',
				'label'       => __( 'List Media', 'cdw' ),
				'desc'        => __( 'Lists recent media attachments with ID, filename, MIME type, and upload date.', 'cdw' ),
				'input'       => array(
					'count' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),
			// ---------------------------------------------------------------
			// Plugin skills
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/skill-list',
				'label'       => __( 'List Plugin Skills', 'cdw' ),
				'desc'        => __( 'Scans all installed plugins for agent skill documentation and returns a list of available skills with their plugin slug and skill name.', 'cdw' ),
				'input'       => array(),
				'cli'         => 'skill list',
				'readonly'    => true,
				'destructive' => false,
			),
			array(
				'name'        => 'cdw/skill-get',
				'label'       => __( 'Get Plugin Skill', 'cdw' ),
				'desc'        => __( 'Returns the contents of a skill documentation file from an installed plugin. Defaults to SKILL.md. Use file to read sub-documents such as instructions/attributes.md.', 'cdw' ),
				'input'       => array(
					'plugin_slug' => array(
						'type'     => 'string',
						'required' => true,
					),
					'skill_name'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'file'        => array(
						'type'     => 'string',
						'required' => false,
					),
				),
				'cli'         => null,
				'readonly'    => true,
				'destructive' => false,
			),

			// ---------------------------------------------------------------
			// Block Patterns
			// ---------------------------------------------------------------
			array(
				'name'        => 'cdw/block-patterns-list',
				'label'       => __( 'List Block Patterns', 'cdw' ),
				'desc'        => __( 'Returns all registered block patterns with name, title, and categories. Optionally filter by category slug.', 'cdw' ),
				'input'       => array(
					'category' => array(
						'type'     => 'string',
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

		// ---------------------------------------------------------------
		// block-patterns-get: abilities-only (no CLI equivalent).
		// Returns raw block markup for a specific pattern by name.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/block-patterns-get',
			array(
				'label'               => __( 'Get Block Pattern Content', 'cdw' ),
				'description'         => __( 'Returns the raw block markup for a specific block pattern by name. Returns base64-encoded content to preserve special characters. Use this to retrieve a pattern before appending it to a page.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$pattern_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					if ( empty( $pattern_name ) ) {
						return new \WP_Error( 'invalid_pattern_name', 'pattern name is required.' );
					}

					$registry = \WP_Block_Patterns_Registry::get_instance();
					$patterns = $registry->get_all_registered();

					$matched = null;
					foreach ( $patterns as $pattern ) {
						if ( $pattern['name'] === $pattern_name ) {
							$matched = $pattern;
							break;
						}
					}

					if ( ! $matched ) {
						return new \WP_Error( 'pattern_not_found', "Pattern not found: $pattern_name" );
					}

					$content = isset( $matched['content'] ) ? $matched['content'] : '';
					if ( empty( $content ) ) {
						return new \WP_Error( 'empty_pattern', "Pattern \"$pattern_name\" has no content." );
					}

					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 encoding for safe transfer
					$content_base64 = base64_encode( $content );

					return array(
						'output'         => "Pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes.',
						'name'           => $pattern_name,
						'title'          => isset( $matched['title'] ) ? $matched['title'] : '',
						'content_length' => strlen( $content ),
						'content_base64' => $content_base64,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Name of the block pattern to retrieve (e.g., blockbase/footer-simple).',
						),
					),
					'required'   => array( 'name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		// ---------------------------------------------------------------
		// post-set-content: abilities-only (no CLI equivalent).
		// Block markup contains quotes, newlines, and angle brackets that
		// cannot survive the whitespace-tokenised CLI command parser.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/post-set-content',
			array(
				'label'               => __( 'Set Post Content', 'cdw' ),
				'description'         => __( 'Replaces the full post_content of an existing post or page with raw block markup. Supply either content (plain string) or content_base64 (base64-encoded string — preferred for block markup because it avoids JSON escaping issues). For large pages: (1) call with content="" to clear, (2) use cdw/post-append-content to push sections.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					if ( isset( $input['content_base64'] ) && '' !== (string) $input['content_base64'] ) {
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 decoding with strict mode
						$content = base64_decode( (string) $input['content_base64'], true );
						if ( false === $content ) {
							return new \WP_Error( 'invalid_base64', 'content_base64 is not valid base64.' );
						}
					} else {
						$content = isset( $input['content'] ) ? (string) $input['content'] : '';
					}

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					if ( ! get_post( $post_id ) ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
					}

					$result = wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $content,
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$total_length = strlen( $content );
					return array( 'output' => "Post $post_id content set. Total content length: $total_length bytes." );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content'        => array(
							'type'        => 'string',
							'description' => 'Raw block markup to write to post_content. Use for short/plain content.',
						),
						'content_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded block markup. Preferred over content when the markup contains JSON block attributes (avoids double-escaping). Provide either content or content_base64, not both.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

			// ---------------------------------------------------------------
			// post-get-content: abilities-only. Returns raw post_content
			// (block markup) so AI can edit it. Supports pagination via
			// offset and limit parameters for large content.
			// ---------------------------------------------------------------
			wp_register_ability(
				'cdw/post-get-content',
				array(
					'label'               => __( 'Get Post Content', 'cdw' ),
					'description'         => __( 'Retrieves the raw post_content of a WordPress post or page, including all Gutenberg block markup. Use offset and limit for pagination on large content. Use this before editing a page with cdw/post-set-content.', 'cdw' ),
					'category'            => 'cdw-admin-tools',
					'permission_callback' => $permission_cb,
					'execute_callback'    => function ( $input = array() ) {
						$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
						$offset   = isset( $input['offset'] ) ? (int) $input['offset'] : 0;
						$limit    = isset( $input['limit'] ) ? (int) $input['limit'] : 5000;

						if ( $post_id <= 0 ) {
							return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
						}
						if ( $limit <= 0 || $limit > 20000 ) {
							$limit = 5000;
						}
						$post = get_post( $post_id );
						if ( ! $post ) {
							return new \WP_Error( 'post_not_found', "Post $post_id not found." );
						}
						if ( ! current_user_can( 'edit_post', $post_id ) ) {
							return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
						}

						$content        = $post->post_content;
						$total_length   = strlen( $content );
						$has_more       = ( $offset + $limit ) < $total_length;
						$chunk_index    = intval( $offset / $limit );
						$chunk_content  = substr( $content, $offset, $limit );

						return array(
							'output'        => $has_more
								? "Post $post_id content retrieved. Chunk $chunk_index (" . strlen( $chunk_content ) . " bytes)."
								: "Post $post_id content retrieved. Length: $total_length bytes.",
							'post_id'       => $post_id,
							'title'         => $post->post_title,
							'content'       => $chunk_content,
							'total_length'  => $total_length,
							'chunk_index'   => $chunk_index,
							'has_more'      => $has_more,
						);
					},
					'input_schema'        => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'description' => 'ID of the post or page to get content from.',
							),
							'offset' => array(
								'type'        => 'integer',
								'description' => 'Starting position for chunked retrieval (default: 0).',
								'default'     => 0,
							),
							'limit' => array(
								'type'        => 'integer',
								'description' => 'Chunk size in characters (default: 5000, max: 20000).',
								'default'     => 5000,
							),
						),
						'required'   => array( 'post_id' ),
					),
					'meta'                => array(
						'show_in_rest' => true,
						'readonly'     => true,
						'idempotent'   => true,
						'annotations'  => array(
							'destructive' => false,
						),
					),
				)
			);

			// ---------------------------------------------------------------
			// post-append-content: abilities-only (no CLI equivalent).
			// Appends a block markup chunk to the existing post_content so large
			// pages can be built in multiple smaller tool calls.
			// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/post-append-content',
			array(
				'label'               => __( 'Append Post Content', 'cdw' ),
				'description'         => __( 'Appends a raw block markup chunk to the existing post_content of a post or page. Supply either content (plain string) or content_base64 (base64-encoded — preferred for block markup to avoid JSON escaping). Workflow: (1) call cdw/post-set-content with content="" to clear the post, (2) call this ability repeatedly with successive chunks. The response includes the running total byte count so you can confirm each chunk landed.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					if ( isset( $input['content_base64'] ) && '' !== (string) $input['content_base64'] ) {
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 decoding with strict mode
						$chunk = base64_decode( (string) $input['content_base64'], true );
						if ( false === $chunk ) {
							return new \WP_Error( 'invalid_base64', 'content_base64 is not valid base64.' );
						}
					} else {
						$chunk = isset( $input['content'] ) ? (string) $input['content'] : '';
					}

					if ( $post_id <= 0 ) {
						return new \WP_Error( 'invalid_post_id', 'post_id is required and must be a positive integer.' );
					}
					$post = get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'post_not_found', "Post $post_id not found." );
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
					}
					if ( '' === $chunk ) {
						return new \WP_Error( 'empty_content', 'content or content_base64 must not be empty.' );
					}

					$new_content = $post->post_content . $chunk;

					$result = wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $new_content,
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$total_length = strlen( $new_content );
					return array( 'output' => "Chunk appended to post $post_id. Total content length: $total_length bytes." );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content'        => array(
							'type'        => 'string',
							'description' => 'Block markup chunk to append. Use for plain/short content.',
						),
						'content_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded block markup chunk to append. Preferred over content when the markup contains JSON block attributes (avoids double-escaping). Provide either content or content_base64, not both.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		// ---------------------------------------------------------------
		// build-page: abilities-only (no CLI equivalent).
		// Accepts structured JSON to generate Gutenberg block markup.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/build-page',
			array(
				'label'               => __( 'Build Page', 'cdw' ),
				'description'         => __( 'Creates a new page or updates an existing one with Gutenberg block markup generated from structured JSON. Input: {"title": "Page Title", "sections": [{"type": "cover", "title": "Hero", "image": "url"}, {"type": "two-column", "left": {...}, "right": {...}}, {"type": "footer", "columns": [...]}]}. Supported section types: cover, two-column, three-column, footer. Returns post_id, title, and section_count.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$title    = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
					$sections = isset( $input['sections'] ) ? (array) $input['sections'] : array();
					$post_id  = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					if ( empty( $title ) ) {
						return new \WP_Error( 'missing_title', 'title is required.' );
					}

					if ( empty( $sections ) ) {
						return new \WP_Error( 'missing_sections', 'sections array is required.' );
					}

					require_once CDW_PLUGIN_DIR . 'includes/renderers/class-cdw-section-renderers.php';

					$content = \CDW_Section_Renderers::render_sections( $sections );

					if ( $post_id > 0 ) {
						$post = get_post( $post_id );
						if ( ! $post ) {
							return new \WP_Error( 'post_not_found', "Post $post_id not found." );
						}
						if ( ! current_user_can( 'edit_post', $post_id ) ) {
							return new \WP_Error( 'forbidden', 'You do not have permission to edit this post.' );
						}

						$result = wp_update_post(
							array(
								'ID'           => $post_id,
								'post_content' => $content,
							),
							true
						);

						if ( is_wp_error( $result ) ) {
							return $result;
						}

						return array(
							'output'         => "Page $post_id updated with " . count( $sections ) . ' sections.',
							'post_id'        => $post_id,
							'title'          => $title,
							'section_count'  => count( $sections ),
							'content_length' => strlen( $content ),
						);
					} else {
						$result = wp_insert_post(
							array(
								'post_title'   => $title,
								'post_content' => $content,
								'post_status'  => 'draft',
								'post_type'    => 'page',
							),
							true
						);

						if ( is_wp_error( $result ) ) {
							return $result;
						}

						return array(
							'output'         => "Page created (draft): ID=$result, Title=\"$title\"",
							'post_id'        => $result,
							'title'          => $title,
							'section_count'  => count( $sections ),
							'content_length' => strlen( $content ),
						);
					}
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array(
							'type'        => 'string',
							'description' => 'Title of the page to create or update.',
						),
						'sections' => array(
							'type'        => 'array',
							'description' => 'Array of section objects. Supported types: cover, two-column, three-column, footer.',
						),
						'post_id'  => array(
							'type'        => 'integer',
							'description' => 'Optional. ID of existing page to update. If omitted, creates a new draft page.',
						),
					),
					'required'   => array( 'title', 'sections' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		// ---------------------------------------------------------------
		// custom-patterns-list: Lists custom patterns from cdw/patterns/ folder.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/custom-patterns-list',
			array(
				'label'               => __( 'List Custom Patterns', 'cdw' ),
				'description'         => __( 'Returns a list of all custom block patterns stored in the cdw/patterns/ folder. Each pattern includes name, title, description, and category.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$patterns_dir = CDW_PLUGIN_DIR . 'patterns';

					if ( ! is_dir( $patterns_dir ) ) {
						return array(
							'output'  => 'No custom patterns found.',
							'patterns' => array(),
						);
					}

					$patterns = array();
					$files    = glob( $patterns_dir . '/**/*.json', GLOB_BRACE );

					foreach ( $files as $file ) {
						$content = file_get_contents( $file );
						$data    = json_decode( $content, true );

						if ( $data && isset( $data['name'] ) ) {
							$patterns[] = array(
								'name'        => $data['name'],
								'title'       => isset( $data['title'] ) ? $data['title'] : '',
								'description' => isset( $data['description'] ) ? $data['description'] : '',
								'category'    => isset( $data['category'] ) ? $data['category'] : 'general',
							);
						}
					}

					return array(
						'output'   => 'Found ' . count( $patterns ) . ' custom patterns.',
						'patterns' => $patterns,
					);
				},
				'input_schema'        => array(),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		// ---------------------------------------------------------------
		// custom-patterns-get: Gets a specific custom pattern by name.
		// ---------------------------------------------------------------
		wp_register_ability(
			'cdw/custom-patterns-get',
			array(
				'label'               => __( 'Get Custom Pattern', 'cdw' ),
				'description'         => __( 'Returns the raw block markup for a specific custom pattern by name. Searches in cdw/patterns/ folder. Returns base64-encoded content to preserve special characters.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => function ( $input = array() ) {
					$pattern_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					if ( empty( $pattern_name ) ) {
						return new \WP_Error( 'invalid_pattern_name', 'pattern name is required.' );
					}

					$patterns_dir = CDW_PLUGIN_DIR . 'patterns';

					if ( ! is_dir( $patterns_dir ) ) {
						return new \WP_Error( 'patterns_dir_not_found', 'Patterns directory not found.' );
					}

					// Search for the pattern file (supports subdirectories)
					$files = glob( $patterns_dir . '/**/*.json', GLOB_BRACE );
					$matched = null;

					foreach ( $files as $file ) {
						$content = file_get_contents( $file );
						$data    = json_decode( $content, true );

						if ( $data && isset( $data['name'] ) && $data['name'] === $pattern_name ) {
							$matched = $data;
							break;
						}
					}

					if ( ! $matched ) {
						return new \WP_Error( 'pattern_not_found', "Custom pattern not found: $pattern_name" );
					}

					$content = isset( $matched['content'] ) ? $matched['content'] : '';
					if ( empty( $content ) ) {
						return new \WP_Error( 'empty_pattern', "Pattern \"$pattern_name\" has no content." );
					}

					$content_base64 = base64_encode( $content );

					return array(
						'output'          => "Custom pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes.',
						'name'            => $pattern_name,
						'title'           => isset( $matched['title'] ) ? $matched['title'] : '',
						'description'    => isset( $matched['description'] ) ? $matched['description'] : '',
						'category'        => isset( $matched['category'] ) ? $matched['category'] : 'general',
						'content_length'  => strlen( $content ),
						'content_base64'  => $content_base64,
					);
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Name of the custom pattern to retrieve (e.g., hero-luxury, gallery-masonry).',
						),
					),
					'required'   => array( 'name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);
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
			// Convert stdClass to array recursively if needed (MCP sends JSON objects).
			if ( is_object( $input ) ) {
				$input = json_decode( json_encode( $input ), true );
			}
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
			'meta'                => array(
				'show_in_rest' => true,
				'readonly'     => $ability['readonly'],
				'idempotent'   => $ability['readonly'],
				'annotations'  => array(
					'destructive' => $ability['destructive'],
				),
			),
		);

		// Register input_schema based on the ability's parameter requirements.
		//
		// - No params at all: omit schema entirely for MCP compatibility.
		// - Has params: include schema with type='object'.
		if ( ! empty( $ability['input'] ) ) {
			$args['input_schema'] = array(
				'type'       => 'object',
				'properties' => $ability['input'],
			);
		}

		wp_register_ability( $ability_name, $args );
	}

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

	/**
	 * Builds a CLI command string from an ability name and user-supplied input.
	 *
	 * Only called for abilities whose CLI string depends on runtime input params.
	 *
	 * @param string               $ability_name Fully-qualified ability name, e.g. `cdw/plugin-activate`.
	 * @param array<string, mixed> $input        Validated input params from the caller.
	 * @return string
	 */
	private static function build_cli_command( string $ability_name, ?array $input ): string {
		$input = $input ?? array();
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
			case 'cdw/post-create':
				return 'post create ' . sanitize_text_field( $input['title'] );
			case 'cdw/page-create':
				return 'page create ' . sanitize_text_field( $input['title'] );
			case 'cdw/task-list':
				$uid = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
				$cmd = 'task list';
				if ( $uid > 0 ) {
					$cmd .= ' --user_id=' . $uid;
				}
				return $cmd;
			case 'cdw/task-create':
				$cmd = 'task create ' . sanitize_text_field( $input['name'] );
				if ( ! empty( $input['assignee_login'] ) ) {
					$cmd .= ' --assignee_login=' . self::sanitize_cli_arg( $input['assignee_login'] );
				} elseif ( ! empty( $input['assignee_id'] ) ) {
					$cmd .= ' --assignee_id=' . (int) $input['assignee_id'];
				}
				return $cmd;
			case 'cdw/task-delete':
				$uid = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
				$cmd = 'task delete';
				if ( $uid > 0 ) {
					$cmd .= ' --user_id=' . $uid;
				}
				return $cmd;
			case 'cdw/comment-list':
				$status = isset( $input['status'] ) ? self::sanitize_cli_arg( (string) $input['status'] ) : 'pending';
				return 'comment list ' . $status;
			case 'cdw/comment-approve':
				return 'comment approve ' . (int) $input['id'];
			case 'cdw/comment-spam':
				return 'comment spam ' . (int) $input['id'];
			case 'cdw/comment-delete':
				return 'comment delete ' . (int) $input['id'] . ' --force';
			case 'cdw/post-list':
				$type = isset( $input['type'] ) ? self::sanitize_cli_arg( (string) $input['type'] ) : 'post';
				return 'post list ' . $type;
			case 'cdw/post-count':
				$type = isset( $input['type'] ) ? self::sanitize_cli_arg( (string) $input['type'] ) : '';
				return $type ? 'post count ' . $type : 'post count';
			case 'cdw/post-status':
				return 'post status ' . (int) $input['post_id'] . ' ' . self::sanitize_cli_arg( (string) $input['status'] );
			case 'cdw/post-delete':
				return 'post delete ' . (int) $input['post_id'] . ' --force';
			case 'cdw/user-role':
				return 'user role ' . self::sanitize_cli_arg( (string) $input['identifier'] ) . ' ' . self::sanitize_cli_arg( (string) $input['role'] );
			case 'cdw/option-delete':
				return 'option delete ' . self::sanitize_cli_arg( (string) $input['name'] );
			case 'cdw/theme-delete':
				return 'theme delete ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
			case 'cdw/transient-delete':
				return 'transient delete ' . self::sanitize_cli_arg( (string) $input['name'] );
			case 'cdw/cron-run':
				return 'cron run ' . self::sanitize_cli_arg( (string) $input['hook'] );
			case 'cdw/media-list':
				$count = isset( $input['count'] ) ? (int) $input['count'] : 20;
				return 'media list ' . $count;
			case 'cdw/block-patterns-list':
				if ( ! empty( $input['category'] ) ) {
					return 'block-patterns list ' . self::sanitize_cli_arg( (string) $input['category'] );
				}
				return 'block-patterns list';
			case 'cdw/skill-get':
				$cmd = 'skill get '
					. self::sanitize_cli_arg( (string) $input['plugin_slug'] ) . ' '
					. self::sanitize_cli_arg( (string) $input['skill_name'] );
				if ( ! empty( $input['file'] ) ) {
					$cmd .= ' ' . self::sanitize_cli_arg( (string) $input['file'] );
				}
				return $cmd;
			default:
				return '';
		}
	}
}

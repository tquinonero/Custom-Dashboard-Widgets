<?php
/**
 * Ability definitions configuration for CDW Abilities.
 *
 * This file contains the configuration for all 59 abilities that use the
 * config-driven approach. The remaining 11 abilities (role-*, block-patterns-get,
 * post-set-content, post-get-content, post-append-content, build-page,
 * custom-patterns-*) are kept inline due to their complex execution logic.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(

	// ---------------------------------------------------------------
	// Plugin management
	// ---------------------------------------------------------------
	'cdw/plugin-list'         => array(
		'label'       => __( 'List Plugins', 'cdw' ),
		'desc'        => __( 'Returns a list of all installed plugins with their activation status, version, and description.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'plugin list',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/plugin-status'       => array(
		'label'       => __( 'Plugin Status', 'cdw' ),
		'desc'        => __( 'Returns the activation status and version of a specific plugin identified by its slug.', 'cdw' ),
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
	'cdw/plugin-activate'     => array(
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
	'cdw/plugin-deactivate'   => array(
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
	'cdw/plugin-install'      => array(
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
	'cdw/plugin-update'       => array(
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
	'cdw/plugin-delete'       => array(
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
	'cdw/plugin-update-all'   => array(
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
	'cdw/theme-list'          => array(
		'label'       => __( 'List Themes', 'cdw' ),
		'desc'        => __( 'Returns a list of all installed themes with their activation status and version.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'theme list',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/theme-activate'      => array(
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
	'cdw/theme-install'       => array(
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
	'cdw/theme-update'        => array(
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
	'cdw/theme-info'          => array(
		'label'       => __( 'Theme Info', 'cdw' ),
		'desc'        => __( 'Returns details about the currently active theme including name, version, and author.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'theme info',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/theme-status'        => array(
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
	'cdw/user-list'           => array(
		'label'       => __( 'List Users', 'cdw' ),
		'desc'        => __( 'Returns a list of all WordPress users with their IDs, usernames, roles, and email addresses.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'user list',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/user-create'         => array(
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
	'cdw/user-delete'         => array(
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
	'cdw/user-get'            => array(
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
	'cdw/cache-flush'         => array(
		'label'       => __( 'Flush Cache', 'cdw' ),
		'desc'        => __( 'Flushes the WordPress object cache, clearing all cached data.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'cache flush',
		'readonly'    => false,
		'destructive' => false,
	),
	'cdw/option-get'          => array(
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
	'cdw/option-list'         => array(
		'label'       => __( 'List Options', 'cdw' ),
		'desc'        => __( 'Returns all WordPress options stored in the database with their values.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'option list',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/option-set'          => array(
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
	'cdw/cron-list'           => array(
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
	'cdw/site-info'           => array(
		'label'       => __( 'Site Info', 'cdw' ),
		'desc'        => __( 'Returns general information about the WordPress site including its name, URL, WordPress version, and active theme.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'site info',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/core-version'        => array(
		'label'       => __( 'Core Version', 'cdw' ),
		'desc'        => __( 'Returns the current WordPress version, PHP version, and whether a core update is available.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'core version',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/comment-list'        => array(
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
	'cdw/comment-approve'     => array(
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
	'cdw/comment-spam'        => array(
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
	'cdw/comment-delete'      => array(
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
	'cdw/site-status'         => array(
		'label'       => __( 'Site Status', 'cdw' ),
		'desc'        => __( 'Returns the current health and configuration status of the WordPress site.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'site status',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/site-settings'       => array(
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
	'cdw/db-size'             => array(
		'label'       => __( 'Database Size', 'cdw' ),
		'desc'        => __( 'Returns the total size of the WordPress database in bytes.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'db size',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/db-tables'           => array(
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
	'cdw/search-replace'      => array(
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
	'cdw/maintenance-on'      => array(
		'label'       => __( 'Enable Maintenance Mode', 'cdw' ),
		'desc'        => __( 'Enables WordPress maintenance mode, making the site temporarily unavailable to visitors while showing a maintenance message.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'maintenance on',
		'readonly'    => false,
		'destructive' => false,
	),
	'cdw/maintenance-off'     => array(
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
	'cdw/post-get'            => array(
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
	'cdw/post-create'         => array(
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
	'cdw/page-create'         => array(
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
	'cdw/task-list'           => array(
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
	'cdw/task-create'         => array(
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
	'cdw/task-delete'         => array(
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
	'cdw/post-list'           => array(
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
	'cdw/post-count'          => array(
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
	'cdw/post-status'         => array(
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
	'cdw/post-delete'         => array(
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
	'cdw/user-role'           => array(
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
	'cdw/option-delete'       => array(
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
	'cdw/theme-delete'        => array(
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
	'cdw/theme-update-all'    => array(
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
	'cdw/transient-list'      => array(
		'label'       => __( 'List Transients', 'cdw' ),
		'desc'        => __( 'Returns the first 20 WordPress transients currently stored in the database.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'transient list',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/transient-delete'    => array(
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
	'cdw/rewrite-flush'       => array(
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
	'cdw/maintenance-status'  => array(
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
	'cdw/cron-run'            => array(
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
	'cdw/media-list'          => array(
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
	'cdw/skill-list'          => array(
		'label'       => __( 'List Plugin Skills', 'cdw' ),
		'desc'        => __( 'Scans all installed plugins for agent skill documentation and returns a list of available skills with their plugin slug and skill name.', 'cdw' ),
		'input'       => array(),
		'cli'         => 'skill list',
		'readonly'    => true,
		'destructive' => false,
	),
	'cdw/skill-get'           => array(
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
	'cdw/block-patterns-list' => array(
		'label'       => __( 'List Block Patterns', 'cdw' ),
		'desc'        => __( 'Returns all registered block patterns with name, title, and categories. Optionally filter by category slug.', 'cdw' ),
		'input'       => array(
			'category' => array(
				'type'     => 'string',
				'required' => false,
			),
		),
		'cli'         => null,
		'readonly'    => true,
		'destructive' => false,
	),
);

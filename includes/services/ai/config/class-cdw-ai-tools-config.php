<?php
/**
 * AI Tools configuration - complete tool definitions.
 *
 * Each entry contains:
 * - cli: CLI command pattern (null = handled inline)
 * - description: Tool description for AI
 * - params: Array of parameter definitions
 * - required: Array of required param names
 *
 * Param format:
 * - 'param_name' => ['type' => 'string', 'description' => '...']
 * - 'param_name' => ['type' => 'integer', 'description' => '...']
 * - 'param_name' => ['type' => 'boolean', 'description' => '...']
 * - 'param_name' => ['type' => 'string', 'enum' => ['val1', 'val2'], 'description' => '...']
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// -----------------------------------------------------------------
	// Plugin tools
	// -----------------------------------------------------------------
	'plugin_list'         => array(
		'cli'         => 'plugin list',
		'description' => 'List all installed plugins with their status (active/inactive) and version.',
		'params'      => array(),
		'required'    => array(),
	),
	'plugin_status'       => array(
		'cli'         => 'plugin status {slug}',
		'description' => 'Show the current status and details of a specific plugin.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Plugin slug, e.g. "woocommerce".',
			),
		),
		'required'    => array( 'slug' ),
	),
	'plugin_activate'     => array(
		'cli'         => 'plugin activate {slug}',
		'description' => 'Activate an installed plugin.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Plugin slug.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'plugin_deactivate'   => array(
		'cli'         => 'plugin deactivate {slug}',
		'description' => 'Deactivate an active plugin.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Plugin slug.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'plugin_install'      => array(
		'cli'         => 'plugin install {slug} --force',
		'description' => 'Install a plugin from WordPress.org by slug.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Plugin slug from WordPress.org.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'plugin_update'       => array(
		'cli'         => 'plugin update {slug} --force',
		'description' => 'Update a plugin to its latest available version.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Plugin slug.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'plugin_update_all'   => array(
		'cli'         => 'plugin update --all',
		'description' => 'Update all installed plugins that have a pending update.',
		'params'      => array(),
		'required'    => array(),
	),
	'plugin_delete'       => array(
		'cli'         => 'plugin delete {slug} --force',
		'description' => 'Delete (uninstall) a plugin. The plugin must be inactive first.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Plugin slug.',
			),
		),
		'required'    => array( 'slug' ),
	),

	// -----------------------------------------------------------------
	// Theme tools
	// -----------------------------------------------------------------
	'theme_list'          => array(
		'cli'         => 'theme list',
		'description' => 'List all installed themes.',
		'params'      => array(),
		'required'    => array(),
	),
	'theme_activate'      => array(
		'cli'         => 'theme activate {slug}',
		'description' => 'Activate an installed theme.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Theme slug.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'theme_install'       => array(
		'cli'         => 'theme install {slug} --force',
		'description' => 'Install a theme from WordPress.org by slug.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Theme slug from WordPress.org.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'theme_update'        => array(
		'cli'         => 'theme update {slug} --force',
		'description' => 'Update a theme to its latest available version.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Theme slug.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'theme_update_all'    => array(
		'cli'         => 'theme update --all',
		'description' => 'Update all installed themes that have a pending update.',
		'params'      => array(),
		'required'    => array(),
	),
	'theme_info'          => array(
		'cli'         => 'theme info',
		'description' => 'Show detailed information about the currently active theme (name, version, author, description, update availability).',
		'params'      => array(),
		'required'    => array(),
	),
	'theme_status'        => array(
		'cli'         => 'theme status {slug}',
		'description' => 'Show status details for a specific installed theme.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Theme slug.',
			),
		),
		'required'    => array( 'slug' ),
	),
	'theme_delete'        => array(
		'cli'         => 'theme delete {slug} --force',
		'description' => 'Delete (uninstall) an inactive theme by slug. The theme must not be the currently active theme.',
		'params'      => array(
			'slug' => array(
				'type'        => 'string',
				'description' => 'Theme slug.',
			),
		),
		'required'    => array( 'slug' ),
	),

	// -----------------------------------------------------------------
	// User tools
	// -----------------------------------------------------------------
	'user_list'           => array(
		'cli'         => 'user list',
		'description' => 'List all WordPress users.',
		'params'      => array(),
		'required'    => array(),
	),
	'user_create'         => array(
		'cli'         => 'user create {username} {email} {role}',
		'description' => 'Create a new WordPress user.',
		'params'      => array(
			'username' => array(
				'type'        => 'string',
				'description' => 'Username (login name).',
			),
			'email'    => array(
				'type'        => 'string',
				'description' => 'Email address.',
			),
			'role'     => array(
				'type'        => 'string',
				'description' => 'WordPress role, e.g. "editor", "subscriber".',
			),
		),
		'required'    => array( 'username', 'email', 'role' ),
	),
	'user_delete'         => array(
		'cli'         => 'user delete {user_id} --force',
		'description' => 'Delete a WordPress user by user ID.',
		'params'      => array(
			'user_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress user ID of the user to delete.',
			),
		),
		'required'    => array( 'user_id' ),
	),
	'user_get'            => array(
		'cli'         => 'user get {identifier}',
		'description' => 'Get detailed information about a WordPress user (username, email, role, post count, registration date).',
		'params'      => array(
			'identifier' => array(
				'type'        => 'string',
				'description' => 'Username (login) or numeric user ID.',
			),
		),
		'required'    => array( 'identifier' ),
	),
	'user_role'           => array(
		'cli'         => 'user role {identifier} {role}',
		'description' => 'Change the role of an existing WordPress user.',
		'params'      => array(
			'identifier' => array(
				'type'        => 'string',
				'description' => 'Username (login) or numeric user ID.',
			),
			'role'       => array(
				'type'        => 'string',
				'description' => 'WordPress role slug, e.g. "editor", "subscriber", "administrator".',
			),
		),
		'required'    => array( 'identifier', 'role' ),
	),

	// -----------------------------------------------------------------
	// Post tools
	// -----------------------------------------------------------------
	'post_get'            => array(
		'cli'         => 'post get {post_id}',
		'description' => 'Get detailed information about a single post (title, status, author, dates, URL, excerpt).',
		'params'      => array(
			'post_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress post ID.',
			),
		),
		'required'    => array( 'post_id' ),
	),
	'post_list'           => array(
		'cli'         => 'post list {type}',
		'description' => 'List recent posts. Optionally filter by post type (default: "post").',
		'params'      => array(
			'type' => array(
				'type'        => 'string',
				'description' => 'Post type slug, e.g. "post", "page". Defaults to "post".',
			),
		),
		'required'    => array(),
	),
	'post_count'          => array(
		'cli'         => 'post count {type}',
		'description' => 'Count posts by status (publish, draft, pending, trash) for each public post type. Excludes attachments. Optionally filter by post type.',
		'params'      => array(
			'type' => array(
				'type'        => 'string',
				'description' => 'Post type to count (e.g. "post", "page"). Omit for all public post types.',
			),
		),
		'required'    => array(),
	),
	'post_status'         => array(
		'cli'         => 'post status {post_id} {status}',
		'description' => 'Change the status of an existing post.',
		'params'      => array(
			'post_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress post ID.',
			),
			'status'  => array(
				'type'        => 'string',
				'enum'        => array( 'draft', 'publish', 'pending', 'private', 'trash' ),
				'description' => 'New post status.',
			),
		),
		'required'    => array( 'post_id', 'status' ),
	),
	'post_delete'         => array(
		'cli'         => 'post delete {post_id} --force',
		'description' => 'Permanently delete a post by ID.',
		'params'      => array(
			'post_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress post ID.',
			),
		),
		'required'    => array( 'post_id' ),
	),

	// -----------------------------------------------------------------
	// Page tools (handled inline)
	// -----------------------------------------------------------------
	'page_create'         => array(
		'cli'         => null,
		'description' => 'Create a new WordPress page with the given title and optional content. Use status="publish" to publish immediately; defaults to draft.',
		'params'      => array(
			'title'   => array(
				'type'        => 'string',
				'description' => 'Title for the new page.',
			),
			'content' => array(
				'type'        => 'string',
				'description' => 'Optional body text or HTML content for the page.',
			),
			'status'  => array(
				'type'        => 'string',
				'enum'        => array( 'draft', 'publish' ),
				'description' => 'Page status: "draft" (default) or "publish".',
			),
		),
		'required'    => array( 'title' ),
	),

	// -----------------------------------------------------------------
	// Option tools
	// -----------------------------------------------------------------
	'option_get'          => array(
		'cli'         => 'option get {name}',
		'description' => 'Get the value of a WordPress option.',
		'params'      => array(
			'name' => array(
				'type'        => 'string',
				'description' => 'Option name.',
			),
		),
		'required'    => array( 'name' ),
	),
	'option_list'         => array(
		'cli'         => 'option list',
		'description' => 'List all CDW-managed option keys.',
		'params'      => array(),
		'required'    => array(),
	),
	'option_set'          => array(
		'cli'         => 'option set {name} {value}',
		'description' => 'Set the value of a WordPress option. Protected core options cannot be changed.',
		'params'      => array(
			'name'  => array(
				'type'        => 'string',
				'description' => 'Option name.',
			),
			'value' => array(
				'type'        => 'string',
				'description' => 'New value for the option.',
			),
		),
		'required'    => array( 'name', 'value' ),
	),
	'option_delete'       => array(
		'cli'         => 'option delete {name}',
		'description' => 'Delete a WordPress option from the database. Protected core options cannot be deleted.',
		'params'      => array(
			'name' => array(
				'type'        => 'string',
				'description' => 'Option name.',
			),
		),
		'required'    => array( 'name' ),
	),

	// -----------------------------------------------------------------
	// Site tools
	// -----------------------------------------------------------------
	'site_info'           => array(
		'cli'         => 'site info',
		'description' => 'Show general information about the WordPress site (URL, theme, admin email, etc.).',
		'params'      => array(),
		'required'    => array(),
	),
	'site_status'         => array(
		'cli'         => 'site status',
		'description' => 'Show a health summary of the WordPress site.',
		'params'      => array(),
		'required'    => array(),
	),
	'site_settings'       => array(
		'cli'         => 'site settings',
		'description' => 'Read key WordPress site settings (admin email, language, timezone, permalink structure, registration, etc.).',
		'params'      => array(),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Cache tools
	// -----------------------------------------------------------------
	'cache_flush'         => array(
		'cli'         => 'cache flush',
		'description' => 'Flush all WordPress object cache and transients.',
		'params'      => array(),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Cron tools
	// -----------------------------------------------------------------
	'cron_list'           => array(
		'cli'         => 'cron list',
		'description' => 'List all scheduled WordPress cron events.',
		'params'      => array(),
		'required'    => array(),
	),
	'cron_run'            => array(
		'cli'         => 'cron run {hook}',
		'description' => 'Manually trigger a scheduled WordPress cron hook immediately.',
		'params'      => array(
			'hook' => array(
				'type'        => 'string',
				'description' => 'Cron hook name to run immediately.',
			),
		),
		'required'    => array( 'hook' ),
	),

	// -----------------------------------------------------------------
	// Database tools
	// -----------------------------------------------------------------
	'db_size'             => array(
		'cli'         => 'db size',
		'description' => 'Show the size of the WordPress database.',
		'params'      => array(),
		'required'    => array(),
	),
	'db_tables'           => array(
		'cli'         => 'db tables',
		'description' => 'List all tables in the WordPress database.',
		'params'      => array(),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Search & Replace (handled inline - needs escapeshellarg)
	// -----------------------------------------------------------------
	'search_replace'      => array(
		'cli'         => null,
		'description' => 'Search and replace a string across the WordPress database. Always run a dry-run first.',
		'params'      => array(
			'search'  => array(
				'type'        => 'string',
				'description' => 'String to search for.',
			),
			'replace' => array(
				'type'        => 'string',
				'description' => 'Replacement string.',
			),
			'dry_run' => array(
				'type'        => 'boolean',
				'description' => 'If true, show what would change without making changes.',
			),
		),
		'required'    => array( 'search', 'replace' ),
	),

	// -----------------------------------------------------------------
	// Maintenance tools
	// -----------------------------------------------------------------
	'maintenance_on'      => array(
		'cli'         => 'maintenance on',
		'description' => 'Enable WordPress maintenance mode.',
		'params'      => array(),
		'required'    => array(),
	),
	'maintenance_off'     => array(
		'cli'         => 'maintenance off',
		'description' => 'Disable WordPress maintenance mode.',
		'params'      => array(),
		'required'    => array(),
	),
	'maintenance_status'  => array(
		'cli'         => 'maintenance status',
		'description' => 'Check whether WordPress maintenance mode is currently enabled or disabled.',
		'params'      => array(),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Task tools
	// -----------------------------------------------------------------
	'task_list'           => array(
		'cli'         => 'task list --user_id={user_id}',
		'description' => 'List pending tasks for a user. Omit user_id to list tasks for yourself.',
		'params'      => array(
			'user_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress user ID to list tasks for. Omit to use the current user.',
			),
		),
		'required'    => array(),
	),
	'task_create'         => array(
		'cli'         => 'task create {name} --assignee_login={assignee_login} --assignee_id={assignee_id}',
		'description' => 'Create a new pending task. Optionally assign it to another user by their WordPress username (assignee_login) or user ID (assignee_id). Assigning to another user requires administrator privileges.',
		'params'      => array(
			'name'           => array(
				'type'        => 'string',
				'description' => 'Task name/title.',
			),
			'assignee_login' => array(
				'type'        => 'string',
				'description' => 'WordPress username (user_login) to assign the task to.',
			),
			'assignee_id'    => array(
				'type'        => 'integer',
				'description' => 'WordPress user ID to assign the task to (use assignee_login when possible).',
			),
		),
		'required'    => array( 'name' ),
	),
	'task_delete'         => array(
		'cli'         => 'task delete --user_id={user_id}',
		'description' => 'Delete all tasks for a user. Omit user_id to delete tasks for yourself.',
		'params'      => array(
			'user_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress user ID whose tasks to delete. Omit to use the current user.',
			),
		),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Comment tools
	// -----------------------------------------------------------------
	'comment_list'        => array(
		'cli'         => 'comment list {status}',
		'description' => 'List comments filtered by status: pending (default), approved, or spam.',
		'params'      => array(
			'status' => array(
				'type'        => 'string',
				'enum'        => array( 'pending', 'approved', 'spam' ),
				'description' => 'Comment status filter. Defaults to "pending".',
			),
		),
		'required'    => array(),
	),
	'comment_approve'     => array(
		'cli'         => 'comment approve {id}',
		'description' => 'Approve a pending comment by its ID.',
		'params'      => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Comment ID.',
			),
		),
		'required'    => array( 'id' ),
	),
	'comment_spam'        => array(
		'cli'         => 'comment spam {id}',
		'description' => 'Mark a comment as spam by its ID.',
		'params'      => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Comment ID.',
			),
		),
		'required'    => array( 'id' ),
	),
	'comment_delete'      => array(
		'cli'         => 'comment delete {id} --force',
		'description' => 'Permanently delete a comment by its ID.',
		'params'      => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Comment ID.',
			),
		),
		'required'    => array( 'id' ),
	),

	// -----------------------------------------------------------------
	// Transient tools
	// -----------------------------------------------------------------
	'transient_list'      => array(
		'cli'         => 'transient list',
		'description' => 'List the first 20 WordPress transients stored in the database.',
		'params'      => array(),
		'required'    => array(),
	),
	'transient_delete'    => array(
		'cli'         => 'transient delete {name}',
		'description' => 'Delete a specific WordPress transient by name.',
		'params'      => array(
			'name' => array(
				'type'        => 'string',
				'description' => 'Transient key (without the _transient_ prefix).',
			),
		),
		'required'    => array( 'name' ),
	),

	// -----------------------------------------------------------------
	// Rewrite tools
	// -----------------------------------------------------------------
	'rewrite_flush'       => array(
		'cli'         => 'rewrite flush',
		'description' => 'Flush WordPress rewrite rules (equivalent to saving permalink settings).',
		'params'      => array(),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Media tools
	// -----------------------------------------------------------------
	'media_list'          => array(
		'cli'         => 'media list {count}',
		'description' => 'List recent media library attachments with ID, filename, MIME type, and upload date.',
		'params'      => array(
			'count' => array(
				'type'        => 'integer',
				'description' => 'Number of attachments to return (1-100, default 20).',
			),
		),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Block Patterns tools
	// -----------------------------------------------------------------
	'block_patterns_list' => array(
		'cli'         => 'block-patterns list {category}',
		'description' => 'List all registered WordPress block patterns (name, title, categories). Optionally filter by category slug.',
		'params'      => array(
			'category' => array(
				'type'        => 'string',
				'description' => 'Category slug to filter by. Omit to list all patterns.',
			),
		),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Core tools
	// -----------------------------------------------------------------
	'core_version'        => array(
		'cli'         => 'core version',
		'description' => 'Show WordPress version, PHP version, and whether a core update is available.',
		'params'      => array(),
		'required'    => array(),
	),

	// -----------------------------------------------------------------
	// Post creation tools (handled inline)
	// -----------------------------------------------------------------
	'post_create'         => array(
		'cli'         => null,
		'description' => 'Create a new WordPress post with the given title and optional content. Use status="publish" to publish immediately; defaults to draft.',
		'params'      => array(
			'title'   => array(
				'type'        => 'string',
				'description' => 'Title for the new post.',
			),
			'content' => array(
				'type'        => 'string',
				'description' => 'Optional body text or HTML content for the post.',
			),
			'status'  => array(
				'type'        => 'string',
				'enum'        => array( 'draft', 'publish' ),
				'description' => 'Post status: "draft" (default) or "publish".',
			),
		),
		'required'    => array( 'title' ),
	),
	// -----------------------------------------------------------------
	// Guide tools (handled inline - reads from skills/gutenberg-design/SKILL.md)
	// -----------------------------------------------------------------
	'gutenberg_guide'     => array(
		'cli'         => null,
		'description' => 'Returns the full Gutenberg design guide: design thinking, personality vibes, color systems, spacing, component patterns, and decision checklist. Use this before building any page to ensure professional, consistent design decisions.',
		'params'      => array(),
		'required'    => array(),
	),
);

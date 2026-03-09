<?php
/**
 * AI Tools for CDW - tool definitions and execution.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';

/**
 * AI Tools handler - manages tool definitions and execution.
 */
class CDW_AI_Tools {

	/**
	 * Returns CDW CLI commands formatted as OpenAI-compatible function-calling tools.
	 *
	 * Each tool maps 1-to-1 with an internal CDW CLI command. The same list is
	 * converted to provider-specific formats inside the provider call methods.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_tool_definitions() {
		return array(
			array(
				'name'        => 'plugin_list',
				'description' => 'List all installed plugins with their status (active/inactive) and version.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'plugin_status',
				'description' => 'Show the current status and details of a specific plugin.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug, e.g. "woocommerce".',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_activate',
				'description' => 'Activate an installed plugin.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_deactivate',
				'description' => 'Deactivate an active plugin.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_install',
				'description' => 'Install a plugin from WordPress.org by slug.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug from WordPress.org.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_update',
				'description' => 'Update a plugin to its latest available version.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_update_all',
				'description' => 'Update all installed plugins that have a pending update.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'plugin_delete',
				'description' => 'Delete (uninstall) a plugin. The plugin must be inactive first.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_list',
				'description' => 'List all installed themes.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'theme_activate',
				'description' => 'Activate an installed theme.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_install',
				'description' => 'Install a theme from WordPress.org by slug.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug from WordPress.org.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_update',
				'description' => 'Update a theme to its latest available version.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_update_all',
				'description' => 'Update all installed themes that have a pending update.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'user_list',
				'description' => 'List all WordPress users.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'user_create',
				'description' => 'Create a new WordPress user.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
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
					'required'   => array( 'username', 'email', 'role' ),
				),
			),
			array(
				'name'        => 'user_delete',
				'description' => 'Delete a WordPress user by user ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the user to delete.',
						),
					),
					'required'   => array( 'user_id' ),
				),
			),
			array(
				'name'        => 'cache_flush',
				'description' => 'Flush all WordPress object cache and transients.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'option_get',
				'description' => 'Get the value of a WordPress option.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
					),
					'required'   => array( 'name' ),
				),
			),
			array(
				'name'        => 'option_list',
				'description' => 'List all CDW-managed option keys.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'option_set',
				'description' => 'Set the value of a WordPress option. Protected core options cannot be changed.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name'  => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
						'value' => array(
							'type'        => 'string',
							'description' => 'New value for the option.',
						),
					),
					'required'   => array( 'name', 'value' ),
				),
			),
			array(
				'name'        => 'cron_list',
				'description' => 'List all scheduled WordPress cron events.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'site_info',
				'description' => 'Show general information about the WordPress site (URL, theme, admin email, etc.).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'site_status',
				'description' => 'Show a health summary of the WordPress site.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'db_size',
				'description' => 'Show the size of the WordPress database.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'db_tables',
				'description' => 'List all tables in the WordPress database.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'search_replace',
				'description' => 'Search and replace a string across the WordPress database. Always run a dry-run first.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
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
					'required'   => array( 'search', 'replace' ),
				),
			),
			array(
				'name'        => 'maintenance_on',
				'description' => 'Enable WordPress maintenance mode.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'maintenance_off',
				'description' => 'Disable WordPress maintenance mode.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'post_get',
				'description' => 'Get detailed information about a single post (title, status, author, dates, URL, excerpt).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID.',
						),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'post_create',
				'description' => 'Create a new WordPress post with the given title and optional content. Use status="publish" to publish immediately; defaults to draft.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
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
					'required'   => array( 'title' ),
				),
			),
			array(
				'name'        => 'page_create',
				'description' => 'Create a new WordPress page with the given title and optional content. Use status="publish" to publish immediately; defaults to draft.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
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
					'required'   => array( 'title' ),
				),
			),
			array(
				'name'        => 'user_get',
				'description' => 'Get detailed information about a WordPress user (username, email, role, post count, registration date).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'identifier' => array(
							'type'        => 'string',
							'description' => 'Username (login) or numeric user ID.',
						),
					),
					'required'   => array( 'identifier' ),
				),
			),
			array(
				'name'        => 'theme_info',
				'description' => 'Show detailed information about the currently active theme (name, version, author, description, update availability).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'theme_status',
				'description' => 'Show status details for a specific installed theme.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'site_settings',
				'description' => 'Read key WordPress site settings (admin email, language, timezone, permalink structure, registration, etc.).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'task_list',
				'description' => 'List pending tasks for a user. Omit user_id to list tasks for yourself.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID to list tasks for. Omit to use the current user.',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'task_create',
				'description' => 'Create a new pending task. Optionally assign it to another user by their WordPress username (assignee_login) or user ID (assignee_id). Assigning to another user requires administrator privileges.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
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
					'required'   => array( 'name' ),
				),
			),
			array(
				'name'        => 'task_delete',
				'description' => 'Delete all tasks for a user. Omit user_id to delete tasks for yourself.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID whose tasks to delete. Omit to use the current user.',
						),
					),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Core
			// ---------------------------------------------------------------
			array(
				'name'        => 'core_version',
				'description' => 'Show WordPress version, PHP version, and whether a core update is available.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Comments
			// ---------------------------------------------------------------
			array(
				'name'        => 'comment_list',
				'description' => 'List comments filtered by status: pending (default), approved, or spam.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'pending', 'approved', 'spam' ),
							'description' => 'Comment status filter. Defaults to "pending".',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'comment_approve',
				'description' => 'Approve a pending comment by its ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Comment ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'comment_spam',
				'description' => 'Mark a comment as spam by its ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Comment ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'comment_delete',
				'description' => 'Permanently delete a comment by its ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Comment ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),

			// ---------------------------------------------------------------
			// Posts (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'post_list',
				'description' => 'List recent posts. Optionally filter by post type (default: "post").',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'        => 'string',
							'description' => 'Post type slug, e.g. "post", "page". Defaults to "post".',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'post_count',
				'description' => 'Count posts by status (publish, draft, pending, trash) for each public post type. Excludes attachments. Optionally filter by post type.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'        => 'string',
							'description' => 'Post type to count (e.g. "post", "page"). Omit for all public post types.',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'post_status',
				'description' => 'Change the status of an existing post.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
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
					'required'   => array( 'post_id', 'status' ),
				),
			),
			array(
				'name'        => 'post_delete',
				'description' => 'Permanently delete a post by ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID.',
						),
					),
					'required'   => array( 'post_id' ),
				),
			),

			// ---------------------------------------------------------------
			// Users (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'user_role',
				'description' => 'Change the role of an existing WordPress user.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'identifier' => array(
							'type'        => 'string',
							'description' => 'Username (login) or numeric user ID.',
						),
						'role'       => array(
							'type'        => 'string',
							'description' => 'WordPress role slug, e.g. "editor", "subscriber", "administrator".',
						),
					),
					'required'   => array( 'identifier', 'role' ),
				),
			),

			// ---------------------------------------------------------------
			// Options (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'option_delete',
				'description' => 'Delete a WordPress option from the database. Protected core options cannot be deleted.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
					),
					'required'   => array( 'name' ),
				),
			),

			// ---------------------------------------------------------------
			// Themes (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'theme_delete',
				'description' => 'Delete (uninstall) an inactive theme by slug. The theme must not be the currently active theme.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),

			// ---------------------------------------------------------------
			// Transients
			// ---------------------------------------------------------------
			array(
				'name'        => 'transient_list',
				'description' => 'List the first 20 WordPress transients stored in the database.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'transient_delete',
				'description' => 'Delete a specific WordPress transient by name.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Transient key (without the _transient_ prefix).',
						),
					),
					'required'   => array( 'name' ),
				),
			),

			// ---------------------------------------------------------------
			// Rewrite
			// ---------------------------------------------------------------
			array(
				'name'        => 'rewrite_flush',
				'description' => 'Flush WordPress rewrite rules (equivalent to saving permalink settings).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Maintenance (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'maintenance_status',
				'description' => 'Check whether WordPress maintenance mode is currently enabled or disabled.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Cron (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cron_run',
				'description' => 'Manually trigger a scheduled WordPress cron hook immediately.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'hook' => array(
							'type'        => 'string',
							'description' => 'Cron hook name to run immediately.',
						),
					),
					'required'   => array( 'hook' ),
				),
			),

			// ---------------------------------------------------------------
			// Media
			// ---------------------------------------------------------------
			array(
				'name'        => 'media_list',
				'description' => 'List recent media library attachments with ID, filename, MIME type, and upload date.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'count' => array(
							'type'        => 'integer',
							'description' => 'Number of attachments to return (1–100, default 20).',
						),
					),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Block Patterns
			// ---------------------------------------------------------------
			array(
				'name'        => 'block_patterns_list',
				'description' => 'List all registered WordPress block patterns (name, title, categories). Optionally filter by category slug.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Category slug to filter by. Omit to list all patterns.',
						),
					),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Post content (block page builder)
			// ---------------------------------------------------------------
			array(
				'name'        => 'post_set_content',
				'description' => 'Write raw block markup (WordPress block HTML comment syntax) to an existing post or page. Use this after creating a page to insert Greenshift or any block-based content.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Full raw block markup string to set as post_content.',
						),
					),
					'required'   => array( 'post_id', 'content' ),
				),
			),
			array(
				'name'        => 'gutenberg_guide',
				'description' => 'Returns a comprehensive reference guide for constructing page content using Gutenberg block markup syntax. Use this when you need to build pages with cover blocks, columns, images, custom CSS, or any other Gutenberg blocks.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

		);
	}

	/**
	 * Converts a tool name + arguments to a CDW CLI command string.
	 *
	 * @param string              $tool_name  Tool name.
	 * @param array<string,mixed> $arguments  Tool arguments.
	 * @return string|null CLI command string, or null if the tool is unknown.
	 */
	public static function tool_name_to_cli_command( $tool_name, $arguments ) {
		$slug    = isset( $arguments['slug'] ) ? trim( (string) $arguments['slug'] ) : '';
		$user_id = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;

		switch ( $tool_name ) {
			case 'plugin_list':
				return 'plugin list';
			case 'plugin_status':
				return 'plugin status ' . $slug;
			case 'plugin_activate':
				return 'plugin activate ' . $slug;
			case 'plugin_deactivate':
				return 'plugin deactivate ' . $slug;
			case 'plugin_install':
				return 'plugin install ' . $slug . ' --force';
			case 'plugin_update':
				return 'plugin update ' . $slug . ' --force';
			case 'plugin_update_all':
				return 'plugin update --all';
			case 'plugin_delete':
				return 'plugin delete ' . $slug . ' --force';
			case 'theme_list':
				return 'theme list';
			case 'theme_activate':
				return 'theme activate ' . $slug;
			case 'theme_install':
				return 'theme install ' . $slug . ' --force';
			case 'theme_update':
				return 'theme update ' . $slug . ' --force';
			case 'theme_update_all':
				return 'theme update --all';
			case 'user_list':
				return 'user list';
			case 'user_create':
				$username = isset( $arguments['username'] ) ? trim( (string) $arguments['username'] ) : '';
				$email    = isset( $arguments['email'] ) ? trim( (string) $arguments['email'] ) : '';
				$role     = isset( $arguments['role'] ) ? trim( (string) $arguments['role'] ) : 'subscriber';
				return 'user create ' . $username . ' ' . $email . ' ' . $role;
			case 'user_delete':
				return 'user delete ' . $user_id . ' --force';
			case 'cache_flush':
				return 'cache flush';
			case 'option_get':
				$name = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				return 'option get ' . $name;
			case 'option_list':
				return 'option list';
			case 'option_set':
				$name  = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				$value = isset( $arguments['value'] ) ? trim( (string) $arguments['value'] ) : '';
				return 'option set ' . $name . ' ' . $value;
			case 'cron_list':
				return 'cron list';
			case 'site_info':
				return 'site info';
			case 'site_status':
				return 'site status';
			case 'db_size':
				return 'db size';
			case 'db_tables':
				return 'db tables';
			case 'search_replace':
				$search  = isset( $arguments['search'] ) ? (string) $arguments['search'] : '';
				$replace = isset( $arguments['replace'] ) ? (string) $arguments['replace'] : '';
				$dry_run = isset( $arguments['dry_run'] ) && $arguments['dry_run'];
				$cmd     = 'search-replace ' . escapeshellarg( $search ) . ' ' . escapeshellarg( $replace );
				if ( $dry_run ) {
					$cmd .= ' --dry-run';
				} else {
					$cmd .= ' --force';
				}
				return $cmd;
			case 'maintenance_on':
				return 'maintenance on';
			case 'maintenance_off':
				return 'maintenance off';
			case 'post_get':
				$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
				return 'post get ' . $post_id;
			case 'post_create':
				$title = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
				return 'post create ' . $title;
			case 'page_create':
				$title = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
				return 'page create ' . $title;
			case 'user_get':
				$identifier = isset( $arguments['identifier'] ) ? trim( (string) $arguments['identifier'] ) : '';
				return 'user get ' . $identifier;
			case 'theme_info':
				return 'theme info';
			case 'theme_status':
				return 'theme status ' . $slug;
			case 'site_settings':
				return 'site settings';
			case 'task_list':
				$target_uid = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
				$cmd        = 'task list';
				if ( $target_uid > 0 ) {
					$cmd .= ' --user_id=' . $target_uid;
				}
				return $cmd;
			case 'task_create':
				$name           = isset( $arguments['name'] ) ? sanitize_text_field( (string) $arguments['name'] ) : '';
				$assignee_login = isset( $arguments['assignee_login'] ) ? trim( (string) $arguments['assignee_login'] ) : '';
				$assignee_id    = isset( $arguments['assignee_id'] ) ? (int) $arguments['assignee_id'] : 0;
				$cmd            = 'task create ' . $name;
				if ( ! empty( $assignee_login ) ) {
					$cmd .= ' --assignee_login=' . $assignee_login;
				} elseif ( $assignee_id > 0 ) {
					$cmd .= ' --assignee_id=' . $assignee_id;
				}
				return $cmd;
			case 'task_delete':
				$target_uid = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
				$cmd        = 'task delete';
				if ( $target_uid > 0 ) {
					$cmd .= ' --user_id=' . $target_uid;
				}
				return $cmd;
			case 'core_version':
				return 'core version';
			case 'comment_list':
				$status = isset( $arguments['status'] ) ? trim( (string) $arguments['status'] ) : 'pending';
				return 'comment list ' . $status;
			case 'comment_approve':
				$id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
				return 'comment approve ' . $id;
			case 'comment_spam':
				$id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
				return 'comment spam ' . $id;
			case 'comment_delete':
				$id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
				return 'comment delete ' . $id . ' --force';
			case 'post_list':
				$type = isset( $arguments['type'] ) ? trim( (string) $arguments['type'] ) : 'post';
				return 'post list ' . $type;
			case 'post_count':
				$type = isset( $arguments['type'] ) ? trim( (string) $arguments['type'] ) : '';
				return $type ? 'post count ' . $type : 'post count';
			case 'post_status':
				$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
				$status  = isset( $arguments['status'] ) ? trim( (string) $arguments['status'] ) : '';
				return 'post status ' . $post_id . ' ' . $status;
			case 'post_delete':
				$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
				return 'post delete ' . $post_id . ' --force';
			case 'user_role':
				$identifier = isset( $arguments['identifier'] ) ? trim( (string) $arguments['identifier'] ) : '';
				$role       = isset( $arguments['role'] ) ? trim( (string) $arguments['role'] ) : '';
				return 'user role ' . $identifier . ' ' . $role;
			case 'option_delete':
				$name = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				return 'option delete ' . $name;
			case 'theme_delete':
				return 'theme delete ' . $slug . ' --force';
			case 'transient_list':
				return 'transient list';
			case 'transient_delete':
				$name = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				return 'transient delete ' . $name;
			case 'rewrite_flush':
				return 'rewrite flush';
			case 'maintenance_status':
				return 'maintenance status';
			case 'cron_run':
				$hook = isset( $arguments['hook'] ) ? trim( (string) $arguments['hook'] ) : '';
				return 'cron run ' . $hook;
			case 'media_list':
				$count = isset( $arguments['count'] ) ? (int) $arguments['count'] : 20;
				return 'media list ' . $count;
			case 'block_patterns_list':
				$cat = isset( $arguments['category'] ) ? trim( (string) $arguments['category'] ) : '';
				return $cat ? 'block-patterns list ' . $cat : 'block-patterns list';
			default:
				return null;
		}
	}

	/**
	 * Maps an AI tool call to a CDW CLI command string and executes it.
	 *
	 * @param string              $function_name Tool name from get_tool_definitions().
	 * @param array<string,mixed> $arguments     Arguments parsed from the tool call.
	 * @param int                 $user_id       WordPress user ID (for rate limiting).
	 * @return string|array<string, mixed> Text output of the command (or error message), or array for gutenberg_guide.
	 */
	public static function execute_tool_call( $function_name, $arguments, $user_id ): string|array {
		if ( 'gutenberg_guide' === $function_name ) {
			$guide = array(
				'intro' => 'Use Gutenberg block markup to create page content. Pass raw block HTML to cdw/post-set-content or cdw/post-append-content.',
				'blocks' => array(
					'cover' => array(
						'description' => 'Full-width hero/cover section',
						'attributes' => array( 'url', 'alt', 'align', 'minHeight' ),
						'example' => "<!-- wp:cover {\"url\":\"https://example.com/image.jpg\",\"align\":\"full\",\"minHeight\":600} -->\n<div class=\"wp-block-cover alignfull\" style=\"min-height:600px\">\n  <span class=\"wp-block-cover__background has-background-dim-40\"></span>\n  <div class=\"wp-block-cover__inner-container\">\n    <h1 class=\"has-text-align-center has-white-color\">Title</h1>\n    <p class=\"has-text-align-center has-white-color\">Subtitle</p>\n  </div>\n</div>\n<!-- /wp:cover -->",
					),
					'columns' => array(
						'description' => 'Multi-column layout',
						'example' => "<!-- wp:columns -->\n<div class=\"wp-block-columns\">\n  <div class=\"wp-block-column\"><p>Column 1</p></div>\n  <div class=\"wp-block-column\"><p>Column 2</p></div>\n</div>\n<!-- /wp:columns -->",
					),
					'image' => array(
						'description' => 'Image block',
						'example' => "<!-- wp:image {\"align\":\"center\"} -->\n<figure class=\"wp-block-image aligncenter\"><img src=\"https://example.com/image.jpg\" alt=\"Description\"/></figure>\n<!-- /wp:image -->",
					),
					'html' => array(
						'description' => 'Custom HTML/CSS block',
						'example' => "<!-- wp:html -->\n<style>.my-class { color: red; }</style>\n<!-- /wp:html -->",
					),
				),
				'workflow' => array(
					'1. Get existing content' => 'cdw/post-get-content with post_id',
					'2. Create new page' => 'cdw/page-create or cdw/build-page',
					'3. Add blocks' => 'cdw/post-set-content with full block markup, OR cdw/post-append-content to add chunks',
					'4. For CSS' => 'Use <!-- wp:html --> block with <style> tags',
				),
			);
			return $guide;
		}

		if ( 'post_set_content' === $function_name ) {
			$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
			$content = isset( $arguments['content'] ) ? (string) $arguments['content'] : '';
			if ( $post_id <= 0 ) {
				return 'Error: post_id is required and must be a positive integer.';
			}
			if ( ! get_post( $post_id ) ) {
				return "Error: Post $post_id not found.";
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return 'Error: You do not have permission to edit this post.';
			}
			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return 'Error: ' . $result->get_error_message();
			}
			return "Post $post_id content updated successfully.";
		}

		if ( 'post_create' === $function_name || 'page_create' === $function_name ) {
			$title       = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
			$content     = isset( $arguments['content'] ) ? wp_kses_post( (string) $arguments['content'] ) : '';
			$raw_status  = isset( $arguments['status'] ) ? sanitize_text_field( (string) $arguments['status'] ) : 'draft';
			$post_status = in_array( $raw_status, array( 'draft', 'publish' ), true ) ? $raw_status : 'draft';
			$post_type   = ( 'page_create' === $function_name ) ? 'page' : 'post';

			if ( empty( $title ) ) {
				return 'Error: a title is required to create a ' . $post_type . '.';
			}

			$post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => $post_status,
					'post_type'    => $post_type,
					'post_author'  => $user_id,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return 'Error: ' . $post_id->get_error_message();
			}

			$type_label   = ( 'page' === $post_type ) ? 'Page' : 'Post';
			$status_label = ( 'publish' === $post_status ) ? 'published' : 'draft';
			return "{$type_label} created ({$status_label}): ID={$post_id}, Title=\"{$title}\"";
		}

		$command = self::tool_name_to_cli_command( $function_name, $arguments );

		if ( null === $command ) {
			return 'Unknown tool: ' . $function_name;
		}

		$cli_service = new CDW_CLI_Service();
		$result      = $cli_service->execute_as_ai( $command, $user_id );

		if ( is_wp_error( $result ) ) {
			return 'Error: ' . $result->get_error_message();
		}

		return isset( $result['output'] ) ? (string) $result['output'] : 'Done.';
	}
}

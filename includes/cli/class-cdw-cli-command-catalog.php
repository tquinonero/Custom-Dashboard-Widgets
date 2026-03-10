<?php
/**
 * CLI command catalog loader.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads modular command category data for CLI autocomplete/help.
 *
 * @package CDW
 */
class CDW_CLI_Command_Catalog {

	/**
	 * Returns command categories managed as separate data files.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_modular_categories() {
		$command_files = array(
			CDW_PLUGIN_DIR . 'includes/cli/commands/plugin-management.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/theme-management.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/user-management.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/post-management.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/page-management.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/media.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/block-patterns.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/cache.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/database.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/options.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/rewrite.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/core.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/skills.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/comments.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/transients.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/cron.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/site.php',
			CDW_PLUGIN_DIR . 'includes/cli/commands/maintenance.php',
		);

		$categories = array();
		foreach ( $command_files as $file ) {
			if ( file_exists( $file ) ) {
				$categories[] = require $file;
			}
		}

		return $categories;
	}
}

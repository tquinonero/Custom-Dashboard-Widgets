<?php
/**
 * Abstract base class for CLI command handlers.
 *
 * Provides shared utilities used across multiple handlers.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/contracts/interface-cdw-command-handler.php';

/**
 * Abstract base class for command handlers.
 *
 * Provides common functionality for filesystem operations,
 * flag detection, and WP API loading.
 */
abstract class CDW_Abstract_Handler implements CDW_Command_Handler_Interface {

	/**
	 * Check whether the --force flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	protected function has_force_flag( array $args ): bool {
		return in_array( '--force', $args, true );
	}

	/**
	 * Check whether the --publish flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	protected function has_publish_flag( array $args ): bool {
		return in_array( '--publish', $args, true );
	}

	/**
	 * Check whether the --dry-run flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	protected function has_dry_run_flag( array $args ): bool {
		return in_array( '--dry-run', $args, true );
	}

	/**
	 * Check whether the --all flag is present in an args array.
	 *
	 * @param array<int,string> $args Parsed argument list.
	 * @return bool
	 */
	protected function has_all_flag( array $args ): bool {
		return in_array( '--all', $args, true );
	}

	/**
	 * Initialise the WP_Filesystem API.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function init_filesystem(): bool {
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
	protected function resolve_plugin_file( string $slug ) {
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
	 * Create a standard success result array.
	 *
	 * @param string $output The output message.
	 * @return array<string,mixed>
	 */
	protected function success( string $output ): array {
		return array(
			'output'  => $output,
			'success' => true,
		);
	}

	/**
	 * Create a standard failure result array.
	 *
	 * @param string $output The error message.
	 * @return array<string,mixed>
	 */
	protected function failure( string $output ): array {
		return array(
			'output'  => $output,
			'success' => false,
		);
	}
}

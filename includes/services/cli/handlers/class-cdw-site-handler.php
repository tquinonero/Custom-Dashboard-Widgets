<?php
/**
 * Site command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles site management commands (info, status, settings, empty).
 */
class CDW_Site_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a site subcommand.
	 *
	 * @param string            $subcmd   Subcommand (info, status, settings, empty).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'info':
				return $this->handle_info();

			case 'settings':
				return $this->handle_settings();

			case 'status':
				return $this->handle_status();

			case 'empty':
				return $this->handle_empty();

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for site commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available site commands:\n  site info                - Show site info\n  site settings            - Show WordPress settings\n  site status             - Show site status\n  site empty              - Optimize database",
			'success' => true,
		);
	}

	/**
	 * Check if subcommand requires --force flag.
	 *
	 * @param string $subcmd The subcommand to check.
	 * @return bool
	 */
	public function requires_force( string $subcmd ): bool {
		return 'empty' === $subcmd;
	}

	/**
	 * Handle site info.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_info(): array {
		$output  = "Site Info:\n";
		$output .= 'Name:    ' . get_bloginfo( 'name' ) . "\n";
		$output .= 'URL:     ' . get_bloginfo( 'url' ) . "\n";
		$output .= 'Admin:   ' . get_admin_url() . "\n";
		$output .= 'Version: ' . get_bloginfo( 'version' ) . "\n";

		return $this->success( $output );
	}

	/**
	 * Handle site settings.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_settings(): array {
		$output  = "WordPress Settings:\n";
		$output .= 'Tagline:       ' . get_option( 'blogdescription' ) . "\n";
		$output .= 'Admin Email:   ' . get_option( 'admin_email' ) . "\n";
		$wplang  = get_option( 'WPLANG' );
		$output .= 'Language:      ' . ( $wplang ? $wplang : 'en_US' ) . "\n";
		$output .= 'Timezone:      ' . get_option( 'timezone_string' ) . "\n";
		$output .= 'Date Format:   ' . get_option( 'date_format' ) . "\n";
		$output .= 'Time Format:   ' . get_option( 'time_format' ) . "\n";
		$output .= 'Permalink:     ' . get_option( 'permalink_structure' ) . "\n";
		$output .= 'Comments:      ' . ( 'open' === get_option( 'default_comment_status' ) ? 'Open' : 'Closed' ) . "\n";
		$output .= 'Registration:  ' . ( get_option( 'users_can_register' ) ? 'Open' : 'Closed' ) . "\n";
		$output .= 'Default Role:  ' . get_option( 'default_role' ) . "\n";

		return $this->success( $output );
	}

	/**
	 * Handle site status.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_status(): array {
		$output  = "Site Status:\n";
		$output .= 'Multisite: ' . ( is_multisite() ? 'Yes' : 'No' ) . "\n";
		$output .= 'Debug:     ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled' ) . "\n";

		return $this->success( $output );
	}

	/**
	 * Handle site empty (optimize database).
	 *
	 * @return array<string,mixed>
	 */
	private function handle_empty(): array {
		if ( ! class_exists( 'WP_Optimize' ) ) {
			return $this->failure( 'WP-Optimize plugin is required for this command.' );
		}

		wp_optimize()->database->clean_all();

		return $this->success( 'Database optimized' );
	}
}

<?php
/**
 * Theme command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles theme management commands (list, install, activate, etc.).
 */
class CDW_Theme_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a theme subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, install, activate, etc.).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'info':
				return $this->handle_info();

			case 'list':
				return $this->handle_list();

			case 'status':
				return $this->handle_status( $args );

			case 'activate':
				return $this->handle_activate( $args );

			case 'delete':
				return $this->handle_delete( $args );

			case 'install':
				return $this->handle_install( $args );

			case 'update':
				return $this->handle_update( $args, $raw_args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for theme commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available theme commands:\n  theme info               - Show active theme details\n  theme list               - List all themes\n  theme status <slug>     - Show status for a theme\n  theme install <slug>   - Install a theme\n  theme activate <slug>  - Activate a theme\n  theme update <slug>    - Update a theme\n  theme update --all    - Update all themes\n  theme delete <slug>    - Delete a theme (requires --force)",
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
		return in_array( $subcmd, array( 'delete', 'update', 'install' ), true );
	}

	/**
	 * Handle theme info.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_info(): array {
		$active_theme = wp_get_theme();
		$updates      = get_site_transient( 'update_themes' );
		$has_update   = ! empty( $updates->response[ $active_theme->get_stylesheet() ] );

		$output  = "Active Theme:\n";
		$output .= 'Name:        ' . $active_theme->get( 'Name' ) . "\n";
		$output .= 'Slug:        ' . $active_theme->get_stylesheet() . "\n";
		$output .= 'Version:     ' . $active_theme->get( 'Version' ) . "\n";
		$output .= 'Author:      ' . wp_strip_all_tags( (string) $active_theme->get( 'Author' ) ) . "\n";
		$output .= 'Description: ' . wp_strip_all_tags( (string) $active_theme->get( 'Description' ) ) . "\n";
		$output .= 'Template:    ' . $active_theme->get_template() . "\n";
		$tags         = (array) $active_theme->get( 'Tags' );
		$output .= 'Tags:        ' . ( ! empty( $tags ) ? implode( ', ', $tags ) : '(none)' ) . "\n";
		$output .= 'Update:      ' . ( $has_update ? 'Available' : 'Up to date' ) . "\n";

		return $this->success( $output );
	}

	/**
	 * Handle theme list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		$themes  = wp_get_themes();
		$current = wp_get_theme();
		$updates = get_site_transient( 'update_themes' );
		$output  = "Installed Themes:\n";

		foreach ( $themes as $theme ) {
			$status     = ( $theme->get_stylesheet() === $current->get_stylesheet() ) ? '[Active]  ' : '[Inactive]';
			$has_update = ! empty( $updates->response[ $theme->get_stylesheet() ] ) ? ' [Update available]' : '';
			$output    .= "$status " . $theme->get( 'Name' ) . ' v' . $theme->get( 'Version' ) . ' (' . $theme->get_stylesheet() . ")$has_update\n";
		}

		return $this->success( $output );
	}

	/**
	 * Handle theme status.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_status( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: theme status <theme-slug>' );
		}

		$slug   = sanitize_text_field( $args[0] );
		$themes = wp_get_themes();

		if ( ! isset( $themes[ $slug ] ) ) {
			return $this->failure( "Theme not found: $slug" );
		}

		$theme      = $themes[ $slug ];
		$current    = wp_get_theme();
		$updates    = get_site_transient( 'update_themes' );
		$has_update = ! empty( $updates->response[ $slug ] );
		$new_ver    = $has_update ? $updates->response[ $slug ]['new_version'] : null;

		$output  = 'Theme:   ' . $theme->get( 'Name' ) . "\n";
		$output .= 'Status:  ' . ( $theme->get_stylesheet() === $current->get_stylesheet() ? 'Active' : 'Inactive' ) . "\n";
		$output .= 'Version: ' . $theme->get( 'Version' ) . "\n";
		$output .= 'Update:  ' . ( $has_update ? "Available (v$new_ver)" : 'Up to date' ) . "\n";

		return $this->success( $output );
	}

	/**
	 * Handle theme activate.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_activate( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: theme activate <theme-slug>' );
		}

		$slug = sanitize_text_field( $args[0] );

		if ( ! wp_get_theme( $slug )->exists() ) {
			return $this->failure( "Theme not found: $slug" );
		}

		switch_theme( $slug );

		return $this->success( "Theme activated: $slug" );
	}

	/**
	 * Handle theme delete.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: theme delete <theme-slug> --force' );
		}

		$slug  = sanitize_text_field( $args[0] );
		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() ) {
			return $this->failure( "Theme not found: $slug" );
		}

		if ( $theme->get_stylesheet() === wp_get_theme()->get_stylesheet() ) {
			return $this->failure( 'Cannot delete active theme.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! $this->init_filesystem() ) {
			return $this->failure( 'Could not initialize filesystem.' );
		}

		global $wp_filesystem;
		$theme_dir = $theme->get_theme_root() . '/' . $slug;
		$result    = $wp_filesystem->delete( $theme_dir, true );

		if ( ! $result ) {
			return $this->failure( 'Delete failed: could not remove theme directory. Check file permissions.' );
		}

		return $this->success( "Theme deleted: $slug" );
	}

	/**
	 * Handle theme install.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_install( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: theme install <theme-slug>' );
		}

		$slug = sanitize_text_field( $args[0] );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! $this->init_filesystem() ) {
			return $this->failure( 'Could not initialize filesystem.' );
		}

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $this->failure( 'Theme not found in repository: ' . $slug );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( false === $result ) {
			$skin_errors = $skin->get_errors();
			$error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
				? $skin_errors->get_error_message()
				: 'Could not connect to the filesystem. Check permissions.';
			return $this->failure( 'Install failed: ' . $error_msg );
		}

		if ( is_wp_error( $result ) ) {
			return $this->failure( 'Install failed: ' . $result->get_error_message() );
		}

		return $this->success( "Theme installed: $slug" );
	}

	/**
	 * Handle theme update.
	 *
	 * @param array<int,string> $args    Command arguments.
	 * @param array<int,string> $raw_args Raw arguments including flags.
	 * @return array<string,mixed>
	 */
	private function handle_update( array $args, array $raw_args ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! $this->init_filesystem() ) {
			return $this->failure( 'Could not initialize filesystem.' );
		}

		$updates = get_site_transient( 'update_themes' );

		if ( $this->has_all_flag( $raw_args ) ) {
			return $this->handle_update_all( $updates );
		}

		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: theme update <slug>  |  theme update --all' );
		}

		$slug  = sanitize_text_field( $args[0] );
		$theme = wp_get_theme( $slug );

		if ( ! $theme->exists() ) {
			return $this->failure( "Theme not found: $slug" );
		}

		$theme_stylesheet = $theme->get_stylesheet();

		if ( empty( $updates->response[ $theme_stylesheet ] ) ) {
			return $this->success( "Theme is already up to date: $slug" );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $theme_stylesheet );

		if ( false === $result ) {
			$skin_errors = $skin->get_errors();
			$error_msg   = is_wp_error( $skin_errors ) && $skin_errors->has_errors()
				? $skin_errors->get_error_message()
				: 'Could not connect to the filesystem.';
			return $this->failure( 'Update failed: ' . $error_msg );
		}

		if ( is_wp_error( $result ) ) {
			return $this->failure( 'Update failed: ' . $result->get_error_message() );
		}

		wp_cache_delete( 'themes', 'themes' );

		return $this->success( "Theme updated: $slug" );
	}

	/**
	 * Handle theme update --all.
	 *
	 * @param object|null $updates Update transient.
	 * @return array<string,mixed>
	 */
	private function handle_update_all( $updates ): array {
		if ( empty( $updates->response ) ) {
			return $this->success( 'All themes are up to date.' );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$results  = $upgrader->bulk_upgrade( array_keys( $updates->response ) );

		$output = "Theme updates:\n";
		foreach ( $results as $theme_stylesheet => $result ) {
			if ( is_wp_error( $result ) ) {
				$output .= "  FAILED  $theme_stylesheet: " . $result->get_error_message() . "\n";
			} elseif ( false === $result ) {
				$output .= "  FAILED  $theme_stylesheet\n";
			} else {
				$output .= "  Updated $theme_stylesheet\n";
			}
		}

		wp_cache_delete( 'themes', 'themes' );

		return $this->success( rtrim( $output ) );
	}
}

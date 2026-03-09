<?php
/**
 * Plugin command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles plugin management commands (list, install, activate, etc.).
 */
class CDW_Plugin_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a plugin subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, install, activate, etc.).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list();

			case 'status':
				return $this->handle_status( $args );

			case 'activate':
				return $this->handle_activate( $args );

			case 'deactivate':
				return $this->handle_deactivate( $args );

			case 'update':
				return $this->handle_update( $args, $raw_args );

			case 'delete':
				return $this->handle_delete( $args );

			case 'install':
				return $this->handle_install( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for plugin commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available plugin commands:\n  plugin list              - List all plugins\n  plugin status <slug>     - Show status for a plugin\n  plugin install <slug>    - Install a plugin\n  plugin activate <slug>   - Activate a plugin\n  plugin deactivate <slug> - Deactivate a plugin\n  plugin update <slug>     - Update a plugin\n  plugin update --all      - Update all plugins\n  plugin delete <slug>     - Delete a plugin (requires --force)",
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
	 * Handle plugin list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		$plugins = get_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$output  = "Installed Plugins:\n";

		if ( empty( $plugins ) ) {
			$output .= '(no plugins installed)';
		}

		foreach ( $plugins as $path => $plugin ) {
			$status     = is_plugin_active( $path ) ? '[Active]  ' : '[Inactive]';
			$has_update = ! empty( $updates->response[ $path ] ) ? ' [Update available]' : '';
			$output    .= "$status " . $plugin['Name'] . ' v' . $plugin['Version'] . ' (' . dirname( $path ) . ")$has_update\n";
		}

		return $this->success( $output );
	}

	/**
	 * Handle plugin status.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_status( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: plugin status <plugin-slug>' );
		}

		$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
		if ( ! $plugin_file ) {
			return $this->failure( 'Plugin not found: ' . $args[0] );
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data       = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
		$updates    = get_site_transient( 'update_plugins' );
		$has_update = ! empty( $updates->response[ $plugin_file ] );
		$new_ver    = $has_update ? $updates->response[ $plugin_file ]->new_version : null;

		$output  = 'Plugin:  ' . $data['Name'] . "\n";
		$output .= 'Status:  ' . ( is_plugin_active( $plugin_file ) ? 'Active' : 'Inactive' ) . "\n";
		$output .= 'Version: ' . $data['Version'] . "\n";
		$output .= 'Update:  ' . ( $has_update ? "Available (v$new_ver)" : 'Up to date' ) . "\n";
		$output .= "File:    $plugin_file\n";

		return $this->success( $output );
	}

	/**
	 * Handle plugin activate.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_activate( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: plugin activate <plugin-slug>' );
		}

		$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
		if ( ! $plugin_file ) {
			return $this->failure( 'Plugin not found: ' . $args[0] );
		}

		$result = activate_plugin( $plugin_file );
		if ( is_wp_error( $result ) ) {
			return $this->failure( 'Activation failed: ' . $result->get_error_message() );
		}

		return $this->success( 'Plugin activated: ' . dirname( $plugin_file ) );
	}

	/**
	 * Handle plugin deactivate.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_deactivate( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: plugin deactivate <plugin-slug>' );
		}

		$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
		if ( ! $plugin_file ) {
			return $this->failure( 'Plugin not found: ' . $args[0] );
		}

		deactivate_plugins( $plugin_file );

		return $this->success( 'Plugin deactivated: ' . dirname( $plugin_file ) );
	}

	/**
	 * Handle plugin update.
	 *
	 * @param array<int,string> $args    Command arguments.
	 * @param array<int,string> $raw_args Raw arguments including flags.
	 * @return array<string,mixed>
	 */
	private function handle_update( array $args, array $raw_args ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! $this->init_filesystem() ) {
			return $this->failure( 'Could not initialize filesystem. Check file permissions.' );
		}

		$updates = get_site_transient( 'update_plugins' );

		if ( $this->has_all_flag( $raw_args ) ) {
			return $this->handle_update_all( $updates );
		}

		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: plugin update <slug>  |  plugin update --all' );
		}

		$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
		if ( ! $plugin_file ) {
			return $this->failure( 'Plugin not found: ' . $args[0] );
		}

		if ( empty( $updates->response[ $plugin_file ] ) ) {
			return $this->success( 'Plugin is already up to date: ' . $args[0] );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $plugin_file );

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

		wp_cache_delete( 'plugins', 'plugins' );

		return $this->success( 'Plugin updated: ' . $args[0] );
	}

	/**
	 * Handle plugin update --all.
	 *
	 * @param object|null $updates Update transient.
	 * @return array<string,mixed>
	 */
	private function handle_update_all( $updates ): array {
		if ( empty( $updates->response ) ) {
			return $this->success( 'All plugins are up to date.' );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$results  = $upgrader->bulk_upgrade( array_keys( $updates->response ) );

		$output = "Plugin updates:\n";
		foreach ( $results as $plugin_file => $result ) {
			if ( is_wp_error( $result ) ) {
				$output .= "  FAILED  $plugin_file: " . $result->get_error_message() . "\n";
			} elseif ( false === $result ) {
				$output .= "  FAILED  $plugin_file\n";
			} else {
				$output .= "  Updated $plugin_file\n";
			}
		}

		wp_cache_delete( 'plugins', 'plugins' );

		return $this->success( rtrim( $output ) );
	}

	/**
	 * Handle plugin delete.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: plugin delete <plugin-slug> --force' );
		}

		$plugin_file = $this->resolve_plugin_file( sanitize_text_field( $args[0] ) );
		if ( ! $plugin_file ) {
			return $this->failure( 'Plugin not found: ' . $args[0] );
		}

		if ( is_plugin_active( $plugin_file ) ) {
			return $this->failure( 'Cannot delete active plugin. Deactivate first.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( ! $this->init_filesystem() ) {
			return $this->failure( 'Could not initialize filesystem. Check file permissions.' );
		}

		$result = delete_plugins( array( $plugin_file ) );
		if ( is_wp_error( $result ) ) {
			return $this->failure( 'Delete failed: ' . $result->get_error_message() );
		}

		return $this->success( 'Plugin deleted: ' . dirname( $plugin_file ) );
	}

	/**
	 * Handle plugin install.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_install( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: plugin install <plugin-slug>' );
		}

		$slug = sanitize_text_field( $args[0] );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! $this->init_filesystem() ) {
			return $this->failure( 'Could not initialize filesystem. Check file permissions.' );
		}

		$existing_plugins  = get_plugins();
		$already_installed = false;

		foreach ( $existing_plugins as $existing_file => $plugin_data ) {
			$existing_slug = dirname( $existing_file );
			if ( '.' === $existing_slug ) {
				$existing_slug = basename( $existing_file, '.php' );
			}
			if ( $existing_slug === $slug || basename( $existing_file, '.php' ) === $slug ) {
				$already_installed = true;
				break;
			}
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $this->failure( 'Plugin not found in repository: ' . $slug . ' - ' . $api->get_error_message() );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install(
			$api->download_link,
			array(
				'overwrite_package' => $already_installed,
			)
		);

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

		wp_cache_delete( 'plugins', 'plugins' );
		$verb = $already_installed ? 're-installed' : 'installed';

		return $this->success( "Plugin $verb successfully: $slug" );
	}
}

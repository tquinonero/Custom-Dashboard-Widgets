<?php
/**
 * Skill command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles skill commands (list, get).
 */
class CDW_Skill_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a skill subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, get).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'list':
				return $this->handle_list();

			case 'get':
				return $this->handle_get( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for skill commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available skill commands:\n  skill list                                          - List all plugin skills\n  skill get <plugin-slug> <skill-name>                - Read the skill overview (SKILL.md)\n  skill get <plugin-slug> <skill-name> <file>         - Read a specific skill doc file",
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
		return false;
	}

	/**
	 * Handle skill list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		$skills = glob( WP_PLUGIN_DIR . '/*/skills/*/SKILL.md' );

		if ( empty( $skills ) ) {
			return $this->success( 'No plugin skills found.' );
		}

		$output = "Available Skills:\n";
		foreach ( $skills as $skill_path ) {
			$parts      = explode( '/', $skill_path );
			$plugin_idx = count( $parts ) - 4;
			$skill_idx  = count( $parts ) - 2;
			if ( isset( $parts[ $plugin_idx ], $parts[ $skill_idx ] ) ) {
				$output .= $parts[ $plugin_idx ] . ' / ' . $parts[ $skill_idx ] . "\n";
			}
		}

		return $this->success( $output );
	}

	/**
	 * Handle skill get.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_get( array $args ): array {
		if ( empty( $args[0] ) || empty( $args[1] ) ) {
			return $this->failure( "Usage: skill get <plugin-slug> <skill-name> [<file>]\nExample: skill get greenshift-animation-and-page-builder-blocks greenlight-vibe" );
		}

		$plugin_slug = sanitize_key( $args[0] );
		$skill_name  = sanitize_key( $args[1] );
		$file        = isset( $args[2] ) ? ltrim( sanitize_text_field( $args[2] ), '/' ) : 'SKILL.md';

		if ( ! str_ends_with( $file, '.md' ) ) {
			return $this->failure( 'Only .md files can be retrieved.' );
		}

		if ( str_contains( $file, '..' ) ) {
			return $this->failure( 'Access denied.' );
		}

		$target_path      = WP_PLUGIN_DIR . '/' . $plugin_slug . '/skills/' . $skill_name . '/' . $file;
		$real_plugins_dir = realpath( WP_PLUGIN_DIR );
		$real_target      = realpath( $target_path );

		if ( false === $real_plugins_dir || false === $real_target ) {
			return $this->failure( "Skill file not found: {$plugin_slug} / {$skill_name} / {$file}" );
		}

		if ( ! str_starts_with( $real_target, $real_plugins_dir . DIRECTORY_SEPARATOR ) ) {
			return $this->failure( 'Access denied.' );
		}

		$content = file_get_contents( $real_target );

		if ( false === $content ) {
			return $this->failure( "Could not read: {$file}" );
		}

		return $this->success( $content );
	}
}

<?php
/**
 * Command handler interface for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for CLI command handlers.
 *
 * Each handler manages a specific category of CLI commands
 * (e.g., plugin, theme, user, post, etc.).
 */
interface CDW_Command_Handler_Interface {
	/**
	 * Execute a subcommand with given arguments.
	 *
	 * @param string            $subcmd   The subcommand to execute.
	 * @param array<int,string> $args     Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array with 'output' and 'success' keys.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array;

	/**
	 * Get the help text for this handler's commands.
	 *
	 * @return array<string,mixed> Help array with 'output' and 'success' keys.
	 */
	public function get_help(): array;

	/**
	 * Check if a subcommand requires the --force flag.
	 *
	 * @param string $subcmd The subcommand to check.
	 * @return bool True if --force is required.
	 */
	public function requires_force( string $subcmd ): bool;
}

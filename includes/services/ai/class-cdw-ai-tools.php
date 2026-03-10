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
require_once CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-ai-tools-config.php';

/**
 * AI Tools handler - manages tool definitions and execution.
 */
class CDW_AI_Tools {

	/**
	 * Cached config for tool definitions.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $tool_config = null;

	/**
	 * Load tool configuration from config file.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_tool_config() {
		if ( null === self::$tool_config ) {
			self::$tool_config = require CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-ai-tools-config.php';
		}
		return self::$tool_config;
	}

	/**
	 * Build OpenAI-style parameter definition from param config.
	 *
	 * @param array<string,mixed> $param_config Param configuration.
	 * @return array<string,mixed>
	 */
	private static function build_param_definition( $param_config ) {
		$def = array(
			'type'        => $param_config['type'] ?? 'string',
			'description' => $param_config['description'] ?? '',
		);

		if ( isset( $param_config['enum'] ) && is_array( $param_config['enum'] ) ) {
			$def['enum'] = $param_config['enum'];
		}

		return $def;
	}

	/**
	 * Build a single tool definition in OpenAI format.
	 *
	 * @param string              $name Tool name.
	 * @param array<string,mixed> $def  Tool definition from config.
	 * @return array<string,mixed>
	 */
	private static function build_tool_definition( $name, $def ) {
		$properties = array();
		$required   = array();

		foreach ( $def['params'] ?? array() as $param_name => $param_config ) {
			$properties[ $param_name ] = self::build_param_definition( $param_config );
		}

		foreach ( $def['required'] ?? array() as $req_param ) {
			$required[] = $req_param;
		}

		return array(
			'name'        => $name,
			'description' => $def['description'] ?? '',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => (object) $properties,
				'required'   => $required,
			),
		);
	}

	/**
	 * Returns CDW CLI commands formatted as OpenAI-compatible function-calling tools.
	 *
	 * Each tool maps 1-to-1 with an internal CDW CLI command. The same list is
	 * converted to provider-specific formats inside the provider call methods.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_tool_definitions() {
		$config = self::get_tool_config();
		$tools  = array();

		foreach ( $config as $name => $def ) {
			$tools[] = self::build_tool_definition( $name, $def );
		}

		return $tools;
	}

	/**
	 * Converts a tool name + arguments to a CDW CLI command string.
	 *
	 * Uses config-based mapping for most tools. Special cases (marked as null
	 * in config) return null to trigger inline handling in execute_tool_call().
	 *
	 * @param string              $tool_name  Tool name.
	 * @param array<string,mixed> $arguments  Tool arguments.
	 * @return string|null CLI command string, or null if the tool is unknown/handled inline.
	 */
	public static function tool_name_to_cli_command( $tool_name, $arguments ) {
		static $config = null;

		if ( null === $config ) {
			$config = require CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-ai-tools-config.php';
		}

		if ( ! isset( $config[ $tool_name ] ) ) {
			return null;
		}

		$cli_pattern = $config[ $tool_name ];

		// null means handled inline in execute_tool_call().
		if ( ! $cli_pattern ) {
			return null;
		}

		// Replace placeholders with arguments.
		$command = preg_replace_callback(
			'/\{(\w+)\}/',
			function ( $matches ) use ( $arguments ) {
				$value = $arguments[ $matches[1] ] ?? '';
				return rawurlencode( $value );
			},
			$cli_pattern
		);

		// Clean up empty flags (e.g., --user_id= becomes omitted).
		$command = preg_replace( '/\s+--\w+=$/', '', $command );
		// Clean up trailing empty placeholders (e.g., "post count " -> "post count").
		$command = preg_replace( '/\s+$/', '', $command );

		return $command;
	}

	/**
	 * Maps an AI tool call to a CDW CLI command string and executes it.
	 *
	 * @param string              $function_name Tool name from get_tool_definitions().
	 * @param array<string,mixed> $arguments     Arguments parsed from the tool call.
	 * @param int                 $user_id       WordPress user ID (for rate limiting).
	 * @return string Text output of the command (or error message).
	 */
	public static function execute_tool_call( $function_name, $arguments, $user_id ): string {
		if ( 'gutenberg_guide' === $function_name ) {
			$skill_path       = WP_PLUGIN_DIR . '/cdw/skills/gutenberg-design/SKILL.md';
			$real_plugins_dir = realpath( WP_PLUGIN_DIR );
			$real_path        = realpath( $skill_path );

			if ( false === $real_plugins_dir || false === $real_path ) {
				return 'Error: Gutenberg design guide not found.';
			}

			if ( ! str_starts_with( $real_path, $real_plugins_dir . DIRECTORY_SEPARATOR ) ) {
				return 'Error: Access denied.';
			}

			$content = file_get_contents( $real_path );

			if ( false === $content ) {
				return 'Error: Could not read Gutenberg design guide.';
			}

			return $content;
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

<?php
/**
 * AI Service — provider abstraction, agentic loop, and key management.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-encryption.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-user-settings.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-rate-limiter.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-usage-tracker.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-tools.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-providers.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-prompts.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-agentic-loop.php';

/**
 * Provides AI chat functionality via OpenAI, Anthropic, and Google Gemini.
 *
 * API keys are stored encrypted in user meta. The agentic loop sends the
 * conversation to the chosen provider with CDW CLI commands exposed as
 * function-calling tools. If the provider calls a tool, the command is
 * executed through CDW_CLI_Service and the result is fed back for a
 * final answer (single-turn-with-tool-use, v1).
 *
 * @package CDW
 */
class CDW_AI_Service {

	/**
	 * BC aliases — use the dedicated classes directly for new code.
	 *
	 * @var string
	 */
	const USAGE_META_KEY = CDW_AI_Usage_Tracker::USAGE_META_KEY;

	// -------------------------------------------------------------------------
	// Providers catalogue
	// -------------------------------------------------------------------------

	/**
	 * Returns the list of supported AI providers with their available models.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_providers() {
		static $providers = null;
		if ( null === $providers ) {
			$providers = include CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-ai-providers-config.php';
		}
		return $providers;
	}

	// -------------------------------------------------------------------------
	// Encryption helpers
	// -------------------------------------------------------------------------

	/**
	 * Encrypts an API key for storage using AES-256-CBC derived from wp salts.
	 *
	 * The IV (16 bytes) is prepended directly to the ciphertext before base64
	 * encoding. No separator is used, so random IV bytes can never corrupt the
	 * split during decryption.
	 *
	 * @param string $plaintext The raw API key to encrypt.
	 * @return string Base64-encoded ciphertext (iv prepended, no separator).
	 */
	public static function encrypt_api_key( $plaintext ) {
		return CDW_AI_Encryption::encrypt( $plaintext );
	}

	/**
	 * Decrypts a stored API key.
	 *
	 * Supports both the current format (IV as fixed 16-byte prefix) and the
	 * legacy format that used a '::' separator between the IV and ciphertext.
	 *
	 * @param string $ciphertext Base64-encoded ciphertext from encrypt_api_key().
	 * @return string Plaintext API key, or empty string on failure.
	 */
	public static function decrypt_api_key( $ciphertext ) {
		return CDW_AI_Encryption::decrypt( $ciphertext );
	}

	// -------------------------------------------------------------------------
	// Per-user AI settings (stored in user meta)
	// -------------------------------------------------------------------------

	/**
	 * Returns the AI settings for the given user.
	 *
	 * The raw API key is never included; only has_key (bool) is returned.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string,mixed> {provider, model, execution_mode, has_key, usage}
	 */
	public static function get_user_ai_settings( $user_id ) {
		return CDW_AI_User_Settings::get_settings( $user_id );
	}

	/**
	 * Saves the AI settings (and optionally the API key) for a user.
	 *
	 * @param int                 $user_id  WordPress user ID.
	 * @param array<string,mixed> $settings Keys: provider, model, execution_mode, api_key (all optional).
	 * @return true|WP_Error
	 */
	public static function save_user_ai_settings( $user_id, $settings ) {
		return CDW_AI_User_Settings::save_settings( $user_id, $settings );
	}

	/**
	 * Retrieves the decrypted API key for the given user and provider.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $provider Provider slug.
	 * @return string Decrypted API key or empty string if not set.
	 */
	public static function get_decrypted_api_key( $user_id, $provider ) {
		return CDW_AI_User_Settings::get_decrypted_api_key( $user_id, $provider );
	}

	// -------------------------------------------------------------------------
	// Rate limiting
	// -------------------------------------------------------------------------

	/**
	 * Checks the per-user AI rate limit.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return true|WP_Error True if within limit, WP_Error if exceeded.
	 */
	public static function check_ai_rate_limit( $user_id ) {
		return CDW_AI_Rate_Limiter::check( $user_id );
	}

	// -------------------------------------------------------------------------
	// Tool definitions (CDW CLI commands as function-calling tools)
	// -------------------------------------------------------------------------

	/**
	 * Returns CDW CLI commands formatted as OpenAI-compatible function-calling tools.
	 *
	 * Each tool maps 1-to-1 with an internal CDW CLI command. The same list is
	 * converted to provider-specific formats inside the provider call methods.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_tool_definitions() {
		return CDW_AI_Tools::get_tool_definitions();
	}

	// -------------------------------------------------------------------------
	// System prompt
	// -------------------------------------------------------------------------

	/**
	 * Builds the system prompt with live WordPress context.
	 *
	 * @param string $custom_prompt Optional custom instructions to append.
	 * @return string Full system prompt text.
	 */
	public static function build_system_prompt( $custom_prompt = '' ) {
		return CDW_AI_Prompts::build_system_prompt( $custom_prompt );
	}

	// -------------------------------------------------------------------------
	// Tool execution
	// -------------------------------------------------------------------------

	/**
	 * Maps an AI tool call to a CDW CLI command string and executes it.
	 *
	 * @param string              $function_name Tool name from get_tool_definitions().
	 * @param array<string,mixed> $arguments     Arguments parsed from the tool call.
	 * @param int                 $user_id       WordPress user ID (for rate limiting).
	 * @return string Text output of the command (or error message).
	 */
	public static function execute_tool_call( $function_name, $arguments, $user_id ): string {
		return CDW_AI_Tools::execute_tool_call( $function_name, $arguments, $user_id );
	}

	// -------------------------------------------------------------------------

	/**
	 * Accumulates token usage for a user after a successful AI call.
	 *
	 * @param int                 $user_id       WordPress user ID.
	 * @param array<string,mixed> $usage_delta   {prompt_tokens, completion_tokens, total_tokens}.
	 * @return void
	 */
	public static function record_usage( $user_id, $usage_delta ) {
		CDW_AI_Usage_Tracker::record_usage( $user_id, $usage_delta );
	}

	// -------------------------------------------------------------------------
	// Provider-specific HTTP calls
	// -------------------------------------------------------------------------

	/**
	 * Parses a wp_remote_post() response into a normalised array.
	 *
	 * @param array<string,mixed>|WP_Error $response  wp_remote_post() result.
	 * @param string                       $provider  Provider slug for error context.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function parse_http_response( $response, $provider ) {
		return CDW_AI_Providers::parse_http_response( $response, $provider );
	}

	/**
	 * Routes the chat request to the appropriate provider.
	 *
	 * @param array<int,array<string,mixed>> $messages       Chat messages.
	 * @param array<int,array<string,mixed>> $tools          Tool definitions.
	 * @param string                         $api_key        Provider API key.
	 * @param string                         $model          Model ID.
	 * @param string                         $system_prompt  System prompt (used for Anthropic).
	 * @param string                         $base_url       Custom base URL for OpenAI-compatible.
	 * @param string                         $provider       Provider slug.
	 * @return array<string,mixed>|WP_Error Parsed response or WP_Error.
	 */
	public static function call_provider( $messages, $tools, $api_key, $model, $system_prompt = '', $base_url = '', $provider = '' ) {
		return CDW_AI_Providers::call_provider( $messages, $tools, $api_key, $model, $system_prompt, $base_url, $provider );
	}

	// -------------------------------------------------------------------------
	// API key test
	// -------------------------------------------------------------------------
	/**
	 * Sends a minimal request to the provider to verify that the API key is valid.
	 *
	 * @param string $api_key  Decrypted API key.
	 * @param string $provider Provider slug.
	 * @param string $model    Model ID to test against.
	 * @param string $base_url Optional custom base URL for OpenAI-compatible endpoints.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_api_key( $api_key, $provider, $model, $base_url = '' ) {
		$minimal_messages = array(
			array(
				'role'    => 'user',
				'content' => 'Reply with the single word: OK',
			),
		);

		$result = self::call_provider( $minimal_messages, array(), $api_key, $model, '', $base_url, $provider );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}

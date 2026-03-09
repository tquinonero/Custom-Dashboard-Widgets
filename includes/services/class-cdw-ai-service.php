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
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-tools.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-providers.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-prompts.php';

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
	 * User meta key prefix for encrypted API keys.
	 *
	 * @var string
	 */
	const API_KEY_META_PREFIX = 'cdw_ai_api_key_';

	/**
	 * User meta key for the selected provider.
	 *
	 * @var string
	 */
	const PROVIDER_META_KEY = 'cdw_ai_provider';

	/**
	 * User meta key for the selected model.
	 *
	 * @var string
	 */
	const MODEL_META_KEY = 'cdw_ai_model';

	/**
	 * User meta key for execution mode (auto|confirm).
	 *
	 * @var string
	 */
	const EXECUTION_MODE_META_KEY = 'cdw_ai_execution_mode';

	/**
	 * User meta key for token usage JSON blob.
	 *
	 * @var string
	 */
	const USAGE_META_KEY = 'cdw_ai_token_usage';

	/**
	 * AI rate-limit transient prefix (per user).
	 *
	 * @var string
	 */
	const RATE_LIMIT_PREFIX = 'cdw_ai_rate_';

	/**
	 * Maximum AI requests per window per user.
	 *
	 * @var int
	 */
	const RATE_LIMIT_COUNT = 30;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * User meta key for the custom provider base URL.
	 *
	 * @var string
	 */
	const BASE_URL_META_KEY = 'cdw_ai_base_url';

	// -------------------------------------------------------------------------
	// Providers catalogue
	// -------------------------------------------------------------------------

	/**
	 * Returns the list of supported AI providers with their available models.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_providers() {
		return array(
			'openai'    => array(
				'label'  => 'OpenAI',
				'models' => array(
					array(
						'id'    => 'gpt-4o',
						'label' => 'GPT-4o',
					),
					array(
						'id'    => 'gpt-4o-mini',
						'label' => 'GPT-4o Mini',
					),
					array(
						'id'    => 'gpt-4-turbo',
						'label' => 'GPT-4 Turbo',
					),
				),
			),
			'anthropic' => array(
				'label'  => 'Anthropic',
				'models' => array(
					array(
						'id'    => 'claude-3-5-sonnet-20241022',
						'label' => 'Claude 3.5 Sonnet',
					),
					array(
						'id'    => 'claude-3-5-haiku-20241022',
						'label' => 'Claude 3.5 Haiku',
					),
					array(
						'id'    => 'claude-3-opus-20240229',
						'label' => 'Claude 3 Opus',
					),
				),
			),
			'google'    => array(
				'label'  => 'Google Gemini',
				'models' => array(
					array(
						'id'    => 'gemini-2.0-flash',
						'label' => 'Gemini 2.0 Flash',
					),
					array(
						'id'    => 'gemini-1.5-pro',
						'label' => 'Gemini 1.5 Pro',
					),
					array(
						'id'    => 'gemini-1.5-flash',
						'label' => 'Gemini 1.5 Flash',
					),
				),
			),
			'custom'    => array(
				'label'      => 'Custom (OpenAI-compatible)',
				'custom_url' => true,
				'models'     => array(
					array(
						'id'    => 'custom',
						'label' => 'Enter model name manually',
					),
				),
			),
		);
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
		$key    = self::derive_encryption_key();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $cipher );
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
		if ( empty( $ciphertext ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = base64_decode( $ciphertext, true );
		if ( false === $decoded || strlen( $decoded ) <= 16 ) {
			return '';
		}

		$key = self::derive_encryption_key();

		// Current format: first 16 bytes are the IV, remainder is the ciphertext.
		$iv        = substr( $decoded, 0, 16 );
		$cipher    = substr( $decoded, 16 );
		$plaintext = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false !== $plaintext ) {
			return $plaintext;
		}

		// Legacy format: iv (16 raw bytes) . '::' . cipher — kept for backward
		// compatibility with keys stored before the separator bug was fixed.
		$parts = explode( '::', $decoded, 2 );
		if ( 2 === count( $parts ) && 16 === strlen( $parts[0] ) ) {
			$plaintext = openssl_decrypt( $parts[1], 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $parts[0] );
			return false !== $plaintext ? $plaintext : '';
		}

		return '';
	}

	/**
	 * Derives a 32-byte encryption key from WordPress AUTH_SALT + SECURE_AUTH_SALT.
	 *
	 * Falls back to a hash of the siteurl if the constants are not defined.
	 *
	 * @return string 32-byte binary string.
	 */
	private static function derive_encryption_key() {
		$salt  = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'cdw-fallback-auth-salt';
		$salt .= defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'cdw-fallback-secure-salt';
		$salt .= 'cdw-ai-key-v1';
		return substr( hash( 'sha256', $salt, true ), 0, 32 );
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
		$provider       = get_user_meta( $user_id, self::PROVIDER_META_KEY, true );
		$model          = get_user_meta( $user_id, self::MODEL_META_KEY, true );
		$execution_mode = get_user_meta( $user_id, self::EXECUTION_MODE_META_KEY, true );
		$usage          = get_user_meta( $user_id, self::USAGE_META_KEY, true );
		$base_url       = get_user_meta( $user_id, self::BASE_URL_META_KEY, true );

		$provider       = ! empty( $provider ) ? $provider : 'openai';
		$model          = ! empty( $model ) ? $model : 'gpt-4o-mini';
		$execution_mode = ! empty( $execution_mode ) ? $execution_mode : 'confirm';
		$base_url       = ! empty( $base_url ) ? $base_url : '';

		// Check whether a key exists for the active provider (without exposing it).
		$encrypted_key = get_user_meta( $user_id, self::API_KEY_META_PREFIX . $provider, true );
		$has_key       = ! empty( $encrypted_key );

		$default_usage = array(
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'request_count'     => 0,
		);

		return array(
			'provider'       => $provider,
			'model'          => $model,
			'execution_mode' => $execution_mode,
			'has_key'        => $has_key,
			'base_url'       => $base_url,
			'usage'          => is_array( $usage ) ? $usage : $default_usage,
		);
	}

	/**
	 * Saves the AI settings (and optionally the API key) for a user.
	 *
	 * @param int                 $user_id  WordPress user ID.
	 * @param array<string,mixed> $settings Keys: provider, model, execution_mode, api_key (all optional).
	 * @return true|WP_Error
	 */
	public static function save_user_ai_settings( $user_id, $settings ) {
		$providers = array_keys( self::get_providers() );

		if ( isset( $settings['provider'] ) ) {
			$provider = sanitize_text_field( $settings['provider'] );
			if ( ! in_array( $provider, $providers, true ) ) {
				return new WP_Error( 'invalid_provider', 'Unsupported AI provider.', array( 'status' => 400 ) );
			}
			update_user_meta( $user_id, self::PROVIDER_META_KEY, $provider );
		} else {
			$provider = get_user_meta( $user_id, self::PROVIDER_META_KEY, true );
			$provider = ! empty( $provider ) ? $provider : 'openai';
		}

		if ( isset( $settings['model'] ) ) {
			update_user_meta( $user_id, self::MODEL_META_KEY, sanitize_text_field( $settings['model'] ) );
		}

		if ( isset( $settings['execution_mode'] ) ) {
			$mode = sanitize_text_field( $settings['execution_mode'] );
			if ( ! in_array( $mode, array( 'auto', 'confirm' ), true ) ) {
				return new WP_Error( 'invalid_execution_mode', 'Execution mode must be "auto" or "confirm".', array( 'status' => 400 ) );
			}
			update_user_meta( $user_id, self::EXECUTION_MODE_META_KEY, $mode );
		}

		if ( isset( $settings['api_key'] ) && '' !== $settings['api_key'] ) {
			$raw_key   = sanitize_text_field( $settings['api_key'] );
			$encrypted = self::encrypt_api_key( $raw_key );
			update_user_meta( $user_id, self::API_KEY_META_PREFIX . $provider, $encrypted );
		}

		if ( isset( $settings['base_url'] ) ) {
			$url = esc_url_raw( trim( $settings['base_url'] ) );
			if ( ! empty( $url ) && ! preg_match( '#^https?://#i', $url ) ) {
				return new WP_Error( 'invalid_base_url', 'Base URL must start with http:// or https://', array( 'status' => 400 ) );
			}
			update_user_meta( $user_id, self::BASE_URL_META_KEY, $url );
		}

		return true;
	}

	/**
	 * Retrieves the decrypted API key for the given user and provider.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $provider Provider slug.
	 * @return string Decrypted API key or empty string if not set.
	 */
	public static function get_decrypted_api_key( $user_id, $provider ) {
		$encrypted = get_user_meta( $user_id, self::API_KEY_META_PREFIX . $provider, true );
		if ( empty( $encrypted ) ) {
			return '';
		}
		return self::decrypt_api_key( $encrypted );
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
		$transient_key = self::RATE_LIMIT_PREFIX . $user_id;
		$count         = (int) get_transient( $transient_key );

		if ( $count >= self::RATE_LIMIT_COUNT ) {
			return new WP_Error(
				'ai_rate_limited',
				sprintf(
					'AI rate limit exceeded. Maximum %d requests per %d seconds.',
					self::RATE_LIMIT_COUNT,
					self::RATE_LIMIT_WINDOW
				),
				array( 'status' => 429 )
			);
		}

		set_transient( $transient_key, $count + 1, self::RATE_LIMIT_WINDOW );
		return true;
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
	 * @return string|array<string, mixed> Text output of the command (or error message), or array for gutenberg_guide.
	 */
	public static function execute_tool_call( $function_name, $arguments, $user_id ): string|array {
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
	private static function record_usage( $user_id, $usage_delta ) {
		$existing = get_user_meta( $user_id, self::USAGE_META_KEY, true );
		if ( ! is_array( $existing ) ) {
			$existing = array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
				'request_count'     => 0,
			);
		}

		$existing['prompt_tokens']     += isset( $usage_delta['prompt_tokens'] ) ? (int) $usage_delta['prompt_tokens'] : 0;
		$existing['completion_tokens'] += isset( $usage_delta['completion_tokens'] ) ? (int) $usage_delta['completion_tokens'] : 0;
		$existing['total_tokens']      += isset( $usage_delta['total_tokens'] ) ? (int) $usage_delta['total_tokens'] : 0;
		$existing['request_count']     += 1;

		update_user_meta( $user_id, self::USAGE_META_KEY, $existing );
	}

	// -------------------------------------------------------------------------
	// Provider-specific HTTP calls
	// -------------------------------------------------------------------------

	/**
	 * Sends a chat request to the OpenAI API with tool definitions.
	 *
	 * @param array<int,array<string,mixed>> $messages  Chat messages (system, user, assistant, tool).
	 * @param array<int,array<string,mixed>> $tools     Tool definitions from get_tool_definitions().
	 * @param string                         $api_key   OpenAI API key.
	 * @param string                         $model     Model ID, e.g. "gpt-4o".
	 * @param string                         $base_url  Optional custom base URL (e.g. Groq, OpenRouter). Defaults to OpenAI.
	 * @return array<string,mixed>|WP_Error Parsed response or WP_Error on failure.
	 */
	private static function call_openai( $messages, $tools, $api_key, $model, $base_url = '' ) {
		return CDW_AI_Providers::call_openai( $messages, $tools, $api_key, $model, $base_url );
	}

	/**
	 * Sends a chat request to the Anthropic API with tool definitions.
	 *
	 * @param array<int,array<string,mixed>> $messages       Chat messages (user/assistant only; system is separate).
	 * @param array<int,array<string,mixed>> $tools          Tool definitions.
	 * @param string                         $api_key        Anthropic API key.
	 * @param string                         $model          Model ID, e.g. "claude-3-5-sonnet-20241022".
	 * @param string                         $system_prompt  System prompt text.
	 * @return array<string,mixed>|WP_Error Parsed response or WP_Error on failure.
	 */
	private static function call_anthropic( $messages, $tools, $api_key, $model, $system_prompt = '' ) {
		return CDW_AI_Providers::call_anthropic( $messages, $tools, $api_key, $model, $system_prompt );
	}

	/**
	 * Sends a chat request to the Google Gemini API with tool definitions.
	 *
	 * @param array<int,array<string,mixed>> $messages  Chat messages.
	 * @param array<int,array<string,mixed>> $tools     Tool definitions.
	 * @param string                         $api_key   Google API key.
	 * @param string                         $model     Model ID, e.g. "gemini-2.0-flash".
	 * @return array<string,mixed>|WP_Error Parsed response or WP_Error on failure.
	 */
	private static function call_google( $messages, $tools, $api_key, $model ) {
		return CDW_AI_Providers::call_google( $messages, $tools, $api_key, $model );
	}

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
	 * Formats the assistant's tool-call turn in a provider-compatible way.
	 *
	 * @param string              $provider     Provider slug.
	 * @param array<string,mixed> $api_response Normalised API response.
	 * @param array<string,mixed> $tc           Single tool call {id, name, arguments}.
	 * @return array<string,mixed> Message array to append to $messages.
	 */
	private static function format_assistant_tool_call_message( $provider, $api_response, $tc ) {
		return CDW_AI_Providers::format_assistant_tool_call_message( $provider, $api_response, $tc );
	}

	/**
	 * Formats a tool result message for feeding back to the provider.
	 *
	 * @param string              $provider    Provider slug.
	 * @param array<string,mixed> $tc          Tool call {id, name, arguments}.
	 * @param string              $tool_output Tool execution output.
	 * @return array<string,mixed> Message array.
	 */
	private static function format_tool_result_message( $provider, $tc, $tool_output ) {
		return CDW_AI_Providers::format_tool_result_message( $provider, $tc, $tool_output );
	}

	// -------------------------------------------------------------------------
	// Agentic loop
	// -------------------------------------------------------------------------

	/**
	 * Runs a single-turn agentic loop: builds context, calls the provider,
	 * executes any tool call, feeds the result back, and returns the final answer.
	 *
	 * @param string                         $user_message    The new user message.
	 * @param array<int,array<string,mixed>> $history         Previous turns [{role, content}].
	 * @param string                         $api_key         Decrypted provider API key.
	 * @param string                         $provider        Provider slug (openai|anthropic|google|custom).
	 * @param string                         $model           Model ID.
	 * @param int                            $user_id         Current WordPress user ID.
	 * @param string                         $custom_prompt   Optional custom system instructions.
	 * @param string                         $base_url        Optional custom base URL for OpenAI-compatible endpoints.
	 * @return array<string,mixed>|WP_Error {
	 *     @type string                       $content         Final assistant text reply.
	 *     @type array<int,array<string,mixed>> $tool_calls_made [{name, arguments, output}].
	 *     @type array<string,int>            $usage           Token counts for this turn.
	 * }
	 */
	public static function execute_agentic_loop( $user_message, $history, $api_key, $provider, $model, $user_id, $custom_prompt = '', $base_url = '' ) {
		$tools         = self::get_tool_definitions();
		$system_prompt = self::build_system_prompt( $custom_prompt );

		// Build messages array.
		// For Anthropic, system goes in a dedicated field (handled in call_anthropic).
		// For OpenAI, Google, and custom, we prepend it as a system message.
		$messages = array();
		if ( 'anthropic' !== $provider ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		// Append prior history (cap at last 20 turns to control token usage).
		$capped_history = array_slice( $history, -20 );
		foreach ( $capped_history as $turn ) {
			if ( isset( $turn['role'], $turn['content'] ) ) {
				$messages[] = array(
					'role'    => sanitize_text_field( $turn['role'] ),
					'content' => sanitize_textarea_field( $turn['content'] ),
				);
			}
		}

		// Append current user message.
		$messages[] = array(
			'role'    => 'user',
			'content' => sanitize_textarea_field( $user_message ),
		);

		// -- First provider call -----------------------------------------------
		$tool_calls_made = array();

		if ( 'openai' === $provider || 'custom' === $provider ) {
			$api_response = self::call_openai( $messages, $tools, $api_key, $model, $base_url );
		} elseif ( 'anthropic' === $provider ) {
			$api_response = self::call_anthropic( $messages, $tools, $api_key, $model, $system_prompt );
		} else {
			$api_response = self::call_google( $messages, $tools, $api_key, $model );
		}

		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}

		$total_usage = $api_response['usage'];

		// -- Handle tool calls -------------------------------------------------
		if ( ! empty( $api_response['tool_calls'] ) ) {
			foreach ( $api_response['tool_calls'] as $tc ) {
				$tool_output = self::execute_tool_call( $tc['name'], $tc['arguments'], $user_id );

				$tool_calls_made[] = array(
					'name'      => $tc['name'],
					'arguments' => $tc['arguments'],
					'output'    => $tool_output,
				);

				// Append assistant tool-call turn + tool result.
				$messages[] = self::format_assistant_tool_call_message( $provider, $api_response, $tc );
				$messages[] = self::format_tool_result_message( $provider, $tc, $tool_output );
			}

			// -- Second provider call to get final answer ----------------------
			if ( 'openai' === $provider || 'custom' === $provider ) {
				$final_response = self::call_openai( $messages, $tools, $api_key, $model, $base_url );
			} elseif ( 'anthropic' === $provider ) {
				$final_response = self::call_anthropic( $messages, $tools, $api_key, $model, $system_prompt );
			} else {
				$final_response = self::call_google( $messages, $tools, $api_key, $model );
			}

			if ( is_wp_error( $final_response ) ) {
				// Return tool results with partial content if final call fails.
				return array(
					'content'         => $api_response['content'],
					'tool_calls_made' => $tool_calls_made,
					'usage'           => $total_usage,
				);
			}

			$total_usage['prompt_tokens']     += $final_response['usage']['prompt_tokens'];
			$total_usage['completion_tokens'] += $final_response['usage']['completion_tokens'];
			$total_usage['total_tokens']      += $final_response['usage']['total_tokens'];

			$final_content = $final_response['content'];

			// Fallback: if the model returned empty content after the tool call
			// (some providers stop without a text reply when the tool result is
			// self-explanatory), synthesise a response from the tool output.
			if ( '' === trim( $final_content ) && ! empty( $tool_calls_made ) ) {
				$outputs       = array_map(
					function ( $tc ) {
						return $tc['output'];
					},
					$tool_calls_made
				);
				$final_content = implode( "\n\n", array_map( 'strval', $outputs ) );
			}
		} else {
			$final_content = $api_response['content'];
		}

		self::record_usage( $user_id, $total_usage );

		return array(
			'content'         => $final_content,
			'tool_calls_made' => $tool_calls_made,
			'usage'           => $total_usage,
	);

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

		if ( 'openai' === $provider || 'custom' === $provider ) {
			$result = self::call_openai( $minimal_messages, array(), $api_key, $model, $base_url );
		} elseif ( 'anthropic' === $provider ) {
			$result = self::call_anthropic( $minimal_messages, array(), $api_key, $model );
		} else {
			$result = self::call_google( $minimal_messages, array(), $api_key, $model );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}

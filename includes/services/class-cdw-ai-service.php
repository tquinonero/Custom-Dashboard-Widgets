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
		return array(
			array(
				'name'        => 'plugin_list',
				'description' => 'List all installed plugins with their status (active/inactive) and version.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'plugin_status',
				'description' => 'Show the current status and details of a specific plugin.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug, e.g. "woocommerce".',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_activate',
				'description' => 'Activate an installed plugin.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_deactivate',
				'description' => 'Deactivate an active plugin.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_install',
				'description' => 'Install a plugin from WordPress.org by slug.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug from WordPress.org.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_update',
				'description' => 'Update a plugin to its latest available version.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'plugin_update_all',
				'description' => 'Update all installed plugins that have a pending update.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'plugin_delete',
				'description' => 'Delete (uninstall) a plugin. The plugin must be inactive first.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_list',
				'description' => 'List all installed themes.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'theme_activate',
				'description' => 'Activate an installed theme.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_install',
				'description' => 'Install a theme from WordPress.org by slug.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug from WordPress.org.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_update',
				'description' => 'Update a theme to its latest available version.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'theme_update_all',
				'description' => 'Update all installed themes that have a pending update.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'user_list',
				'description' => 'List all WordPress users.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'user_create',
				'description' => 'Create a new WordPress user.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'username' => array(
							'type'        => 'string',
							'description' => 'Username (login name).',
						),
						'email'    => array(
							'type'        => 'string',
							'description' => 'Email address.',
						),
						'role'     => array(
							'type'        => 'string',
							'description' => 'WordPress role, e.g. "editor", "subscriber".',
						),
					),
					'required'   => array( 'username', 'email', 'role' ),
				),
			),
			array(
				'name'        => 'user_delete',
				'description' => 'Delete a WordPress user by user ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID of the user to delete.',
						),
					),
					'required'   => array( 'user_id' ),
				),
			),
			array(
				'name'        => 'cache_flush',
				'description' => 'Flush all WordPress object cache and transients.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'option_get',
				'description' => 'Get the value of a WordPress option.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
					),
					'required'   => array( 'name' ),
				),
			),
			array(
				'name'        => 'option_list',
				'description' => 'List all CDW-managed option keys.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'option_set',
				'description' => 'Set the value of a WordPress option. Protected core options cannot be changed.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name'  => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
						'value' => array(
							'type'        => 'string',
							'description' => 'New value for the option.',
						),
					),
					'required'   => array( 'name', 'value' ),
				),
			),
			array(
				'name'        => 'cron_list',
				'description' => 'List all scheduled WordPress cron events.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'site_info',
				'description' => 'Show general information about the WordPress site (URL, theme, admin email, etc.).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'site_status',
				'description' => 'Show a health summary of the WordPress site.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'db_size',
				'description' => 'Show the size of the WordPress database.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'db_tables',
				'description' => 'List all tables in the WordPress database.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'search_replace',
				'description' => 'Search and replace a string across the WordPress database. Always run a dry-run first.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'search'  => array(
							'type'        => 'string',
							'description' => 'String to search for.',
						),
						'replace' => array(
							'type'        => 'string',
							'description' => 'Replacement string.',
						),
						'dry_run' => array(
							'type'        => 'boolean',
							'description' => 'If true, show what would change without making changes.',
						),
					),
					'required'   => array( 'search', 'replace' ),
				),
			),
			array(
				'name'        => 'maintenance_on',
				'description' => 'Enable WordPress maintenance mode.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'maintenance_off',
				'description' => 'Disable WordPress maintenance mode.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'post_get',
				'description' => 'Get detailed information about a single post (title, status, author, dates, URL, excerpt).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID.',
						),
					),
					'required'   => array( 'post_id' ),
				),
			),
			array(
				'name'        => 'post_create',
				'description' => 'Create a new WordPress post with the given title and optional content. Use status="publish" to publish immediately; defaults to draft.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'title'   => array(
							'type'        => 'string',
							'description' => 'Title for the new post.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Optional body text or HTML content for the post.',
						),
						'status'  => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => 'Post status: "draft" (default) or "publish".',
						),
					),
					'required'   => array( 'title' ),
				),
			),
			array(
				'name'        => 'page_create',
				'description' => 'Create a new WordPress page with the given title and optional content. Use status="publish" to publish immediately; defaults to draft.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'title'   => array(
							'type'        => 'string',
							'description' => 'Title for the new page.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Optional body text or HTML content for the page.',
						),
						'status'  => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => 'Page status: "draft" (default) or "publish".',
						),
					),
					'required'   => array( 'title' ),
				),
			),
			array(
				'name'        => 'user_get',
				'description' => 'Get detailed information about a WordPress user (username, email, role, post count, registration date).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'identifier' => array(
							'type'        => 'string',
							'description' => 'Username (login) or numeric user ID.',
						),
					),
					'required'   => array( 'identifier' ),
				),
			),
			array(
				'name'        => 'theme_info',
				'description' => 'Show detailed information about the currently active theme (name, version, author, description, update availability).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'theme_status',
				'description' => 'Show status details for a specific installed theme.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),
			array(
				'name'        => 'site_settings',
				'description' => 'Read key WordPress site settings (admin email, language, timezone, permalink structure, registration, etc.).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'task_list',
				'description' => 'List pending tasks for a user. Omit user_id to list tasks for yourself.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID to list tasks for. Omit to use the current user.',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'task_create',
				'description' => 'Create a new pending task. Optionally assign it to another user by their WordPress username (assignee_login) or user ID (assignee_id). Assigning to another user requires administrator privileges.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name'           => array(
							'type'        => 'string',
							'description' => 'Task name/title.',
						),
						'assignee_login' => array(
							'type'        => 'string',
							'description' => 'WordPress username (user_login) to assign the task to.',
						),
						'assignee_id'    => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID to assign the task to (use assignee_login when possible).',
						),
					),
					'required'   => array( 'name' ),
				),
			),
			array(
				'name'        => 'task_delete',
				'description' => 'Delete all tasks for a user. Omit user_id to delete tasks for yourself.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID whose tasks to delete. Omit to use the current user.',
						),
					),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Core
			// ---------------------------------------------------------------
			array(
				'name'        => 'core_version',
				'description' => 'Show WordPress version, PHP version, and whether a core update is available.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Comments
			// ---------------------------------------------------------------
			array(
				'name'        => 'comment_list',
				'description' => 'List comments filtered by status: pending (default), approved, or spam.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'pending', 'approved', 'spam' ),
							'description' => 'Comment status filter. Defaults to "pending".',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'comment_approve',
				'description' => 'Approve a pending comment by its ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Comment ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'comment_spam',
				'description' => 'Mark a comment as spam by its ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Comment ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),
			array(
				'name'        => 'comment_delete',
				'description' => 'Permanently delete a comment by its ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Comment ID.',
						),
					),
					'required'   => array( 'id' ),
				),
			),

			// ---------------------------------------------------------------
			// Posts (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'post_list',
				'description' => 'List recent posts. Optionally filter by post type (default: "post").',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'        => 'string',
							'description' => 'Post type slug, e.g. "post", "page". Defaults to "post".',
						),
					),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'post_status',
				'description' => 'Change the status of an existing post.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID.',
						),
						'status'  => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish', 'pending', 'private', 'trash' ),
							'description' => 'New post status.',
						),
					),
					'required'   => array( 'post_id', 'status' ),
				),
			),
			array(
				'name'        => 'post_delete',
				'description' => 'Permanently delete a post by ID.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress post ID.',
						),
					),
					'required'   => array( 'post_id' ),
				),
			),

			// ---------------------------------------------------------------
			// Users (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'user_role',
				'description' => 'Change the role of an existing WordPress user.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'identifier' => array(
							'type'        => 'string',
							'description' => 'Username (login) or numeric user ID.',
						),
						'role'       => array(
							'type'        => 'string',
							'description' => 'WordPress role slug, e.g. "editor", "subscriber", "administrator".',
						),
					),
					'required'   => array( 'identifier', 'role' ),
				),
			),

			// ---------------------------------------------------------------
			// Options (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'option_delete',
				'description' => 'Delete a WordPress option from the database. Protected core options cannot be deleted.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Option name.',
						),
					),
					'required'   => array( 'name' ),
				),
			),

			// ---------------------------------------------------------------
			// Themes (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'theme_delete',
				'description' => 'Delete (uninstall) an inactive theme by slug. The theme must not be the currently active theme.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Theme slug.',
						),
					),
					'required'   => array( 'slug' ),
				),
			),

			// ---------------------------------------------------------------
			// Transients
			// ---------------------------------------------------------------
			array(
				'name'        => 'transient_list',
				'description' => 'List the first 20 WordPress transients stored in the database.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),
			array(
				'name'        => 'transient_delete',
				'description' => 'Delete a specific WordPress transient by name.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Transient key (without the _transient_ prefix).',
						),
					),
					'required'   => array( 'name' ),
				),
			),

			// ---------------------------------------------------------------
			// Rewrite
			// ---------------------------------------------------------------
			array(
				'name'        => 'rewrite_flush',
				'description' => 'Flush WordPress rewrite rules (equivalent to saving permalink settings).',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Maintenance (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'maintenance_status',
				'description' => 'Check whether WordPress maintenance mode is currently enabled or disabled.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Cron (additional)
			// ---------------------------------------------------------------
			array(
				'name'        => 'cron_run',
				'description' => 'Manually trigger a scheduled WordPress cron hook immediately.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'hook' => array(
							'type'        => 'string',
							'description' => 'Cron hook name to run immediately.',
						),
					),
					'required'   => array( 'hook' ),
				),
			),

			// ---------------------------------------------------------------
			// Media
			// ---------------------------------------------------------------
			array(
				'name'        => 'media_list',
				'description' => 'List recent media library attachments with ID, filename, MIME type, and upload date.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'count' => array(
							'type'        => 'integer',
							'description' => 'Number of attachments to return (1–100, default 20).',
						),
					),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Block Patterns
			// ---------------------------------------------------------------
			array(
				'name'        => 'block_patterns_list',
				'description' => 'List all registered WordPress block patterns (name, title, categories). Optionally filter by category slug.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Category slug to filter by. Omit to list all patterns.',
						),
					),
					'required'   => array(),
				),
			),

			// ---------------------------------------------------------------
			// Post content (block page builder)
			// ---------------------------------------------------------------
			array(
				'name'        => 'post_set_content',
				'description' => 'Write raw block markup (WordPress block HTML comment syntax) to an existing post or page. Use this after creating a page to insert Greenshift or any block-based content.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the post or page to update.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Full raw block markup string to set as post_content.',
						),
					),
					'required'   => array( 'post_id', 'content' ),
				),
			),

		);
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
		$user           = wp_get_current_user();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$prompt = sprintf(
			"You are an AI assistant for the WordPress admin dashboard (CDW plugin).\n" .
			"You help administrators manage their WordPress site through natural language.\n\n" .
			"=== SITE CONTEXT ===\n" .
			"Site URL: %s\n" .
			"WordPress version: %s\n" .
			"PHP version: %s\n" .
			"Active plugins: %d\n" .
			"Current user: %s (Administrator)\n\n" .
			"=== CAPABILITIES ===\n" .
			"You have access to tools that let you manage plugins, themes, users, options, database, cron, and site settings.\n" .
			"You can also create posts and pages (as drafts), manage personal task lists (create, list, delete), search-replace content in the database, and query site/post/user details.\n\n" .
			"=== RULES ===\n" .
			"1. Always prefer read-only tools (list, status, info) before making changes.\n" .
			"2. For destructive operations (delete, update, search-replace), explain what you plan to do and ask for confirmation UNLESS you are already in auto-execute mode.\n" .
			"3. For search-replace, always run with dry_run=true first, then ask the user to confirm before the real run.\n" .
			"4. Never expose or request API keys, passwords, or secrets.\n" .
			"5. If a tool returns an error, explain it clearly and suggest a fix.\n" .
			"6. Keep responses concise and use markdown formatting where helpful.\n" .
			"7. You can only use the provided tools — you cannot run arbitrary PHP or shell commands.\n" .
			"8. After every tool call, ALWAYS write a short natural-language reply summarising the result — never return an empty response.\n",
			site_url(),
			get_bloginfo( 'version' ),
			PHP_VERSION,
			count( $active_plugins ),
			esc_html( $user->user_login )
		);

		if ( ! empty( $custom_prompt ) ) {
			$prompt .= "\n=== CUSTOM INSTRUCTIONS ===\n" . $custom_prompt . "\n";
		}

		return $prompt;
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
	public static function execute_tool_call( $function_name, $arguments, $user_id ) {
		// post_set_content is handled directly: block markup contains quotes,
		// newlines, and angle brackets that cannot survive the CLI tokeniser.
		if ( 'post_set_content' === $function_name ) {
			$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
			$content = isset( $arguments['content'] ) ? (string) $arguments['content'] : '';
			if ( $post_id <= 0 ) {
				return 'Error: post_id is required and must be a positive integer.';
			}
			if ( ! get_post( $post_id ) ) {
				return "Error: Post $post_id not found.";
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return 'Error: You do not have permission to edit this post.';
			}
			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return 'Error: ' . $result->get_error_message();
			}
			return "Post $post_id content updated successfully.";
		}

		// post_create and page_create are handled directly here because the optional
		// 'content' field can contain arbitrary multi-word text that cannot be safely
		// passed through the whitespace-tokenised CLI command string.
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

	/**
	 * Converts a tool name + arguments to a CDW CLI command string.
	 *
	 * @param string              $tool_name  Tool name.
	 * @param array<string,mixed> $arguments  Tool arguments.
	 * @return string|null CLI command string, or null if the tool is unknown.
	 */
	private static function tool_name_to_cli_command( $tool_name, $arguments ) {
		$slug    = isset( $arguments['slug'] ) ? trim( (string) $arguments['slug'] ) : '';
		$user_id = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;

		switch ( $tool_name ) {
			case 'plugin_list':
				return 'plugin list';
			case 'plugin_status':
				return 'plugin status ' . $slug;
			case 'plugin_activate':
				return 'plugin activate ' . $slug;
			case 'plugin_deactivate':
				return 'plugin deactivate ' . $slug;
			case 'plugin_install':
				return 'plugin install ' . $slug . ' --force';
			case 'plugin_update':
				return 'plugin update ' . $slug . ' --force';
			case 'plugin_update_all':
				return 'plugin update --all';
			case 'plugin_delete':
				return 'plugin delete ' . $slug . ' --force';
			case 'theme_list':
				return 'theme list';
			case 'theme_activate':
				return 'theme activate ' . $slug;
			case 'theme_install':
				return 'theme install ' . $slug . ' --force';
			case 'theme_update':
				return 'theme update ' . $slug . ' --force';
			case 'theme_update_all':
				return 'theme update --all';
			case 'user_list':
				return 'user list';
			case 'user_create':
				$username = isset( $arguments['username'] ) ? trim( (string) $arguments['username'] ) : '';
				$email    = isset( $arguments['email'] ) ? trim( (string) $arguments['email'] ) : '';
				$role     = isset( $arguments['role'] ) ? trim( (string) $arguments['role'] ) : 'subscriber';
				return 'user create ' . $username . ' ' . $email . ' ' . $role;
			case 'user_delete':
				return 'user delete ' . $user_id . ' --force';
			case 'cache_flush':
				return 'cache flush';
			case 'option_get':
				$name = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				return 'option get ' . $name;
			case 'option_list':
				return 'option list';
			case 'option_set':
				$name  = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				$value = isset( $arguments['value'] ) ? trim( (string) $arguments['value'] ) : '';
				return 'option set ' . $name . ' ' . $value;
			case 'cron_list':
				return 'cron list';
			case 'site_info':
				return 'site info';
			case 'site_status':
				return 'site status';
			case 'db_size':
				return 'db size';
			case 'db_tables':
				return 'db tables';
			case 'search_replace':
				$search  = isset( $arguments['search'] ) ? (string) $arguments['search'] : '';
				$replace = isset( $arguments['replace'] ) ? (string) $arguments['replace'] : '';
				$dry_run = isset( $arguments['dry_run'] ) && $arguments['dry_run'];
				$cmd     = 'search-replace ' . escapeshellarg( $search ) . ' ' . escapeshellarg( $replace );
				if ( $dry_run ) {
					$cmd .= ' --dry-run';
				} else {
					$cmd .= ' --force';
				}
				return $cmd;
			case 'maintenance_on':
				return 'maintenance on';
			case 'maintenance_off':
				return 'maintenance off';
			case 'post_get':
				$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
				return 'post get ' . $post_id;
			case 'post_create':
				$title = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
				return 'post create ' . $title;
			case 'page_create':
				$title = isset( $arguments['title'] ) ? sanitize_text_field( (string) $arguments['title'] ) : '';
				return 'page create ' . $title;
			case 'user_get':
				$identifier = isset( $arguments['identifier'] ) ? trim( (string) $arguments['identifier'] ) : '';
				return 'user get ' . $identifier;
			case 'theme_info':
				return 'theme info';
			case 'theme_status':
				return 'theme status ' . $slug;
			case 'site_settings':
				return 'site settings';
			case 'task_list':
				$target_uid = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
				$cmd        = 'task list';
				if ( $target_uid > 0 ) {
					$cmd .= ' --user_id=' . $target_uid;
				}
				return $cmd;
			case 'task_create':
				$name           = isset( $arguments['name'] ) ? sanitize_text_field( (string) $arguments['name'] ) : '';
				$assignee_login = isset( $arguments['assignee_login'] ) ? trim( (string) $arguments['assignee_login'] ) : '';
				$assignee_id    = isset( $arguments['assignee_id'] ) ? (int) $arguments['assignee_id'] : 0;
				$cmd            = 'task create ' . $name;
				if ( ! empty( $assignee_login ) ) {
					$cmd .= ' --assignee_login=' . $assignee_login;
				} elseif ( $assignee_id > 0 ) {
					$cmd .= ' --assignee_id=' . $assignee_id;
				}
				return $cmd;
			case 'task_delete':
				$target_uid = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
				$cmd        = 'task delete';
				if ( $target_uid > 0 ) {
					$cmd .= ' --user_id=' . $target_uid;
				}
				return $cmd;
			case 'core_version':
				return 'core version';
			case 'comment_list':
				$status = isset( $arguments['status'] ) ? trim( (string) $arguments['status'] ) : 'pending';
				return 'comment list ' . $status;
			case 'comment_approve':
				$id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
				return 'comment approve ' . $id;
			case 'comment_spam':
				$id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
				return 'comment spam ' . $id;
			case 'comment_delete':
				$id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
				return 'comment delete ' . $id . ' --force';
			case 'post_list':
				$type = isset( $arguments['type'] ) ? trim( (string) $arguments['type'] ) : 'post';
				return 'post list ' . $type;
			case 'post_status':
				$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
				$status  = isset( $arguments['status'] ) ? trim( (string) $arguments['status'] ) : '';
				return 'post status ' . $post_id . ' ' . $status;
			case 'post_delete':
				$post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
				return 'post delete ' . $post_id . ' --force';
			case 'user_role':
				$identifier = isset( $arguments['identifier'] ) ? trim( (string) $arguments['identifier'] ) : '';
				$role       = isset( $arguments['role'] ) ? trim( (string) $arguments['role'] ) : '';
				return 'user role ' . $identifier . ' ' . $role;
			case 'option_delete':
				$name = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				return 'option delete ' . $name;
			case 'theme_delete':
				return 'theme delete ' . $slug . ' --force';
			case 'transient_list':
				return 'transient list';
			case 'transient_delete':
				$name = isset( $arguments['name'] ) ? trim( (string) $arguments['name'] ) : '';
				return 'transient delete ' . $name;
			case 'rewrite_flush':
				return 'rewrite flush';
			case 'maintenance_status':
				return 'maintenance status';
			case 'cron_run':
				$hook = isset( $arguments['hook'] ) ? trim( (string) $arguments['hook'] ) : '';
				return 'cron run ' . $hook;
			case 'media_list':
				$count = isset( $arguments['count'] ) ? (int) $arguments['count'] : 20;
				return 'media list ' . $count;
			case 'block_patterns_list':
				$cat = isset( $arguments['category'] ) ? trim( (string) $arguments['category'] ) : '';
				return $cat ? 'block-patterns list ' . $cat : 'block-patterns list';
			default:
				return null;
		}
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
		$formatted_tools = array();
		foreach ( $tools as $tool ) {
			$params = $tool['parameters'];
			// PHP encodes empty array() as [] but JSON Schema requires {} for "properties".
			if ( isset( $params['properties'] ) && is_array( $params['properties'] ) && empty( $params['properties'] ) ) {
				$params['properties'] = new \stdClass();
			}
			$formatted_tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $tool['name'],
					'description' => $tool['description'],
					'parameters'  => $params,
				),
			);
		}

		$payload = array(
			'model'    => $model,
			'messages' => $messages,
		);

		// Only include tools if there are any (some custom endpoints reject empty arrays).
		if ( ! empty( $formatted_tools ) ) {
			$payload['tools'] = $formatted_tools;
		}

		$default_endpoint = 'https://api.openai.com/v1/chat/completions';
		$endpoint         = ! empty( $base_url )
			? rtrim( $base_url, '/' ) . '/chat/completions'
			: $default_endpoint;

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 60,
			)
		);

		return self::parse_http_response( $response, 'openai' );
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
		$formatted_tools = array();
		foreach ( $tools as $tool ) {
			$params = $tool['parameters'];
			// PHP encodes empty array() as [] but JSON Schema requires {} for "properties".
			if ( isset( $params['properties'] ) && is_array( $params['properties'] ) && empty( $params['properties'] ) ) {
				$params['properties'] = new \stdClass();
			}
			$formatted_tools[] = array(
				'name'         => $tool['name'],
				'description'  => $tool['description'],
				'input_schema' => $params,
			);
		}

		$payload = array(
			'model'      => $model,
			'max_tokens' => 4096,
			'messages'   => $messages,
			'tools'      => $formatted_tools,
		);

		if ( ! empty( $system_prompt ) ) {
			$payload['system'] = $system_prompt;
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 60,
			)
		);

		return self::parse_http_response( $response, 'anthropic' );
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
		// Gemini uses 'contents' with 'model'/'user' roles.
		$contents = array();
		foreach ( $messages as $msg ) {
			$role = 'user' === $msg['role'] ? 'user' : 'model';
			if ( 'system' === $msg['role'] ) {
				continue; // system handled separately via systemInstruction.
			}
			$content_entry = array(
				'role'  => $role,
				'parts' => array(),
			);
			if ( is_string( $msg['content'] ) ) {
				$content_entry['parts'][] = array( 'text' => $msg['content'] );
			} elseif ( is_array( $msg['content'] ) ) {
				foreach ( $msg['content'] as $part ) {
					if ( isset( $part['type'] ) && 'tool_result' === $part['type'] ) {
						$content_entry['parts'][] = array(
							'functionResponse' => array(
								'name'     => $part['tool_use_id'],
								'response' => array( 'output' => $part['content'] ),
							),
						);
					} else {
						$content_entry['parts'][] = array( 'text' => is_string( $part ) ? $part : wp_json_encode( $part ) );
					}
				}
			}
			$contents[] = $content_entry;
		}

		$function_declarations = array();
		foreach ( $tools as $tool ) {
			$params = $tool['parameters'];
			// PHP encodes empty array() as [] but JSON Schema requires {} for "properties".
			if ( isset( $params['properties'] ) && is_array( $params['properties'] ) && empty( $params['properties'] ) ) {
				$params['properties'] = new \stdClass();
			}
			$function_declarations[] = array(
				'name'        => $tool['name'],
				'description' => $tool['description'],
				'parameters'  => $params,
			);
		}

		$payload = array(
			'contents' => $contents,
			'tools'    => array(
				array( 'functionDeclarations' => $function_declarations ),
			),
		);

		// Extract system prompt from messages.
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$payload['systemInstruction'] = array(
					'parts' => array( array( 'text' => $msg['content'] ) ),
				);
				break;
			}
		}

		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 60,
			)
		);

		return self::parse_http_response( $response, 'google' );
	}

	/**
	 * Parses a wp_remote_post() response into a normalised array.
	 *
	 * The normalised format is:
	 * {
	 *   content: string,          // text reply (may be empty when tool_calls present)
	 *   tool_calls: [{name, arguments}],
	 *   usage: {prompt_tokens, completion_tokens, total_tokens},
	 *   raw: array                // full decoded body
	 * }
	 *
	 * @param array<string,mixed>|WP_Error $response  wp_remote_post() result.
	 * @param string                       $provider  Provider slug for error context.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function parse_http_response( $response, $provider ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ai_http_error',
				sprintf( 'HTTP request to %s failed: %s', $provider, $response->get_error_message() ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== (int) $code ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] :
				( isset( $data['message'] ) ? $data['message'] : 'Unknown API error' );
			return new WP_Error(
				'ai_api_error',
				sprintf( '%s API error (HTTP %d): %s', ucfirst( $provider ), $code, $message ),
				array( 'status' => 502 )
			);
		}

		$normalised = array(
			'content'    => '',
			'tool_calls' => array(),
			'usage'      => array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
			),
			'raw'        => $data,
		);

		if ( 'openai' === $provider ) {
			$choice = isset( $data['choices'][0]['message'] ) ? $data['choices'][0]['message'] : array();
			if ( isset( $choice['content'] ) && ! empty( $choice['content'] ) ) {
				$normalised['content'] = $choice['content'];
			}
			if ( isset( $choice['tool_calls'] ) && is_array( $choice['tool_calls'] ) ) {
				foreach ( $choice['tool_calls'] as $tc ) {
					$args = array();
					if ( isset( $tc['function']['arguments'] ) ) {
						$decoded = json_decode( $tc['function']['arguments'], true );
						$args    = is_array( $decoded ) ? $decoded : array();
					}
					$normalised['tool_calls'][] = array(
						'id'        => isset( $tc['id'] ) ? $tc['id'] : '',
						'name'      => isset( $tc['function']['name'] ) ? $tc['function']['name'] : '',
						'arguments' => $args,
					);
				}
			}
			if ( isset( $data['usage'] ) ) {
				$normalised['usage'] = array(
					'prompt_tokens'     => (int) ( $data['usage']['prompt_tokens'] ?? 0 ),
					'completion_tokens' => (int) ( $data['usage']['completion_tokens'] ?? 0 ),
					'total_tokens'      => (int) ( $data['usage']['total_tokens'] ?? 0 ),
				);
			}
		} elseif ( 'anthropic' === $provider ) {
			if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
				foreach ( $data['content'] as $block ) {
					if ( isset( $block['type'] ) ) {
						if ( 'text' === $block['type'] ) {
							$normalised['content'] .= $block['text'];
						} elseif ( 'tool_use' === $block['type'] ) {
							$normalised['tool_calls'][] = array(
								'id'        => $block['id'],
								'name'      => $block['name'],
								'arguments' => is_array( $block['input'] ) ? $block['input'] : array(),
							);
						}
					}
				}
			}
			if ( isset( $data['usage'] ) ) {
				$normalised['usage'] = array(
					'prompt_tokens'     => (int) ( $data['usage']['input_tokens'] ?? 0 ),
					'completion_tokens' => (int) ( $data['usage']['output_tokens'] ?? 0 ),
					'total_tokens'      => (int) ( ( $data['usage']['input_tokens'] ?? 0 ) + ( $data['usage']['output_tokens'] ?? 0 ) ),
				);
			}
		} elseif ( 'google' === $provider ) {
			$candidate = isset( $data['candidates'][0]['content'] ) ? $data['candidates'][0]['content'] : array();
			if ( isset( $candidate['parts'] ) && is_array( $candidate['parts'] ) ) {
				foreach ( $candidate['parts'] as $part ) {
					if ( isset( $part['text'] ) ) {
						$normalised['content'] .= $part['text'];
					} elseif ( isset( $part['functionCall'] ) ) {
						$normalised['tool_calls'][] = array(
							'id'        => isset( $part['functionCall']['name'] ) ? $part['functionCall']['name'] : '',
							'name'      => isset( $part['functionCall']['name'] ) ? $part['functionCall']['name'] : '',
							'arguments' => isset( $part['functionCall']['args'] ) && is_array( $part['functionCall']['args'] )
								? $part['functionCall']['args'] : array(),
						);
					}
				}
			}
			if ( isset( $data['usageMetadata'] ) ) {
				$normalised['usage'] = array(
					'prompt_tokens'     => (int) ( $data['usageMetadata']['promptTokenCount'] ?? 0 ),
					'completion_tokens' => (int) ( $data['usageMetadata']['candidatesTokenCount'] ?? 0 ),
					'total_tokens'      => (int) ( $data['usageMetadata']['totalTokenCount'] ?? 0 ),
				);
			}
		}

		return $normalised;
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
				$final_content = implode( "\n\n", $outputs );
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

	/**
	 * Formats the assistant's tool-call turn in a provider-compatible way.
	 *
	 * @param string              $provider     Provider slug.
	 * @param array<string,mixed> $api_response Normalised API response.
	 * @param array<string,mixed> $tc           Single tool call {id, name, arguments}.
	 * @return array<string,mixed> Message array to append to $messages.
	 */
	private static function format_assistant_tool_call_message( $provider, $api_response, $tc ) {
		if ( 'openai' === $provider || 'custom' === $provider ) {
			return array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => array(
					array(
						'id'       => $tc['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => $tc['name'],
							'arguments' => wp_json_encode( $tc['arguments'] ),
						),
					),
				),
			);
		}

		if ( 'anthropic' === $provider ) {
			return array(
				'role'    => 'assistant',
				'content' => $api_response['raw']['content'],
			);
		}

		// Google: model turn with functionCall part.
		return array(
			'role'    => 'model',
			'content' => array(
				'parts' => array(
					array(
						'functionCall' => array(
							'name' => $tc['name'],
							'args' => $tc['arguments'],
						),
					),
				),
			),
		);
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
		if ( 'openai' === $provider || 'custom' === $provider ) {
			return array(
				'role'         => 'tool',
				'tool_call_id' => $tc['id'],
				'content'      => $tool_output,
			);
		}

		if ( 'anthropic' === $provider ) {
			return array(
				'role'    => 'user',
				'content' => array(
					array(
						'type'        => 'tool_result',
						'tool_use_id' => $tc['id'],
						'content'     => $tool_output,
					),
				),
			);
		}

		// Google.
		return array(
			'role'  => 'user',
			'parts' => array(
				array(
					'functionResponse' => array(
						'name'     => $tc['name'],
						'response' => array( 'output' => $tool_output ),
					),
				),
			),
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

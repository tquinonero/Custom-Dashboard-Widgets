<?php
/**
 * AI User Settings - handles per-user AI settings and API key storage.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-encryption.php';
require_once CDW_PLUGIN_DIR . 'includes/services/ai/class-cdw-ai-usage-tracker.php';

/**
 * Manages per-user AI settings and API key storage.
 */
class CDW_AI_User_Settings {

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
	 * User meta key for the custom provider base URL.
	 *
	 * @var string
	 */
	const BASE_URL_META_KEY = 'cdw_ai_base_url';

	/**
	 * Returns the AI settings for the given user.
	 *
	 * The raw API key is never included; only has_key (bool) is returned.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string,mixed> {provider, model, execution_mode, has_key, usage}
	 */
	public static function get_settings( $user_id ) {
		$provider       = get_user_meta( $user_id, self::PROVIDER_META_KEY, true );
		$model          = get_user_meta( $user_id, self::MODEL_META_KEY, true );
		$execution_mode = get_user_meta( $user_id, self::EXECUTION_MODE_META_KEY, true );
		$base_url       = get_user_meta( $user_id, self::BASE_URL_META_KEY, true );

		$provider       = ! empty( $provider ) ? $provider : 'openai';
		$model          = ! empty( $model ) ? $model : 'gpt-4o-mini';
		$execution_mode = ! empty( $execution_mode ) ? $execution_mode : 'confirm';
		$base_url       = ! empty( $base_url ) ? $base_url : '';

		// Check whether a key exists for the active provider (without exposing it).
		$encrypted_key = get_user_meta( $user_id, self::API_KEY_META_PREFIX . $provider, true );
		$has_key       = ! empty( $encrypted_key );

		return array(
			'provider'        => $provider,
			'model'           => $model,
			'execution_mode'  => $execution_mode,
			'has_key'         => $has_key,
			'base_url'        => $base_url,
			'usage'           => CDW_AI_Usage_Tracker::get_usage( $user_id ),
		);
	}

	/**
	 * Saves the AI settings (and optionally the API key) for a user.
	 *
	 * @param int                 $user_id  WordPress user ID.
	 * @param array<string,mixed> $settings Keys: provider, model, execution_mode, api_key (all optional).
	 * @param array<string,array<string,mixed>> $providers Available providers.
	 * @return true|WP_Error
	 */
	public static function save_settings( $user_id, $settings, $providers = array() ) {
		if ( empty( $providers ) ) {
			$providers = include CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-ai-providers-config.php';
			$providers = array_keys( $providers );
		} else {
			$providers = array_keys( $providers );
		}

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
			$encrypted = CDW_AI_Encryption::encrypt( $raw_key );
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
		return CDW_AI_Encryption::decrypt( $encrypted );
	}
}

<?php
/**
 * AI Encryption - handles API key encryption/decryption.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles encryption and decryption of API keys.
 */
class CDW_AI_Encryption {

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
	public static function encrypt( $plaintext ) {
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
	 * @param string $ciphertext Base64-encoded ciphertext from encrypt().
	 * @return string Plaintext API key, or empty string on failure.
	 */
	public static function decrypt( $ciphertext ) {
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
}

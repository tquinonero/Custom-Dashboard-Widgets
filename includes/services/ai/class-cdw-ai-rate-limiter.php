<?php
/**
 * AI Rate Limiter - handles per-user AI request rate limiting.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages per-user AI rate limiting.
 */
class CDW_AI_Rate_Limiter {

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
	 * Checks the per-user AI rate limit.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return true|WP_Error True if within limit, WP_Error if exceeded.
	 */
	public static function check( $user_id ) {
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
}

<?php
/**
 * AI Usage Tracker - handles tracking AI token usage per user.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks AI token usage per user.
 */
class CDW_AI_Usage_Tracker {

	/**
	 * User meta key for token usage JSON blob.
	 *
	 * @var string
	 */
	const USAGE_META_KEY = 'cdw_ai_token_usage';

	/**
	 * Default token usage structure.
	 *
	 * @var array<string,int>
	 */
	const DEFAULT_USAGE = array(
		'prompt_tokens'     => 0,
		'completion_tokens' => 0,
		'total_tokens'      => 0,
		'request_count'     => 0,
	);

	/**
	 * Accumulates token usage for a user after a successful AI call.
	 *
	 * @param int                 $user_id       WordPress user ID.
	 * @param array<string,mixed> $usage_delta   {prompt_tokens, completion_tokens, total_tokens}.
	 * @return void
	 */
	public static function record_usage( $user_id, $usage_delta ) {
		$existing = get_user_meta( $user_id, self::USAGE_META_KEY, true );
		if ( ! is_array( $existing ) ) {
			$existing = self::DEFAULT_USAGE;
		}

		$existing['prompt_tokens']     += isset( $usage_delta['prompt_tokens'] ) ? (int) $usage_delta['prompt_tokens'] : 0;
		$existing['completion_tokens'] += isset( $usage_delta['completion_tokens'] ) ? (int) $usage_delta['completion_tokens'] : 0;
		$existing['total_tokens']      += isset( $usage_delta['total_tokens'] ) ? (int) $usage_delta['total_tokens'] : 0;
		$existing['request_count']     += 1;

		update_user_meta( $user_id, self::USAGE_META_KEY, $existing );
	}

	/**
	 * Resets the token usage statistics for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return void
	 */
	public static function reset_usage( $user_id ) {
		delete_user_meta( $user_id, self::USAGE_META_KEY );
	}

	/**
	 * Gets the current usage statistics for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string,mixed> Usage statistics.
	 */
	public static function get_usage( $user_id ) {
		$usage = get_user_meta( $user_id, self::USAGE_META_KEY, true );
		return is_array( $usage ) ? $usage : self::DEFAULT_USAGE;
	}
}

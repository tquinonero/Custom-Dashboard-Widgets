<?php
/**
 * AI request helper utilities for controllers.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-ai-service.php';

/**
 * Shared helpers for resolving common AI request context.
 *
 * @package CDW
 */
class CDW_AI_Request_Helper {

	/**
	 * Resolves provider/model/base_url for the current user.
	 *
	 * Uses request values when present; otherwise falls back to saved user settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array{user_id:int,provider:string,model:string,base_url:string}
	 */
	public static function resolve_provider_context( WP_REST_Request $request ) {
		$params   = $request->get_json_params();
		$user_id  = get_current_user_id();
		$settings = CDW_AI_Service::get_user_ai_settings( $user_id );

		$provider = isset( $params['provider'] ) ? sanitize_text_field( $params['provider'] ) : $settings['provider'];
		$model    = isset( $params['model'] ) ? sanitize_text_field( $params['model'] ) : $settings['model'];
		$base_url = isset( $params['base_url'] ) ? esc_url_raw( $params['base_url'] ) : $settings['base_url'];

		return array(
			'user_id'  => $user_id,
			'provider' => $provider,
			'model'    => $model,
			'base_url' => $base_url,
		);
	}
}

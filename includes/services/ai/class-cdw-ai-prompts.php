<?php
/**
 * AI Prompts for CDW - system prompts.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Prompts handler.
 */
class CDW_AI_Prompts {

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
}

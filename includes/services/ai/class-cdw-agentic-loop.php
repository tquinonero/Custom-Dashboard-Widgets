<?php
/**
 * Agentic Loop - Handles AI conversation with tool execution.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agentic loop handler for AI conversations.
 *
 * Runs a single-turn agentic loop: builds context, calls the provider,
 * executes any tool call, feeds the result back, and returns the final answer.
 *
 * @package CDW
 */
class CDW_Agentic_Loop {

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
		$tools         = CDW_AI_Service::get_tool_definitions();
		$system_prompt = CDW_AI_Prompts::build_system_prompt( $custom_prompt );

		// Build messages array
		$messages = self::build_messages_array( $system_prompt, $history, $user_message, $provider );

		// First provider call
		$api_response = CDW_AI_Providers::call_provider( $messages, $tools, $api_key, $model, $system_prompt, $base_url, $provider );

		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}

		$total_usage = isset( $api_response['usage'] ) ? $api_response['usage'] : self::DEFAULT_USAGE;

		// Handle tool calls
		$tool_calls_made = self::handle_tool_calls( $api_response, $provider, $user_id, $messages );

		// If tools were called, make second provider call
		if ( ! empty( $tool_calls_made ) ) {
			$final_response = CDW_AI_Providers::call_provider( $messages, $tools, $api_key, $model, $system_prompt, $base_url, $provider );

			if ( is_wp_error( $final_response ) ) {
				return array(
					'content'         => $api_response['content'],
					'tool_calls_made' => $tool_calls_made,
					'usage'           => $total_usage,
				);
			}

			$processed = self::process_response( $final_response, $tool_calls_made, $total_usage );
			$final_content = $processed['content'];
			$total_usage   = $processed['usage'];
		} else {
			$final_content = $api_response['content'];
		}

		CDW_AI_Usage_Tracker::record_usage( $user_id, $total_usage );

		return array(
			'content'         => $final_content,
			'tool_calls_made' => $tool_calls_made,
			'usage'           => $total_usage,
		);
	}

	/**
	 * Builds the messages array for the AI provider.
	 *
	 * @param string                         $system_prompt  System prompt.
	 * @param array<int,array<string,mixed>> $history        Previous conversation turns.
	 * @param string                         $user_message   Current user message.
	 * @param string                         $provider       Provider slug (affects format).
	 * @return array<int,array<string,mixed>> Messages array.
	 */
	public static function build_messages_array( $system_prompt, $history, $user_message, $provider ) {
		$messages = array();

		if ( 'anthropic' !== $provider ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		$capped_history = array_slice( $history, -20 );
		foreach ( $capped_history as $turn ) {
			if ( isset( $turn['role'], $turn['content'] ) ) {
				$messages[] = array(
					'role'    => sanitize_text_field( $turn['role'] ),
					'content' => sanitize_textarea_field( $turn['content'] ),
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => sanitize_textarea_field( $user_message ),
		);

		return $messages;
	}

	/**
	 * Executes tool calls from the AI response and updates messages.
	 *
	 * @param array<string,mixed>            $api_response  The AI response with tool_calls.
	 * @param string                         $provider      Provider slug.
	 * @param int                           $user_id       WordPress user ID.
	 * @param array<int,array<string,mixed>> &$messages    Messages array to append to.
	 * @return array<int,array<string,mixed>> Array of tool calls made.
	 */
	public static function handle_tool_calls( $api_response, $provider, $user_id, &$messages ) {
		$tool_calls_made = array();

		if ( empty( $api_response['tool_calls'] ) ) {
			return $tool_calls_made;
		}

		foreach ( $api_response['tool_calls'] as $tool_call ) {
			$tool_output = CDW_AI_Tools::execute_tool_call( $tool_call['name'], $tool_call['arguments'], $user_id );

			$tool_calls_made[] = array(
				'name'      => $tool_call['name'],
				'arguments' => $tool_call['arguments'],
				'output'    => $tool_output,
			);

			$messages[] = CDW_AI_Providers::format_assistant_tool_call_message( $provider, $api_response, $tool_call );
			$messages[] = CDW_AI_Providers::format_tool_result_message( $provider, $tool_call, $tool_output );
		}

		return $tool_calls_made;
	}

	/**
	 * Processes the final AI response and handles edge cases.
	 *
	 * @param array<string,mixed>            $final_response  The final AI response.
	 * @param array<int,array<string,mixed>> $tool_calls_made Array of tool calls executed.
	 * @param array<string,int>              $total_usage     Current usage totals.
	 * @return array{content: string, usage: array<string,int>} Processed result.
	 */
	public static function process_response( $final_response, $tool_calls_made, $total_usage ) {
		$final_content = $final_response['content'];

		if ( isset( $final_response['usage'] ) ) {
			$total_usage['prompt_tokens']     += (int) ( $final_response['usage']['prompt_tokens'] ?? 0 );
			$total_usage['completion_tokens'] += (int) ( $final_response['usage']['completion_tokens'] ?? 0 );
			$total_usage['total_tokens']      += (int) ( $final_response['usage']['total_tokens'] ?? 0 );
		}

		if ( '' === trim( $final_content ) && ! empty( $tool_calls_made ) ) {
			$outputs       = array_map(
				function ( $tool_call_item ) {
					return $tool_call_item['output'];
				},
				$tool_calls_made
			);
			$final_content = implode( "\n\n", array_map( 'strval', $outputs ) );
		}

		return array(
			'content' => $final_content,
			'usage'   => $total_usage,
		);
	}
}

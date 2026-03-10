<?php
/**
 * AI Providers for CDW - OpenAI, Anthropic, Google API calls.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Providers handler - manages API calls to AI providers.
 */
class CDW_AI_Providers {

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
	public static function call_openai( $messages, $tools, $api_key, $model, $base_url = '' ) {
		$formatted_tools = array();
		foreach ( $tools as $tool ) {
			$params = $tool['parameters'];
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
	public static function call_anthropic( $messages, $tools, $api_key, $model, $system_prompt = '' ) {
		$formatted_tools = array();
		foreach ( $tools as $tool ) {
			$params = $tool['parameters'];
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
	public static function call_google( $messages, $tools, $api_key, $model ) {
		$contents = array();
		foreach ( $messages as $msg ) {
			$role = 'user' === $msg['role'] ? 'user' : 'model';
			if ( 'system' === $msg['role'] ) {
				continue;
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
		if ( 'openai' === $provider || 'custom' === $provider ) {
			return self::call_openai( $messages, $tools, $api_key, $model, $base_url );
		}

		if ( 'anthropic' === $provider ) {
			return self::call_anthropic( $messages, $tools, $api_key, $model, $system_prompt );
		}

		return self::call_google( $messages, $tools, $api_key, $model );
	}

	/**
	 * Parses a wp_remote_post() response into a normalised array.
	 *
	 * @param array<string,mixed>|WP_Error $response  wp_remote_post() result.
	 * @param string                       $provider  Provider slug for error context.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function parse_http_response( $response, $provider ) {
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

	/**
	 * Formats the assistant's tool-call turn in a provider-compatible way.
	 *
	 * @param string              $provider     Provider slug.
	 * @param array<string,mixed> $api_response Normalised API response.
	 * @param array<string,mixed> $tool_call    Single tool call {id, name, arguments}.
	 * @return array<string,mixed> Message array to append to $messages.
	 */
	public static function format_assistant_tool_call_message( $provider, $api_response, $tool_call ) {
		if ( 'openai' === $provider || 'custom' === $provider ) {
			return array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => array(
					array(
						'id'       => $tool_call['id'],
						'type'     => 'function',
						'function' => array(
							'name'      => $tool_call['name'],
							'arguments' => wp_json_encode( $tool_call['arguments'] ),
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

		return array(
			'role'    => 'model',
			'content' => array(
				'parts' => array(
					array(
						'functionCall' => array(
							'name' => $tool_call['name'],
							'args' => $tool_call['arguments'],
						),
					),
				),
			),
		);
	}

	/**
	 * Formats a tool result message for feeding back to the provider.
	 *
	 * @param string              $provider     Provider slug.
	 * @param array<string,mixed> $tool_call   Tool call {id, name, arguments}.
	 * @param string              $tool_output  Tool execution output.
	 * @return array<string,mixed> Message array.
	 */
	public static function format_tool_result_message( $provider, $tool_call, $tool_output ) {
		if ( 'openai' === $provider || 'custom' === $provider ) {
			return array(
				'role'         => 'tool',
				'tool_call_id' => $tool_call['id'],
				'content'      => $tool_output,
			);
		}

		if ( 'anthropic' === $provider ) {
			return array(
				'role'    => 'user',
				'content' => array(
					array(
						'type'        => 'tool_result',
						'tool_use_id' => $tool_call['id'],
						'content'     => $tool_output,
					),
				),
			);
		}

		return array(
			'role'  => 'user',
			'parts' => array(
				array(
					'functionResponse' => array(
						'name'     => $tool_call['name'],
						'response' => array( 'output' => $tool_output ),
					),
				),
			),
		);
	}
}

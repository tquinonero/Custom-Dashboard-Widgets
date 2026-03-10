<?php
/**
 * AI Providers configuration.
 *
 * Defines supported AI providers and their available models.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

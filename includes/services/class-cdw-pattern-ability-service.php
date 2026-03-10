<?php
/**
 * Pattern ability execution service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes pattern-related ability logic.
 */
class CDW_Pattern_Ability_Service {

	/**
	 * Recursively discovers custom pattern JSON files.
	 *
	 * @param string $patterns_dir Absolute patterns directory.
	 * @return array<int,string>
	 */
	private static function find_pattern_json_files( $patterns_dir ) {
		$files = array();

		if ( ! is_dir( $patterns_dir ) ) {
			return $files;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$patterns_dir,
				FilesystemIterator::SKIP_DOTS
			)
		);

		foreach ( $iterator as $file_info ) {
			if ( ! $file_info->isFile() ) {
				continue;
			}

			if ( 'json' !== strtolower( (string) $file_info->getExtension() ) ) {
				continue;
			}

			$files[] = $file_info->getPathname();
		}

		sort( $files );

		return $files;
	}

	/**
	 * Executes `cdw/block-patterns-get`.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_block_pattern( $input = array() ) {
		$pattern_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

		if ( empty( $pattern_name ) ) {
			return new WP_Error( 'invalid_pattern_name', 'pattern name is required.' );
		}

		$registry = WP_Block_Patterns_Registry::get_instance();
		$patterns = $registry->get_all_registered();

		$matched = null;
		foreach ( $patterns as $pattern ) {
			if ( $pattern['name'] === $pattern_name ) {
				$matched = $pattern;
				break;
			}
		}

		if ( ! $matched ) {
			return new WP_Error( 'pattern_not_found', "Pattern not found: $pattern_name" );
		}

		$content = isset( $matched['content'] ) ? $matched['content'] : '';
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_pattern', "Pattern \"$pattern_name\" has no content." );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- base64 encoding for safe transfer
		$content_base64 = base64_encode( $content );

		return array(
			'output'         => "Pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes.',
			'name'           => $pattern_name,
			'title'          => isset( $matched['title'] ) ? $matched['title'] : '',
			'content_length' => strlen( $content ),
			'content_base64' => $content_base64,
		);
	}

	/**
	 * Executes `cdw/custom-patterns-list`.
	 *
	 * @return array<string,mixed>
	 */
	public static function list_custom_patterns( $input = array() ) {
		$patterns_dir = CDW_PLUGIN_DIR . 'patterns';

		if ( ! is_dir( $patterns_dir ) ) {
			return array(
				'output'   => 'No custom patterns found.',
				'patterns' => array(),
			);
		}

		$patterns = array();
		$files    = self::find_pattern_json_files( $patterns_dir );

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			if ( false === $content ) {
				continue;
			}

			$data = json_decode( $content, true );

			if ( $data && isset( $data['name'] ) ) {
				$patterns[] = array(
					'name'        => $data['name'],
					'title'       => isset( $data['title'] ) ? $data['title'] : '',
					'description' => isset( $data['description'] ) ? $data['description'] : '',
					'category'    => isset( $data['category'] ) ? $data['category'] : 'general',
				);
			}
		}

		return array(
			'output'   => 'Found ' . count( $patterns ) . ' custom patterns.',
			'patterns' => $patterns,
		);
	}

	/**
	 * Executes `cdw/custom-patterns-get`.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function get_custom_pattern( $input = array() ) {
		$pattern_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

		if ( empty( $pattern_name ) ) {
			return new WP_Error( 'invalid_pattern_name', 'pattern name is required.' );
		}

		$patterns_dir = CDW_PLUGIN_DIR . 'patterns';

		if ( ! is_dir( $patterns_dir ) ) {
			return new WP_Error( 'patterns_dir_not_found', 'Patterns directory not found.' );
		}

		$files   = self::find_pattern_json_files( $patterns_dir );
		$matched = null;

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			if ( false === $content ) {
				continue;
			}

			$data = json_decode( $content, true );

			if ( $data && isset( $data['name'] ) && $data['name'] === $pattern_name ) {
				$matched = $data;
				break;
			}
		}

		if ( ! $matched ) {
			return new WP_Error( 'pattern_not_found', "Custom pattern not found: $pattern_name" );
		}

		$content = isset( $matched['content'] ) ? $matched['content'] : '';
		if ( empty( $content ) ) {
			return new WP_Error( 'empty_pattern', "Pattern \"$pattern_name\" has no content." );
		}

		$content_base64 = base64_encode( $content );

		return array(
			'output'         => "Custom pattern \"$pattern_name\" retrieved. Length: " . strlen( $content ) . ' bytes.',
			'name'           => $pattern_name,
			'title'          => isset( $matched['title'] ) ? $matched['title'] : '',
			'description'    => isset( $matched['description'] ) ? $matched['description'] : '',
			'category'       => isset( $matched['category'] ) ? $matched['category'] : 'general',
			'content_length' => strlen( $content ),
			'content_base64' => $content_base64,
		);
	}
}

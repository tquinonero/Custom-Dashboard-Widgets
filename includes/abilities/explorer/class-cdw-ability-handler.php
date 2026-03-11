<?php
/**
 * Abilities API wrapper for CDW Abilities Explorer.
 *
 * Interfaces with WordPress Abilities API to fetch, format, validate,
 * and invoke abilities.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles ability operations for the explorer.
 *
 * @package CDW
 */
class CDW_Ability_Handler {

	/**
	 * Fetches all CDW abilities from the Abilities API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		$abilities = wp_get_abilities();

		if ( ! is_array( $abilities ) ) {
			return array();
		}

		$abilities = array_filter(
			$abilities,
			function ( $ability ) {
				return str_starts_with( $ability->get_name() ?? '', 'cdw/' );
			}
		);

		return array_values( $abilities );
	}

	/**
	 * Fetches a single ability by name.
	 *
	 * @param string $ability_name Ability ID (e.g., 'cdw/plugin-list').
	 * @return array<string, mixed>|null
	 */
	public static function get_ability( string $ability_name ): ?array {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return null;
		}

		$ability = wp_get_ability( $ability_name );

		if ( ! $ability || ! str_starts_with( $ability_name, 'cdw/' ) ) {
			return null;
		}

		return array(
			'id'               => $ability->get_name(),
			'label'           => $ability->get_label(),
			'description'     => $ability->get_description(),
			'input_schema'    => $ability->get_input_schema(),
			'output_schema'   => $ability->get_output_schema(),
			'meta'            => $ability->get_meta(),
			'ability_object'  => $ability,
		);
	}

	/**
	 * Formats an ability for table display.
	 *
	 * @param object $ability Raw ability data from API.
	 * @return array<string, mixed>
	 */
	public static function format_for_table( object $ability ): array {
		$meta = $ability->get_meta() ?? array();

		return array(
			'id'          => $ability->get_name() ?? '',
			'name'        => $ability->get_name() ?? '',
			'label'       => $ability->get_label() ?? '',
			'description' => $ability->get_description() ?? '',
			'readonly'    => ! empty( $meta['readonly'] ),
			'destructive' => ! empty( $meta['annotations']['destructive'] ),
		);
	}

	/**
	 * Formats input schema for display.
	 *
	 * @param array<string, mixed>|null $input_schema Input schema or null.
	 * @return array<string, mixed>
	 */
	public static function format_input_schema( ?array $input_schema ): array {
		if ( ! $input_schema ) {
			return array(
				'has_input' => false,
				'schema'    => null,
				'example'   => null,
			);
		}

		$properties = $input_schema['properties'] ?? array();
		$example    = array();

		foreach ( $properties as $key => $field ) {
			$example[ $key ] = self::generate_example_value( $field );
		}

		return array(
			'has_input' => ! empty( $properties ),
			'schema'    => $input_schema,
			'example'   => $example,
		);
	}

	/**
	 * Generates an example value from a field schema.
	 *
	 * @param array<string, mixed> $field Field schema.
	 * @return mixed
	 */
	private static function generate_example_value( array $field ) {
		if ( isset( $field['default'] ) ) {
			return $field['default'];
		}

		if ( isset( $field['example'] ) ) {
			return $field['example'];
		}

		$type = $field['type'] ?? 'string';

		switch ( $type ) {
			case 'string':
				return ! empty( $field['enum'] ) ? $field['enum'][0] : 'example';
			case 'integer':
			case 'number':
				return 0;
			case 'boolean':
				return false;
			case 'array':
				return array();
			case 'object':
				return (object) array();
			default:
				return 'example';
		}
	}

	/**
	 * Validates input against an ability's input schema.
	 *
	 * @param array<string, mixed>|null $input_schema Input schema.
	 * @param array<string, mixed>      $input         User input to validate.
	 * @return array{valid: bool, errors: array<int, string>}
	 */
	public static function validate_input( ?array $input_schema, array $input ): array {
		$errors = array();

		if ( ! $input_schema ) {
			if ( ! empty( $input ) ) {
				$errors[] = __( 'This ability does not accept any input.', 'cdw' );
			}
			return array(
				'valid'  => empty( $errors ),
				'errors' => $errors,
			);
		}

		$properties = $input_schema['properties'] ?? array();

		foreach ( $properties as $key => $field ) {
			$is_required = in_array( $key, $input_schema['required'] ?? array(), true );

			if ( $is_required && ! array_key_exists( $key, $input ) ) {
				$errors[] = sprintf(
					/* translators: %s: field name */
					__( 'Required field "%s" is missing.', 'cdw' ),
					$key
				);
				continue;
			}

			if ( array_key_exists( $key, $input ) ) {
				$value    = $input[ $key ];
				$expected = $field['type'] ?? 'string';

				if ( ! self::validate_type( $value, $expected ) ) {
					$errors[] = sprintf(
						/* translators: %1$s: field name, %2$s: expected type */
						__( 'Field "%1$s" must be of type %2$s.', 'cdw' ),
						$key,
						$expected
					);
				}

				if ( isset( $field['enum'] ) && ! in_array( $value, $field['enum'], true ) ) {
					$errors[] = sprintf(
						/* translators: %1$s: field name, %2$s: allowed values */
						__( 'Field "%1$s" must be one of: %2$s.', 'cdw' ),
						$key,
						implode( ', ', $field['enum'] )
					);
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validates a value against an expected type.
	 *
	 * @param mixed  $value    Value to validate.
	 * @param string $expected Expected type.
	 * @return bool
	 */
	private static function validate_type( $value, string $expected ): bool {
		switch ( $expected ) {
			case 'string':
				return is_string( $value );
			case 'integer':
				return is_int( $value );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'boolean':
				return is_bool( $value );
			case 'array':
				return is_array( $value );
			case 'object':
				return is_object( $value ) || is_array( $value );
			default:
				return true;
		}
	}

	/**
	 * Invokes an ability with the given input.
	 *
	 * @param string               $ability_name Ability ID.
	 * @param array<string, mixed> $input       Validated input.
	 * @return array{success: bool, data: mixed, error?: string}
	 */
	public static function invoke_ability( string $ability_name, array $input ): array {
		$ability_data = self::get_ability( $ability_name );

		if ( ! $ability_data ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Ability not found.', 'cdw' ),
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Permission denied.', 'cdw' ),
			);
		}

		$ability = $ability_data['ability_object'] ?? null;

		if ( ! $ability ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => __( 'Ability object not available.', 'cdw' ),
			);
		}

		try {
			// Only pass input if the ability has an input schema
			$input_schema = $ability->get_input_schema();
			$input_to_pass = ( empty( $input_schema['properties'] ) ) ? null : $input;

			$result = $ability->execute( $input_to_pass );

			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'data'    => null,
					'error'   => $result->get_error_message(),
				);
			}

			return array(
				'success' => true,
				'data'    => $result,
			);
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $e->getMessage(),
			);
		}
	}
}

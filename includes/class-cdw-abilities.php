<?php
/**
 * WordPress Abilities API registration.
 *
 * Registers all 32 CDW tools as WP_Ability objects so they are discoverable
 * by the WordPress Abilities API (WP 6.9+) and, optionally, by external AI
 * clients via the MCP Adapter plugin.
 *
 * This class is purely additive — the existing REST API and agentic loop are
 * untouched.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers CDW abilities via the WordPress Abilities API.
 *
 * @package CDW
 */
class CDW_Abilities {

	/**
	 * Registers the ability category and all CDW abilities.
	 *
	 * Called unconditionally from CDW_Loader::run(). Bails silently on
	 * WordPress versions older than 6.9 that lack the Abilities API.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( static::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( static::class, 'register_abilities' ) );

		// Opt-in MCP exposure — applied before abilities are finalised.
		if ( get_option( 'cdw_mcp_public', false ) ) {
			add_filter(
				'wp_register_ability_args',
				function ( $args, $ability_name ) {
					if ( str_starts_with( $ability_name, 'cdw/' ) ) {
						$args['meta']['mcp']['public'] = true;
					}
					return $args;
				},
				10,
				2
			);
		}
	}

	/**
	 * Registers the `cdw-admin-tools` ability category.
	 *
	 * Hooked to `wp_abilities_api_categories_init`.
	 *
	 * @return void
	 */
	public static function register_category() {
		wp_register_ability_category(
			'cdw-admin-tools',
			array(
				'label'       => __( 'CDW Admin Tools', 'cdw' ),
				'description' => __( 'WordPress admin management tools provided by CDW.', 'cdw' ),
			)
		);
	}

	/**
	 * Registers all CDW abilities from config plus inline abilities.
	 *
	 * Hooked to `wp_abilities_api_init`.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-cli-service.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/builders/class-cdw-ability-cli-command-builders.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/definitions/class-cdw-role-abilities.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/definitions/class-cdw-pattern-abilities.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/definitions/class-cdw-content-abilities.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/definitions/class-cdw-meta-abilities.php';

		$permission_cb = function () {
			return current_user_can( 'manage_options' );
		};

		// Load abilities from config file.
		// Note: We use include instead of require_once because PHPUnit runs all tests
		// in the same process, and require_once returns true after first include.
		// The config file returns an array, so we check for that.
		$config_abilities = include CDW_PLUGIN_DIR . 'includes/services/ai/config/class-cdw-abilities-config.php';

		foreach ( $config_abilities as $ability_name => $ability ) {
			// Add name to the ability array for register_one().
			$ability['name'] = $ability_name;
			self::register_one( $ability, $permission_cb );
		}

		CDW_Role_Abilities::register( $permission_cb );
		CDW_Pattern_Abilities::register( $permission_cb );
		CDW_Content_Abilities::register( $permission_cb );
		CDW_Meta_Abilities::register( $permission_cb );
	}

	/**
	 * Registers a single ability.
	 *
	 * Builds the execute_callback by mapping the ability name to the correct
	 * CLI command string, then calls wp_register_ability().
	 *
	 * @param array<string, mixed> $ability       Ability definition (name, label, input, cli).
	 * @param callable             $permission_cb Shared permission callback.
	 * @return void
	 */
	private static function register_one( array $ability, callable $permission_cb ) {
		$ability_name = $ability['name'];
		$static_cli   = $ability['cli'];

		$execute_cb = function ( $input = null ) use ( $ability_name, $static_cli ) {
			// Convert stdClass to array recursively if needed (MCP sends JSON objects).
			if ( is_object( $input ) ) {
				$input = json_decode( json_encode( $input ), true );
			}
			if ( ! is_array( $input ) ) {
				$input = array();
			}

			$input = self::normalize_ability_input( $ability_name, $input );
			$cli_command = $static_cli ?? self::build_cli_command( $ability_name, $input );
			$service     = new CDW_CLI_Service();
			$result      = $service->execute_as_ai( $cli_command, get_current_user_id() );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'output' => $result );
		};


		$args = array(
			'label'               => $ability['label'],
			'description'         => $ability['desc'],
			'category'            => 'cdw-admin-tools',
			'permission_callback' => $permission_cb,
			'execute_callback'    => $execute_cb,
			'meta'                => array(
				'show_in_rest' => true,
				'readonly'     => $ability['readonly'],
				'idempotent'   => $ability['readonly'],
				'annotations'  => array(
					'destructive' => $ability['destructive'],
				),
			),
		);

		if ( ! empty( $ability['input'] ) ) {
			$allows_null_input = true;
			foreach ( $ability['input'] as $field_schema ) {
				if ( ! empty( $field_schema['required'] ) ) {
					$allows_null_input = false;
					break;
				}
			}

			$args['input_schema'] = array(
				// MCP execute-ability converts empty {} to null; allow null when all fields are optional.
				'type'       => $allows_null_input ? array( 'object', 'null' ) : 'object',
				'properties' => $ability['input'],
			);
		} else {
			// Register an explicit empty schema so the MCP adapter returns {} not [] for properties.
			$args['input_schema'] = array(
				'type'       => array( 'object', 'null' ),
				'properties' => new \stdClass(),
			);
		}

		wp_register_ability( $ability_name, $args );
	}

	/**
	 * Normalizes ability input for optional params and nullable MCP payloads.
	 *
	 * @param string               $ability_name Ability identifier.
	 * @param array<string, mixed> $input        Raw input payload.
	 * @return array<string, mixed>
	 */
	private static function normalize_ability_input( string $ability_name, array $input ): array {
		$normalized = array();

		foreach ( $input as $key => $value ) {
			if ( null === $value || '' === $value ) {
				continue;
			}
			$normalized[ $key ] = $value;
		}

		switch ( $ability_name ) {
			case 'cdw/comment-list':
				if ( ! isset( $normalized['status'] ) ) {
					$normalized['status'] = 'pending';
				}
				break;
			case 'cdw/post-list':
				if ( ! isset( $normalized['type'] ) ) {
					$normalized['type'] = 'post';
				}
				break;
			case 'cdw/post-count':
				if ( ! isset( $normalized['type'] ) ) {
					$normalized['type'] = '';
				}
				break;
			case 'cdw/task-list':
			case 'cdw/task-delete':
				if ( ! isset( $normalized['user_id'] ) ) {
					$normalized['user_id'] = 0;
				}
				break;
		}

		return $normalized;
	}

	/**
	 * Builds a CLI command string from an ability name and user-supplied input.
	 *
	 * Only called for abilities whose CLI string depends on runtime input params.
	 *
	 * @param string               $ability_name Fully-qualified ability name, e.g. `cdw/plugin-activate`.
	 * @param array<string, mixed> $input        Validated input params from the caller.
	 * @return string
	 */
	private static function build_cli_command( string $ability_name, ?array $input ): string {
		return CDW_Ability_CLI_Command_Builders::build( $ability_name, $input ?? array() );
	}
}

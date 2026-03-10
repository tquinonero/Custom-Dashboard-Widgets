<?php
/**
 * Pattern-related ability registrations.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-pattern-ability-service.php';

/**
 * Registers pattern-related abilities.
 */
class CDW_Pattern_Abilities {

	/**
	 * Registers all pattern abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	public static function register( callable $permission_cb ) {
		wp_register_ability(
			'cdw/block-patterns-get',
			array(
				'label'               => __( 'Get Block Pattern Content', 'cdw' ),
				'description'         => __( 'Returns the raw block markup for a specific block pattern by name. Returns base64-encoded content to preserve special characters. Use this to retrieve a pattern before appending it to a page.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Pattern_Ability_Service', 'get_block_pattern' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Name of the block pattern to retrieve (e.g., blockbase/footer-simple).',
						),
					),
					'required'   => array( 'name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		wp_register_ability(
			'cdw/custom-patterns-list',
			array(
				'label'               => __( 'List Custom Patterns', 'cdw' ),
				'description'         => __( 'Returns a list of all custom block patterns stored in the cdw/patterns/ folder. Each pattern includes name, title, description, and category.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Pattern_Ability_Service', 'list_custom_patterns' ),
				'input_schema'        => array(),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);

		wp_register_ability(
			'cdw/custom-patterns-get',
			array(
				'label'               => __( 'Get Custom Pattern', 'cdw' ),
				'description'         => __( 'Returns the raw block markup for a specific custom pattern by name. Searches in cdw/patterns/ folder. Returns base64-encoded content to preserve special characters.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Pattern_Ability_Service', 'get_custom_pattern' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Name of the custom pattern to retrieve (e.g., hero-luxury, gallery-masonry).',
						),
					),
					'required'   => array( 'name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array(
						'destructive' => false,
					),
				),
			)
		);
	}
}

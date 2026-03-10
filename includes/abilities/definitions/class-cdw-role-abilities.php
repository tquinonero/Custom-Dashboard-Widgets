<?php
/**
 * Role ability registrations.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/class-cdw-role-ability-service.php';

/**
 * Registers role-related abilities.
 */
class CDW_Role_Abilities {

	/**
	 * Registers all role abilities.
	 *
	 * @param callable $permission_cb Shared permission callback.
	 * @return void
	 */
	public static function register( callable $permission_cb ) {
		wp_register_ability(
			'cdw/role-list',
			array(
				'label'               => __( 'List Roles', 'cdw' ),
				'description'         => __( 'Returns a list of all WordPress roles with their display names and capabilities. Use this to see what capabilities each role has before creating or updating roles.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Role_Ability_Service', 'list_roles' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'Optional. Filter by role slug to return only that role.',
						),
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => true,
					'idempotent'   => true,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/role-create',
			array(
				'label'               => __( 'Create Role', 'cdw' ),
				'description'         => __( 'Creates a new WordPress role with specified display name and capabilities. Optionally clone capabilities from an existing role. Cannot override built-in roles (administrator, editor, author, contributor, subscriber).', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Role_Ability_Service', 'create_role' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'         => array(
							'type'        => 'string',
							'description' => 'The role slug (e.g., manager, consultant).',
						),
						'display_name' => array(
							'type'        => 'string',
							'description' => 'The role display name shown in the admin.',
						),
						'capabilities' => array(
							'type'        => 'array',
							'description' => 'Array of capability keys (e.g., ["read", "edit_posts", "manage_options"]).',
						),
						'clone_from'   => array(
							'type'        => 'string',
							'description' => 'Optional. Role slug to clone capabilities from (e.g., editor).',
						),
					),
					'required'   => array( 'role', 'display_name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/role-update',
			array(
				'label'               => __( 'Update Role', 'cdw' ),
				'description'         => __( 'Updates a role by adding or removing capabilities. Use add_caps to grant new capabilities, remove_caps to revoke capabilities. Cannot modify built-in roles (administrator, editor, author, contributor, subscriber).', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Role_Ability_Service', 'update_role' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role'        => array(
							'type'        => 'string',
							'description' => 'The role slug to update (must be a custom role, not built-in).',
						),
						'add_caps'    => array(
							'type'        => 'array',
							'description' => 'Array of capability keys to add.',
						),
						'remove_caps' => array(
							'type'        => 'array',
							'description' => 'Array of capability keys to remove.',
						),
					),
					'required'   => array( 'role' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => false ),
				),
			)
		);

		wp_register_ability(
			'cdw/role-delete',
			array(
				'label'               => __( 'Delete Role', 'cdw' ),
				'description'         => __( 'Deletes a custom WordPress role. Cannot delete built-in roles (administrator, editor, author, contributor, subscriber). Users currently assigned to the deleted role will be moved to subscriber.', 'cdw' ),
				'category'            => 'cdw-admin-tools',
				'permission_callback' => $permission_cb,
				'execute_callback'    => array( 'CDW_Role_Ability_Service', 'delete_role' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'The role slug to delete (must be a custom role, not built-in).',
						),
					),
					'required'   => array( 'role' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'readonly'     => false,
					'idempotent'   => false,
					'annotations'  => array( 'destructive' => true ),
				),
			)
		);
	}
}

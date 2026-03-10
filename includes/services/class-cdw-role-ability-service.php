<?php
/**
 * Role ability execution service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes role-related ability logic.
 */
class CDW_Role_Ability_Service {

	/**
	 * Built-in role slugs that must not be modified/deleted.
	 */
	const BUILT_IN_ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

	/**
	 * Executes `cdw/role-list`.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>
	 */
	public static function list_roles( $input = array() ) {
		$filter_role = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';
		$wp_roles    = wp_roles();

		$roles = array();
		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			if ( $filter_role && $role_key !== $filter_role ) {
				continue;
			}

			$capabilities = isset( $role_data['capabilities'] ) ? $role_data['capabilities'] : array();
			$roles[]      = array(
				'role'         => $role_key,
				'display_name' => isset( $role_data['name'] ) ? $role_data['name'] : $role_key,
				'is_builtin'   => in_array( $role_key, self::BUILT_IN_ROLES, true ),
				'capabilities' => array_keys( $capabilities ),
				'cap_count'    => count( $capabilities ),
			);
		}

		return array(
			'output' => 'Found ' . count( $roles ) . ' roles.',
			'roles'  => $roles,
		);
	}

	/**
	 * Executes `cdw/role-create`.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function create_role( $input = array() ) {
		$role         = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';
		$display_name = isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : '';
		$capabilities = isset( $input['capabilities'] ) ? (array) $input['capabilities'] : array();
		$clone_from   = isset( $input['clone_from'] ) ? sanitize_key( $input['clone_from'] ) : '';

		if ( empty( $role ) ) {
			return new WP_Error( 'missing_role', 'role (slug) is required.' );
		}
		if ( empty( $display_name ) ) {
			return new WP_Error( 'missing_display_name', 'display_name is required.' );
		}
		if ( in_array( $role, self::BUILT_IN_ROLES, true ) ) {
			return new WP_Error( 'builtin_role', "Cannot override built-in role: $role" );
		}

		$wp_roles = wp_roles();

		if ( isset( $wp_roles->roles[ $role ] ) ) {
			return new WP_Error( 'role_exists', "Role '$role' already exists. Use role-update to modify it." );
		}

		if ( ! empty( $clone_from ) ) {
			if ( ! isset( $wp_roles->roles[ $clone_from ] ) ) {
				return new WP_Error( 'clone_source_not_found', "Source role '$clone_from' does not exist." );
			}
			$source_caps  = isset( $wp_roles->roles[ $clone_from ]['capabilities'] )
				? $wp_roles->roles[ $clone_from ]['capabilities']
				: array();
			$capabilities = array_merge( $capabilities, $source_caps );
		}

		if ( empty( $capabilities ) ) {
			$capabilities = array( 'read' => true );
		}

		$result = $wp_roles->add_role( $role, $display_name, $capabilities );

		if ( ! $result ) {
			return new WP_Error( 'add_role_failed', "Failed to create role '$role'." );
		}

		return array(
			'output'       => "Role '$role' created successfully with " . count( $capabilities ) . ' capabilities.',
			'role'         => $role,
			'display_name' => $display_name,
			'cap_count'    => count( $capabilities ),
			'cloned_from'  => ! empty( $clone_from ) ? $clone_from : null,
		);
	}

	/**
	 * Executes `cdw/role-update`.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function update_role( $input = array() ) {
		$role        = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';
		$add_caps    = isset( $input['add_caps'] ) ? (array) $input['add_caps'] : array();
		$remove_caps = isset( $input['remove_caps'] ) ? (array) $input['remove_caps'] : array();

		if ( empty( $role ) ) {
			return new WP_Error( 'missing_role', 'role (slug) is required.' );
		}

		if ( in_array( $role, self::BUILT_IN_ROLES, true ) ) {
			return new WP_Error( 'builtin_role', "Cannot modify built-in role: $role. Use role-create to make a custom copy instead." );
		}

		$wp_roles = wp_roles();

		if ( ! isset( $wp_roles->roles[ $role ] ) ) {
			return new WP_Error( 'role_not_found', "Role '$role' does not exist. Use role-create to create it." );
		}

		$role_object = $wp_roles->get_role( $role );
		if ( ! $role_object ) {
			return new WP_Error( 'role_get_failed', "Failed to get role '$role'." );
		}

		$added   = array();
		$removed = array();

		foreach ( $add_caps as $cap ) {
			$cap = sanitize_key( $cap );
			if ( ! empty( $cap ) ) {
				$role_object->add_cap( $cap );
				$added[] = $cap;
			}
		}

		foreach ( $remove_caps as $cap ) {
			$cap = sanitize_key( $cap );
			if ( ! empty( $cap ) ) {
				$role_object->remove_cap( $cap );
				$removed[] = $cap;
			}
		}

		$current_caps = isset( $wp_roles->roles[ $role ]['capabilities'] )
			? $wp_roles->roles[ $role ]['capabilities']
			: array();

		return array(
			'output'    => "Role '$role' updated.",
			'role'      => $role,
			'added'     => $added,
			'removed'   => $removed,
			'cap_count' => count( $current_caps ),
		);
	}

	/**
	 * Executes `cdw/role-delete`.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function delete_role( $input = array() ) {
		$role = isset( $input['role'] ) ? sanitize_key( $input['role'] ) : '';

		if ( empty( $role ) ) {
			return new WP_Error( 'missing_role', 'role (slug) is required.' );
		}

		if ( in_array( $role, self::BUILT_IN_ROLES, true ) ) {
			return new WP_Error( 'builtin_role', "Cannot delete built-in role: $role" );
		}

		$wp_roles = wp_roles();

		if ( ! isset( $wp_roles->roles[ $role ] ) ) {
			return new WP_Error( 'role_not_found', "Role '$role' does not exist." );
		}

		$users_with_role = count(
			get_users(
				array(
					'role'   => $role,
					'fields' => 'ID',
					'number' => -1,
				)
			)
		);

		$wp_roles->remove_role( $role );

		return array(
			'output'         => "Role '$role' deleted successfully.",
			'role'           => $role,
			'users_affected' => $users_with_role,
		);
	}
}

<?php
/**
 * Task service — stores and manages per-user task data.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages personal task lists stored in user meta.
 *
 * @package CDW
 */
class CDW_Task_Service {
	const META_KEY = 'cdw_tasks';

	/**
	 * Returns the task list for the given user ID.
	 *
	 * @param int|null $user_id Target user ID (defaults to current user).
	 * @return array<int,array<string,mixed>>
	 */
	public function get_tasks( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return array();
		}

		$tasks_json = get_user_meta( $user_id, self::META_KEY, true );
		$tasks      = $tasks_json ? json_decode( $tasks_json, true ) : array();
		// Require a sequential list — reject JSON objects decoded as assoc arrays.
		return ( is_array( $tasks ) && array_is_list( $tasks ) ) ? $tasks : array();
	}

	/**
	 * Validates, sanitizes, and persists tasks for the target user.
	 *
	 * @param array<int,array<string,mixed>> $tasks           Incoming task array.
	 * @param int|null                       $target_user_id  Target user ID (defaults to current user).
	 * @param int|null                       $current_user_id Authenticated user ID (defaults to current user).
	 * @return array<int,array<string,mixed>>|WP_Error Sanitized task list or WP_Error on failure.
	 */
	public function save_tasks( $tasks, $target_user_id = null, $current_user_id = null ) {
		if ( null === $current_user_id ) {
			$current_user_id = get_current_user_id();
		}

		if ( ! $current_user_id ) {
			return new WP_Error( 'not_logged_in', 'User not logged in', array( 'status' => 401 ) );
		}

		if ( null === $target_user_id ) {
			$target_user_id = $current_user_id;
		}

		if ( ! is_array( $tasks ) ) {
			return new WP_Error( 'invalid_tasks', 'Invalid tasks data', array( 'status' => 400 ) );
		}

		$sanitized_tasks = $this->sanitize_tasks( $tasks, $current_user_id );

		if ( $target_user_id !== $current_user_id ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'permission_denied', 'Permission denied', array( 'status' => 403 ) );
			}

			if ( ! get_userdata( $target_user_id ) ) {
				return new WP_Error( 'invalid_user', 'Invalid user', array( 'status' => 400 ) );
			}

			$existing_tasks = $this->get_tasks( $target_user_id );
			if ( ! empty( $existing_tasks ) ) {
				$sanitized_tasks = array_merge( $existing_tasks, $sanitized_tasks );
			}
		}

		update_user_meta( $target_user_id, self::META_KEY, wp_json_encode( $sanitized_tasks ) );

		return $sanitized_tasks;
	}

	/**
	 * Strips invalid entries and sanitizes text fields in the task array.
	 *
	 * @param array<int,array<string,mixed>> $tasks           Raw incoming tasks.
	 * @param int                            $current_user_id ID of the authenticated user (stored in created_by).
	 * @return array<int,array<string,mixed>>
	 */
	private function sanitize_tasks( $tasks, $current_user_id ) {
		$sanitized = array();
		$now       = time();

		foreach ( $tasks as $task ) {
			$name = isset( $task['name'] ) ? sanitize_text_field( wp_unslash( $task['name'] ) ) : '';

			if ( empty( $name ) ) {
				continue;
			}

			$timestamp = isset( $task['timestamp'] ) ? intval( $task['timestamp'] ) : 0;
			if ( $timestamp <= 0 || $timestamp > $now ) {
				$timestamp = $now;
			}

			$sanitized[] = array(
				'name'       => $name,
				'timestamp'  => $timestamp,
				'created_by' => $current_user_id,
			);
		}

		return $sanitized;
	}

	/**
	 * Removes all tasks for the given user.
	 *
	 * @param int|null $user_id Target user ID (defaults to current user).
	 * @return bool True on success, false if no user ID.
	 */
	public function delete_tasks( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		return delete_user_meta( $user_id, self::META_KEY );
	}
}

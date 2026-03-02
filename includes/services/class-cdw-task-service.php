<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CDW_Task_Service {
    const META_KEY = 'cdw_tasks';

    public function get_tasks( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return array();
        }

        $tasks_json = get_user_meta( $user_id, self::META_KEY, true );
        $tasks      = $tasks_json ? json_decode( $tasks_json, true ) : array();
        return is_array( $tasks ) ? $tasks : array();
    }

    public function save_tasks( $tasks, $target_user_id = null, $current_user_id = null ) {
        if ( null === $current_user_id ) {
            $current_user_id = get_current_user_id();
        }

        if ( ! $current_user_id ) {
            return new WP_Error( 'no_user', 'User not logged in', array( 'status' => 401 ) );
        }

        if ( null === $target_user_id ) {
            $target_user_id = $current_user_id;
        }

        if ( ! is_array( $tasks ) ) {
            return new WP_Error( 'invalid_data', 'Invalid tasks data', array( 'status' => 400 ) );
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

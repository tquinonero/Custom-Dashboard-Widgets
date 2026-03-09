<?php
/**
 * User command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles user management commands (list, create, delete, role).
 */
class CDW_User_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute a user subcommand.
	 *
	 * @param string            $subcmd   Subcommand (list, create, delete, role).
	 * @param array<int,string> $args    Positional arguments.
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		switch ( $subcmd ) {
			case 'get':
				return $this->handle_get( $args );

			case 'list':
				return $this->handle_list();

			case 'create':
				return $this->handle_create( $args );

			case 'delete':
				return $this->handle_delete( $args, $raw_args );

			case 'role':
				return $this->handle_role( $args );

			default:
				return $this->get_help();
		}
	}

	/**
	 * Get help text for user commands.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Available user commands:\n  user get <username|id>   - Get user details\n  user list                - List all users\n  user create <user> <email> <role> - Create user\n  user delete <user>      - Delete user\n  user role <user> <role>  - Change user role",
			'success' => true,
		);
	}

	/**
	 * Check if subcommand requires --force flag.
	 *
	 * @param string $subcmd The subcommand to check.
	 * @return bool
	 */
	public function requires_force( string $subcmd ): bool {
		return 'delete' === $subcmd;
	}

	/**
	 * Handle user get.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_get( array $args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: user get <username|id>' );
		}

		$identifier  = sanitize_text_field( $args[0] );
		$target_user = get_user_by( 'login', $identifier );

		if ( ! $target_user ) {
			$target_user = get_user_by( 'id', intval( $identifier ) );
		}

		if ( ! $target_user ) {
			return $this->failure( "User not found: $identifier" );
		}

		$roles      = implode( ', ', $target_user->roles );
		$post_count = (int) count_user_posts( $target_user->ID );
		$author_url = get_author_posts_url( $target_user->ID );

		$output  = "ID:          {$target_user->ID}\n";
		$output .= "Username:    {$target_user->user_login}\n";
		$output .= "Display:     {$target_user->display_name}\n";
		$output .= "Email:       {$target_user->user_email}\n";
		$output .= "Role:        $roles\n";
		$output .= "Registered:  {$target_user->user_registered}\n";
		$output .= "Posts:       $post_count\n";
		$output .= "URL:         $author_url\n";

		return $this->success( $output );
	}

	/**
	 * Handle user list.
	 *
	 * @return array<string,mixed>
	 */
	private function handle_list(): array {
		$users  = get_users( array( 'number' => 200 ) );
		$output = "Users:\n";

		foreach ( $users as $user ) {
			$roles   = implode( ', ', $user->roles );
			$output .= $user->user_login . " (ID: {$user->ID}, $roles)\n";
		}

		return $this->success( $output );
	}

	/**
	 * Handle user create.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_create( array $args ): array {
		if ( count( $args ) < 3 ) {
			return $this->failure( 'Usage: user create <username> <email> <role>' );
		}

		$username = sanitize_user( $args[0] );
		$email    = sanitize_email( $args[1] );
		$role     = sanitize_text_field( $args[2] );

		if ( username_exists( $username ) ) {
			return $this->failure( "User already exists: $username" );
		}

		if ( ! is_email( $email ) ) {
			return $this->failure( "Invalid email: $email" );
		}

		if ( ! get_role( $role ) && 'administrator' !== $role ) {
			return $this->failure( "Invalid role: $role" );
		}

		$user_id = wp_create_user( $username, wp_generate_password(), $email );

		if ( is_wp_error( $user_id ) ) {
			return $this->failure( 'User creation failed: ' . $user_id->get_error_message() );
		}

		wp_update_user(
			array(
				'ID'   => $user_id,
				'role' => $role,
			)
		);

		wp_new_user_notification( $user_id, null, 'both' );

		return $this->success( "User created: $username (ID: $user_id, role: $role). Password reset email sent to $email." );
	}

	/**
	 * Handle user delete.
	 *
	 * @param array<int,string> $args    Command arguments.
	 * @param array<int,string> $raw_args Raw arguments including flags.
	 * @return array<string,mixed>
	 */
	private function handle_delete( array $args, array $raw_args ): array {
		if ( empty( $args[0] ) ) {
			return $this->failure( 'Usage: user delete <username> --force' );
		}

		$identifier = sanitize_text_field( $args[0] );
		$user       = get_user_by( 'login', $identifier );

		if ( ! $user ) {
			$user = get_user_by( 'id', intval( $identifier ) );
		}

		if ( ! $user ) {
			return $this->failure( "User not found: $identifier" );
		}

		if ( get_current_user_id() === $user->ID ) {
			return $this->failure( 'Cannot delete yourself.' );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$reassign_id    = null;
		$delete_content = in_array( '--delete-content', $raw_args, true );

		foreach ( $raw_args as $arg ) {
			if ( preg_match( '/^--reassign=(\d+)$/', $arg, $m ) ) {
				$reassign_id = (int) $m[1];
				break;
			}
		}

		if ( null === $reassign_id && ! $delete_content ) {
			return $this->failure(
				"user delete requires one of:\n  --reassign=<user_id>  Reassign content to another user\n  --delete-content      Permanently delete all their content\nExample: user delete $identifier --reassign=1 --force"
			);
		}

		if ( $reassign_id && ! get_userdata( $reassign_id ) ) {
			return $this->failure( "Reassign target not found: $reassign_id" );
		}

		$result = wp_delete_user( $user->ID, $reassign_id );

		if ( ! $result ) {
			return $this->failure( 'Delete failed: could not delete user.' );
		}

		$note = $reassign_id ? " (content reassigned to user $reassign_id)" : ' (content deleted)';

		return $this->success( "User deleted: $identifier$note" );
	}

	/**
	 * Handle user role.
	 *
	 * @param array<int,string> $args Command arguments.
	 * @return array<string,mixed>
	 */
	private function handle_role( array $args ): array {
		if ( count( $args ) < 2 ) {
			return $this->failure( 'Usage: user role <username> <role>' );
		}

		$identifier = sanitize_text_field( $args[0] );
		$new_role   = sanitize_text_field( $args[1] );

		if ( ! get_role( $new_role ) ) {
			return $this->failure( "Invalid role: $new_role" );
		}

		$user = get_user_by( 'login', $identifier );

		if ( ! $user ) {
			$user = get_user_by( 'id', intval( $identifier ) );
		}

		if ( ! $user ) {
			return $this->failure( "User not found: $identifier" );
		}

		$user->set_role( $new_role );

		return $this->success( "User role updated: $identifier -> $new_role" );
	}
}

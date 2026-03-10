<?php
/**
 * Runtime CLI command builders for input-dependent abilities.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds CLI command strings for config-based abilities.
 */
class CDW_Ability_CLI_Command_Builders {

	/**
	 * Builds a CLI command string for an ability.
	 *
	 * @param string               $ability_name Ability name.
	 * @param array<string, mixed> $input        Validated ability input.
	 * @return string
	 */
	public static function build( string $ability_name, array $input ): string {
		$builders = self::get_builders();

		if ( ! isset( $builders[ $ability_name ] ) ) {
			return '';
		}

		return $builders[ $ability_name ]( $input );
	}

	/**
	 * Returns map of ability builders.
	 *
	 * @return array<string, callable>
	 */
	private static function get_builders(): array {
		return array(
			'cdw/plugin-status'    => array( static::class, 'build_plugin_status' ),
			'cdw/plugin-activate'  => array( static::class, 'build_plugin_activate' ),
			'cdw/plugin-deactivate'=> array( static::class, 'build_plugin_deactivate' ),
			'cdw/plugin-install'   => array( static::class, 'build_plugin_install' ),
			'cdw/plugin-update'    => array( static::class, 'build_plugin_update' ),
			'cdw/plugin-delete'    => array( static::class, 'build_plugin_delete' ),
			'cdw/theme-activate'   => array( static::class, 'build_theme_activate' ),
			'cdw/theme-install'    => array( static::class, 'build_theme_install' ),
			'cdw/theme-update'     => array( static::class, 'build_theme_update' ),
			'cdw/theme-status'     => array( static::class, 'build_theme_status' ),
			'cdw/user-create'      => array( static::class, 'build_user_create' ),
			'cdw/user-delete'      => array( static::class, 'build_user_delete' ),
			'cdw/user-get'         => array( static::class, 'build_user_get' ),
			'cdw/option-get'       => array( static::class, 'build_option_get' ),
			'cdw/option-set'       => array( static::class, 'build_option_set' ),
			'cdw/search-replace'   => array( static::class, 'build_search_replace' ),
			'cdw/post-get'         => array( static::class, 'build_post_get' ),
			'cdw/post-create'      => array( static::class, 'build_post_create' ),
			'cdw/page-create'      => array( static::class, 'build_page_create' ),
			'cdw/task-list'        => array( static::class, 'build_task_list' ),
			'cdw/task-create'      => array( static::class, 'build_task_create' ),
			'cdw/task-delete'      => array( static::class, 'build_task_delete' ),
			'cdw/comment-list'     => array( static::class, 'build_comment_list' ),
			'cdw/comment-approve'  => array( static::class, 'build_comment_approve' ),
			'cdw/comment-spam'     => array( static::class, 'build_comment_spam' ),
			'cdw/comment-delete'   => array( static::class, 'build_comment_delete' ),
			'cdw/post-list'        => array( static::class, 'build_post_list' ),
			'cdw/post-count'       => array( static::class, 'build_post_count' ),
			'cdw/post-status'      => array( static::class, 'build_post_status' ),
			'cdw/post-delete'      => array( static::class, 'build_post_delete' ),
			'cdw/user-role'        => array( static::class, 'build_user_role' ),
			'cdw/option-delete'    => array( static::class, 'build_option_delete' ),
			'cdw/theme-delete'     => array( static::class, 'build_theme_delete' ),
			'cdw/transient-delete' => array( static::class, 'build_transient_delete' ),
			'cdw/cron-run'         => array( static::class, 'build_cron_run' ),
			'cdw/media-list'       => array( static::class, 'build_media_list' ),
			'cdw/block-patterns-list' => array( static::class, 'build_block_patterns_list' ),
			'cdw/skill-get'        => array( static::class, 'build_skill_get' ),
		);
	}

	/**
	 * Removes whitespace from a CLI argument to avoid token injection.
	 *
	 * @param string $value Raw user value.
	 * @return string
	 */
	private static function sanitize_cli_arg( string $value ): string {
		return preg_replace( '/\s+/', '', trim( $value ) );
	}

	public static function build_plugin_status( array $input ): string {
		return 'plugin status ' . self::sanitize_cli_arg( (string) $input['slug'] );
	}

	public static function build_plugin_activate( array $input ): string {
		return 'plugin activate ' . self::sanitize_cli_arg( (string) $input['slug'] );
	}

	public static function build_plugin_deactivate( array $input ): string {
		return 'plugin deactivate ' . self::sanitize_cli_arg( (string) $input['slug'] );
	}

	public static function build_plugin_install( array $input ): string {
		return 'plugin install ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
	}

	public static function build_plugin_update( array $input ): string {
		return 'plugin update ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
	}

	public static function build_plugin_delete( array $input ): string {
		return 'plugin delete ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
	}

	public static function build_theme_activate( array $input ): string {
		return 'theme activate ' . self::sanitize_cli_arg( (string) $input['slug'] );
	}

	public static function build_theme_install( array $input ): string {
		return 'theme install ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
	}

	public static function build_theme_update( array $input ): string {
		return 'theme update ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
	}

	public static function build_theme_status( array $input ): string {
		return 'theme status ' . self::sanitize_cli_arg( (string) $input['slug'] );
	}

	public static function build_user_create( array $input ): string {
		return 'user create '
			. self::sanitize_cli_arg( (string) $input['username'] ) . ' '
			. self::sanitize_cli_arg( (string) $input['email'] ) . ' '
			. self::sanitize_cli_arg( (string) $input['role'] );
	}

	public static function build_user_delete( array $input ): string {
		return 'user delete ' . (int) $input['user_id'] . ' --force';
	}

	public static function build_user_get( array $input ): string {
		return 'user get ' . self::sanitize_cli_arg( (string) $input['identifier'] );
	}

	public static function build_option_get( array $input ): string {
		return 'option get ' . self::sanitize_cli_arg( (string) $input['name'] );
	}

	public static function build_option_set( array $input ): string {
		return 'option set ' . self::sanitize_cli_arg( (string) $input['name'] ) . ' ' . self::sanitize_cli_arg( (string) $input['value'] );
	}

	public static function build_search_replace( array $input ): string {
		$flag = ! empty( $input['dry_run'] ) ? '--dry-run' : '--force';
		return 'search-replace ' . self::sanitize_cli_arg( (string) $input['search'] ) . ' ' . self::sanitize_cli_arg( (string) $input['replace'] ) . ' ' . $flag;
	}

	public static function build_post_get( array $input ): string {
		return 'post get ' . (int) $input['post_id'];
	}

	public static function build_post_create( array $input ): string {
		return 'post create ' . sanitize_text_field( (string) $input['title'] );
	}

	public static function build_page_create( array $input ): string {
		return 'page create ' . sanitize_text_field( (string) $input['title'] );
	}

	public static function build_task_list( array $input ): string {
		$uid = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
		$cmd = 'task list';
		if ( $uid > 0 ) {
			$cmd .= ' --user_id=' . $uid;
		}
		return $cmd;
	}

	public static function build_task_create( array $input ): string {
		$cmd = 'task create ' . sanitize_text_field( (string) $input['name'] );
		if ( ! empty( $input['assignee_login'] ) ) {
			$cmd .= ' --assignee_login=' . self::sanitize_cli_arg( (string) $input['assignee_login'] );
		} elseif ( ! empty( $input['assignee_id'] ) ) {
			$cmd .= ' --assignee_id=' . (int) $input['assignee_id'];
		}
		return $cmd;
	}

	public static function build_task_delete( array $input ): string {
		$uid = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
		$cmd = 'task delete';
		if ( $uid > 0 ) {
			$cmd .= ' --user_id=' . $uid;
		}
		return $cmd;
	}

	public static function build_comment_list( array $input ): string {
		$status = isset( $input['status'] ) ? self::sanitize_cli_arg( (string) $input['status'] ) : 'pending';
		return 'comment list ' . $status;
	}

	public static function build_comment_approve( array $input ): string {
		return 'comment approve ' . (int) $input['id'];
	}

	public static function build_comment_spam( array $input ): string {
		return 'comment spam ' . (int) $input['id'];
	}

	public static function build_comment_delete( array $input ): string {
		return 'comment delete ' . (int) $input['id'] . ' --force';
	}

	public static function build_post_list( array $input ): string {
		$type = isset( $input['type'] ) ? self::sanitize_cli_arg( (string) $input['type'] ) : 'post';
		return 'post list ' . $type;
	}

	public static function build_post_count( array $input ): string {
		$type = isset( $input['type'] ) ? self::sanitize_cli_arg( (string) $input['type'] ) : '';
		return $type ? 'post count ' . $type : 'post count';
	}

	public static function build_post_status( array $input ): string {
		return 'post status ' . (int) $input['post_id'] . ' ' . self::sanitize_cli_arg( (string) $input['status'] );
	}

	public static function build_post_delete( array $input ): string {
		return 'post delete ' . (int) $input['post_id'] . ' --force';
	}

	public static function build_user_role( array $input ): string {
		return 'user role ' . self::sanitize_cli_arg( (string) $input['identifier'] ) . ' ' . self::sanitize_cli_arg( (string) $input['role'] );
	}

	public static function build_option_delete( array $input ): string {
		return 'option delete ' . self::sanitize_cli_arg( (string) $input['name'] );
	}

	public static function build_theme_delete( array $input ): string {
		return 'theme delete ' . self::sanitize_cli_arg( (string) $input['slug'] ) . ' --force';
	}

	public static function build_transient_delete( array $input ): string {
		return 'transient delete ' . self::sanitize_cli_arg( (string) $input['name'] );
	}

	public static function build_cron_run( array $input ): string {
		return 'cron run ' . self::sanitize_cli_arg( (string) $input['hook'] );
	}

	public static function build_media_list( array $input ): string {
		$count = isset( $input['count'] ) ? (int) $input['count'] : 20;
		return 'media list ' . $count;
	}

	public static function build_block_patterns_list( array $input ): string {
		if ( ! empty( $input['category'] ) ) {
			return 'block-patterns list ' . self::sanitize_cli_arg( (string) $input['category'] );
		}

		return 'block-patterns list';
	}

	public static function build_skill_get( array $input ): string {
		$cmd = 'skill get '
			. self::sanitize_cli_arg( (string) $input['plugin_slug'] ) . ' '
			. self::sanitize_cli_arg( (string) $input['skill_name'] );

		if ( ! empty( $input['file'] ) ) {
			$cmd .= ' ' . self::sanitize_cli_arg( (string) $input['file'] );
		}

		return $cmd;
	}
}

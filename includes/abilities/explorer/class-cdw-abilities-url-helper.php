<?php
/**
 * URL helper for CDW Abilities Explorer.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL utilities for the abilities explorer.
 *
 * @package CDW
 */
class CDW_Abilities_Url_Helper {

	/**
	 * Gets the current page name from query args.
	 *
	 * @return string
	 */
	public static function get_current_page(): string {
		return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Builds a back URL to the abilities list.
	 *
	 * @return string
	 */
	public static function build_back_url(): string {
		return add_query_arg(
			array(
				'page'   => self::get_current_page(),
				'action' => 'list',
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Builds a URL for viewing ability details.
	 *
	 * @param string $ability_name Ability name.
	 * @return string
	 */
	public static function build_view_url( string $ability_name ): string {
		return add_query_arg(
			array(
				'page'    => self::get_current_page(),
				'action'  => 'view',
				'ability' => rawurlencode( $ability_name ),
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Builds a URL for testing an ability.
	 *
	 * @param string $ability_name Ability name.
	 * @return string
	 */
	public static function build_test_url( string $ability_name ): string {
		return add_query_arg(
			array(
				'page'    => self::get_current_page(),
				'action'  => 'test',
				'ability' => rawurlencode( $ability_name ),
			),
			admin_url( 'tools.php' )
		);
	}
}

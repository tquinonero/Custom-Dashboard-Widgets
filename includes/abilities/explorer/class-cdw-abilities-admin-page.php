<?php
/**
 * Admin page router for CDW Abilities Explorer.
 *
 * Routes requests to appropriate renderer classes.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes requests to appropriate renderer classes.
 *
 * @package CDW
 */
class CDW_Abilities_Admin_Page {

	/**
	 * Renders the abilities explorer page.
	 */
	public static function render(): void {
		$action       = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$ability_name = isset( $_GET['ability'] ) ? sanitize_text_field( wp_unslash( $_GET['ability'] ) ) : '';

		if ( 'view' === $action && $ability_name ) {
			CDW_Abilities_Detail_Renderer::render( $ability_name );
			return;
		}

		if ( 'test' === $action && $ability_name ) {
			CDW_Abilities_Test_Renderer::render( $ability_name );
			return;
		}

		CDW_Abilities_List_Renderer::render();
	}
}

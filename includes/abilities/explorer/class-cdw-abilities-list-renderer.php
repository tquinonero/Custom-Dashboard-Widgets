<?php
/**
 * List renderer for CDW Abilities Explorer.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the abilities list view.
 *
 * @package CDW
 */
class CDW_Abilities_List_Renderer {

	/**
	 * Renders the list view.
	 *
	 * @return void
	 */
	public static function render(): void {
		$abilities = CDW_Ability_Handler::get_abilities();
		$formatted = array_map(
			function ( $ability ) {
				return CDW_Ability_Handler::format_for_table( $ability );
			},
			$abilities
		);

		$table = new CDW_Abilities_Table( $formatted );
		$table->prepare_items();

		self::render_header( $abilities );
		self::render_table( $table );
	}

	/**
	 * Renders the page header with stats.
	 *
	 * @param array $abilities Abilities list.
	 * @return void
	 */
	private static function render_header( array $abilities ): void {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'CDW Abilities Explorer', 'cdw' ); ?>
			</h1>

			<div class="cdw-abilities-stats">
				<p>
					<strong><?php esc_html_e( 'Total CDW Abilities:', 'cdw' ); ?></strong>
					<?php echo esc_html( (string) count( $abilities ) ); ?>
				</p>
				<p class="cdw-abilities-stats-desc">
					<?php esc_html_e( 'Browse and test all CDW admin tools registered via the WordPress Abilities API.', 'cdw' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the abilities table with search.
	 *
	 * @param CDW_Abilities_Table $table Table instance.
	 * @return void
	 */
	private static function render_table( CDW_Abilities_Table $table ): void {
		$page = CDW_Abilities_Url_Helper::get_current_page();
		?>
		<div class="wrap">
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
				<?php
				$table->search_box( __( 'Search Abilities', 'cdw' ), 'ability' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}

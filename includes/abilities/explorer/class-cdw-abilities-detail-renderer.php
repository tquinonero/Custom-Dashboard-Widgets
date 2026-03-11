<?php
/**
 * Detail renderer for CDW Abilities Explorer.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the ability detail view.
 *
 * @package CDW
 */
class CDW_Abilities_Detail_Renderer {

	/**
	 * Renders the detail view for a single ability.
	 *
	 * @param string $ability_name Ability name.
	 * @return void
	 */
	public static function render( string $ability_name ): void {
		$ability = CDW_Ability_Handler::get_ability( $ability_name );

		if ( ! $ability ) {
			CDW_Abilities_Error_Renderer::render( __( 'Ability not found.', 'cdw' ) );
			return;
		}

		$input_schema = CDW_Ability_Handler::format_input_schema( $ability['input_schema'] ?? null );
		$meta         = $ability['meta'] ?? array();

		self::render_header( $ability_name );
		self::render_metadata( $meta );
		self::render_input_schema( $input_schema );
		self::render_test_button( $ability_name );
	}

	/**
	 * Renders page header with back link.
	 *
	 * @param string $ability_name Ability name.
	 * @return void
	 */
	private static function render_header( string $ability_name ): void {
		?>
		<div class="wrap">
			<p>
				<a href="<?php echo esc_url( CDW_Abilities_Url_Helper::build_back_url() ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Abilities List', 'cdw' ); ?>
				</a>
			</p>

			<h1 class="wp-heading-inline">
				<code><?php echo esc_html( $ability_name ); ?></code>
			</h1>
		</div>
		<?php
	}

	/**
	 * Renders ability metadata table.
	 *
	 * @param array $meta Ability meta.
	 * @return void
	 */
	private static function render_metadata( array $meta ): void {
		?>
		<div class="wrap">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Label', 'cdw' ); ?></th>
						<td><?php echo esc_html( $meta['label'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Description', 'cdw' ); ?></th>
						<td><?php echo esc_html( $meta['description'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Read-only', 'cdw' ); ?></th>
						<td class="cdw-ability-readonly">
							<?php self::render_boolean( ! empty( $meta['readonly'] ) ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Destructive', 'cdw' ); ?></th>
						<td class="cdw-ability-destructive">
							<?php self::render_boolean( ! empty( $meta['annotations']['destructive'] ) ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders a boolean value with color coding.
	 *
	 * @param bool $value Boolean value.
	 * @return void
	 */
	private static function render_boolean( bool $value ): void {
		if ( $value ) {
			echo '<span class="cdw-boolean cdw-boolean-yes">' . esc_html__( 'Yes', 'cdw' ) . '</span>';
		} else {
			echo '<span class="cdw-boolean cdw-boolean-no">' . esc_html__( 'No', 'cdw' ) . '</span>';
		}
	}

	/**
	 * Renders input schema section.
	 *
	 * @param array $input_schema Formatted input schema.
	 * @return void
	 */
	private static function render_input_schema( array $input_schema ): void {
		if ( ! $input_schema['has_input'] ) {
			?>
			<div class="wrap">
				<p class="cdw-no-input"><?php esc_html_e( 'This ability does not accept any input.', 'cdw' ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Input Schema', 'cdw' ); ?></h2>
			<p>
				<button type="button" class="button button-secondary cdw-copy-schema" data-schema="input">
					<?php esc_html_e( 'Copy Schema', 'cdw' ); ?>
				</button>
			</p>
			<pre class="cdw-schema-code"><code id="cdw-input-schema"><?php echo esc_html( wp_json_encode( $input_schema['schema'], JSON_PRETTY_PRINT ) ); ?></code></pre>

			<h3><?php esc_html_e( 'Example Input', 'cdw' ); ?></h3>
			<pre class="cdw-schema-code"><code><?php echo esc_html( wp_json_encode( $input_schema['example'], JSON_PRETTY_PRINT ) ); ?></code></pre>
		</div>
		<?php
	}

	/**
	 * Renders the test button.
	 *
	 * @param string $ability_name Ability name.
	 * @return void
	 */
	private static function render_test_button( string $ability_name ): void {
		?>
		<div class="wrap">
			<p>
				<a href="<?php echo esc_url( CDW_Abilities_Url_Helper::build_test_url( $ability_name ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Test This Ability', 'cdw' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

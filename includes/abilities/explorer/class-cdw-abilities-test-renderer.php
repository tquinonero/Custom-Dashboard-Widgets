<?php
/**
 * Test renderer for CDW Abilities Explorer.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the ability test/invoke view.
 *
 * @package CDW
 */
class CDW_Abilities_Test_Renderer {

	/**
	 * Renders the test view for invoking an ability.
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
		$example_json = $input_schema['example'] ?? array();

		self::render_header( $ability_name );
		self::render_form( $ability_name, $example_json );
	}

	/**
	 * Renders page header with navigation.
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
				<a href="<?php echo esc_url( CDW_Abilities_Url_Helper::build_view_url( $ability_name ) ); ?>" class="button">
					<?php esc_html_e( 'View Details', 'cdw' ); ?>
				</a>
			</p>

			<h1 class="wp-heading-inline">
				<?php
				printf(
					esc_html__( 'Test Ability: %s', 'cdw' ),
					'<code>' . esc_html( $ability_name ) . '</code>'
				);
				?>
			</h1>
		</div>
		<?php
	}

	/**
	 * Renders the invocation form.
	 *
	 * @param string $ability_name Ability name.
	 * @param array  $example_json Example input JSON.
	 * @return void
	 */
	private static function render_form( string $ability_name, array $example_json ): void {
		?>
		<div class="wrap">
			<form id="cdw-ability-test-form" method="post">
				<?php wp_nonce_field( 'cdw_ability_explorer_invoke', 'cdw_explorer_nonce' ); ?>
				<input type="hidden" name="ability_name" value="<?php echo esc_attr( $ability_name ); ?>" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="cdw-ability-input"><?php esc_html_e( 'Input (JSON)', 'cdw' ); ?></label>
							</th>
							<td>
								<textarea
									id="cdw-ability-input"
									name="input"
									rows="10"
									class="large-text code"
									placeholder='{"key": "value"}'
								><?php echo esc_textarea( wp_json_encode( $example_json, JSON_PRETTY_PRINT ) ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Enter JSON input for the ability. Click "Generate Example" to pre-fill from the schema.', 'cdw' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="button" id="cdw-validate-input" class="button button-secondary">
						<?php esc_html_e( 'Validate Input', 'cdw' ); ?>
					</button>
					<button type="submit" id="cdw-invoke-ability" class="button button-primary">
						<?php esc_html_e( 'Invoke Ability', 'cdw' ); ?>
					</button>
				</p>

				<div id="cdw-validation-result" class="cdw-result-box" style="display: none;"></div>
				<div id="cdw-invoke-result" class="cdw-result-box" style="display: none;"></div>
			</form>
		</div>
		<?php
	}
}

<?php
/**
 * Admin page renderer for CDW Abilities Explorer.
 *
 * Handles rendering of list, detail, and test views.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page renderer class.
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

		switch ( $action ) {
			case 'view':
				if ( $ability_name ) {
					self::render_detail_view( $ability_name );
				} else {
					self::render_list_view();
				}
				break;
			case 'test':
				if ( $ability_name ) {
					self::render_test_view( $ability_name );
				} else {
					self::render_list_view();
				}
				break;
			default:
				self::render_list_view();
				break;
		}
	}

	/**
	 * Renders the list view (default).
	 */
	private static function render_list_view(): void {
		$abilities = CDW_Ability_Handler::get_abilities();
		$formatted = array_map(
			function ( $ability ) {
				return CDW_Ability_Handler::format_for_table( $ability );
			},
			$abilities
		);

		$table = new CDW_Abilities_Table( $formatted );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'CDW Abilities Explorer', 'cdw' ); ?>
			</h1>

			<div class="cdw-abilities-stats" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<p style="margin: 0;">
					<strong><?php esc_html_e( 'Total CDW Abilities:', 'cdw' ); ?></strong>
					<?php echo esc_html( (string) count( $abilities ) ); ?>
				</p>
				<p style="margin: 5px 0 0 0; font-size: 13px; color: #646970;">
					<?php esc_html_e( 'Browse and test all CDW admin tools registered via the WordPress Abilities API.', 'cdw' ); ?>
				</p>
			</div>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '' ); ?>" />
				<?php
				$table->search_box( __( 'Search Abilities', 'cdw' ), 'ability' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the detail view for a single ability.
	 *
	 * @param string $ability_name Ability name.
	 */
	private static function render_detail_view( string $ability_name ): void {
		$ability = CDW_Ability_Handler::get_ability( $ability_name );

		if ( ! $ability ) {
			self::render_error( __( 'Ability not found.', 'cdw' ) );
			return;
		}

		$input_schema = CDW_Ability_Handler::format_input_schema( $ability['input_schema'] ?? null );
		$meta         = $ability['meta'] ?? array();

		$back_url = add_query_arg(
			array(
				'page'   => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '',
				'action' => 'list',
			),
			admin_url( 'tools.php' )
		);
		?>
		<div class="wrap">
			<p>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Abilities List', 'cdw' ); ?>
				</a>
			</p>

			<h1 class="wp-heading-inline">
				<code><?php echo esc_html( $ability_name ); ?></code>
			</h1>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Label', 'cdw' ); ?></th>
						<td><?php echo esc_html( $ability['label'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Description', 'cdw' ); ?></th>
						<td><?php echo esc_html( $ability['description'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Read-only', 'cdw' ); ?></th>
						<td>
							<?php
							if ( ! empty( $meta['readonly'] ) ) {
								echo '<span style="color: #2e7d32;">' . esc_html__( 'Yes', 'cdw' ) . '</span>';
							} else {
								echo '<span style="color: #c62828;">' . esc_html__( 'No', 'cdw' ) . '</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Destructive', 'cdw' ); ?></th>
						<td>
							<?php
							if ( ! empty( $meta['annotations']['destructive'] ) ) {
								echo '<span style="color: #c62828;">' . esc_html__( 'Yes', 'cdw' ) . '</span>';
							} else {
								echo '<span style="color: #2e7d32;">' . esc_html__( 'No', 'cdw' ) . '</span>';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( $input_schema['has_input'] ) : ?>
				<h2><?php esc_html_e( 'Input Schema', 'cdw' ); ?></h2>
				<p>
					<button type="button" class="button button-secondary cdw-copy-schema" data-schema="input">
						<?php esc_html_e( 'Copy Schema', 'cdw' ); ?>
					</button>
				</p>
				<pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto; max-width: 100%;"><code id="cdw-input-schema"><?php echo esc_html( wp_json_encode( $input_schema['schema'], JSON_PRETTY_PRINT ) ); ?></code></pre>

				<h3><?php esc_html_e( 'Example Input', 'cdw' ); ?></h3>
				<pre style="background: #f6f7f7; padding: 15px; border-radius: 4px; overflow-x: auto; max-width: 100%;"><code><?php echo esc_html( wp_json_encode( $input_schema['example'], JSON_PRETTY_PRINT ) ); ?></code></pre>
			<?php else : ?>
				<p style="color: #646970;"><?php esc_html_e( 'This ability does not accept any input.', 'cdw' ); ?></p>
			<?php endif; ?>

			<p>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'action'  => 'test',
							'ability' => rawurlencode( $ability_name ),
						)
					)
				);
				?>
							" class="button button-primary">
					<?php esc_html_e( 'Test This Ability', 'cdw' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the test view for invoking an ability.
	 *
	 * @param string $ability_name Ability name.
	 */
	private static function render_test_view( string $ability_name ): void {
		$ability = CDW_Ability_Handler::get_ability( $ability_name );

		if ( ! $ability ) {
			self::render_error( __( 'Ability not found.', 'cdw' ) );
			return;
		}

		$input_schema = CDW_Ability_Handler::format_input_schema( $ability['input_schema'] ?? null );
		$example_json = $input_schema['example'] ?? array();

		$back_url = add_query_arg(
			array(
				'page'   => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '',
				'action' => 'list',
			),
			admin_url( 'tools.php' )
		);
		?>
		<div class="wrap">
			<p>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Abilities List', 'cdw' ); ?>
				</a>
				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'action'  => 'view',
							'ability' => rawurlencode( $ability_name ),
						)
					)
				);
				?>
							" class="button">
					<?php esc_html_e( 'View Details', 'cdw' ); ?>
				</a>
			</p>

			<h1 class="wp-heading-inline">
				<?php
				printf(
					/* translators: %s: ability name */
					esc_html__( 'Test Ability: %s', 'cdw' ),
					'<code>' . esc_html( $ability_name ) . '</code>'
				);
				?>
			</h1>

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

				<div id="cdw-validation-result" style="display: none; margin: 20px 0; padding: 15px; border-radius: 4px;"></div>
				<div id="cdw-invoke-result" style="display: none; margin: 20px 0;"></div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $form = $('#cdw-ability-test-form');
			var $input = $('#cdw-ability-input');
			var $validateBtn = $('#cdw-validate-input');
			var $invokeBtn = $('#cdw-invoke-ability');
			var $validationResult = $('#cdw-validation-result');
			var $invokeResult = $('#cdw-invoke-result');

			$validateBtn.on('click', function() {
				var input = $input.val();
				try {
					if (input.trim()) {
						JSON.parse(input);
					}
					$validationResult
						.show()
						.css('background', '#d4edda')
						.css('border', '1px solid #c3e6cb')
						.css('color', '#155724')
						.html('<strong>✓</strong> <?php echo esc_js( __( 'Valid JSON', 'cdw' ) ); ?>');
				} catch (e) {
					$validationResult
						.show()
						.css('background', '#f8d7da')
						.css('border', '1px solid #f5c6cb')
						.css('color', '#721c24')
						.html('<strong>✗</strong> <?php echo esc_js( __( 'Invalid JSON:', 'cdw' ) ); ?> ' + e.message);
				}
			});

			$form.on('submit', function(e) {
				e.preventDefault();

				$invokeBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Invoking...', 'cdw' ) ); ?>');
				$invokeResult.hide();

				var formData = {
					action: 'cdw_ability_explorer_invoke',
					nonce: $('#cdw_explorer_nonce').val(),
					ability_name: $('input[name="ability_name"]').val(),
					input: $input.val()
				};

				$.post(ajaxurl, formData, function(response) {
					if (response.success) {
						$invokeResult
							.show()
							.css('background', '#d4edda')
							.css('border', '1px solid #c3e6cb')
							.css('padding', '15px')
							.html('<h3 style="margin-top:0;color:#155724;"><?php echo esc_js( __( 'Result:', 'cdw' ) ); ?></h3><pre style="background:#fff;padding:10px;overflow-x:auto;max-width:100%;">' + cdwEscapeHtml(JSON.stringify(response.data, null, 2)) + '</pre>');
					} else {
						var errorMessage = (response.data && (response.data.message || response.data.error))
							? (response.data.message || response.data.error)
							: 'Unknown error';
						$invokeResult
							.show()
							.css('background', '#f8d7da')
							.css('border', '1px solid #f5c6cb')
							.css('padding', '15px')
							.html('<h3 style="margin-top:0;color:#721c24;"><?php echo esc_js( __( 'Error:', 'cdw' ) ); ?></h3><p style="color:#721c24;">' + cdwEscapeHtml(errorMessage) + '</p>');
					}
				}).always(function() {
					$invokeBtn.prop('disabled', false).text('<?php echo esc_js( __( 'Invoke Ability', 'cdw' ) ); ?>');
				});
			});

			function cdwEscapeHtml(text) {
				if (typeof text !== 'string') return text;
				return text
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#039;');
			}
		});
		</script>
		<?php
	}

	/**
	 * Renders an error message.
	 *
	 * @param string $message Error message.
	 */
	private static function render_error( string $message ): void {
		?>
		<div class="wrap">
			<div class="notice notice-error">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<p>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '' ), admin_url( 'tools.php' ) ) ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Abilities List', 'cdw' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

<?php
/**
 * Error renderer for CDW Abilities Explorer.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders error messages.
 *
 * @package CDW
 */
class CDW_Abilities_Error_Renderer {

	/**
	 * Renders an error message with back link.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	public static function render( string $message ): void {
		?>
		<div class="wrap">
			<div class="notice notice-error">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<p>
				<a href="<?php echo esc_url( CDW_Abilities_Url_Helper::build_back_url() ); ?>" class="button">
					&larr; <?php esc_html_e( 'Back to Abilities List', 'cdw' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

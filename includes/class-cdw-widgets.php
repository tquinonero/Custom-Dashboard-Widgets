<?php
/**
 * Dashboard widget registration and renderer.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers CDW dashboard widgets and the plugin settings page menu item.
 *
 * @package CDW
 */
class CDW_Widgets {
	/**
	 * Hooks widget setup and settings-page registration into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'manage_dashboard_widgets' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
	}

	/**
	 * Removes default WordPress dashboard widgets (if configured) and adds the
	 * CDW widgets appropriate to the current user’s capabilities.
	 *
	 * Hooked to wp_dashboard_setup.
	 *
	 * @return void
	 */
	public function manage_dashboard_widgets() {
		$remove_defaults = get_option( 'cdw_remove_default_widgets', true );

		if ( $remove_defaults ) {
			remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		}

		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget( 'cdw_help', __( 'Help & Support', 'cdw' ), array( $this, 'render_help_widget' ) );
			wp_add_dashboard_widget( 'cdw_stats', __( 'Site Statistics', 'cdw' ), array( $this, 'render_stats_widget' ) );
			wp_add_dashboard_widget( 'cdw_media', __( 'Latest Media', 'cdw' ), array( $this, 'render_media_widget' ) );
			wp_add_dashboard_widget( 'cdw_posts', __( 'Latest Posts', 'cdw' ), array( $this, 'render_posts_widget' ) );
		}

		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'cdw_tasks', __( 'Pending Tasks', 'cdw' ), array( $this, 'render_tasks_widget' ) );
			wp_add_dashboard_widget( 'cdw_updates', __( 'Updates', 'cdw' ), array( $this, 'render_updates_widget' ) );
			wp_add_dashboard_widget( 'cdw_quicklinks', __( 'Quick Links', 'cdw' ), array( $this, 'render_quicklinks_widget' ) );

			$cli_enabled = get_option( 'cdw_cli_enabled', true );
			if ( $cli_enabled ) {
				wp_add_dashboard_widget( 'cdw_command', __( 'Command Line', 'cdw' ), array( $this, 'render_command_widget' ) );
			}
		}
	}

	/**
	 * Renders the Help & Support widget HTML container.
	 *
	 * @return void
	 */
	public function render_help_widget() {
		$email    = get_option( 'cdw_support_email', get_option( 'custom_dashboard_widget_email', '' ) );
		$docs_url = get_option( 'cdw_docs_url', get_option( 'custom_dashboard_widget_docs_url', '' ) );

		echo '<div class="cdw-widget" data-widget="help">';

		if ( ! empty( $email ) ) {
			echo '<p>' . esc_html__( 'Need help? Contact our support team at', 'cdw' ) . ' <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>.</p>';
		}

		if ( ! empty( $docs_url ) ) {
			echo '<p>' . esc_html__( 'Visit our', 'cdw' ) . ' <a href="' . esc_url( $docs_url ) . '">' . esc_html__( 'documentation', 'cdw' ) . '</a> ' . esc_html__( 'for more information.', 'cdw' ) . '</p>';
		}

		if ( empty( $email ) && empty( $docs_url ) ) {
			echo '<p>' . esc_html__( 'No support information configured.', 'cdw' ) . ' <a href="' . esc_url( get_admin_url( null, 'options-general.php?page=cdw-settings' ) ) . '">' . esc_html__( 'Configure settings', 'cdw' ) . '</a></p>';
		} else {
			echo '<p><a href="' . esc_url( get_admin_url( null, 'options-general.php?page=cdw-settings' ) ) . '" class="button">' . esc_html__( 'Edit Widget Settings', 'cdw' ) . '</a></p>';
		}

		echo '</div>';
	}

	/**
	 * Renders the Site Statistics widget HTML container.
	 *
	 * @return void
	 */
	public function render_stats_widget() {
		echo '<div class="cdw-widget" data-widget="stats"></div>';
	}

	/**
	 * Renders the Latest Media widget HTML container.
	 *
	 * @return void
	 */
	public function render_media_widget() {
		echo '<div class="cdw-widget" data-widget="media"></div>';
	}

	/**
	 * Renders the Latest Posts widget HTML container.
	 *
	 * @return void
	 */
	public function render_posts_widget() {
		echo '<div class="cdw-widget" data-widget="posts"></div>';
	}

	/**
	 * Renders the Pending Tasks widget HTML container.
	 *
	 * @return void
	 */
	public function render_tasks_widget() {
		echo '<div class="cdw-widget" data-widget="tasks"></div>';
	}

	/**
	 * Renders the Updates widget HTML container.
	 *
	 * @return void
	 */
	public function render_updates_widget() {
		echo '<div class="cdw-widget" data-widget="updates"></div>';
	}

	/**
	 * Renders the Quick Links widget HTML container.
	 *
	 * @return void
	 */
	public function render_quicklinks_widget() {
		echo '<div class="cdw-widget" data-widget="quicklinks"></div>';
	}

	/**
	 * Renders the Command Line widget HTML container.
	 *
	 * @return void
	 */
	public function render_command_widget() {
		echo '<div class="cdw-widget" data-widget="command"></div>';
	}

	/**
	 * Registers the CDW settings page under Settings in the WordPress admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'CDW Settings', 'cdw' ),
			__( 'Dashboard Widgets', 'cdw' ),
			'manage_options',
			'cdw-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Outputs the settings page wrapper; React mounts on #cdw-settings-root.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Custom Dashboard Widgets Settings', 'cdw' ); ?></h1>
			<div id="cdw-settings-root" data-loading="true">
				<p><?php esc_html_e( 'Loading settings...', 'cdw' ); ?></p>
			</div>
		</div>
		<?php
	}
}

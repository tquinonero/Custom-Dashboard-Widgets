<?php
/**
 * Plugin loader class.
 *
 * Wires together the REST API, admin widgets, asset enqueueing
 * and cache-invalidation hooks.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/class-cdw-rest-api.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-widgets.php';
require_once CDW_PLUGIN_DIR . 'includes/class-cdw-welcome-page.php';
require_once CDW_PLUGIN_DIR . 'includes/cli/class-cdw-cli-command.php';

/**
 * Boots REST API, admin widgets, enqueue hook and cache-clear hooks.
 *
 * @package CDW
 */
class CDW_Loader {
	/**
	 * REST API handler.
	 *
	 * @var CDW_REST_API
	 */
	private $rest_api;

	/**
	 * Dashboard widgets handler.
	 *
	 * @var CDW_Widgets
	 */
	private $widgets;

	/**
	 * Registers all REST routes, admin widgets and action hooks.
	 *
	 * @return void
	 */
	public function run() {
		// REST API routes must be registered unconditionally: REST_REQUEST is
		// not yet defined at plugins_loaded (it is set later at parse_request),
		// so any runtime check here would incorrectly skip registration.
		$this->rest_api = new CDW_REST_API();
		$this->rest_api->register();

		// Abilities API — unconditional, bails silently on WP < 6.9.
		require_once CDW_PLUGIN_DIR . 'includes/class-cdw-abilities.php';
		CDW_Abilities::register();

		// Abilities Explorer admin page.
		require_once CDW_PLUGIN_DIR . 'includes/abilities/explorer/class-cdw-ability-handler.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/explorer/class-cdw-abilities-table.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/explorer/class-cdw-abilities-admin-page.php';
		require_once CDW_PLUGIN_DIR . 'includes/abilities/explorer/class-cdw-abilities-explorer.php';
		CDW_Abilities_Explorer::init();

		if ( is_admin() ) {
			$this->widgets = new CDW_Widgets();
			$this->widgets->register();

			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_post_cdw_set_user_type', 'cdw_handle_set_user_type' );
			add_action( 'admin_post_cdw_reset_user_type', 'cdw_handle_reset_user_type' );
			add_action( 'admin_notices', array( $this, 'show_welcome_notice' ) );
			add_action( 'wp_ajax_cdw_dismiss_welcome_notice', array( $this, 'dismiss_welcome_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_floating_button' ), 20 );
			add_filter(
				'admin_body_class',
				function ( $classes ) {
					return $classes . ' cdw-sidebar-hidden';
				}
			);
		}

		// Cache hooks must fire on all contexts (REST API saves, CLI, admin).
		add_action( 'save_post', array( $this, 'clear_content_cache' ) );
		add_action( 'delete_post', array( $this, 'clear_content_cache' ) );
		add_action( 'add_attachment', array( $this, 'clear_content_cache' ) );
		add_action( 'edit_attachment', array( $this, 'clear_content_cache' ) );

		// Clear admin menu cache when plugins or themes change.
		add_action( 'activated_plugin', array( $this, 'clear_menu_cache' ) );
		add_action( 'deactivated_plugin', array( $this, 'clear_menu_cache' ) );
		add_action( 'switch_theme', array( $this, 'clear_menu_cache' ) );
	}

	/**
	 * Deletes all CDW transients when content is saved or deleted.
	 *
	 * Hooked to save_post, delete_post, add_attachment, edit_attachment.
	 *
	 * @return void
	 */
	public function clear_content_cache() {
		delete_transient( 'cdw_stats_cache' );
		delete_transient( 'cdw_admin_menu_cache' );
		global $wpdb;

		// Delete all CDW transients in a single query using REGEXP
		// Matches: _transient_cdw_posts_cache_*, _transient_cdw_media_cache_*,
		// _transient_timeout_cdw_posts_cache_*, _transient_timeout_cdw_media_cache_*.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- pattern is a hardcoded literal, no user input
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name REGEXP '^_transient(_timeout)?_cdw_(posts|media)_cache_'"
		);
	}

	/**
	 * Clears the admin menu cache.
	 *
	 * Hooked to activated_plugin, deactivated_plugin, switch_theme.
	 *
	 * @return void
	 */
	public function clear_menu_cache() {
		delete_transient( 'cdw_admin_menu_cache' );
	}

	/**
	 * Enqueues the compiled React bundle and optional inline CSS.
	 *
	 * Hooked to admin_enqueue_scripts. Runs on all admin pages when the floating
	 * widget is enabled, or on index.php and settings pages for dashboard widgets.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$cli_enabled      = get_option( 'cdw_cli_enabled', true );
		$floating_enabled = get_option( 'cdw_floating_enabled', true );
		$user_can_cli     = current_user_can( 'manage_options' );
		$show_floating    = $cli_enabled && $floating_enabled && $user_can_cli;

		$is_widget_page = in_array( $hook_suffix, array( 'index.php', 'settings_page_cdw-settings', 'tools_page_cdw-welcome' ), true );

		if ( ! $show_floating && ! $is_widget_page ) {
			return;
		}

		$asset_file = CDW_PLUGIN_DIR . 'build/index.asset.php';
		$js_file    = CDW_PLUGIN_DIR . 'build/index.js';

		if ( ! file_exists( $asset_file ) || ! file_exists( $js_file ) ) {
			if ( $show_floating ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-warning">';
						echo '<p><strong>CDW Plugin:</strong> Build files not found. Please run <code>npm install && npm run build</code>.</p>';
						echo '</div>';
					}
				);
			}
			return;
		}

		$asset        = require $asset_file;
		$dependencies = array_merge( $asset['dependencies'], array( 'wp-api-fetch' ) );
		wp_enqueue_script(
			'cdw-script',
			CDW_PLUGIN_URL . 'build/index.js',
			$dependencies,
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'cdw-style',
			CDW_PLUGIN_URL . 'build/index.css',
			array(),
			$asset['version']
		);

		$font_size         = get_option( 'cdw_font_size', '' );
		$bg_color          = get_option( 'cdw_bg_color', '' );
		$header_bg_color   = get_option( 'cdw_header_bg_color', '' );
		$header_text_color = get_option( 'cdw_header_text_color', '' );

		$css = '';
		if ( is_numeric( $font_size ) && (int) $font_size > 0 ) {
			$css .= '.cdw-widget { font-size: ' . (int) $font_size . 'px; }' . "\n";
		}
		if ( ! empty( $bg_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $bg_color ) ) {
			$css .= '.cdw-widget { background-color: ' . esc_attr( $bg_color ) . '; }' . "\n";
		}
		if ( ! empty( $header_bg_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $header_bg_color ) ) {
			$css .= '.cdw-widget .cdw-widget-header, .postbox .hndle { background: ' . esc_attr( $header_bg_color ) . ' !important; background-image: none !important; }' . "\n";
		}
		if ( ! empty( $header_text_color ) && preg_match( '/^#[0-9a-fA-F]{3,6}$/', $header_text_color ) ) {
			$css .= '.cdw-widget .cdw-widget-header, .postbox .hndle { color: ' . esc_attr( $header_text_color ) . ' !important; }' . "\n";
		}
		if ( ! empty( $css ) ) {
			wp_add_inline_style( 'cdw-style', $css );
		}

		$is_settings_page = 'settings_page_cdw-settings' === $hook_suffix;
		$is_dashboard     = 'index.php' === $hook_suffix;
		$admin_menu_data  = $this->get_admin_menu_data();
		$admin_tools_data = $this->get_admin_tools_data();
		$cli_enabled      = get_option( 'cdw_cli_enabled', true );
		$floating_enabled = get_option( 'cdw_floating_enabled', true );
		$user_can_cli     = current_user_can( 'manage_options' );

		wp_localize_script(
			'cdw-script',
			'cdwData',
			array(
				'root'              => esc_url_raw( rest_url() ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'pluginUrl'         => CDW_PLUGIN_URL,
				'adminUrl'          => admin_url(),
				'isSettings'        => $is_settings_page,
				'isDashboard'       => $is_dashboard,
				'adminMenuData'     => $admin_menu_data,
				'adminToolsData'    => $admin_tools_data,
				'floatingEnabled'   => $cli_enabled && $floating_enabled && $user_can_cli,
			)
		);
	}

	/**
	 * Enqueues floating dashboard button on all admin pages except dashboard.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_floating_button( $hook_suffix ) {
		if ( 'index.php' === $hook_suffix ) {
			return;
		}

		$admin_url = admin_url( 'index.php' );

		$button_css = '
			.cdw-floating-btn {
				position: fixed;
				bottom: 32px;
				right: 32px;
				z-index: 9990;
				background: #0073aa;
				color: #fff;
				border-radius: 50%;
				width: 56px;
				height: 56px;
				display: flex;
				align-items: center;
				justify-content: center;
				box-shadow: 0 2px 8px rgba(0,0,0,0.3);
				text-decoration: none;
				transition: transform 0.2s ease, box-shadow 0.2s ease;
			}
			.cdw-floating-btn:hover {
				transform: scale(1.1);
				box-shadow: 0 4px 12px rgba(0,0,0,0.4);
			}
			.cdw-floating-btn svg {
				width: 24px;
				height: 24px;
			}
		';

		wp_add_inline_style( 'wp-admin', $button_css );

		$button_html = sprintf(
			'<a href="%s" class="cdw-floating-btn" title="Back to Dashboard">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
					<polyline points="9 22 9 12 15 12 15 22"></polyline>
				</svg>
			</a>',
			esc_url( $admin_url )
		);

		add_action(
			'admin_footer',
			function () use ( $button_html ) {
				echo wp_kses_post( $button_html );
			}
		);
	}

	/**
	 * Build all admin menu categories (raw, unfiltered).
	 *
	 * @return array<string, array{label: string, items: array<int, array{label: string, href: string}>, icon: string}>
	 */
	private function build_all_menu_categories(): array {
		global $menu, $submenu;

		$categories = array(
			'content'    => array(
				'label' => 'Content',
				'items' => array(),
				'icon'  => 'dashicons-admin-post',
			),
			'appearance' => array(
				'label' => 'Appearance',
				'items' => array(),
				'icon'  => 'dashicons-admin-appearance',
			),
			'plugins'    => array(
				'label' => 'Plugins',
				'items' => array(),
				'icon'  => 'dashicons-admin-plugins',
			),
			'users'      => array(
				'label' => 'Users',
				'items' => array(),
				'icon'  => 'dashicons-admin-users',
			),
			'settings'   => array(
				'label' => 'Settings',
				'items' => array(),
				'icon'  => 'dashicons-admin-settings',
			),
			'tools'      => array(
				'label' => 'Tools',
				'items' => array(),
				'icon'  => 'dashicons-admin-tools',
			),
			'other'      => array(
				'label' => 'Other',
				'items' => array(),
				'icon'  => 'dashicons-admin-generic',
			),
		);

		$category_map = array(
			'edit.php'                => 'content',
			'upload.php'              => 'content',
			'edit.php?post_type=page' => 'content',
			'themes.php'              => 'appearance',
			'widgets.php'             => 'appearance',
			'nav-menus.php'           => 'appearance',
			'customize.php'           => 'appearance',
			'site-editor.php'         => 'appearance',
			'plugins.php'             => 'plugins',
			'plugin-install.php'      => 'plugins',
			'users.php'               => 'users',
			'user-new.php'            => 'users',
			'profile.php'             => 'users',
			'options-general.php'     => 'settings',
			'tools.php'               => 'tools',
			'import.php'              => 'tools',
			'export.php'              => 'tools',
			'site-health.php'         => 'tools',
		);

		if ( ! empty( $submenu ) ) {
			foreach ( $submenu as $parent_file => $items ) {
				$category = isset( $category_map[ $parent_file ] ) ? $category_map[ $parent_file ] : 'other';

				foreach ( $items as $item ) {
					if ( empty( $item[0] ) || empty( $item[1] ) ) {
						continue;
					}

					$capability = $item[1];

					if ( ! current_user_can( $capability ) ) {
						continue;
					}

					$submenu_href = $item[2];
					if ( strpos( $submenu_href, 'http' ) === 0 || strpos( $submenu_href, '//' ) === 0 ) {
						$full_submenu_href = $submenu_href;
					} elseif ( strpos( $submenu_href, '.php' ) !== false || strpos( $submenu_href, '?' ) !== false ) {
						$full_submenu_href = admin_url( $submenu_href );
					} else {
						$full_submenu_href = admin_url( 'admin.php?page=' . $submenu_href );
					}

					$categories[ $category ]['items'][] = array(
						'label' => wp_strip_all_tags( $item[0] ),
						'href'  => $full_submenu_href,
					);
				}
			}
		}

		if ( ! empty( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				if ( empty( $menu_item[0] ) || empty( $menu_item[1] ) ) {
					continue;
				}

				if ( ' ' === $menu_item[0] ) {
					continue;
				}

				$capability = $menu_item[1];
				if ( ! current_user_can( $capability ) ) {
					continue;
				}

				$href = $menu_item[2];
				if ( empty( $href ) || 'index.php' === $href ) {
					continue;
				}

				if ( ! empty( $submenu ) && isset( $submenu[ $href ] ) ) {
					continue;
				}

				$full_href = '';
				if ( strpos( $href, 'http' ) === 0 || strpos( $href, '//' ) === 0 ) {
					$full_href = $href;
				} elseif ( strpos( $href, '.php' ) !== false || strpos( $href, '?' ) !== false ) {
					$full_href = admin_url( $href );
				} else {
					$full_href = admin_url( 'admin.php?page=' . $href );
				}

				$categories['other']['items'][] = array(
					'label' => wp_strip_all_tags( $menu_item[0] ),
					'href'  => $full_href,
				);
			}
		}

		return $categories;
	}

	/**
	 * Get admin menu data for the Quick Links widget (excludes Tools and Other).
	 *
	 * @return array<int, array{label: string, items: array<int, array{label: string, href: string}>, icon: string}>
	 */
	private function get_admin_menu_data(): array {
		$cached = get_transient( 'cdw_admin_menu_cache' );
		if ( is_array( $cached ) ) {
			$categories = $cached;
		} else {
			$categories = $this->build_all_menu_categories();
			set_transient( 'cdw_admin_menu_cache', $categories, HOUR_IN_SECONDS );
		}

		$excluded   = array( 'tools', 'other' );
		$categories = array_filter(
			$categories,
			function ( $cat, $key ) use ( $excluded ) {
				return ! empty( $cat['items'] ) && ! in_array( $key, $excluded, true );
			},
			ARRAY_FILTER_USE_BOTH
		);

		return array_values( $categories );
	}

	/**
	 * Get admin menu data for the Tools & Other widget (tools and other categories only).
	 *
	 * @return array<int, array{label: string, items: array<int, array{label: string, href: string}>, icon: string}>
	 */
	private function get_admin_tools_data(): array {
		$cached = get_transient( 'cdw_admin_menu_cache' );
		if ( is_array( $cached ) ) {
			$categories = $cached;
		} else {
			$categories = $this->build_all_menu_categories();
			set_transient( 'cdw_admin_menu_cache', $categories, HOUR_IN_SECONDS );
		}

		$included   = array( 'tools', 'other' );
		$categories = array_filter(
			$categories,
			function ( $cat, $key ) use ( $included ) {
				return ! empty( $cat['items'] ) && in_array( $key, $included, true );
			},
			ARRAY_FILTER_USE_BOTH
		);

		return array_values( $categories );
	}

	/**
	 * Registers the CDW welcome page.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_management_page(
			__( 'Welcome to CDW', 'cdw' ),
			__( 'CDW Welcome', 'cdw' ),
			'manage_options',
			'cdw-welcome',
			'cdw_render_welcome_page'
		);
	}

	/**
	 * Shows the welcome admin notice if user hasn't made a choice yet.
	 *
	 * @return void
	 */
	public function show_welcome_notice() {
		$user_type = get_option( 'cdw_user_type', null );
		$dismissed = get_option( 'cdw_welcome_notice_dismissed', false );

		if ( null !== $user_type || $dismissed ) {
			return;
		}

		$welcome_url = admin_url( 'tools.php?page=cdw-welcome' );
		?>
		<div class="notice notice-info is-dismissible cdw-welcome-notice" id="cdw-welcome-notice">
			<div style="display: flex; align-items: center; gap: 16px; padding: 12px 0;">
				<div style="font-size: 32px;">&#127919;</div>
				<div style="flex: 1;">
					<p style="margin: 0 0 4px 0; font-weight: 600; font-size: 14px;">
						<?php esc_html_e( 'Welcome to Custom Dashboard Widgets!', 'cdw' ); ?>
					</p>
					<p style="margin: 0; font-size: 13px; color: #646970;">
						<?php esc_html_e( 'Get started with widgets, CLI commands, and AI assistance for your WordPress site.', 'cdw' ); ?>
					</p>
				</div>
				<div>
					<a href="<?php echo esc_url( $welcome_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Get Started', 'cdw' ); ?>
					</a>
				</div>
			</div>
		</div>
		<script>
		jQuery(document).on('click', '#cdw-welcome-notice .notice-dismiss', function() {
			jQuery.post(ajaxurl, { action: 'cdw_dismiss_welcome_notice' });
		});
		</script>
		<?php
	}

	/**
	 * Dismisses the welcome notice.
	 *
	 * @return void
	 */
	public function dismiss_welcome_notice() {
		update_option( 'cdw_welcome_notice_dismissed', true, false );
		wp_die();
	}
}

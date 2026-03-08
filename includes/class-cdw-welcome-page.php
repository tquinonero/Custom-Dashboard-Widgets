<?php
/**
 * CDW Welcome Page
 *
 * Renders the welcome/onboarding screens.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the welcome page based on user type and step.
 */
function cdw_render_welcome_page() {
	$user_type = get_option( 'cdw_user_type', null );
	$step      = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 0;

	if ( null === $user_type ) {
		cdw_render_welcome_choice();
	} elseif ( 'developer' === $user_type ) {
		cdw_render_developer_message();
	} else {
		cdw_render_onboarding_steps( $step );
	}
}

/**
 * Renders the initial choice: developer or user.
 */
function cdw_render_welcome_choice() {
	$action_url = admin_url( 'admin-post.php' );
	?>
	<div class="cdw-welcome-container">
		<div class="cdw-welcome-card">
			<div class="cdw-welcome-icon">&#127919;</div>
			<h1>Welcome to Custom Dashboard Widgets!</h1>
			<p class="cdw-welcome-desc">This plugin gives you a powerful dashboard with widgets, CLI commands, and AI assistance.</p>
			<p class="cdw-welcome-question">Are you a WordPress developer?</p>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php wp_nonce_field( 'cdw_set_user_type', 'cdw_nonce' ); ?>
				<input type="hidden" name="action" value="cdw_set_user_type">
				<div class="cdw-welcome-buttons">
					<button type="submit" name="user_type" value="developer" class="button button-primary cdw-btn-developer">Yes, I'm a developer</button>
					<button type="submit" name="user_type" value="user" class="button cdw-btn-user">No, help me get started</button>
				</div>
			</form>
		</div>
	</div>
	<style>.cdw-welcome-container{display:flex;justify-content:center;align-items:center;min-height:60vh;padding:20px}.cdw-welcome-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:40px;max-width:600px;width:100%;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1)}.cdw-welcome-icon{font-size:64px;margin-bottom:20px}.cdw-welcome-card h1{font-size:28px;margin-bottom:16px;color:#1d2327}.cdw-welcome-desc{font-size:16px;color:#50575e;margin-bottom:24px}.cdw-welcome-question{font-size:18px;font-weight:600;margin-bottom:24px;color:#1d2327}.cdw-welcome-buttons{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}.cdw-welcome-buttons .button{padding:12px 24px;font-size:16px}.cdw-btn-developer{background:#2271b1;border-color:#2271b1}.cdw-btn-user{background:#f0f6fc;border-color:#c0c5c9;color:#1d2327}</style>
	<?php
}

/**
 * Renders the developer welcome message.
 */
function cdw_render_developer_message() {
	$nonce_url = wp_nonce_url( admin_url( 'admin-post.php?action=cdw_reset_user_type' ), 'cdw_reset_user_type' );
	?>
	<div class="cdw-welcome-container">
		<div class="cdw-welcome-card cdw-developer-card">
			<div class="cdw-welcome-icon">&#127912;</div>
			<h1>Happy WordPressing!</h1>
			<p class="cdw-welcome-desc">Code is poetry, and you're the poet! The plugin is ready for you to use.</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" class="button button-primary">Go to Dashboard</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cdw-settings' ) ); ?>" class="button">Settings</a>
			</p>
			<p class="cdw-welcome-note">Not a developer? <a href="<?php echo esc_url( $nonce_url ); ?>">Change my choice</a></p>
		</div>
	</div>
	<style>.cdw-welcome-container{display:flex;justify-content:center;align-items:center;min-height:60vh;padding:20px}.cdw-welcome-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:40px;max-width:600px;width:100%;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1)}.cdw-developer-card{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none}.cdw-developer-card .cdw-welcome-note{color:rgba(255,255,255,0.8)}.cdw-developer-card .cdw-welcome-note a{color:#fff;text-decoration:underline}.cdw-welcome-icon{font-size:64px;margin-bottom:20px}.cdw-welcome-card h1{font-size:28px;margin-bottom:16px}.cdw-welcome-desc{font-size:16px;margin-bottom:24px}.cdw-welcome-note{margin-top:24px;font-size:14px}</style>
	<?php
}

/**
 * Renders the onboarding steps for regular users.
 *
 * @param int $step The current step (0-4).
 */
function cdw_render_onboarding_steps( $step ) {
	$steps = cdw_get_onboarding_steps();
	$step  = max( 0, min( $step, count( $steps ) - 1 ) );
	$current = $steps[ $step ];
	?>
	<div class="cdw-onboarding-container">
		<div class="cdw-onboarding-header">
			<div class="cdw-onboarding-progress">
				<?php
				foreach ( $steps as $i => $s ) {
					$active      = ( $i === $step ) ? 'active' : '';
					$completed   = ( $i < $step ) ? 'completed' : '';
					$step_url    = admin_url( 'tools.php?page=cdw-welcome&step=' . $i );
					echo '<div class="cdw-progress-step ' . esc_attr( $active ) . ' ' . esc_attr( $completed ) . '">';
					echo '<span class="cdw-progress-number">' . ( $i + 1 ) . '</span>';
					echo '<span class="cdw-progress-label">' . esc_html( $s['title'] ) . '</span>';
					echo '</div>';
				}
				?>
			</div>
		</div>
		<div class="cdw-onboarding-card">
			<div class="cdw-onboarding-icon"><?php echo $current['icon']; ?></div>
			<h1><?php echo esc_html( $current['title'] ); ?></h1>
			<div class="cdw-onboarding-content">
				<?php echo $current['content']; ?>
			</div>
			<div class="cdw-onboarding-actions">
				<?php
				if ( $step > 0 ) {
					echo '<a href="' . esc_url( admin_url( 'tools.php?page=cdw-welcome&step=' . ( $step - 1 ) ) ) . '" class="button">Previous</a>';
				}
				if ( $step < count( $steps ) - 1 ) {
					echo '<a href="' . esc_url( admin_url( 'tools.php?page=cdw-welcome&step=' . ( $step + 1 ) ) ) . '" class="button button-primary">Next</a>';
				} else {
					echo '<a href="' . esc_url( admin_url( 'index.php' ) ) . '" class="button button-primary">Go to Dashboard</a>';
				}
				?>
				<span class="cdw-onboarding-skip">or <a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>">skip the rest</a></span>
			</div>
		</div>
	</div>
	<style>.cdw-onboarding-container{padding:40px 20px;max-width:900px;margin:0 auto}.cdw-onboarding-header{margin-bottom:30px}.cdw-onboarding-progress{display:flex;justify-content:space-between;position:relative}.cdw-onboarding-progress::before{content:"";position:absolute;top:15px;left:0;right:0;height:2px;background:#e0e0e0;z-index:0}.cdw-progress-step{position:relative;z-index:1;text-align:center;flex:1}.cdw-progress-number{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#e0e0e0;color:#646970;font-weight:600;margin-bottom:8px}.cdw-progress-step.active .cdw-progress-number{background:#2271b1;color:#fff}.cdw-progress-step.completed .cdw-progress-number{background:#46b450;color:#fff}.cdw-progress-label{font-size:12px;color:#646970}.cdw-progress-step.active .cdw-progress-label{color:#2271b1;font-weight:600}.cdw-onboarding-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:40px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.1)}.cdw-onboarding-icon{font-size:48px;margin-bottom:16px}.cdw-onboarding-card h1{font-size:28px;margin-bottom:16px;color:#1d2327}.cdw-onboarding-content{text-align:left;margin:24px 0;color:#50575e}.cdw-onboarding-content ul{margin:16px 0;padding-left:20px}.cdw-onboarding-content li{margin-bottom:8px}.cdw-onboarding-content code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:14px}.cdw-onboarding-actions{display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap}.cdw-onboarding-skip{font-size:14px;color:#646970}</style>
	<?php
}

/**
 * Returns the onboarding steps content.
 *
 * @return array
 */
function cdw_get_onboarding_steps() {
	$dashboard_url = admin_url( 'index.php' );
	$settings_url  = admin_url( 'options-general.php?page=cdw-settings' );

	return array(
		array(
			'title'   => 'Dashboard Widgets',
			'icon'    => '&#128200;',
			'content' => '<p>Your dashboard now shows custom widgets with useful information:</p><ul><li><strong>Site Stats</strong> — Quick overview of posts, pages, comments, users, and media.</li><li><strong>Recent Posts</strong> — See and edit your latest posts.</li><li><strong>Tasks</strong> — Create and manage your own to-do list.</li><li><strong>Quick Links</strong> — Fast access to common admin areas.</li><li><strong>Updates</strong> — See pending WordPress, plugin, and theme updates.</li></ul><p><a href="' . esc_url( $dashboard_url ) . '" class="button">View Dashboard</a></p>',
		),
		array(
			'title'   => 'AI Assistant',
			'icon'    => '&#10022;',
			'content' => '<p>The AI Assistant helps you manage your site using natural language:</p><ul><li>Describe what you want to do, and the AI will execute it for you.</li><li>Create posts, install plugins, manage users, and more.</li><li>Choose to confirm each action before it runs, or let it run automatically.</li></ul><p>To use the AI, you will need to add your API key in Settings.</p><p><a href="' . esc_url( $settings_url ) . '#cdw-ai-settings" class="button">Configure AI Settings</a></p>',
		),
		array(
			'title'   => 'CLI Commands',
			'icon'    => '&#128187;',
			'content' => '<p>The Command Line Widget lets you run WP-CLI-style commands directly from your dashboard:</p><ul><li><code>plugin list</code> — List installed plugins</li><li><code>plugin install &lt;slug&gt;</code> — Install a new plugin</li><li><code>user create &lt;user&gt; &lt;email&gt; &lt;role&gt;</code> — Create a new user</li><li><code>cache flush</code> — Clear the object cache</li></ul><p>Type <code>help</code> in the CLI widget to see all available commands.</p>',
		),
		array(
			'title'   => 'MCP Integration',
			'icon'    => '&#128268;',
			'content' => '<p>MCP (Model Context Protocol) lets external AI tools connect to your WordPress site.</p><ul><li>Works with Claude, Cursor, and other AI assistants.</li><li>Requires the MCP Adapter plugin to be installed.</li><li>Disabled by default — you must opt-in to enable it.</li></ul><p>This is an advanced feature — only enable on trusted sites.</p><p><a href="' . esc_url( $settings_url ) . '#cdw-ai-settings" class="button">View Settings</a></p>',
		),
	);
}

/**
 * Handles the user type selection form submission.
 */
function cdw_handle_set_user_type() {
	check_admin_referer( 'cdw_set_user_type', 'cdw_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	$user_type = isset( $_POST['user_type'] ) ? sanitize_text_field( $_POST['user_type'] ) : '';

	if ( in_array( $user_type, array( 'developer', 'user' ), true ) ) {
		update_option( 'cdw_user_type', $user_type, false );
		update_option( 'cdw_welcome_notice_dismissed', true, false );
	}

	wp_safe_redirect( admin_url( 'tools.php?page=cdw-welcome' ) );
	exit;
}

/**
 * Handles resetting the user type to show onboarding again.
 */
function cdw_handle_reset_user_type() {
	check_admin_referer( 'cdw_reset_user_type' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	delete_option( 'cdw_user_type' );
	delete_option( 'cdw_welcome_notice_dismissed' );

	wp_safe_redirect( admin_url( 'tools.php?page=cdw-welcome' ) );
	exit;
}

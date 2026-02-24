<?php
/**
 * Plugin Name: Custom Dashboard Widgets
 * Description: This MU plugin removes specific widgets, adds custom widgets, and applies custom styling to the dashboard widgets.
 * Author: Your Name
 * Version: 1.3
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the options page file.
require_once plugin_dir_path( __FILE__ ) . 'admin-options.php';

// Hook into the 'wp_dashboard_setup' action to manage dashboard widgets.
add_action( 'wp_dashboard_setup', 'cdw_manage_dashboard_widgets' );

function cdw_manage_dashboard_widgets() {
    global $wp_meta_boxes;

	// Remove existing widgets using core API for clarity.
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );

    // Add custom widgets for all users
    wp_add_dashboard_widget( 'custom_help_widget', 'Help & Support', 'cdw_custom_help_widget_display' );
    wp_add_dashboard_widget( 'custom_stats_widget', 'Site Statistics', 'cdw_custom_stats_widget_display' );
    wp_add_dashboard_widget( 'custom_media_widget', 'Latest Media', 'cdw_custom_media_widget_display' );
    wp_add_dashboard_widget( 'custom_posts_widget', 'Latest Posts', 'cdw_custom_posts_widget_display' );

    // Check if the current user is an administrator
    if ( current_user_can( 'administrator' ) ) {
        // Add custom widgets only for administrators
        wp_add_dashboard_widget( 'custom_tasks_widget', 'Pending Tasks', 'cdw_custom_tasks_widget_display' );
        wp_add_dashboard_widget( 'custom_updates_widget', 'Updates', 'cdw_custom_updates_widget_display' );
        wp_add_dashboard_widget( 'custom_appearance_widget', 'Appearance', 'cdw_custom_appearance_widget_display' );
        wp_add_dashboard_widget( 'custom_users_widget', 'Users', 'cdw_custom_users_widget_display' );
        wp_add_dashboard_widget( 'custom_tools_widget', 'Tools', 'cdw_custom_tools_widget_display' );
        wp_add_dashboard_widget( 'custom_settings_widget', 'Settings', 'cdw_custom_settings_widget_display' );
    }
}

// Display function for the "Help & Support" widget
function cdw_custom_help_widget_display() {
    $email    = get_option( 'custom_dashboard_widget_email', 'support@example.com' );
    $docs_url = get_option( 'custom_dashboard_widget_docs_url', 'https://example.com/docs' );
    
    echo '<p>Need help? Contact our support team at <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>.</p>';
    echo '<p>Visit our <a href="' . esc_url($docs_url) . '">documentation</a> for more information.</p>';

    $options_page_url = esc_url( get_admin_url( null, 'options-general.php?page=custom-dashboard-widget-settings' ) );
    echo '<p><a href="' . $options_page_url . '" class="button">Edit Widget Settings</a></p>';
}


// Display function for the "Site Statistics" widget
function cdw_custom_stats_widget_display() {
    echo '<p><strong>Total Posts:</strong> ' . wp_count_posts()->publish . '</p>';
    echo '<p><strong>Total Pages:</strong> ' . wp_count_posts( 'page' )->publish . '</p>';
    echo '<p><strong>Total Comments:</strong> ' . wp_count_comments()->approved . '</p>';
}

// Display function for the "Pending Tasks" widget
function cdw_custom_tasks_widget_display() {
    $current_user_id = get_current_user_id();
    $tasks = get_user_meta($current_user_id, 'custom_dashboard_tasks', true);
    $tasks = $tasks ? json_decode($tasks, true) : [];

    ?>
    <div id="tasks-container">
        <table id="tasks-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Added</th>
                </tr>
            </thead>
            <tbody id="tasks-list">
                <?php foreach ( $tasks as $task ) : ?>
                    <tr>
                        <td><?php echo esc_html( $task['name'] ); ?></td>
                        <td class="task-time" data-timestamp="<?php echo esc_attr( $task['timestamp'] ); ?>">
                            <?php echo cdw_calculate_time_ago( $task['timestamp'] ); ?> ago
                        </td>
                        <td><span class="remove-task" style="cursor:pointer;color:red;">&#x2715;</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <input type="text" id="new-task" placeholder="Add new task">
        <button id="add-task-button">Add Task</button>
    </div>
    <?php
}

// Helper function to calculate time ago
function cdw_calculate_time_ago( $timestamp ) {
    $time_diff = time() - intval( $timestamp );
    if ( $time_diff < 60 ) {
        return $time_diff . ' seconds';
    } elseif ( $time_diff < 3600 ) {
        return floor( $time_diff / 60 ) . ' minutes';
    } elseif ( $time_diff < 86400 ) {
        return floor( $time_diff / 3600 ) . ' hours';
    } else {
        return floor( $time_diff / 86400 ) . ' days';
    }
}

add_action( 'wp_ajax_cdw_save_tasks', 'cdw_save_tasks_callback' );
function cdw_save_tasks_callback() {
	// Verify nonce for security.
	check_ajax_referer( 'cdw_tasks_nonce', 'nonce' );

	if ( ! current_user_can( 'read' ) ) {
		wp_send_json_error( 'unauthorized' );
	}

    $current_user_id = get_current_user_id();
    $tasks           = isset( $_POST['tasks'] ) && is_array( $_POST['tasks'] ) ? $_POST['tasks'] : [];

    $tasks_with_timestamps = array_map(
		static function( $task ) {
			$name       = isset( $task['name'] ) ? sanitize_text_field( wp_unslash( $task['name'] ) ) : '';
			$timestamp  = isset( $task['timestamp'] ) ? intval( $task['timestamp'] ) : 0;
			if ( $timestamp <= 0 ) {
				$timestamp = time();
			}

			return [
				'name'      => $name,
				'timestamp' => $timestamp,
			];
		},
		$tasks
	);

    update_user_meta( $current_user_id, 'custom_dashboard_tasks', wp_json_encode( $tasks_with_timestamps ) );
    wp_send_json_success();
}

// Display function for the "Updates" widget
function cdw_custom_updates_widget_display() {
    $updates = get_site_transient('update_plugins');

    if ( ! empty( $updates ) && ! empty( $updates->response ) ) {
        echo '<ul>';
        foreach ( $updates->response as $plugin => $update ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            echo '<li>';
            echo '<strong>' . esc_html( $plugin_data['Name'] ) . '</strong> - ';
            echo '<a href="' . esc_url( network_admin_url( 'update-core.php' ) ) . '">Update Now</a>';
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Good job, you have no pending updates.</p>';
    }

    // Wrap the buttons in a container div
    echo '<div class="custom-widget-buttons">';
    echo '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '" class="button button-primary">Go to Plugins</a>';
    echo '<a href="' . esc_url( admin_url( 'plugin-install.php' ) ) . '" class="button">Add New Plugin</a>';
    echo '</div>';
}

// Display function for the "Media" widget
function cdw_custom_media_widget_display() {
    // Query for the latest 10 media items
    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 10,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    $media_query = new WP_Query( $args );

    if ( $media_query->have_posts() ) {
        echo '<ul>';
        while ( $media_query->have_posts() ) {
            $media_query->the_post();
            $media_url   = wp_get_attachment_url( get_the_ID() );
            $media_title = get_the_title();
            echo '<li><a href="' . esc_url( $media_url ) . '" target="_blank">' . esc_html( $media_title ) . '</a></li>';
        }
        echo '</ul>';
        wp_reset_postdata();
    } else {
        echo '<p>No media found.</p>';
    }

    // Add a button to go to the Media Library
    echo '<p><a href="' . esc_url( admin_url( 'upload.php' ) ) . '" class="button button-primary">Go to Media Library</a></p>';
}

// Display function for the "Posts" widget
function cdw_custom_posts_widget_display() {
    // Get the latest 10 posts
    $args = array(
        'numberposts' => 10,
        'post_status' => 'publish',
    );
    $recent_posts = wp_get_recent_posts( $args );

    // Display the list of recent posts
    if ( ! empty( $recent_posts ) ) {
        echo '<ul>';
        foreach ( $recent_posts as $post ) {
            echo '<li>' . esc_html( $post['post_title'] ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No posts found.</p>';
    }

    // Wrap the buttons in a container div with a specific class
    echo '<div class="custom-widget-buttons posts-widget">';
    echo '<a href="' . esc_url( admin_url( 'edit.php' ) ) . '" class="button button-primary">All Posts</a>';
    echo '<a href="' . esc_url( admin_url( 'post-new.php' ) ) . '" class="button">Add New Post</a>';
    echo '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=category' ) ) . '" class="button">Categories</a>';
    echo '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=post_tag' ) ) . '" class="button">Tags</a>';
    echo '</div>';
}

// Display function for the "Appearance" widget
function cdw_custom_appearance_widget_display() {
    // Wrap the buttons in a container div
    echo '<div class="custom-widget-buttons">';
    echo '<a href="' . esc_url( admin_url( 'themes.php' ) ) . '" class="button button-primary">Go to Themes</a>';
    echo '<a href="' . esc_url( admin_url( 'site-editor.php' ) ) . '" class="button">Go to Site Editor</a>';
    echo '</div>';
}

// Display function for the "Users" widget
function cdw_custom_users_widget_display() {
    $users = get_users();
    
    if ( ! empty( $users ) ) {
        echo '<ul>';
        foreach ( $users as $user ) {
            echo '<li>' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No users found.</p>';
    }

    // Wrap the buttons in a container div
    echo '<div class="custom-widget-buttons">';
    echo '<a href="' . esc_url( admin_url( 'user-new.php' ) ) . '" class="button button-primary">Add New User</a>';
    echo '<a href="' . esc_url( admin_url( 'profile.php' ) ) . '" class="button">My Profile</a>';
    echo '</div>';
}

// Display function for the "Tools" widget
function cdw_custom_tools_widget_display() {
    // Wrap the buttons in a container div with a specific class
    echo '<div class="custom-widget-buttons tools-widget">';
    echo '<a href="' . esc_url( admin_url( 'tools.php' ) ) . '" class="button">Tools</a>';
    echo '<a href="' . esc_url( admin_url( 'import.php' ) ) . '" class="button">Import</a>';
    echo '<a href="' . esc_url( admin_url( 'export.php' ) ) . '" class="button">Export</a>';
    echo '<a href="' . esc_url( admin_url( 'site-health.php' ) ) . '" class="button">Site Health</a>';
    echo '<a href="' . esc_url( admin_url( 'export-personal-data.php' ) ) . '" class="button">Export Personal Data</a>';
    echo '<a href="' . esc_url( admin_url( 'erase-personal-data.php' ) ) . '" class="button">Erase Personal Data</a>';

    // Add missing buttons
    if ( current_user_can( 'edit_theme_options' ) ) {
        echo '<a href="' . esc_url( admin_url( 'theme-editor.php' ) ) . '" class="button">Theme File Editor</a>';
    }

    if ( current_user_can( 'edit_plugins' ) ) {
        echo '<a href="' . esc_url( admin_url( 'plugin-editor.php' ) ) . '" class="button">Plugin File Editor</a>';
    }

    echo '</div>';
}

// Display function for the "Settings" widget
function cdw_custom_settings_widget_display() {
    // Wrap the buttons in a container div with a specific class
    echo '<div class="custom-widget-buttons settings-widget">';
    echo '<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '" class="button">General</a>';
    echo '<a href="' . esc_url( admin_url( 'options-writing.php' ) ) . '" class="button">Writing</a>';
    echo '<a href="' . esc_url( admin_url( 'options-reading.php' ) ) . '" class="button">Reading</a>';
    echo '<a href="' . esc_url( admin_url( 'options-discussion.php' ) ) . '" class="button">Discussion</a>';
    echo '<a href="' . esc_url( admin_url( 'options-media.php' ) ) . '" class="button">Media</a>';
    echo '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '" class="button">Permalinks</a>';
    echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'cdw_enqueue_custom_dashboard_assets' );

function cdw_enqueue_custom_dashboard_assets( $hook_suffix ) {
	// Only load assets on the main Dashboard screen and this plugin's settings page.
	if ( 'index.php' !== $hook_suffix && 'settings_page_custom-dashboard-widget-settings' !== $hook_suffix ) {
		return;
	}

    // Get the URL of the plugin folder
    $plugin_url = plugin_dir_url( __FILE__ );

    // Enqueue the custom CSS file
    wp_enqueue_style( 'custom-dashboard-widgets-style', $plugin_url . 'custom-dashboard-widgets.css' );

    // Enqueue the custom JS file
    wp_enqueue_script( 'custom-dashboard-widgets-script', $plugin_url . 'custom-dashboard-widgets.js', array( 'jquery' ), null, true );

    // Apply appearance options as inline CSS, if set.
    $font_size              = get_option( 'custom_dashboard_widget_font_size', '' );
    $background_color       = get_option( 'custom_dashboard_widget_background_color', '' );
    $header_background_color = get_option( 'custom_dashboard_widget_header_background_color', '' );
    $header_text_color      = get_option( 'custom_dashboard_widget_header_text_color', '' );

    $inline_css = '';

    if ( ! empty( $font_size ) ) {
        $font_size  = absint( $font_size );
        if ( $font_size > 0 ) {
            $inline_css .= '#dashboard-widgets .postbox .inside { font-size: ' . $font_size . 'px; }';
        }
    }

    if ( ! empty( $background_color ) ) {
        $background_color = sanitize_hex_color( $background_color );
        if ( $background_color ) {
            $inline_css .= '#dashboard-widgets .postbox { background-color: ' . $background_color . '; }';
        }
    }

    if ( ! empty( $header_background_color ) ) {
        $header_background_color = sanitize_hex_color( $header_background_color );
        if ( $header_background_color ) {
            $inline_css .= '#dashboard-widgets .postbox .postbox-header { background: ' . $header_background_color . '; }';
        }
    }

    if ( ! empty( $header_text_color ) ) {
        $header_text_color = sanitize_hex_color( $header_text_color );
        if ( $header_text_color ) {
            $inline_css .= '#dashboard-widgets .postbox .postbox-header, #dashboard-widgets .postbox .postbox-header h2 { color: ' . $header_text_color . '; }';
        }
    }

    if ( $inline_css ) {
        wp_add_inline_style( 'custom-dashboard-widgets-style', $inline_css );
    }

    // Localize script to pass Ajax URL and nonce
    wp_localize_script(
		'custom-dashboard-widgets-script',
		'cdw_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cdw_tasks_nonce' ),
		)
	);
}

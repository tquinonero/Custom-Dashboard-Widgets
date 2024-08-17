<?php
// Exit if accessed directly
if ( !defined('ABSPATH') ) exit;

// Register the options page
function custom_register_options_page() {
    add_options_page(
        'Custom Dashboard Widget Settings',  // Page title
        'Dashboard Widget Settings',          // Menu title
        'manage_options',                      // Capability
        'custom-dashboard-widget-settings',    // Menu slug
        'custom_options_page_html'             // Function to display options page content
    );
}
add_action('admin_menu', 'custom_register_options_page');

// Display the options page content
function custom_options_page_html() {
    ?>
    <div class="wrap">
        <h1>Custom Dashboard Widget Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('custom_dashboard_widget_options');
            do_settings_sections('custom-dashboard-widget-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function custom_register_settings() {
    register_setting(
        'custom_dashboard_widget_options',      // Option group
        'custom_dashboard_widget_email',         // Option name
        'sanitize_email'                        // Sanitize callback
    );
    register_setting(
        'custom_dashboard_widget_options',      // Option group
        'custom_dashboard_widget_docs_url',      // Option name
        'esc_url_raw'                           // Sanitize callback
    );

    add_settings_section(
        'custom_dashboard_widget_section',      // ID
        'Widget Settings',                      // Title
        null,                                   // Callback
        'custom-dashboard-widget-settings'      // Page
    );

    add_settings_field(
        'custom_dashboard_widget_email',        // ID
        'Support Email',                        // Title
        'custom_email_field_html',              // Callback
        'custom-dashboard-widget-settings',     // Page
        'custom_dashboard_widget_section'       // Section
    );

    add_settings_field(
        'custom_dashboard_widget_docs_url',      // ID
        'Documentation URL',                    // Title
        'custom_docs_url_field_html',           // Callback
        'custom-dashboard-widget-settings',     // Page
        'custom_dashboard_widget_section'       // Section
    );
}
add_action('admin_init', 'custom_register_settings');

// Email field HTML
function custom_email_field_html() {
    $email = get_option('custom_dashboard_widget_email', '');
    echo '<input type="email" name="custom_dashboard_widget_email" value="' . esc_attr($email) . '" />';
}

// Documentation URL field HTML
function custom_docs_url_field_html() {
    $url = get_option('custom_dashboard_widget_docs_url', '');
    echo '<input type="url" name="custom_dashboard_widget_docs_url" value="' . esc_url($url) . '" />';
}
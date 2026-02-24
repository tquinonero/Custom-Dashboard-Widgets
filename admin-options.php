<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register the options page
function cdw_register_options_page() {
    add_options_page(
        'Custom Dashboard Widget Settings',  // Page title
        'Dashboard Widget Settings',          // Menu title
        'manage_options',                      // Capability
        'custom-dashboard-widget-settings',    // Menu slug
        'cdw_options_page_html'             // Function to display options page content
    );
}
add_action( 'admin_menu', 'cdw_register_options_page' );

// Display the options page content
function cdw_options_page_html() {
    ?>
    <div class="wrap">
        <h1>Custom Dashboard Widget Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'custom_dashboard_widget_options' );
            do_settings_sections( 'custom-dashboard-widget-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function cdw_register_settings() {
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
        'cdw_email_field_html',              // Callback
        'custom-dashboard-widget-settings',     // Page
        'custom_dashboard_widget_section'       // Section
    );

    add_settings_field(
        'custom_dashboard_widget_docs_url',      // ID
        'Documentation URL',                    // Title
        'cdw_docs_url_field_html',           // Callback
        'custom-dashboard-widget-settings',     // Page
        'custom_dashboard_widget_section'       // Section
    );

    // Appearance options
    register_setting(
        'custom_dashboard_widget_options',
        'custom_dashboard_widget_font_size',
        'absint'
    );
    register_setting(
        'custom_dashboard_widget_options',
        'custom_dashboard_widget_background_color',
        'sanitize_hex_color'
    );
    register_setting(
        'custom_dashboard_widget_options',
        'custom_dashboard_widget_header_background_color',
        'sanitize_hex_color'
    );
    register_setting(
        'custom_dashboard_widget_options',
        'custom_dashboard_widget_header_text_color',
        'sanitize_hex_color'
    );

    add_settings_section(
        'custom_dashboard_widget_appearance_section',
        'Widget Appearance',
        null,
        'custom-dashboard-widget-settings'
    );

    add_settings_field(
        'custom_dashboard_widget_font_size',
        'Widget Text Size (px)',
        'cdw_font_size_field_html',
        'custom-dashboard-widget-settings',
        'custom_dashboard_widget_appearance_section'
    );

    add_settings_field(
        'custom_dashboard_widget_background_color',
        'Widget Background Color',
        'cdw_background_color_field_html',
        'custom-dashboard-widget-settings',
        'custom_dashboard_widget_appearance_section'
    );

    add_settings_field(
        'custom_dashboard_widget_header_background_color',
        'Widget Header Background',
        'cdw_header_background_color_field_html',
        'custom-dashboard-widget-settings',
        'custom_dashboard_widget_appearance_section'
    );

    add_settings_field(
        'custom_dashboard_widget_header_text_color',
        'Widget Header Text Color',
        'cdw_header_text_color_field_html',
        'custom-dashboard-widget-settings',
        'custom_dashboard_widget_appearance_section'
    );
}
add_action( 'admin_init', 'cdw_register_settings' );

// Email field HTML
function cdw_email_field_html() {
    $email = get_option( 'custom_dashboard_widget_email', '' );
    echo '<input type="email" name="custom_dashboard_widget_email" value="' . esc_attr( $email ) . '" />';
}

// Documentation URL field HTML
function cdw_docs_url_field_html() {
    $url = get_option( 'custom_dashboard_widget_docs_url', '' );
    echo '<input type="url" name="custom_dashboard_widget_docs_url" value="' . esc_url( $url ) . '" />';
}

// Appearance fields HTML
function cdw_font_size_field_html() {
    $size = get_option( 'custom_dashboard_widget_font_size', '' );
    echo '<input type="number" min="10" max="40" name="custom_dashboard_widget_font_size" value="' . esc_attr( $size ) . '" /> ';
    echo '<span class="description">Leave empty to use the default admin font size.</span>';
}

function cdw_background_color_field_html() {
    $color = get_option( 'custom_dashboard_widget_background_color', '' );
    echo '<input type="text" class="cdw-color-field" name="custom_dashboard_widget_background_color" value="' . esc_attr( $color ) . '" placeholder="#ffffff" /> ';
    echo '<span class="description">Hex color for widget backgrounds. Leave empty for default.</span>';
}

function cdw_header_background_color_field_html() {
    $color = get_option( 'custom_dashboard_widget_header_background_color', '' );
    echo '<input type="text" class="cdw-color-field" name="custom_dashboard_widget_header_background_color" value="' . esc_attr( $color ) . '" placeholder="#ff7e5f" /> ';
    echo '<span class="description">Hex color for widget header background. Overrides the default gradient when set.</span>';
}

function cdw_header_text_color_field_html() {
    $color = get_option( 'custom_dashboard_widget_header_text_color', '' );
    echo '<input type="text" class="cdw-color-field" name="custom_dashboard_widget_header_text_color" value="' . esc_attr( $color ) . '" placeholder="#ffffff" /> ';
    echo '<span class="description">Hex color for widget header text. Leave empty for default.</span>';
}
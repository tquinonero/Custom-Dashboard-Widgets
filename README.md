Custom admin dashboard for WordPress
Author: Toni Quiñonero
License: GPLv3

## Overview

This plugin customizes the WordPress admin dashboard by:

- Removing several core dashboard widgets.
- Adding custom widgets (Help & Support, Site Statistics, Media, Posts, Tasks, Updates, Appearance, Users, Tools, Settings).
- Adding a simple settings page to configure the support email and documentation URL.
- Styling dashboard widgets for a more modern look.

## Installation

1. Upload the plugin folder to your WordPress installation:
	- Via FTP/SFTP: copy this folder into `wp-content/plugins/`.
	- Via Git (for development): clone into `wp-content/plugins/CDW`.
2. In the WordPress admin, go to **Plugins → Installed Plugins**.
3. Activate **Custom Dashboard Widgets**.

> Note: This plugin is designed for the admin area only. It does not affect the public front‑end of your site.

## Usage

### Dashboard widgets

Once activated, visit **Dashboard → Home**. You will see:

- **Help & Support** – shows a support email and documentation link.
- **Site Statistics** – basic counts for posts, pages, and comments.
- **Latest Media** – links to the most recent media items.
- **Latest Posts** – a list of recent posts plus quick links to posts, categories, and tags.
- **Pending Tasks** (administrators only) – a personal to‑do list stored per user.
- **Updates** (administrators only) – a list of plugins with available updates, plus shortcuts to plugin management.
- **Appearance, Users, Tools, Settings** (administrators only) – quick‑access panels to common admin screens.

### Configuring Help & Support widget

1. Go to **Settings → Dashboard Widget Settings**.
2. Set:
	- **Support Email** – email address shown in the Help & Support widget.
	- **Documentation URL** – link used for the “documentation” button.
3. Save changes. The widget will immediately reflect the new values on the Dashboard.

### Using the Tasks widget

- Add a task by typing into the input and either clicking **Add Task** or pressing **Enter**.
- Remove a task by clicking the **×** icon in the row.
- Timestamps are stored per task so you can see how long ago each task was added; the “time ago” text refreshes automatically while you keep the dashboard open.

### Customizing widget appearance

In **Settings → Dashboard Widget Settings → Widget Appearance** you can globally adjust how the dashboard widgets look:

- **Widget Text Size (px)** – changes the font size inside widget content areas.
- **Widget Background Color** – sets the background color for widget boxes.
- **Widget Header Background** – sets a solid background color for widget headers (overrides the default gradient when used).
- **Widget Header Text Color** – sets the text color for widget header titles.

Leave any field empty to fall back to the default WordPress admin styles.

## Security and stability notes

This plugin has undergone security and robustness improvements compared to the initial version. It is important to be transparent about what was fixed.

### Fixed security issues

1. **Insecure AJAX endpoint for Tasks widget**  
	**Issue (pre-1.3):** The AJAX action used to save tasks (`save_tasks`) did not validate a nonce and had no capability checks. This meant that any logged-in user could be tricked (via CSRF) into sending unintended task data to their own account.  
	**Fix (1.3+):**
	- The action is now `cdw_save_tasks` and is handled by `cdw_save_tasks_callback()`.
	- A nonce (`cdw_tasks_nonce`) is generated server-side and passed to JavaScript via `wp_localize_script()`.
	- The AJAX callback now calls `check_ajax_referer( 'cdw_tasks_nonce', 'nonce' )` and verifies `current_user_can( 'read' )` before processing.
	- Incoming task data is sanitized (`sanitize_text_field`) and validated before being stored in user meta.

2. **Timestamp integrity in Tasks widget**  
	**Issue (pre-1.3):** Every time tasks were saved, the server code overwrote all timestamps with the current time. This was not a direct security issue but affected data integrity and could confuse users (tasks always looked “just added”).  
	**Fix (1.3+):** The backend now preserves the original `timestamp` sent from the client (if valid) and only falls back to `time()` when no valid timestamp is present.

3. **Function name collisions and direct access**  
	**Issue (pre-1.3):** Most functions in the plugin were in the global namespace without a prefix and the main file lacked an `ABSPATH` guard. This increased the risk of function name collisions with other themes/plugins and of direct execution of the file outside of WordPress.  
	**Fix (1.3+):**
	- All public functions are now prefixed with `cdw_`.
	- The main plugin file includes an `if ( ! defined( 'ABSPATH' ) ) exit;` guard.

### Behavioral changes

1. **Scoped asset loading**  
	CSS and JS assets are now only enqueued on:
	- The main Dashboard screen (`index.php`).
	- This plugin’s settings page (`settings_page_custom-dashboard-widget-settings`).

	This reduces the plugin’s footprint on other admin screens and avoids unintended styling/JS side effects.

2. **Removed invasive admin layout changes**  
	Previous versions:
	- Hid the entire left admin menu on the Dashboard via inline CSS.
	- Applied a hard-coded background image and global color changes to `body.wp-admin` and `.wrap` across the admin.

	Current version:
	- Does **not** hide the admin menu.
	- Does **not** apply a site-specific background image or global color overrides.
	- Limits styling to dashboard widgets (e.g., rounded postboxes and gradient headers).

3. **Tasks widget structure and UX**  
	- CSS now matches the actual markup (which uses a `<table>` for tasks), fixing earlier mismatched `li` styling.
	- The “time ago” labels update automatically every 60 seconds while the dashboard is open.
	- You can add tasks by clicking the button or pressing Enter in the input.

### Backwards compatibility notes

- The AJAX action name changed from `save_tasks` to `cdw_save_tasks`, and the localized JS object changed from `ajax_object` to `cdw_ajax`. If you had custom code hooking into the old action or relying on the old JS object name, you will need to update it.
- The removal of global background and menu-hiding CSS means the dashboard may look different compared to earlier versions, but should now be more consistent with WordPress core and other plugins.

## Changelog

- **1.3**
	- Hardened AJAX handling for the Tasks widget (nonce + capability checks, sanitized input) and preserved task timestamps.
	- Added `ABSPATH` guard and `cdw_` prefix to all plugin functions to reduce collision risk.
	- Scoped CSS/JS loading to the Dashboard and plugin settings page only.
	- Removed invasive admin UI changes (hidden menu, hard‑coded background image, global color overrides).
	- Fixed Tasks widget CSS to match table markup and improved UX (auto‑updating “time ago”, Enter key to add tasks).
	- Added configurable appearance options for widget font size and colors.
	- Updated README with explicit documentation of past issues and their fixes.

- **1.0 – 1.2**
	- Initial releases of the custom dashboard with core widget removal, custom widgets, and basic styling.

=== Custom Dashboard Widgets ===
Contributors: toniquinonero
Tags: dashboard, admin, widgets, cli, customization
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 3.0.0
Requires PHP: 8.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Modernize your WordPress admin dashboard with React-powered widgets, a personal task manager, and a built-in CLI terminal.

== Description ==

Custom Dashboard Widgets replaces the standard WordPress dashboard with a fast, modern interface built with React. Every widget talks to a dedicated REST API endpoint — no page reloads, no clutter.

= Widgets =

* **Help & Support** — Display a support email and documentation link for your team.
* **Site Statistics** — Post, page, comment, user, media, category, and tag counts at a glance.
* **Latest Media** — Quick access to recently uploaded files.
* **Latest Posts** — See your most recent content across any post type.
* **Pending Tasks** — A personal todo list stored per user account.
* **Updates** — Available core, plugin, and theme updates (admin only).
* **Quick Links** — One-click access to Appearance, Users, Tools, and Settings.
* **Command Line** — A WP-CLI-style terminal for managing your site without leaving the dashboard.

= Command Line widget =

The CLI widget simulates WP-CLI commands through WordPress APIs. Available commands include:

`plugin list / install / activate / deactivate / update / delete`
`theme list / install / activate / update / delete`
`user list / get / create / update / delete`
`post list / get / create / publish / unpublish / delete`
`db optimize / repair / size / tables`
`option get / set / delete`
`transient get / delete / flush`
`cron list / run`
`maintenance on / off / status`
`search-replace <old> <new> --dry-run | --force`
`cache flush`
`site info / status`
`help`

Security guardrails:

* All destructive commands require `--force`
* `search-replace` requires `--dry-run` or `--force`
* Core WordPress options (siteurl, auth keys, etc.) are protected from modification
* Users cannot delete their own account
* The entire widget can be disabled from Settings → Dashboard Widgets

= Appearance customisation =

Set a custom font size, widget background colour, and widget header colour/text colour from the settings page. Changes apply instantly on next dashboard load.

= Data management =

A "Delete all data on uninstall" toggle in settings controls whether your tasks, settings, CLI history, audit logs, and the custom database table are removed when the plugin is deleted. Defaults to on.

== Installation ==

1. Upload the `CDW` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Visit your WordPress dashboard — the widgets appear immediately.
4. Optional: go to **Settings → Dashboard Widgets** to configure appearance, the CLI widget, and uninstall behaviour.

**Requirements**

* WordPress 6.0 or higher
* PHP 8.0 or higher

== Screenshots ==

1. **Dashboard overview** — all eight widgets displayed on the WordPress admin dashboard.
2. **Command Line widget** — the CLI terminal showing command history and autocomplete suggestions.
3. **Statistics widget** — post, page, comment, user, and media counts.
4. **Tasks widget** — personal to-do list with timestamp display.
5. **Updates widget** — available core, plugin, and theme updates.
6. **Settings page** — appearance controls and feature toggles.

== Frequently Asked Questions ==

= Does this plugin affect the public front-end? =

No. All code runs in the admin area only. Assets are not loaded on any public-facing page.

= Does the CLI widget execute real shell commands? =

No. It simulates WP-CLI commands through WordPress PHP APIs (activate_plugin(), wp_update_plugins(), etc.). It does not exec(), shell_exec(), or open any shell process.

= Can I disable the CLI widget? =

Yes. Go to **Settings → Dashboard Widgets** and uncheck "Enable Command Line Widget". The REST endpoint is still registered but returns an error if the setting is off.

= Does it work on multisite? =

The plugin works on individual sites within a network. Network-wide activation is not tested and not officially supported.

= Where is data stored? =

* **Tasks** — User meta (`cdw_tasks`)
* **CLI history** — User meta (`cdw_cli_history`)
* **Audit log** — Custom database table (`{prefix}cdw_cli_logs`)
* **Settings** — WordPress options (`cdw_*`)

= How do I remove all plugin data? =

Make sure "Delete all data on uninstall" is checked in **Settings → Dashboard Widgets**, then delete the plugin from **Plugins → Installed Plugins**. All options, user meta, transients, and the custom table are removed.

= I deactivated the plugin and my data is gone =

Deactivation intentionally does not remove data. Only deletion (uninstall) removes data, and only when the "Delete all data on uninstall" option is enabled.

== Changelog ==

= 3.0.1 =
* Fix: REST API routes were not registered during REST requests (REST_REQUEST constant is not defined at plugins_loaded time)
* Fix: ensure_audit_table() now skips frontend requests to avoid a DB query on every visitor page load
* Fix: Rate limiter no longer writes to the options table; uses transients only (works correctly with Redis/Memcached)
* Fix: Plugin name header corrected
* New: "Delete all data on uninstall" setting — choose whether to preserve or remove data when the plugin is deleted

= 3.0.0 =
* Major architecture refactor — REST API split into dedicated controllers and a service layer
* Added WP-CLI integration (`wp cdw stats`, `wp cdw tasks`, `wp cdw cli`)
* Added audit logging for all CLI commands
* Added React-based settings page
* Updated minimum PHP to 8.0, minimum WordPress to 6.0

= 2.0.0 =
* Complete rewrite with React and the WordPress REST API
* Added Command Line widget
* Added rate limiting on CLI endpoints
* Added appearance customisation options

= 1.3 =
* Hardened AJAX handling (nonce + capability checks)
* Scoped assets to dashboard only
* Fixed Tasks widget CSS

= 1.0 – 1.2 =
* Initial releases

== Upgrade Notice ==

= 3.0.1 =
Fixes a critical regression introduced in 3.0.0 where all REST API endpoints failed to register, breaking every dashboard widget. Update immediately.

= 3.0.0 =
Major architectural update. REST API endpoints and data formats are unchanged. Minimum PHP version raised to 8.0.

= 2.0.0 =
Major update. New React interface, CLI widget, and REST API. Settings are migrated automatically from v1 option names.

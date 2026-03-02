# Custom Dashboard Widgets (v3)

**Contributors:** toniquinonero
**Tags:** dashboard, admin, widgets, customization  
**Requires at least:** 6.0  
**Requires PHP:** 8.0
**Tested up to:** 6.7  
**Stable tag:** 3.0.0  
**License:** GPLv3 or later  
**License URI:** https://www.gnu.org/licenses/gpl-3.0.html

Modernize your WordPress admin dashboard with custom React-powered widgets and a sleek design.

## Description

Custom Dashboard Widgets replaces the default WordPress dashboard with a modern, customizable interface featuring:

- **8 Custom Widgets** - Help & Support, Site Statistics, Latest Media, Latest Posts, Tasks, Updates, Quick Links, and Command Line
- **React-Powered** - Fast, interactive widgets built with React
- **Modern Design** - Clean, professional styling that matches WordPress admin
- **CLI Terminal** - Built-in command line interface for managing plugins, themes, users, and more
- **Fully Customizable** - Configure widget appearance with colors and font sizes

### Widgets

- **Help & Support** - Display support email and documentation link
- **Site Statistics** - View post, page, comment, user, and media counts at a glance
- **Latest Media** - Quick access to recent uploaded files
- **Latest Posts** - See your most recent published content
- **Pending Tasks** - Personal todo list (stored per user)
- **Updates** - View available plugin and theme updates (admin only)
- **Quick Links** - Fast access to common admin pages (admin only)
- **Command Line** - WP-CLI-like terminal for site management (admin only)

### Features

- Drag-and-drop widget reordering (native WordPress)
- Per-user task management
- Configurable appearance (colors, fonts)
- Support email and documentation URL settings
- Enable/disable individual widgets
- Remove default WordPress widgets option
- **WP-CLI Commands** - Manage site from terminal with `wp cdw`

## Installation

1. Upload the plugin folder to your WordPress installation:
   - Via FTP/SFTP: copy this folder into `wp-content/plugins/`
   - Via Git (for development): clone into `wp-content/plugins/CDW`
2. In the WordPress admin, go to **Plugins → Installed Plugins**
3. Activate **Custom Dashboard Widgets**

> Note: This plugin is designed for the admin area only. It does not affect the public front-end of your site.

## Frequently Asked Questions

### Does this plugin work with multisite?

Currently, multisite support is limited. The plugin works on individual sites within a network but network-wide activation is not fully tested.

### Can I choose which widgets to display?

Yes! Use the **Settings → Dashboard Widgets** page to configure:
- Enable/disable the Command Line widget
- Choose whether to remove default WordPress widgets

### Can I customize the widget appearance?

Absolutely. Go to **Settings → Dashboard Widgets → Widget Appearance** to adjust:
- Widget text size
- Widget background color
- Widget header background color
- Widget header text color

### Where is my data stored?

- **Tasks** - Stored in user meta (`cdw_tasks`)
- **Settings** - Stored in WordPress options
- **CLI History** - Stored in user meta (`cdw_cli_history`)
- **Audit Logs** - Stored in custom database table (`wp_cdw_cli_logs`)

### Does the CLI widget really run WP-CLI commands?

The CLI widget simulates WP-CLI commands through WordPress APIs. It provides a subset of common commands:

### Available Commands

**Help:**
  help                                  - Show this help message

**Plugin Management:**
  plugin list                           - List all plugins (with update status)
  plugin status <slug>                  - Show version, status, update info
  plugin install <slug>                 - Install a plugin from wordpress.org
  plugin activate <slug>                - Activate a plugin
  plugin deactivate <slug>             - Deactivate a plugin
  plugin update <slug>                  - Update a specific plugin
  plugin update --all                   - Update all plugins
  plugin delete <slug>                  - Delete a plugin (requires --force)

**Theme Management:**
  theme list                           - List all themes (with update status)
  theme status <slug>                  - Show version, status, update info
  theme install <slug>                 - Install a theme from wordpress.org
  theme activate <slug>                - Activate a theme
  theme delete <slug>                  - Delete a theme (requires --force)

**User Management:**
  user list                            - List all users
  user create <user> <email> <role>    - Create a user
  user role <user> <role>              - Change user role
  user delete <user>                   - Delete user (requires --force)

**Post Management:**
  post list [<type>]                   - List posts by type
  post status <id> <status>            - Change post status
  post delete <id>                     - Delete post

**Site Management:**
  site info                            - Show site information
  site status                          - Show site status
  site empty                           - Optimize database

**Cache:**
  cache flush [type]                  - Flush cache (all, posts, terms, comments, transients, object)

**Database:**
  db size                             - Show database size
  db tables                           - List all tables

**Options:**
  option get <name>                   - Get option value
  option set <name> <value>           - Set option value
  option delete <name>                 - Delete option (protected options blocked)
  option list                          - List CDW options

**Transients:**
  transient list                       - List transients
  transient delete <name>              - Delete transient

**Cron:**
  cron list                           - List scheduled cron events
  cron run <hook>                     - Manually trigger a cron hook

**Maintenance:**
  maintenance on                       - Enable maintenance mode
  maintenance off                      - Disable maintenance mode

**Search & Replace:**
  search-replace <old> <new> --dry-run  - Preview matches without making changes
  search-replace <old> <new> --force    - Replace a string sitewide

### WP-CLI Terminal Commands

You can also manage your site from the terminal using WP-CLI:

  wp cdw stats                        - Show site statistics
  wp cdw tasks list                   - List user tasks
  wp cdw tasks clear                  - Clear user tasks
  wp cdw cli execute <command>         - Execute a CLI command
  wp cdw cli history                  - Show command history
  wp cdw cli clear                    - Clear command history

Security notes:
  - Destructive commands require --force flag
  - search-replace supports --dry-run to safely preview before committing
  - Critical options (siteurl, admin_email, auth keys, etc.) are protected
  - user delete cannot target your own account

Examples:
  plugin update --all
  theme install twentytwentyfive
  user role john editor
  search-replace https://old.com https://new.com --dry-run
  maintenance on
  cron list
  option get blogname
  post status 42 draft
  cache flush
  wp cdw stats";
  
## Changelog

### 3.0.0
- **Major Architecture Refactor** - Complete rewrite of REST API architecture
- Split 2,500+ line REST API class into separate controllers and services
- Added service layer for better separation of concerns
- Created dedicated controllers for each endpoint (stats, media, posts, users, updates, tasks, settings, cli)
- Added WP-CLI command support (`wp cdw stats`, `wp cdw tasks`, `wp cdw cli`)
- Added deactivation hook for proper cleanup
- Updated minimum PHP version to 8.0
- Updated minimum WordPress version to 6.0
- Improved code maintainability and testability

### 2.0.0
- Complete rewrite with React and REST API
- Added Command Line widget for site management
- New modern settings page with React
- Improved security with proper nonce verification
- Added audit logging for CLI commands
- Rate limiting on CLI endpoints
- New appearance customization options
- Fixed various database query issues
- Added widget visibility controls

### 1.3
- Hardened AJAX handling (nonce + capability checks)
- Added ABSPATH guard and function prefixing
- Scoped CSS/JS loading to dashboard only
- Removed invasive admin UI changes
- Fixed Tasks widget CSS and improved UX

### 1.0 - 1.2
- Initial releases

## Upgrade Notice

### 3.0.0
This is a major architectural update with no breaking changes:
- REST API endpoints remain the same
- New WP-CLI commands available
- Improved code organization and maintainability
- Minimum PHP increased to 8.0

### 2.0.0
This is a major update with significant changes:
- New React-based interface
- CLI widget added (can be disabled in settings)
- Settings moved to new REST API endpoints
- Database schema changes (new audit log table)

## Credits

- Built with [WordPress](https://wordpress.org/)
- React integration via [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)
- Styled with modern CSS

## License

This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

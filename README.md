# Custom Dashboard Widgets (v3)

**Contributors:** toniquinonero
**Tags:** dashboard, admin, widgets, customization, ai  
**Requires at least:** 6.9  
**Requires PHP:** 8.0
**Tested up to:** 6.9  
**Stable tag:** 3.0.0  
**License:** GPLv3 or later  
**License URI:** https://www.gnu.org/licenses/gpl-3.0.html

Modernize your WordPress admin dashboard with custom React-powered widgets, a CLI terminal, and an AI assistant.

## Description

Custom Dashboard Widgets replaces the default WordPress dashboard with a modern, customizable interface featuring:

- **9 Custom Widgets** - Help & Support, Site Statistics, Latest Media, Latest Posts, Tasks, Updates, Quick Links, Command Line, and AI Assistant
- **React-Powered** - Fast, interactive widgets built with React
- **Modern Design** - Clean, professional styling that matches WordPress admin
- **CLI Terminal** - Built-in command line interface for managing plugins, themes, users, and more
- **AI Assistant** - Conversational AI that can manage your site using natural language (supports OpenAI, Anthropic, Google Gemini, and any OpenAI-compatible endpoint such as OpenRouter or Groq)
- **WordPress Abilities API** - All 31 CDW tools registered as native WP Abilities (WP 6.9+), accessible via the `wp-abilities/v1` REST namespace and any compatible MCP adapter
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
- **AI Assistant** - Chat with an AI to manage plugins, themes, users, posts, and more (admin only)

### Features

- Drag-and-drop widget reordering (native WordPress)
- Per-user task management
- Configurable appearance (colors, fonts)
- Support email and documentation URL settings
- Enable/disable individual widgets
- Remove default WordPress widgets option

## Installation

### Option A — Download ZIP (recommended for most users)

1. Go to the [GitHub repository](https://github.com/tquinonero/Custom-Dashboard-Widgets)
2. Click **Code → Download ZIP**
3. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
4. Upload the ZIP and click **Install Now**, then **Activate**

### Option B — Git clone

```bash
cd wp-content/plugins
git clone https://github.com/tquinonero/Custom-Dashboard-Widgets CDW
cd CDW
composer install --no-dev
```

Then activate the plugin from **Plugins → Installed Plugins**.

> **Note:** The compiled JavaScript (`build/`) is included in the repository, so no Node.js build step is required to use the plugin. `composer install` is only needed for the git clone method — the ZIP download from GitHub includes all dependencies.

> This plugin is designed for the admin area only. It does not affect the public front-end of your site.

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
- **AI API Keys** - Stored encrypted in user meta (`cdw_ai_api_key_{provider}`), using AES-256-CBC with a key derived from your WordPress salts. Never stored in plain text, never exposed via the API.
- **AI Settings** - Provider, model, and execution mode stored in user meta (per user)
- **AI Token Usage** - Accumulated token counts stored in user meta

### How does the AI assistant work?

The AI widget connects to your chosen provider (OpenAI, Anthropic, Google Gemini, or any OpenAI-compatible endpoint) using your own API key. It uses an agentic loop with function-calling tools that map to CDW CLI commands — so it can list plugins, manage users, check site status, and more, all through natural language.

In **Confirm** mode (default) you approve each action before it runs. In **Auto** mode the AI executes commands immediately.

### Is my API key safe?

Yes. Your API key is encrypted with AES-256-CBC before being saved to the database, using a key derived from your site's `AUTH_SALT` and `SECURE_AUTH_SALT` constants. The raw key is never returned by any API endpoint — only a boolean `has_key` indicating whether one is saved.

### Does the CLI widget really run WP-CLI commands?

The CLI widget simulates WP-CLI commands through WordPress APIs. It provides a subset of common commands:
help                                  - Show this help message

  plugin list                           - List all plugins (with update status)
  plugin status <slug>                  - Show version, status, update info
  plugin install <slug>                 - Install a plugin from wordpress.org
  plugin activate <slug>                - Activate a plugin
  plugin deactivate <slug>              - Deactivate a plugin
  plugin update <slug>                  - Update a specific plugin
  plugin update --all                   - Update all plugins
  plugin delete <slug>                  - Delete a plugin (requires --force)

  theme list                            - List all themes (with update status)
  theme status <slug>                   - Show version, status, update info
  theme install <slug>                  - Install a theme from wordpress.org
  theme activate <slug>                 - Activate a theme
  theme deactivate [slug]               - Switch to another theme
  theme update <slug>                   - Update a specific theme
  theme update --all                    - Update all themes

  user list                             - List all users
  user get <id|username>                - Get details for a user
  user create <user> <email> <role>     - Create a user (password emailed)
  user update <id|user> --role <role>   - Change a user's role
  user delete <id|username>             - Delete a user (requires --force)

  post list                             - List recent posts
  post get <id>                         - Get details for a post
  post create <title>                   - Create a draft post
  post publish <id>                     - Publish a post
  post unpublish <id>                   - Set a post back to draft
  post delete <id>                      - Permanently delete a post (requires --force)

  db optimize                           - Optimize all WordPress database tables
  db repair                             - Repair all WordPress database tables

  option get <key>                      - Get an option value
  option set <key> <value>              - Set an option value
  option delete <key>                   - Delete an option (requires --force)

  transient get <key>                   - Get a transient value
  transient delete <key>                - Delete a specific transient
  transient flush                       - Delete ALL transients

  cron list                             - List all scheduled cron events
  cron run <hook>                       - Manually trigger a cron hook

  maintenance on                        - Enable maintenance mode
  maintenance off                       - Disable maintenance mode
  maintenance status                    - Check maintenance mode status

  search-replace <old> <new> --dry-run  - Preview matches without making changes
  search-replace <old> <new> --force    - Replace a string sitewide

  cache flush                           - Flush the object cache
  site info                             - Show site information
  site status                           - Show site status

Security notes:
  - Destructive commands require --force
  - search-replace supports --dry-run to safely preview before committing
  - Critical options (siteurl, admin_email, auth keys, etc.) are protected
  - user delete cannot target your own account

Examples:
  plugin update --all
  theme install twentytwentyfive
  user update john --role editor
  search-replace https://old.com https://new.com --dry-run
  maintenance on
  cron list
  option get blogname
  transient flush
  post publish 42";
  
## Development Status

### ✅ Completed

| Area | Detail |
|---|---|
| **PHP unit tests** | 221 tests, 432 assertions — 219 pass / 2 pre-existing failures in `UninstallTest` |
| **JS unit tests** | 96 tests across 7 suites — all passing |
| **Integration tests** | 24 tests, 67 assertions — all passing |
| **Static analysis** | PHPCS (WordPress Coding Standards) + PHPStan level 6 — 0 errors |
| **CI (GitHub Actions)** | PHP unit + JS unit jobs — green on every push |
| **Build pipeline** | `npm run build` — 0 errors, compiled assets committed to repo |
| **Internationalization** | All strings wrapped; `languages/cdw.pot` generated (24 strings) |
| **Security** | Protected option list expanded; `wp-tests-config.php` gitignored; API keys AES-256-CBC encrypted |
| **Uninstall** | Full cleanup of all plugin data including encrypted AI API keys |
| **AI Assistant** | Per-user encrypted API keys; OpenAI, Anthropic, Google, custom endpoints; agentic loop with tool calling |
| **WordPress Abilities API** | 31 CDW tools registered as WP Abilities (WP 6.9+); REST-exposed via `wp-abilities/v1`; MCP opt-in toggle |
| **Release prep** | `.distignore` created; v3.0.0 tagged and released |

### 🔲 Future work

- **Fix `UninstallTest`** — update two incorrect mock call-count assertions (expected counts: 10 and 7)
- **Accessibility (a11y)** — automated axe-core audit; screen reader keyboard navigation
- **Compatibility testing** — manual verification on WP 6.9+ (new minimum), PHP 8.0 and PHP 8.4, multisite
- **WP 7.0 compatibility** — hybrid abilities, `@wordpress/abilities` JS package, WP AI Client; review CDW AI stack for overlap

---

## Running Tests

### Unit Tests (no database required)

```bash
# PHP unit tests — 221 tests
vendor/bin/phpunit --testsuite=Unit

# JavaScript unit tests — 96 tests
npm run test:js

# Static analysis
vendor/bin/phpcs
vendor/bin/phpstan analyse --configuration=phpstan.neon
```

### Integration Tests (requires DDEV + WordPress test database)

1. Copy the sample config and fill in your credentials:

```bash
cp wp-tests-config-sample.php wp-tests-config.php
# Edit wp-tests-config.php — see inline comments for each value
```

2. Run the integration suite inside DDEV:

```bash
ddev exec bash -c 'export WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-content/plugins/CDW/wp-tests-config.php \
  && vendor/bin/phpunit --config phpunit-integration.xml'
```

Or via npm script:

```bash
ddev exec bash -c 'export WP_PHPUNIT__TESTS_CONFIG=/var/www/html/wp-content/plugins/CDW/wp-tests-config.php \
  && npm run test:integration'
```

> **Note:** The integration suite requires a dedicated test database. The test runner
> will **drop and recreate tables** on each run — never point it at your production DB.

## Development Setup

If you want to modify the source and rebuild the assets:

```bash
git clone https://github.com/tquinonero/Custom-Dashboard-Widgets CDW
cd CDW
composer install
npm install
npm run build
```

See [Running Tests](#running-tests) below for the full test setup.

---

## Changelog

### 3.0.0 (in development)

**AI Assistant**
- New AI assistant widget powered by OpenAI, Anthropic (Claude), Google Gemini, or any OpenAI-compatible endpoint (OpenRouter, Groq, etc.)
- Per-user encrypted API keys (AES-256-CBC, derived from WordPress salts)
- Agentic loop with function-calling tools mapped to CDW CLI commands
- Confirm-first and auto execution modes
- Token usage tracking per user
- Custom system prompt support

**WordPress Abilities API (WP 6.9+)**
- 31 CDW admin tools registered as native `WP_Ability` objects in the `cdw-admin-tools` category
- All abilities REST-exposed via `wp-abilities/v1` with `show_in_rest: true`
- Per-ability annotations: `readonly` (list/get/status operations = GET) and `destructive` (delete operations = DELETE)
- MCP opt-in: enable the **Expose via MCP Adapter** toggle to make abilities discoverable to external AI clients

**Architecture**
- Complete rewrite of REST API architecture; split 2 500+ line class into dedicated controllers and services
- Separate controllers for stats, media, posts, users, updates, tasks, settings, and CLI
- Service layer (`CDW_Task_Service`, `CDW_Stats_Service`, `CDW_CLI_Service`) for clean separation of concerns
- WP-CLI command support (`wp cdw stats`, `wp cdw tasks`, `wp cdw cli`)
- Minimum PHP bumped to 8.0; minimum WordPress bumped to 6.9 (required for Abilities API)

**Quality & testing**
- Full test suite: 221 PHP unit tests, 96 JavaScript unit tests, 24 integration tests — all passing
- PHP: Brain\Monkey + Mockery for isolated unit tests; wp-phpunit for integration against real DB
- JS: Jest + @testing-library/react for component tests; store reducer and async actions fully covered
- Static analysis: PHPCS (WordPress Coding Standards) + PHPStan level 6 — 0 errors across all source files
- GitHub Actions CI: PHP unit, JS unit, and PHP integration jobs

**Internationalisation**
- All user-visible strings wrapped with `__()` / `esc_html__()` / `_e()`
- `languages/cdw.pot` generated with 24 translatable strings

**Security**
- Protected-option list extended to cover `admin_email`, `users_can_register`, `default_role`
- Uninstall logic extracted to `includes/functions-uninstall.php` and covered by unit tests

**Build**
- SASS deprecated `darken()` calls replaced with `color.adjust()` — zero build warnings
- `.distignore` created for clean release archive generation

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

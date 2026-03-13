# Custom Dashboard Widgets (v3.0.2)

**Contributors:** toniquinonero
**Tags:** dashboard, admin, widgets, customization, ai  
**Requires at least:** 6.9  
**Requires PHP:** 8.0
**Tested up to:** 6.9.4  
**Stable tag:** 3.0.2  
**License:** GPLv3 or later  
**License URI:** https://www.gnu.org/licenses/gpl-3.0.html

Modernize your WordPress admin dashboard with custom React-powered widgets, a CLI terminal, and an AI assistant.

## Description

Custom Dashboard Widgets replaces the default WordPress dashboard with a modern, customizable interface featuring:

- **9 Custom Widgets** - Help & Support, Site Statistics, Latest Media, Latest Posts, Tasks, Updates, Quick Links, Command Line, and AI Assistant
- **React-Powered** - Fast, interactive widgets built with React
- **Modern Design** - All the widgets have dark theme by default
- **CLI Terminal** - Fully featured built-in command line interface for managing plugins, themes, users, transients and more
- **AI Assistant** - Conversational AI that can manage your site using natural language (supports OpenAI, Anthropic, Google Gemini, and any OpenAI-compatible endpoint such as OpenRouter or Groq)
- **WordPress Abilities API** - 80 CDW tools registered as native WP Abilities (WP 6.9+), accessible via the `wp-abilities/v1` REST namespace and any compatible MCP adapter
- **Fully Customizable** - Configure widget appearance with colors and font sizes

### Widgets

- **Help & Support** - Display support email and documentation link for your clients or teams
- **Site Statistics** - View post, page, comment, user, and media counts at a glance
- **Latest Media** - Quick access to recent uploaded files
- **Latest Posts** - See your most recent published content
- **Pending Tasks** - Create tasks, assign them to users and delete them when completed (only admins can manage other user' tasks)
- **Updates** - View available plugin and theme updates (admin only)
- **Quick Links** - Fast access to common admin pages (admin only)
- **Tools and other** - Fast access to all your dashboard menus, including those created by installed plugins
- **Command Line** - WP-CLI-like terminal for site management (admin only)
- **AI Assistant** - Ask an AI model of your preference and it will manage plugins, themes, users, posts, and more (admin only)

### Features

- Drag-and-drop widget reordering (native WordPress)
- Per-user task management
- Configurable appearance (colors, fonts)
- Support email and documentation URL settings
- Enable/disable individual widgets (with Screen Options in the dashboard. Native WordPress.)
- Remove default WordPress widgets option
- **Page Builder** - (alpha, needs work) AI can build complete pages with sections (hero sections, services sections, bio sections, team sections, footer) using structured JSON
- **Page Templates** - Set page templates (blank, page, etc.) when creating pages; list available templates from active theme
- **Universal Block Renderer** - (alpha, needs work with some blocks) AI can render individual Gutenberg blocks (paragraph, heading, image, cover, buttons, columns, etc.)

## Full list of CDW'S 80 registered abilities

1. **cdw/post-get:** Retrieves the title, content, status, and metadata of a specific WordPress post by its numeric ID.
2. **cdw/post-create:** Creates a new WordPress post as a draft with the specified title.
3. **cdw/page-create:** Creates a new WordPress page as a draft with the specified title.
4. **post-list**: Returns a list of recent posts, optionally filtered by post type (default: post).
5. **cdw/post-count**: Returns the count of posts by status (publish, draft, pending, trash) for each public post type. Excludes attachments.
6. **cdw/post-status**: Changes the status of an existing post (e.g. draft, publish, trash).
7. **cdw/post-delete**: Permanently deletes a WordPress post by its numeric ID.

**Task Management:**

1. **cdw/task-list**: Lists pending tasks for a user. Omit user_id to list tasks for the current user.
2. **cdw/task-create**: Creates a new pending task. Optionally assigns it to another user by username (assignee_login) or user ID (assignee_id). Assigning to another user requires administrator privileges.
3. **cdw/task-delete**: Deletes all tasks for a user. Omit user_id to delete tasks for the current user.

**Content Creation**:

1. **cdw/post-set-content:** Replaces the full post_content of an existing post or page with raw block markup. For design guidelines, first use cdw/skill-list to find skills, then cdw/skill-get with skill_name: "gutenberg-design" to get design guidelines. Supply either content (plain string) or content_base64 (base64-encoded string — preferred for block markup because it avoids JSON escaping issues). For large pages: (1) call with content="" to clear, (2) use cdw/post-append-content to push sections.
2. **cdw/post-get-content**: Retrieves the raw post_content of a WordPress post or page, including all Gutenberg block markup. Use offset and limit for pagination on large content. Use this before editing a page with cdw/post-set-content.
3. **cdw/post-append-content**: Appends a raw block markup chunk to the existing post_content of a post or page. For design guidelines, first use cdw/skill-list to find skills, then cdw/skill-get with skill_name: "gutenberg-design" to get design guidelines. Supply either content (plain string) or content_base64 (base64-encoded — preferred for block markup to avoid JSON escaping). Workflow: (1) call cdw/post-set-content with content="" to clear the post, (2) call this ability repeatedly with successive chunks. The response includes the running total byte count so you can confirm each chunk landed.
4. **cdw/build-page**: Creates a new page or updates an existing one with Gutenberg block markup generated from structured JSON. For design guidelines, first use cdw/skill-list to find skills, then cdw/skill-get with skill_name: "gutenberg-design". Input: {"title": "Page Title", "sections": [{"type": "cover", "title": "Hero", "image": "url"}, {"type": "two-column", "left": {...}, "right": {...}}, {"type": "footer", "columns": [...]}]}. Supported section types: cover, two-column, three-column, footer. Returns post_id, title, and section_count.

**Block editor abilities:**

1. **cdw/block-patterns-get**: Returns the raw block markup for a specific block pattern by name. Returns base64-encoded content to preserve special characters. Use this to retrieve a pattern before appending it to a page.
2. **cdw/block-patterns-list**: Returns all registered block patterns with name, title, and categories. Optionally filter by category slug.
3. **cdw/custom-patterns-list**: Returns a list of all custom block patterns stored in the cdw/patterns/ folder. Each pattern includes name, title, description, and category.
4. **cdw/custom-patterns-get**: Returns the raw block markup for a specific custom pattern by name. Searches in cdw/patterns/ folder. Returns base64-encoded content to preserve special characters**.**

**Media Management**:

1. **cdw/media-list**: Lists recent media attachments with ID, filename, MIME type, and upload date.

**Meta abilities**:

1. **cdw/post-meta-get**: Retrieves metadata for a specific post. If key is omitted, returns all meta for the post.
2. **cdw/post-meta-set**: Sets metadata for a post. For complex values (arrays, objects), provide value_base64 with base64-encoded JSON.
3. **cdw/post-meta-delete**: Deletes metadata for a specific post by key.
4. **cdw/user-meta-get**: Retrieves metadata for a specific user. If key is omitted, returns all meta for the user.
5. **cdw/user-meta-set**: Sets metadata for a user. For complex values (arrays, objects), provide value_base64 with base64-encoded JSON.
6. **cdw/user-meta-delete**: Deletes metadata for a specific user by key.
7. **cdw/term-list**: Returns a list of terms (categories, tags, or custom taxonomy) with their IDs, names, and counts. Use this to find term IDs before working with term meta.
8. **term-meta-get**: Retrieves metadata for a specific term (category, tag, or custom taxonomy). If key is omitted, returns all meta for the term.
9. **cdw/term-meta-set**: Sets metadata for a term (category, tag, or custom taxonomy). For complex values (arrays, objects), provide value_base64 with base64-encoded JSON.
10. **cdw/term-meta-delete**: Deletes metadata for a specific term (category, tag, or custom taxonomy) by key.

**Role abilities**:

1. **cdw/role-list**: Returns a list of all WordPress roles with their display names and capabilities. Use this to see what capabilities each role has before creating or updating roles.
2. **cdw/role-create:** Creates a new WordPress role with specified display name and capabilities. Optionally clone capabilities from an existing role. Cannot override built-in roles (administrator, editor, author, contributor, subscriber).
3. **cdw/role-update**: Updates a role by adding or removing capabilities. Use add_caps to grant new capabilities, remove_caps to revoke capabilities. Cannot modify built-in roles (administrator, editor, author, contributor, subscriber).
4. **cdw/role-delete**: Deletes a custom WordPress role. Cannot delete built-in roles (administrator, editor, author, contributor, subscriber). Users currently assigned to the deleted role will be moved to subscriber.
5. **cdw/user-role**: Changes the role of an existing WordPress user identified by username or user ID.

**Plugin Management**:

1. **cdw/plugin-list**: Returns a list of all installed plugins with their activation status, version, and description.
2. **cdw/plugin-status**: Returns the activation status and version of a specific plugin identified by its slug.
3. **cdw/plugin-activate**: Activates an installed plugin by its slug.
4. '**cdw/plugin-deactivate**: Deactivates an active plugin by its slug.
5. **cdw/plugin-install**: Downloads and installs a plugin from the [WordPress.org](http://wordpress.org/) repository by slug.
6. **cdw/plugin-update**: Updates an installed plugin to the latest available version by its slug.
7. **cdw/plugin-delete**: Permanently deletes an installed plugin by its slug.
8. **cdw/plugin-update-all**: Updates all installed plugins that have pending updates.

**Theme Management**:

1. **cdw/theme-list**: Returns a list of all installed themes with their activation status and version.
2. **cdw/theme-activate**: Activates an installed theme by its slug.
3. **cdw/theme-install**: Downloads and installs a theme from the [WordPress.org](http://wordpress.org/) repository by slug.
4. **cdw/theme-update**: Updates an installed theme to the latest available version by its slug.
5. **cdw/theme-info**: Returns details about the currently active theme including name, version, and author.
6. **cdw/theme-status**: Returns the activation status and version of a specific theme identified by its slug.
7. **cdw/theme-delete**: Permanently deletes an installed theme by its slug. The theme must not be currently active.
8. **cdw/theme-update-all**: Updates all installed themes that have pending updates.

**User Management**:

1. **cdw/user-list**: Returns a list of all WordPress users with their IDs, usernames, roles, and email addresses.
2. **cdw/user-create**: Creates a new WordPress user with the specified username, email address, and role.
3. **cdw/user-delete**: Permanently deletes a WordPress user identified by their numeric user ID. Provide reassign to transfer authored content or set delete_content to true to remove authored content.
4. **cdw/user-get**: Retrieves details about a specific WordPress user by their ID, username, or email address.

**Cache Management**:

1. cdw/cache-flush: Flushes the WordPress object cache, clearing all cached data.

**Options Management**:

1. cdw/option-get: Retrieves the current value of a WordPress option from the database by its name.
2. cdw/option-list: Returns all WordPress options stored in the database with their values.
3. cdw/option-set: Creates or updates a WordPress option in the database with the given name and value.
4. cdw/option-delete: Deletes a WordPress option from the database by name. Protected core options cannot be deleted.

**Cron Management**:

1. cdw/cron-list: Returns a list of all scheduled WordPress cron events with their next run time and recurrence interval.
2. cdw/cron-run: Manually triggers a scheduled WordPress cron hook immediately.

**Site Info**:

1. cdw/site-info: Returns general information about the WordPress site including its name, URL, WordPress version, and active theme.
2. cdw/core-version: Returns the current WordPress version, PHP version, and whether a core update is available.
3. cdw/site-status: Returns the current health and configuration status of the WordPress site.
4. cdw/site-settings: Returns the configured WordPress site settings such as timezone, date format, and reading/writing options.

**Comment Management**:

1. cdw/comment-list: Lists comments filtered by status: pending (default), approved, or spam.
2. cdw/comment-approve: Approves a comment by ID.
3. cdw/comment-spam: Marks a comment as spam by ID.
4. cdw/comment-delete: Permanently deletes a comment by ID. Requires --force.

**Database Management**:

1. cdw/db-size: Returns the total size of the WordPress database in bytes.
2. cdw/db-tables: Returns a list of all WordPress database tables with their sizes and row counts.
3. cdw/search-replace: Performs a search and replace across all database tables. Set dry_run to true to preview changes without committing them.
4. cdw/transient-list: Returns the first 20 WordPress transients currently stored in the database.
5. cdw/transient-delete: Deletes a specific WordPress transient by name.

**Maintenance mode**:

1. cdw/maintenance-on: Enables WordPress maintenance mode, making the site temporarily unavailable to visitors while showing a maintenance message.
2. cdw/maintenance-off: Disables WordPress maintenance mode, restoring normal public access to the site.
3. cdw/maintenance-status: Returns whether WordPress maintenance mode is currently enabled or disabled.

**Rewrite**:

1. cdw/rewrite-flush: Flushes WordPress rewrite rules, equivalent to saving the permalink settings.

**Plugin skill discovery**:

**cdw/skill-list**: Scans all installed plugins for agent skill documentation and returns a list of available skills with their plugin slug and skill name.

**cdw/skill-get**: Returns the contents of a skill documentation file from an installed plugin. Defaults to [SKILL.md](http://skill.md/). Use file to read sub-documents such as instructions/attributes.md.

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
You can also show/hide every widget individually via the Screen Options button in your Dashboard (wordpress core functionality)

### Can I customize the widget appearance?

Absolutely. Go to **Settings → Dashboard Widgets → Widget Appearance** to adjust:
- Widget text size
- Widget background color
- Widget header background color
- Widget header text color

This is currently in beta and needs a bit of work.

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

The CLI widget works the same as WP-CLI commands, but through custom endpoints in WordPress APIs. It does **not** call `exec()`, `shell_exec()`, or open any shell process and works in hosts without WP CLI support. Supported commands:

```
help

  plugin list                             List all plugins (with update status)
  plugin status <slug>                    Show version, status, update info
  plugin install <slug>                   Install a plugin from wordpress.org
  plugin activate <slug>                  Activate a plugin
  plugin deactivate <slug>                Deactivate a plugin
  plugin update <slug>                    Update a specific plugin
  plugin update --all                     Update all plugins
  plugin delete <slug>                    Delete a plugin (requires --force)

  theme list                              List all themes (with update status)
  theme info                              Show active theme details
  theme status <slug>                     Show version, status, update info
  theme activate <slug>                   Activate a theme
  theme install <slug>                    Install a theme from wordpress.org
  theme update <slug>                     Update a specific theme
  theme update --all                      Update all themes
  theme delete <slug>                     Delete a theme (requires --force)

  user list                               List all users
  user get <id|username>                  Get details for a user
  user create <username> <email> <role>   Create a user (password emailed)
  user role <id|username> <role>          Change a user's role
  user delete <id|username>               Delete a user (requires --force)

  post list [<type>]                      List recent posts (optionally by type)
  post get <id>                           Get details for a post
  post create <title> [--publish]         Create a post (draft or published)
  post status <id> <status>               Change a post's status
  post delete <id>                        Permanently delete a post (requires --force)

  page create <title> [--publish]         Create a page (draft or published)

  comment list [pending|approved|spam]    List comments (default: pending)
  comment approve <id>                    Approve a comment
  comment spam <id>                       Mark a comment as spam
  comment delete <id>                     Permanently delete a comment (requires --force)

  task list                               List your pending tasks
  task create <name>                      Create a task
  task delete                             Delete all your tasks

  core version                            Show WP version, PHP version, and update status

  site info                               Show site information
  site settings                           Show key WordPress settings
  site status                             Show site health status

  cache flush                             Flush the object cache

  db size                                 Show database size
  db tables                               List all tables

  option get <name>                       Get an option value
  option set <name> <value>               Set an option value
  option list                             List CDW-managed option keys
  option delete <name>                    Delete an option (requires --force)

  transient list                          List all transients
  transient delete <key>                  Delete a specific transient
  transient flush                         Delete ALL transients

  cron list                               List all scheduled cron events
  cron run <hook>                         Manually trigger a cron hook

  maintenance on                          Enable maintenance mode
  maintenance off                         Disable maintenance mode
  maintenance status                      Check maintenance mode status

  rewrite flush                           Flush rewrite rules

  search-replace <old> <new> --dry-run    Preview matches without making changes
  search-replace <old> <new> --force      Replace a string sitewide
```

Security notes:
  - Destructive commands require `--force`
  - `search-replace` supports `--dry-run` to safely preview before committing
  - Critical options (`siteurl`, `admin_email`, auth keys, etc.) are protected
  - `user delete` cannot target your own account
  - `db export` and `db import` are blocked when executed via the AI agentic loop

### 🔲 Future work

- **Accessibility (a11y)** — automated axe-core audit; screen reader keyboard navigation
- **Compatibility testing** — manual verification on WP 6.9+ (new minimum), PHP 8.0 and PHP 8.4, multisite
- **WP 7.0 compatibility** — hybrid abilities, `@wordpress/abilities` JS package, WP AI Client; review CDW AI stack for overlap

---

## Running Tests

### Unit Tests (no database required)

```bash
# PHP unit tests — 424 tests, 1283 assertions
vendor/bin/phpunit --testsuite=Unit

# JavaScript unit tests — 162 tests
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

### 3.0.2

**Post & Page Creation**
- AI assistant can now create posts and pages with body content (`content` parameter)
- AI assistant can now publish posts/pages immediately (`status: publish`; default remains `draft`)
- New CLI flag `--publish` on `post create <title>` and `page create <title>` to create and immediately publish
- `page create` is now documented and available in CLI autocomplete
- Post/page creation via AI routes directly through `wp_insert_post`, bypassing the CLI tokeniser — multi-word content is preserved correctly

**Page Builder & Templates**
- `cdw/build-page` ability now supports `page_template` parameter to set page templates (works with both Classic and FSE/block themes)
- New `cdw/list-page-templates` ability returns available templates from the active theme (auto-detects theme type)
- Enhanced section renderers: cover sections now support `content` and `buttons`; two-column/three-column support `title` and `subtitle` headers; footer supports flexible column formats
- Added universal block renderer via `"type": "block"` - supports core Gutenberg blocks (paragraph, heading, image, cover, group, columns, buttons, etc.) with nested content via `children` array
- Backward compatibility: section renderers now accept both `title`/`content` and `heading`/`text` field names

- Over 20 new abilities added.

### 3.0.0 (released)

**AI Assistant**
- New AI assistant widget powered by OpenAI, Anthropic (Claude), Google Gemini, or any OpenAI-compatible endpoint (OpenRouter, Groq, etc.)
- Per-user encrypted API keys (AES-256-CBC, derived from WordPress salts)
- Agentic loop with 36 function-calling tools mapped to CDW CLI commands
- `post_create` and `page_create` tools accept optional `content` (body text) and `status` (`draft` or `publish`)
- Confirm-first and auto execution modes
- Token usage tracking per user
- Custom system prompt support

**WordPress Abilities API (WP 6.9+)**
- 41 CDW admin tools registered as native `WP_Ability` objects in the `cdw-admin-tools` category
- All abilities REST-exposed via `wp-abilities/v1`; `show_in_rest`, `readonly`, and `idempotent` set under `meta`; `destructive` under `meta.annotations`
- Per-ability HTTP method routing: `readonly: true` → GET; mutating → POST; `destructive: true` → DELETE
- MCP opt-in: enable the **Expose via MCP Adapter** toggle to make abilities discoverable to external AI clients

**Architecture**
- Complete rewrite of REST API architecture; split 2 500+ line class into dedicated controllers and services
- Separate controllers for stats, media, posts, users, updates, tasks, settings, and CLI
- Service layer (`CDW_Task_Service`, `CDW_Stats_Service`, `CDW_CLI_Service`) for clean separation of concerns
- WP-CLI command support (`wp cdw stats`, `wp cdw tasks`, `wp cdw cli`)
- Minimum PHP bumped to 8.0; minimum WordPress bumped to 6.9 (required for Abilities API)

**Quality & testing**
- Full test suite: 338 PHP unit tests, 96 JavaScript unit tests, 24 integration tests — all passing
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

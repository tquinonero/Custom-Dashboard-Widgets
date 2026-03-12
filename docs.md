# Custom Dashboard Widgets (CDW) — Plugin Documentation

> **Version:** 3.0.0 | **License:** GPLv3 | **Author:** Toni Quiñonero  
> **Requires:** WordPress 6.9+, PHP 8.1+

---

## Table of Contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Directory Structure](#directory-structure)
4. [Bootstrap & Lifecycle](#bootstrap--lifecycle)
5. [Dashboard Widgets](#dashboard-widgets)
6. [REST API](#rest-api)
7. [CLI System](#cli-system)
8. [AI Assistant](#ai-assistant)
9. [WordPress Abilities API](#wordpress-abilities-api)
10. [Abilities Explorer](#abilities-explorer)
11. [Frontend (React)](#frontend-react)
12. [Settings](#settings)
13. [Data Storage](#data-storage)
14. [Caching Strategy](#caching-strategy)
15. [Security](#security)
16. [WP-CLI Integration](#wp-cli-integration)
17. [Block Patterns](#block-patterns)
18. [Welcome & Onboarding](#welcome--onboarding)
19. [Uninstall & Data Cleanup](#uninstall--data-cleanup)
20. [Testing](#testing)
21. [Code Quality](#code-quality)
22. [Development Workflow](#development-workflow)

---

## Overview

**Custom Dashboard Widgets (CDW)** replaces the standard WordPress admin dashboard with a fast, modern interface built with React. Every widget communicates with a dedicated REST API endpoint — no page reloads, no clutter.

Key capabilities:
- **9 dashboard widgets** covering stats, tasks, posts, media, updates, CLI terminal, and AI chat
- **Built-in CLI terminal** that simulates ~40 WP-CLI-style commands through WordPress PHP APIs (no shell access)
- **AI Assistant** supporting OpenAI, Anthropic, Google Gemini, and any OpenAI-compatible endpoint
- **WordPress Abilities API** integration — 70+ abilities registered as `WP_Ability` objects (WP 6.9+)
- **Floating command panel** accessible via `Ctrl+Shift+C` on any admin page

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.9 |
| PHP | 8.1 |
| PHP extensions | `openssl` (for API key encryption) |

---

## Directory Structure

```
CDW.php                         Main plugin file (singleton bootstrap)
uninstall.php                   Uninstall entry point
includes/
  class-cdw-loader.php          Wires REST API, widgets, hooks
  class-cdw-rest-api.php        Loads all controllers, registers routes
  class-cdw-widgets.php         Registers dashboard widgets
  class-cdw-welcome-page.php    Welcome / onboarding screens
  class-cdw-abilities.php       WordPress Abilities API registration
  functions-uninstall.php       Cleanup logic (extracted for testability)
  controllers/                  REST API controllers (one per resource)
  services/                     Business logic services
    ai/                         AI subsystem (providers, loop, tools, etc.)
      config/                   Config files for abilities, tools, providers
    cli/                        CLI handlers (one file per command group)
  abilities/
    definitions/                Ability class registrations (content, meta, role, pattern)
    builders/                   Ability CLI command builders
    explorer/                   Abilities Explorer admin page assets
  cli/
    class-cdw-cli-command.php   WP-CLI `wp cdw` command
    commands/                   (legacy command files, superseded by service handlers)
  renderers/
    class-cdw-section-renderers.php
src/
  index.js                      React entry point
  components/                   One file per widget/panel
  hooks/                        useAi.js, useCli.js, useFloatingWidget.js
  data/
    store.js                    @wordpress/data store
  styles/                       SCSS
build/                          Compiled webpack output (index.js, index.css, index-rtl.css)
tests/
  php/unit/                     PHPUnit unit tests (27 test files)
  php/integration/              PHPUnit integration tests (6 files, with real DB)
  js/                           Jest tests
patterns/                       Block pattern JSON files
```

---

## Bootstrap & Lifecycle

### Entry point — `CDW.php`

The file defines three global constants:

| Constant | Value |
|---|---|
| `CDW_VERSION` | `3.0.0` |
| `CDW_PLUGIN_DIR` | Absolute path to the plugin folder |
| `CDW_PLUGIN_URL` | URL to the plugin folder |

### Singleton — `CDW_Plugin`

```
CDW()  →  CDW_Plugin::get_instance()  →  new CDW_Plugin()  →  CDW_Loader::run()
```

On first call `CDW()` creates the singleton. The constructor immediately instantiates `CDW_Loader` and calls `run()`.

### Loader — `CDW_Loader::run()`

Execution order on every request:

1. Instantiate `CDW_REST_API` and call `register()` (unconditional — REST_REQUEST is not yet set at `plugins_loaded`).
2. Register WordPress Abilities (`CDW_Abilities::register()`) — bails silently on WP < 6.9.
3. Initialise the Abilities Explorer admin page (`CDW_Abilities_Explorer::init()`).
4. If `is_admin()`: register dashboard widgets, admin menu, enqueue assets, attach floating button.
5. Hook content/menu cache invalidation on `save_post`, `delete_post`, `add_attachment`, `edit_attachment`, `activated_plugin`, `deactivated_plugin`, `switch_theme`.

### Activation hook — `CDW_activate()`

- Deletes legacy `cdw_user_type` option.
- Creates the `cdw_cli_logs` audit table via `CDW_CLI_Service::create_audit_log_table()`.
- Sets default options: `cdw_db_version`, `cdw_cli_enabled`, `cdw_floating_enabled`, `cdw_remove_default_widgets`, `cdw_delete_on_uninstall` (all `true`).

### Deactivation hook — `CDW_deactivate()`

Flushes rewrite rules only. Data is intentionally preserved.

---

## Dashboard Widgets

Dashboard widgets are registered in `CDW_Widgets::manage_dashboard_widgets()` (hooked to `wp_dashboard_setup`).

If the option `cdw_remove_default_widgets` is `true` (default), the following core widgets are removed:
`dashboard_right_now`, `dashboard_activity`, `dashboard_site_health`, `dashboard_primary`, `dashboard_quick_press`.

### Widget permission matrix

| Widget | Capability required | Widget ID |
|---|---|---|
| Help & Support | `edit_posts` | `cdw_help` |
| Site Statistics | `edit_posts` | `cdw_stats` |
| Latest Media | `edit_posts` | `cdw_media` |
| Latest Posts | `edit_posts` | `cdw_posts` |
| Pending Tasks | `manage_options` | `cdw_tasks` |
| Updates | `manage_options` | `cdw_updates` |
| Quick Links | `manage_options` | `cdw_quicklinks` |
| Tools & Other | `manage_options` | `cdw_toolsother` |
| Command Line | `manage_options` + `cdw_cli_enabled` | `cdw_command` |

Each widget renders a `<div class="cdw-widget" data-widget="{type}">` container that React hydrates on `DOMContentLoaded`.

### AI Widget

The AI Assistant widget is rendered inside the `ToolsOtherWidget` or as a standalone panel (depending on the settings). It is only accessible to administrators.

---

## REST API

**Namespace:** `cdw/v1`

All controllers extend `CDW_Base_Controller` which provides:
- `$namespace` = `cdw/v1`
- `check_admin_permission()` — requires `manage_options`
- `check_contributor_permission()` — requires `edit_posts`
- `$protected_options[]` — blocklist of WordPress options that cannot be modified/deleted via API
- Rate limiting helpers using transients
- Standard JSON response methods

### Rate limits

| Endpoint type | Limit |
|---|---|
| Read endpoints | 60 requests / 60 s per user |
| Write endpoints | Lower (per-controller) |
| AI endpoints | 30 requests / 60 s per user (`CDW_AI_Rate_Limiter`) |
| CLI endpoints | 20 requests / 60 s per user |

### Route reference

| Method | Route | Controller | Auth |
|---|---|---|---|
| GET | `/cdw/v1/stats` | `CDW_Stats_Controller` | `edit_posts` |
| GET | `/cdw/v1/media` | `CDW_Media_Controller` | `edit_posts` |
| GET | `/cdw/v1/posts` | `CDW_Posts_Controller` | `edit_posts` |
| GET | `/cdw/v1/users` | `CDW_Users_Controller` | `manage_options` |
| GET | `/cdw/v1/updates` | `CDW_Updates_Controller` | `manage_options` |
| GET/POST | `/cdw/v1/tasks` | `CDW_Tasks_Controller` | `edit_posts` |
| GET/POST | `/cdw/v1/settings` | `CDW_Settings_Controller` | `manage_options` |
| POST | `/cdw/v1/cli` | `CDW_CLI_Controller` | `manage_options` |
| GET/POST | `/cdw/v1/ai/settings` | `CDW_AI_Settings_Controller` | `manage_options` |
| GET | `/cdw/v1/ai/usage` | `CDW_AI_Usage_Controller` | `manage_options` |
| POST | `/cdw/v1/ai/test` | `CDW_AI_Test_Controller` | `manage_options` |
| GET | `/cdw/v1/ai/providers` | `CDW_AI_Providers_Controller` | `manage_options` |
| POST | `/cdw/v1/ai/chat` | `CDW_AI_Controller` | `manage_options` |
| POST | `/cdw/v1/ai/execute` | `CDW_AI_Controller` | `manage_options` |

All REST routes use nonce authentication via `cdwData.nonce` injected into the page, consumed by `@wordpress/api-fetch` middleware.

---

## CLI System

The CLI widget simulates WP-CLI-style commands entirely through WordPress PHP APIs — **no shell is opened**.

### Architecture

```
CommandWidget (React)
  → POST /cdw/v1/cli  {command: "plugin list"}
    → CDW_CLI_Controller
      → CDW_CLI_Service::execute_command()
        → CDW_CLI_Service::get_handler(cmd)
          → CDW_{Cmd}_Handler::handle(args, flags)
```

### Handler classes (`includes/services/cli/handlers/`)

| Handler class | Commands handled |
|---|---|
| `CDW_Plugin_Handler` | `plugin list/status/install/activate/deactivate/update/delete` |
| `CDW_Theme_Handler` | `theme list/info/status/activate/install/update/delete` |
| `CDW_User_Handler` | `user list/get/create/role/delete` |
| `CDW_Post_Handler` | `post list/get/create/status/delete` |
| `CDW_Page_Handler` | `page create` |
| `CDW_Cache_Handler` | `cache flush` |
| `CDW_Media_Handler` | `media list` |
| `CDW_Site_Handler` | `site info/settings/status` |
| `CDW_Option_Handler` | `option get/set/list/delete` |
| `CDW_Transient_Handler` | `transient list/delete/flush` |
| `CDW_Cron_Handler` | `cron list/run` |
| `CDW_Task_Handler` | `task list/create/delete` |
| `CDW_Block_Patterns_Handler` | `block-patterns list/get/apply` |
| `CDW_Skill_Handler` | `skill list/get` |
| `CDW_Comment_Handler` | `comment list/approve/spam/delete` |
| `CDW_Maintenance_Handler` | `maintenance on/off/status` |
| `CDW_Rewrite_Handler` | `rewrite flush` |
| `CDW_Core_Handler` | `core version` |
| `CDW_DB_Handler` | `db size/tables` |
| `CDW_Search_Replace_Handler` | `search-replace <old> <new> --dry-run\|--force` |

### Security guardrails

- All destructive commands require `--force` flag.
- `search-replace` requires either `--dry-run` or `--force`.
- Protected options (see `CDW_Base_Controller::$protected_options`) cannot be modified or deleted via `option set/delete`.
- Users cannot delete their own account.
- Commands `db export` and `db import` are **always blocked** when executed via the AI agentic loop (`CDW_CLI_Service::BLOCKED_AI_COMMANDS`).

### Audit log

Every command execution is written to the `{prefix}cdw_cli_logs` table:

| Column | Type | Description |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `user_id` | `BIGINT UNSIGNED` | WordPress user ID |
| `command` | `TEXT` | Full command string |
| `output` | `LONGTEXT` | Command output |
| `status` | `VARCHAR(20)` | `success` or `error` |
| `created_at` | `DATETIME` | UTC timestamp |

DB schema version is tracked in `cdw_db_version` option (current: `1.2`).

### Command history

Per-user command history is stored in user meta under `cdw_cli_history` (accessed via `CDW_CLI_Service::HISTORY_META_KEY`).

---

## AI Assistant

The AI Assistant is an admin-only feature that allows natural-language management of the WordPress site via an agentic loop with tool-calling.

### Supported providers & models

| Provider | Models |
|---|---|
| **OpenAI** | GPT-4o, GPT-4o Mini, GPT-4 Turbo |
| **Anthropic** | Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus |
| **Google Gemini** | Gemini 2.0 Flash, Gemini 1.5 Pro, Gemini 1.5 Flash |
| **Custom** | Any OpenAI-compatible endpoint (bring your own base URL & model name) |

### Agentic loop — `CDW_Agentic_Loop::execute_agentic_loop()`

Single-turn with tool-use pattern:

```
1. Build system prompt (CDW_AI_Prompts::build_system_prompt)
2. Build messages array (history + new user message)
3. First provider call → may return tool_calls
4. If tool_calls present:
   a. Execute each tool via CDW_CLI_Service
   b. Append tool results to messages
   c. Second provider call → final text answer
5. Return {content, tool_calls_made, usage}
```

The system prompt includes live site context: URL, WP version, PHP version, active plugin count, and current user's login.

### Execution modes

| Mode | Behaviour |
|---|---|
| `confirm` (default) | AI proposes a tool call; user confirms before `/ai/execute` runs it |
| `auto` | AI calls tools automatically without user confirmation |

### API key encryption — `CDW_AI_Encryption`

API keys are stored in user meta, encrypted with **AES-256-CBC**:
- Encryption key is derived from `AUTH_SALT + SECURE_AUTH_SALT` WordPress constants.
- A random 16-byte IV is generated per encryption and prepended to the ciphertext before base64 encoding.
- Backward compatibility: legacy keys stored with a `::` separator are also decryptable.

### Token usage tracking — `CDW_AI_Usage_Tracker`

Per-user cumulative token usage is stored in user meta (`cdw_ai_token_usage`):

```json
{
  "prompt_tokens": 0,
  "completion_tokens": 0,
  "total_tokens": 0,
  "request_count": 0
}
```

### AI subsystem file map

| File | Class | Responsibility |
|---|---|---|
| `class-cdw-ai-service.php` | `CDW_AI_Service` | Facade; provider catalogue, encryption helpers, per-user settings |
| `ai/class-cdw-agentic-loop.php` | `CDW_Agentic_Loop` | Single-turn agentic loop |
| `ai/class-cdw-ai-providers.php` | `CDW_AI_Providers` | HTTP calls to OpenAI, Anthropic, Google |
| `ai/class-cdw-ai-tools.php` | `CDW_AI_Tools` | Tool definitions (loaded from config, formatted for providers) |
| `ai/class-cdw-ai-prompts.php` | `CDW_AI_Prompts` | System prompt builder |
| `ai/class-cdw-ai-encryption.php` | `CDW_AI_Encryption` | AES-256-CBC encrypt/decrypt |
| `ai/class-cdw-ai-rate-limiter.php` | `CDW_AI_Rate_Limiter` | 30 req/60 s per user |
| `ai/class-cdw-ai-usage-tracker.php` | `CDW_AI_Usage_Tracker` | Cumulative token accounting |
| `ai/class-cdw-ai-user-settings.php` | `CDW_AI_User_Settings` | Provider/model/key in user meta |
| `ai/config/class-cdw-ai-providers-config.php` | — | Returns provider/model catalogue array |
| `ai/config/class-cdw-ai-tools-config.php` | — | Returns tool definitions array |
| `ai/config/class-cdw-abilities-config.php` | — | Returns ability definitions array (59 config-driven) |

---

## WordPress Abilities API

CDW registers **70+ abilities** as `WP_Ability` objects using the WordPress Abilities API (introduced in WP 6.9). All abilities are in the `cdw-admin-tools` category and require `manage_options`.

If `cdw_mcp_public` is `true`, a filter sets `meta.mcp.public = true` on all `cdw/*` abilities, exposing them to external AI clients via the MCP Adapter plugin.

### Ability groups

#### Config-driven abilities (59) — `class-cdw-abilities-config.php`

These map directly to CLI commands via `CDW_Ability_CLI_Command_Builders`:

| Group | Abilities |
|---|---|
| Plugin management | `cdw/plugin-list`, `cdw/plugin-status`, `cdw/plugin-activate`, `cdw/plugin-deactivate`, `cdw/plugin-install`, `cdw/plugin-update`, `cdw/plugin-delete` |
| Theme management | `cdw/theme-list`, `cdw/theme-status`, `cdw/theme-activate`, `cdw/theme-install`, `cdw/theme-update`, `cdw/theme-delete` |
| User management | `cdw/user-list`, `cdw/user-get`, `cdw/user-create`, `cdw/user-role`, `cdw/user-delete` |
| Post management | `cdw/post-list`, `cdw/post-get`, `cdw/post-create`, `cdw/post-status`, `cdw/post-delete` |
| Comment management | `cdw/comment-list`, `cdw/comment-approve`, `cdw/comment-spam`, `cdw/comment-delete` |
| Site / core | `cdw/core-version`, `cdw/site-info`, `cdw/site-settings`, `cdw/site-status` |
| Options | `cdw/option-get`, `cdw/option-set`, `cdw/option-list`, `cdw/option-delete` |
| Transients | `cdw/transient-list`, `cdw/transient-delete`, `cdw/transient-flush` |
| Cache | `cdw/cache-flush` |
| Database | `cdw/db-size`, `cdw/db-tables`, `cdw/db-search-replace` |
| Cron | `cdw/cron-list`, `cdw/cron-run` |
| Maintenance | `cdw/maintenance-on`, `cdw/maintenance-off`, `cdw/maintenance-status` |
| Rewrite | `cdw/rewrite-flush` |
| Tasks | `cdw/task-list`, `cdw/task-create`, `cdw/task-delete` |
| Media | `cdw/media-list` |
| Skills | `cdw/skill-list`, `cdw/skill-get` |

#### Inline abilities — definition classes

| Class | Abilities registered |
|---|---|
| `CDW_Role_Abilities` | `cdw/role-list`, `cdw/role-create`, `cdw/role-update`, `cdw/role-delete` |
| `CDW_Pattern_Abilities` | `cdw/block-patterns-get`, `cdw/custom-patterns-list`, `cdw/custom-patterns-get`, `cdw/custom-patterns-apply` |
| `CDW_Content_Abilities` | `cdw/post-set-content`, `cdw/post-get-content`, `cdw/post-append-content`, `cdw/build-page` |
| `CDW_Meta_Abilities` | `cdw/post-meta-get`, `cdw/post-meta-set`, `cdw/post-meta-delete`, `cdw/user-meta-get`, `cdw/user-meta-set`, `cdw/user-meta-delete`, `cdw/term-meta-get`, `cdw/term-meta-set`, `cdw/term-meta-delete` |

### Ability metadata schema

Each ability includes a `meta` array with:

```php
'meta' => [
    'show_in_rest' => true,
    'readonly'     => true|false,
    'idempotent'   => true|false,
    'annotations'  => ['destructive' => false|true],
    'mcp'          => ['public' => true],  // set dynamically if cdw_mcp_public
]
```

---

## Abilities Explorer

An admin page at **Tools → Abilities Explorer** (`tools_page_cdw-abilities-explorer`) that provides a `WP_List_Table`-based UI to browse, inspect, and test all registered CDW abilities.

Only available when `wp_register_ability()` exists (WP 6.9+).

### Classes

| Class | Responsibility |
|---|---|
| `CDW_Abilities_Explorer` | Menu registration, asset enqueueing, AJAX handler |
| `CDW_Abilities_Admin_Page` | Page render orchestration |
| `CDW_Abilities_Table` | `WP_List_Table` subclass listing all abilities |
| `CDW_Ability_Handler` | Invokes an ability by name with given input |
| `CDW_Abilities_List_Renderer` | Renders the list view |
| `CDW_Abilities_Detail_Renderer` | Renders single ability detail |
| `CDW_Abilities_Test_Renderer` | Renders the test/invoke form |
| `CDW_Abilities_Error_Renderer` | Renders error states |
| `CDW_Abilities_URL_Helper` | Generates admin page URLs |

Assets: `explorer.css` + `explorer.js` (plain JS with jQuery, loaded only on the explorer page).  
AJAX action: `cdw_ability_explorer_invoke` (nonce-protected).

---

## Frontend (React)

### Build toolchain

- **Bundler:** `@wordpress/scripts` (webpack under the hood)
- **Entry:** `src/index.js`
- **Output:** `build/index.js`, `build/index.css`, `build/index-rtl.css`, `build/index.asset.php`
- **JSX transpilation:** via Babel (`babel.config.js`)

### Key dependencies

| Package | Purpose |
|---|---|
| `@wordpress/element` | React wrapper |
| `@wordpress/api-fetch` | REST API calls with nonce |
| `@wordpress/data` | Redux-like state management |
| `@wordpress/components` | WordPress UI components |
| `@wordpress/i18n` | Translations |
| `chart.js` + `react-chartjs-2` | Charts in Stats widget |

### Components

| Component | Widget |
|---|---|
| `StatsWidget.js` | Site statistics with charts |
| `TasksWidget.js` | Personal task list (add/delete) |
| `PostsWidget.js` | Recent posts list |
| `MediaWidget.js` | Recent media uploads |
| `HelpWidget.js` | Support email & docs link |
| `UpdatesWidget.js` | Plugin/theme/core updates |
| `QuickLinksWidget.js` | Admin shortcut links |
| `ToolsOtherWidget.js` | Tools panel + AI chat |
| `CommandWidget.js` | In-dashboard CLI terminal |
| `FloatingCommandWidget.js` | Floating CLI overlay |
| `FloatingDashboardButton.js` | Trigger button for floating widget |
| `SettingsPanel.js` | Full settings page React app |
| `command/` | Sub-components for CLI terminal |

### Custom hooks

| Hook | Purpose |
|---|---|
| `useAi.js` | Manages AI chat state, sends `/ai/chat` and `/ai/execute` requests |
| `useCli.js` | Manages CLI terminal state, sends `/cli` requests |
| `useFloatingWidget.js` | Keyboard shortcut (`Ctrl+Shift+C`) and visibility toggle |

### Data store — `src/data/store.js`

A `@wordpress/data` store that holds shared state across widgets (loaded settings, widget data, etc.).

### Global data injection

The PHP loader injects a `window.cdwData` object containing:
- `nonce` — REST API nonce
- `floatingEnabled` — whether the floating widget should be rendered
- `apiUrl` — REST root URL

---

## Settings

All settings are stored in WordPress options (`wp_options`).

### Option reference

| Option key | Default | Description |
|---|---|---|
| `cdw_support_email` | `''` | Email shown in Help widget |
| `cdw_docs_url` | `''` | Documentation URL in Help widget |
| `cdw_font_size` | `''` | Custom font size for widgets |
| `cdw_bg_color` | `''` | Widget background colour |
| `cdw_header_bg_color` | `''` | Widget header background colour |
| `cdw_header_text_color` | `''` | Widget header text colour |
| `cdw_cli_enabled` | `true` | Show/hide Command Line widget |
| `cdw_floating_enabled` | `true` | Enable floating CLI button |
| `cdw_remove_default_widgets` | `true` | Remove stock WP dashboard widgets |
| `cdw_delete_on_uninstall` | `true` | Delete all data on uninstall |
| `cdw_ai_enabled` | `false` | Enable AI Assistant feature |
| `cdw_ai_execution_mode` | `'confirm'` | `confirm` or `auto` |
| `cdw_ai_custom_system_prompt` | `''` | Extra instructions appended to system prompt |
| `cdw_mcp_public` | `false` | Expose abilities to MCP clients |
| `cdw_user_type` | `null` | `developer` or `user` (welcome flow) |
| `cdw_db_version` | — | Tracks audit table schema version |
| `cdw_welcome_notice_dismissed` | — | Whether onboarding notice is dismissed |

### Legacy option support

The settings controller and uninstall function also handle v1/v2 option names for backward compatibility:
`custom_dashboard_widget_email`, `custom_dashboard_widget_docs_url`, `custom_dashboard_widget_font_size`, `custom_dashboard_widget_background_color`, `custom_dashboard_widget_header_background_color`, `custom_dashboard_widget_header_text_color`.

---

## Data Storage

### Database table — `{prefix}cdw_cli_logs`

Created on plugin activation. Tracks every CLI command for audit purposes.

Schema (DB version `1.2`):

```sql
CREATE TABLE {prefix}cdw_cli_logs (
    id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT(20) UNSIGNED NOT NULL,
    command     TEXT NOT NULL,
    output      LONGTEXT,
    status      VARCHAR(20) NOT NULL DEFAULT 'success',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id)
)
```

### User meta

| Meta key | Content |
|---|---|
| `cdw_tasks` | JSON array of task objects `[{id, title, done}]` |
| `cdw_cli_history` | Array of recent commands |
| `cdw_ai_token_usage` | `{prompt_tokens, completion_tokens, total_tokens, request_count}` |
| `cdw_ai_provider` | Selected AI provider slug |
| `cdw_ai_model` | Selected model ID |
| `cdw_ai_api_key` | AES-256-CBC encrypted API key |
| `cdw_ai_execution_mode` | Per-user override of execution mode |

### Task service — `CDW_Task_Service`

Tasks are stored as a JSON-encoded list in user meta (`cdw_tasks`):
- Non-admin users can only manage their own tasks.
- Admins can manage any user's tasks (used by CLI/AI).
- Input is sanitized before saving.

---

## Caching Strategy

CDW uses WordPress transients for caching to minimise database queries.

| Transient key | TTL | Invalidated by |
|---|---|---|
| `cdw_stats_cache` | 60 s | `save_post`, `delete_post`, `add_attachment`, `edit_attachment` |
| `cdw_media_cache_{hash}` | 5 min | Same content hooks + bulk regex query |
| `cdw_posts_cache_{hash}` | 5 min | Same content hooks + bulk regex query |
| `cdw_admin_menu_cache` | — | `activated_plugin`, `deactivated_plugin`, `switch_theme` |
| `cdw_rate_{user_id}` | 60 s | Auto-expiry (rate limiter) |
| `cdw_ai_rate_{user_id}` | 60 s | Auto-expiry (AI rate limiter) |

Cache invalidation uses a single `REGEXP` SQL query on `wp_options` to delete all matching transients in one round-trip.

---

## Security

### Authentication & Authorization

- All REST routes use WordPress capability checks (`manage_options` or `edit_posts`).
- REST nonce is injected server-side into `window.cdwData.nonce` and applied globally via `apiFetch.createNonceMiddleware`.
- Abilities Explorer AJAX uses a separate nonce (`cdw_explorer_nonce`).

### Rate limiting

- Read endpoints: 60 req/min per user (transient-based).
- CLI endpoints: 20 req/min per user.
- AI endpoints: 30 req/min per user.

### Protected options

The following core WordPress options cannot be modified or deleted via the CDW option or CLI controllers:
`siteurl`, `home`, `admin_email`, `blogname`, `blogdescription`, `wp_user_roles`, `active_plugins`, `template`, `stylesheet`, all auth/salt keys, `db_version`, `cron`, all widget options, `users_can_register`, `default_role`.

### API key security

- Provider API keys are encrypted with **AES-256-CBC** before storage in user meta.
- The encryption key is derived from WordPress `AUTH_SALT + SECURE_AUTH_SALT` (site-specific).
- Keys are never returned in API responses — only a `has_key: true|false` boolean is exposed.

### CLI security

- Destructive commands are blocked without the `--force` flag.
- `search-replace` requires `--dry-run` first, then `--force` for the real run.
- `db export` and `db import` are permanently blocked when called via the AI (system-level restriction).
- Users cannot delete their own account through the CLI.

### Input sanitization

All user-supplied data is sanitized per WordPress standards. The `CDW_Task_Service` sanitises task text with `sanitize_text_field`. Pattern names use `sanitize_text_field`. SQL queries use `$wpdb->prepare()` or `$wpdb->esc_like()`.

---

## WP-CLI Integration

CDW registers a `wp cdw` command via `CDW_CLI_Command` (loaded only when `WP_CLI` class exists).

```bash
wp cdw stats           # Display site statistics
wp cdw tasks list      # List current user's tasks
wp cdw tasks create "Buy milk"
wp cdw tasks delete <id>
wp cdw cli "plugin list"   # Run any CDW CLI command
```

---

## Block Patterns

The `patterns/` directory contains block pattern JSON files organized by category:

| Category | Sub-folder |
|---|---|
| About | `patterns/about/` |
| Call to Action | `patterns/cta/` |
| Features | `patterns/features/` |
| Footer | `patterns/footer/` |
| Gallery | `patterns/gallery/` |
| Hero | `patterns/hero/` |
| Pricing | `patterns/pricing/` |
| Services | `patterns/services/` |
| Team | `patterns/team/` |
| Testimonials | `patterns/testimonials/` |

Patterns are discoverable via the `cdw/custom-patterns-list` ability and retrievable with `cdw/custom-patterns-get`. The `cdw/custom-patterns-apply` ability appends a pattern to a post's block content. This system integrates with the `CDW_Pattern_Ability_Service` which recursively scans the patterns directory for JSON files.

---

## Welcome & Onboarding

On first activation (no `cdw_user_type` option set), visiting the CDW admin page shows a welcome screen asking the user to self-identify:

- **Developer** → Shows "Happy WordPressing!" card with links to dashboard and settings. No further onboarding.
- **User** → Multi-step onboarding wizard guides through initial configuration (support email, docs URL, appearance, etc.).

The choice is stored in `cdw_user_type`. A "Change my choice" link lets users reset it. Implemented as standalone template functions in `class-cdw-welcome-page.php`.

A dismissable admin notice also appears after activation (`cdw_welcome_notice_dismissed` in options).

---

## Uninstall & Data Cleanup

Uninstall is handled by `uninstall.php` → `cdw_do_uninstall()` (in `functions-uninstall.php`).

Cleanup only runs if `cdw_delete_on_uninstall` is `true` (default). Removed data includes:

- **All CDW options** (current and legacy v1/v2 names)
- **Named transients:** `cdw_stats_cache`, `cdw_admin_menu_cache`
- **Pattern transients:** `cdw_media_cache_*`, `cdw_posts_cache_*` (SQL LIKE queries)
- **Rate limit transients:** `cdw_cli_rate_*`, `cdw_ai_rate_*`
- **User meta:** `cdw_tasks`, `cdw_cli_history`, `cdw_ai_token_usage`, and all AI settings meta per user
- **Custom table:** `{prefix}cdw_cli_logs` is dropped

---

## Testing

### PHP unit tests — `tests/php/unit/`

27 test files using PHPUnit 9.6 with Brain\Monkey (WP function mocking) and Mockery:

| Test file | Covers |
|---|---|
| `AbilitiesTest.php` | Ability registration, MCP exposure |
| `AiControllerTest.php` | /ai/chat and /ai/execute endpoints |
| `AiEncryptionTest.php` | AES-256-CBC encrypt/decrypt |
| `AiRateLimiterTest.php` | AI rate limit transient logic |
| `AiServiceTest.php` | Provider abstraction, key management |
| `AiUsageTrackerTest.php` | Token recording and reset |
| `AiUserSettingsTest.php` | Per-user settings in user meta |
| `BaseControllerTest.php` | Permission helpers, protected options |
| `BaseControllerSecurityTest.php` | Security edge cases |
| `CliServiceCoreTest.php` | CLI execution, audit logging |
| `CliServiceHandlersTest.php` | Individual command handlers |
| `CliControllerTest.php` | REST CLI endpoint |
| `CliCommandTest.php` | WP-CLI `wp cdw` command |
| `CliCommandCatalogTest.php` | Command catalog completeness |
| `AbilityCliCommandBuildersTest.php` | Config-to-ability mapping |
| `LoaderTest.php` | Hook registration |
| `MediaControllerTest.php` | Media REST endpoint |
| `PostsControllerTest.php` | Posts REST endpoint |
| `RestApiTest.php` | Controller loading |
| `SettingsControllerTest.php` | Settings GET/POST |
| `StatsControllerTest.php` | Stats endpoint |
| `StatsServiceTest.php` | Stats caching |
| `TaskServiceTest.php` | Task CRUD |
| `TasksControllerTest.php` | Tasks REST endpoint |
| `UninstallTest.php` | Uninstall cleanup |
| `UpdatesControllerTest.php` | Updates endpoint |
| `WidgetsTest.php` | Widget registration |

### PHP integration tests — `tests/php/integration/`

6 test files that run against a real WordPress database (uses `wp-phpunit/wp-phpunit`):

| Test file | Covers |
|---|---|
| `AiSettingsRoundTripTest.php` | Save/retrieve AI settings end-to-end |
| `AuditLogTest.php` | CLI audit log table creation and writes |
| `CliRoundTripTest.php` | CLI commands against real WP APIs |
| `RestRoutesTest.php` | REST route registration verification |
| `SettingsRoundTripTest.php` | Settings save/restore |
| `TaskRoundTripTest.php` | Task create/list/delete |

### JavaScript tests — `tests/js/`

Jest with `@wordpress/jest-preset-default` and `@testing-library/react`.  
CSS/SCSS imports are mocked via `tests/js/__mocks__/styleMock.js`.

### Running the tests

```bash
# PHP unit tests
composer test:php
# or
vendor/bin/phpunit

# PHP integration tests (requires WP test environment)
vendor/bin/phpunit --config phpunit-integration.xml

# JavaScript tests
npm run test:js
```

---

## Code Quality

### PHP linting — PHPCS

```bash
composer phpcs
# Runs: phpcs --standard=WordPress includes/ CDW.php uninstall.php
```

WordPress Coding Standards (WPCS 3.1) enforced via phpcs.xml.

### Static analysis — PHPStan

```bash
composer phpstan
# Level: 6 — analyses includes/ and CDW.php
```

Configuration: `phpstan.neon`. Notable ignores:
- WP_CLI static calls (optional dependency, not in Composer)
- `wp_optimize()` (optional WP Optimize plugin)
- Abilities API functions (WP 6.9 feature, not in stubs)

---

## Development Workflow

### Initial setup

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install
```

### Development

```bash
# Start webpack in watch mode
npm run dev
# or
npm start

# Production build
npm run build
```

### Workflow summary

1. PHP business logic lives in `includes/` (PSR-4 autoloading only for tests; production uses `require_once`).
2. React frontend lives in `src/`, compiled to `build/` via `@wordpress/scripts`.
3. Every REST controller has a corresponding unit test.
4. AI tools are config-driven — add a new tool by editing `class-cdw-ai-tools-config.php`.
5. New abilities should be added to `class-cdw-abilities-config.php` (CLI-mapped) or as inline abilities in the appropriate definition class (`CDW_Content_Abilities`, `CDW_Meta_Abilities`, etc.).
6. The audit log schema version is `CDW_CLI_Service::DB_VERSION`. Bump it and add a migration in `create_audit_log_table()` for schema changes.

=== WP AI Connector ===
Contributors: ljutaev
Tags: ai, rest api, mcp, claude, chatgpt
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight REST API & MCP connector for WordPress. Manage your site from Claude, ChatGPT, Cursor, and the terminal.

== Description ==

WP AI Connector is a lightweight, vendor-neutral connector that lets AI agents (Claude Code, Claude Desktop, ChatGPT, Cursor) and developers (curl, scripts, CI) manage a WordPress site through a clean REST API and an optional MCP wrapper.

= Key features =

* Per-user API keys with scoped capabilities.
* Read-only by default; opt-in writes through dedicated endpoints.
* Dangerous SQL keywords blocked out of the box.
* Modular architecture: core for WordPress, optional modules for WooCommerce, Yoast SEO, and Advanced Custom Fields.
* Discovery via `GET /manifest` plus a ready-to-paste skill at `GET /skill`.
* Token-efficient: curl-friendly REST is the primary interface; the MCP wrapper is optional.

= Privacy =

WP AI Connector does not send data to any third party. All AI calls are initiated by your AI client (your Claude, ChatGPT, or Cursor session); the plugin only responds to authenticated REST requests originating from your own tools.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wp-ai-connector/` or install through the Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → WP AI Connector** to generate your first API key.
4. Paste the key into your AI client or terminal and start using it.

== Frequently Asked Questions ==

= Is this the official WordPress AI plugin? =

No. WP AI Connector is an independent open-source project, not affiliated with WordPress.org or Automattic. WordPress is a registered trademark of the WordPress Foundation.

= How does this differ from the official WordPress MCP Adapter? =

WP AI Connector is REST-first (low token cost, works from any HTTP client) with an optional MCP wrapper. The official MCP Adapter is MCP-first. They can coexist on the same site.

= Will my data leave my server? =

Not unless you ask an AI client to read it and respond. The plugin itself never initiates outbound calls.

== Changelog ==

= 0.1.0 =
* Initial repository scaffold.

== Upgrade Notice ==

= 0.1.0 =
Initial release.

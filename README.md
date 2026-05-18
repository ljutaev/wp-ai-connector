<div align="center">

# WP AI Connector

**Lightweight REST API & MCP connector for WordPress.**
**Manage your site from Claude, ChatGPT, Cursor, and the terminal.**

[![License: GPL v2+](https://img.shields.io/badge/License-GPL_v2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.1+-777BB4)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.6+-21759B)](https://wordpress.org/)
[![CI](https://github.com/ljutaev/wp-ai-connector/actions/workflows/ci.yml/badge.svg)](https://github.com/ljutaev/wp-ai-connector/actions/workflows/ci.yml)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-2.1-blueviolet.svg)](CODE_OF_CONDUCT.md)

</div>

---

> **Status:** Pre-alpha. Scaffold only — no implementation yet. Design specs land in [`docs/superpowers/specs/`](docs/superpowers/specs/) before any feature ships.

## Why

WordPress 7.0 shipped the official **Connectors API**, but most AI-for-WordPress plugins are heavy MCP servers that burn tokens on every request by loading a full tool manifest into every conversation. WP AI Connector takes the opposite approach: a **lightweight REST API** that an AI agent (or any HTTP client) can call directly with curl, paired with an **optional thin MCP wrapper** for native Claude Desktop integration.

- **Token-efficient.** REST endpoints expose only what's needed, when it's needed.
- **Vendor-neutral.** Works with Claude Code, Claude Desktop, ChatGPT, Cursor, or any curl-capable client.
- **Plug-and-play.** Generate a scoped API key in the admin UI, paste it into your AI tool, done.
- **Modular.** Core covers WordPress (posts, pages, users, options, SQL). Optional modules for WooCommerce, Yoast SEO, and Advanced Custom Fields auto-load only when their host plugin is active.

## Quick start

### Install

```sh
# Once published on wp.org, install from the Plugins screen.
# For now, install from GitHub:
git clone https://github.com/ljutaev/wp-ai-connector.git wp-content/plugins/wp-ai-connector
```

### Generate a key

1. **Activate** WP AI Connector in `Plugins`.
2. Open **Settings → WP AI Connector**.
3. Click **Generate key** — by default the key is **read-only**.
4. Optionally tighten the scope (for example `posts:read` only, or `woo:orders:write` only).

### Use from the terminal

```sh
curl https://your-site.com/wp-json/wp-ai-connector/v1/posts \
  -H "Authorization: Bearer YOUR_KEY"
```

### Discover what your site exposes

```sh
curl https://your-site.com/wp-json/wp-ai-connector/v1/manifest \
  -H "Authorization: Bearer YOUR_KEY"
```

The manifest lists every active module, every route it exposes, capability requirements, and ready-to-copy curl examples.

### Hand the manifest to Claude Code as a skill

```sh
curl https://your-site.com/wp-json/wp-ai-connector/v1/skill \
  -H "Authorization: Bearer YOUR_KEY" \
  > .claude/skills/my-site.md
```

Claude now knows your site's exact capabilities — without paying the per-turn token cost of an MCP manifest.

## Modules

| Module | Status | What it exposes |
|---|---|---|
| Core | Designing | Posts, pages, users, options, SQL SELECT, manifest, skill |
| WooCommerce | Planned | Orders, products, customers, coupons |
| Yoast SEO | Planned | Per-post SEO meta, sitemap status, redirects |
| Advanced Custom Fields | Planned | Field groups, post meta read/write |

Want a module for the plugin you depend on? Open a feature request or send a PR — see [CONTRIBUTING.md](CONTRIBUTING.md#adding-a-module).

## Security model

- API keys are **per WordPress user**, **per capability scope**, and **revocable** from the admin.
- The plugin is **read-only by default**. Writes require an explicit capability on the key.
- A **deny-list of dangerous SQL** (INSERT/UPDATE/DELETE/DROP/etc.) is enforced even on the generic SQL endpoint.
- All requests are **logged** for audit. Logs are visible in the admin and exportable.

Found a security issue? See [SECURITY.md](SECURITY.md) for the disclosure process.

## How it compares

| | WP AI Connector | Royal MCP | Vibe AI | Official MCP Adapter |
|---|---|---|---|---|
| Primary protocol | REST (with optional MCP wrapper) | MCP | MCP | MCP |
| Token cost per request | Minimal (only the called endpoint) | Full manifest each turn | Full manifest each turn | Full manifest each turn |
| Works from curl/terminal | Natively | Via gateway | Via gateway | Via gateway |
| Modular extensions | Auto-detect | Built-in tools | Built-in tools | Via Abilities API |
| Scoped per-user keys | Yes | Yes | No | Via WP roles |

## Documentation

- [Design specifications](docs/superpowers/specs/) — architectural decisions and module designs
- [Contributing](CONTRIBUTING.md) — local development, coding standards, PR process
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Security Policy](SECURITY.md)
- [Changelog](CHANGELOG.md)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Disclaimer

WP AI Connector is an independent open-source project. It is **not affiliated with WordPress.org, Automattic, Anthropic, OpenAI, or any other vendor**. "WordPress" is a registered trademark of the WordPress Foundation.

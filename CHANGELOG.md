# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.5.0] - 2026-05-25

### Added — Yoast SEO module v2

- `YoastDb` shared helper — direct `$wpdb` access to all four Yoast tables (`wp_yoast_indexable`, `wp_yoast_seo_links`, `wp_yoast_primary_term`, `wp_yoast_indexable_hierarchy`)
- `GET /yoast/posts/{id}` — expanded from ~12 to 40+ fields: schema_page_type, schema_article_type, is_cornerstone, estimated_reading_time, advanced robots (noarchive/noimageindex/nosnippet), inclusive_language_score, breadcrumb_title, link_count, incoming_link_count, primary_terms, post_status, is_public, is_protected
- `POST /yoast/posts/{id}` — write cornerstone toggle, schema types, advanced robots flags (packed into `_yoast_wpseo_meta-robots-adv` comma list as Yoast expects)
- `GET /yoast/posts/{id}/internal-links` — outgoing + incoming internal links from `wp_yoast_seo_links` graph, joined with indexable for source/target titles and permalinks
- `GET /yoast/health` — site-wide SEO health summary: total indexables, cornerstone count, missing focus keyphrase/title/description counts, avg SEO and readability scores
- `GET /yoast/cornerstone` — high-value posts ordered by incoming link count
- `GET /yoast/needs-improvement` — published posts with SEO or readability below configurable threshold (default 40)
- `GET /yoast/orphaned` — posts with zero incoming internal links (linking candidates)
- `GET /yoast/search-appearance` — title templates and noindex defaults for home/author/archive/search/404 plus every public post_type and taxonomy
- `GET /yoast/settings` — extended with breadcrumbs config, LinkedIn/Instagram/YouTube/Pinterest URLs, RSS footer, sitemap enable flag
- YoastSeoModule version bumped to `0.2.0`

## [0.4.0] - 2026-05-20

### Added

- `MediaController` v2 — `POST /media/{id}` (update alt/caption/title), `DELETE /media/{id}` (trash or permanent), `POST /media/upload` (multipart); list returns `width`, `height`, `thumbnail`; detail returns all registered `sizes` with dimensions
- `MenusController` v2 — `GET /menu-locations` (theme locations with assigned menu); list includes `locations[]`; single menu returns nested item tree with resolved URLs, `type`, `target`, `classes`
- `YoastSeoModule` — conditional module (activates when Yoast SEO active)
  - `GET /yoast/posts/{id}` — reads `wp_yoast_indexable` (Yoast 14+), falls back to `_yoast_wpseo_*` postmeta
  - `POST /yoast/posts/{id}` — update `seo_title`, `meta_description`, `focus_keyphrase`, `canonical`, `noindex`, `og_*`, `twitter_*`
  - `GET /yoast/terms/{id}` — SEO data for taxonomy terms
  - `GET /yoast/settings` — company/person entity, social profiles, separator

## [0.3.0] - 2026-05-19

### Added

- `PostsController` — `GET /posts`, `GET /posts/{id}`, `POST /posts/{id}` (update title/content/status)
- `UsersController` — `GET /users`, `GET /users/{id}` with role filter
- `TermsController` — `GET /terms` (any taxonomy), `GET /terms/{id}`; validates taxonomy existence
- `CommentsController` — `GET /comments`, `GET /comments/{id}`, `POST /comments/{id}` (approve/unapprove/spam)
- `MediaController` — `GET /media`, `GET /media/{id}` with mime_type filter
- `MenusController` — `GET /menus`, `GET /menus/{id}` with full item tree
- `PluginsController` — `GET /plugins` with active/inactive filter
- `ThemesController` — `GET /themes`, `GET /themes/active`
- `TransientsController` — `GET /transients/{key}` (expired flag), `DELETE /transients/{key}`, `POST /transients/{key}/flush`
- `CronController` — `GET /cron` sorted by next run; supports hook filter
- `tests/Stubs/WpStubs.php` — minimal WP class stubs for unit tests (WP_REST_Controller, WP_REST_Response, WP_Error, etc.)
- All 10 controllers wired in `CoreModule` with DI container and manifest routes (22 total routes documented)
- Version bumped to `0.3.0`

## [0.2.0-alpha] - 2026-05-19

### Added

- Minimal DI container (`WPAIConnector\Core\Container`)
- PHP and WordPress version conditionals (`PHPVersionConditional`, `WordPressVersionConditional`)
- Module system (`ModuleInterface`, `AbstractModule`, `ModuleRegistry`) with conditional loading
- Bearer-token authentication: `BearerHeaderReader`, `ApiKeyFactory`, `ApiKeyRepository`, `ApiKeyAuthenticator`, `RestAuthBridge`
- `GeneratedKey` value object for factory output
- Capability-scope matcher (`KeyScope`) supporting exact match, resource wildcards (`posts:*`), and deep wildcards (`woo:*`)
- Custom table `wp_wpaic_keys` via `Migrator` + `Installer` (runs on activation and upgrade)
- `CoreModule` with curated `OptionsAllowlist` (~45 safe WordPress options, sensitive keys hard-blocked)
- `OptionsController` — `GET /wp-ai-connector/v1/options/{key}` and `POST /wp-ai-connector/v1/options/{key}`
- `ManifestController` — `GET /wp-ai-connector/v1/manifest` with 60-second transient cache
- `SkillController` — `GET /wp-ai-connector/v1/skill` returns Markdown skill file
- `ManifestGenerator` and `SkillGenerator` — build structured JSON manifest and AI-ready Markdown skill
- Plugin bootstrap (`Plugin::boot()`) wiring activation hook, `plugins_loaded`, `init`, `rest_api_init`, and `cli_init`
- WP-CLI command `wp ai-connector key create --user --label --scope`

## [0.1.0] - 2026-05-18

### Added

- Initial repository scaffold: README, LICENSE (GPL-2.0-or-later), `.gitignore`, `.gitattributes`, `.editorconfig`.
- `composer.json` with PSR-4 autoloading (`WPAIConnector\\`), PHPUnit, PHPStan, and WordPress Coding Standards as dev dependencies.
- PHPCS, PHPStan (level 8), and PHPUnit configurations.
- Plugin header file (`wp-ai-connector.php`) with metadata only.
- `readme.txt` for wp.org plugin directory.
- GitHub Actions CI workflow (lint, static analysis, test matrix across PHP 8.1–8.4 and WordPress 6.6–latest).
- Issue templates (bug report, feature request) and PR template.
- `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`.

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

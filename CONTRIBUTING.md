# Contributing to WP AI Connector

Thanks for your interest! WP AI Connector welcomes pull requests, bug reports, module contributions, and design feedback.

## Code of Conduct

This project adheres to the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold it.

## Getting started

### Prerequisites

- **PHP 8.1** or newer
- **Composer 2.x**
- **WordPress 6.6** or newer (for integration testing)
- A local WordPress environment — Local by Flywheel, DDEV, `wp-env`, or similar.

### Setup

```sh
git clone https://github.com/ljutaev/wp-ai-connector.git
cd wp-ai-connector
composer install
```

Symlink (or copy) the repo into `wp-content/plugins/wp-ai-connector` in your local WordPress install, then activate the plugin from the **Plugins** screen.

### Daily workflow

```sh
composer lint        # PHP CodeSniffer with WordPress Coding Standards
composer lint:fix    # auto-fix what can be auto-fixed
composer analyse     # PHPStan level 8
composer test        # PHPUnit (unit + integration)
composer check       # all of the above
```

CI runs the same suite. Fix it locally before opening a PR.

## Pull requests

1. **Fork** the repository and create a topic branch from `main`.
2. **Add tests** for any behaviour change. Aim for >80% coverage on `src/`.
3. **Run `composer check`** locally.
4. **Write a clear PR description**: what changed, why, and how to test.
5. **Reference any related issue** with `Closes #N`.

We squash-merge by default, so commit titles inside the PR don't need to be perfect — but the PR title should be.

## Adding a module

WP AI Connector is built around auto-detected modules in `src/Modules/`. To add support for a third-party plugin (Polylang, GravityForms, etc.):

1. Create `src/Modules/YourModule/`.
2. Implement the module interface (see `src/Modules/Core/` once it lands).
3. Auto-detect the target plugin via `is_plugin_active()` or a class/function existence check.
4. Register routes only when the target is detected.
5. Add tests in `tests/Modules/YourModule/`.
6. Update the module table in `README.md` and add a `CHANGELOG.md` entry.

The module-system design lives in `docs/superpowers/specs/`.

## Reporting bugs

Open an issue using the **Bug report** template. Include:

- WordPress version
- PHP version
- WP AI Connector version
- Other active plugins that might interact
- Reproduction steps
- Expected vs. actual behaviour
- Relevant curl output or logs

## Reporting security issues

**Do not open a public GitHub issue** for security vulnerabilities. See [SECURITY.md](SECURITY.md) for the responsible-disclosure process.

## License

By contributing, you agree that your contributions will be licensed under the project's [GPL-2.0-or-later license](LICENSE).

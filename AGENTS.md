# AGENTS.md

Working agreement for AI assistants (Claude Code, Cursor, Codex, ChatGPT, etc.) collaborating on **WP AI Connector**. Humans are welcome to read this too — it's also the project's operating manual.

This file is the canonical source for project conventions. If it conflicts with anything else, this file wins (unless the human explicitly overrides).

---

## 1. Project at a glance

- **Name:** WP AI Connector
- **wp.org slug:** `wp-ai-connector`
- **What it is:** A lightweight REST API and optional MCP connector for WordPress. Lets AI agents (Claude, ChatGPT, Cursor) and developers (curl, scripts, CI) manage a WordPress site through scoped API keys.
- **Why it exists:** Most AI-for-WordPress plugins are MCP servers that burn tokens by loading a full tool manifest on every turn. WP AI Connector exposes a curl-friendly REST API and only adds an MCP wrapper as an optional, separate layer.
- **License:** GPL-2.0-or-later (required by wp.org).
- **Status:** Pre-alpha. Scaffold only as of 2026-05-18. No implementation code yet — design specifications come first.

## 2. Decisions already made (do not re-litigate)

| Decision | Value | Reason |
|----------|-------|--------|
| Primary integration channel | REST API | Token-efficient; works from curl, scripts, any AI tool. |
| Secondary channel | MCP wrapper, optional | For Claude Desktop / ChatGPT MCP users who want it. |
| API style | Hybrid: generic `POST /query` (SELECT-only) + dedicated domain endpoints | SQL for ad-hoc reads, typed routes for writes. |
| Authentication | Per-WordPress-user API keys with scoped capabilities | Familiar WP model, audit trail, revocable. |
| Default key permissions | Read-only | Writes require explicit capability on the key. |
| SQL safety | Hard-coded deny-list (INSERT/UPDATE/DELETE/DROP/etc.) enforced server-side | Even on the generic SQL endpoint. |
| Architecture | Modular core with auto-detected modules in `src/Modules/` | `core/`, `modules/woocommerce/`, `modules/yoast/`, `modules/acf/` planned. |
| Discovery | `GET /manifest` (dynamic JSON) and `GET /skill` (ready-to-paste skill.md) | One curl call tells an AI what the site can do. |
| Min PHP | 8.1 | Modern type system, readonly properties. |
| Min WordPress | 6.6 | Two minor versions back from 7.0. |
| Documentation language | English | Maximum OSS reach. Conversation can be in any language. |
| Coding standards | WordPress Coding Standards + PHPStan level 8 | wp.org expectation. |

If a decision needs to change, update this table in the same commit that changes the behaviour.

## 3. How we work

### 3.1 Design before code

For any non-trivial feature, **write the design first** in `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`, get human approval, then implement.

Order of operations:
1. **Brainstorm** the feature with the human until requirements are clear.
2. **Write the spec** to `docs/superpowers/specs/`.
3. **Wait for human approval** of the spec.
4. **Write the plan** (decomposition into steps with verification gates).
5. **Implement** following TDD where applicable.

Do not skip steps 2–4 because something "seems simple."

### 3.2 Test-driven development

For business logic: **write the failing test first, then the implementation.** Run tests, watch them go red, make them green, refactor. Skip TDD only for trivial wiring (autoloader hooks, plugin headers, etc.).

Test pyramid: lots of unit tests, fewer integration tests, even fewer end-to-end tests. Unit tests do not hit the database. Integration tests hit a real test database, not mocks.

### 3.3 Verification before claiming done

Run `composer check` (lint + analyse + test) before saying a task is complete. "It compiles" is not done. "Tests pass and I ran them" is done.

## 4. Code conventions

### 4.1 PHP

- **PSR-4 autoloading.** Namespace root `WPAIConnector\\` maps to `src/`. Tests use `WPAIConnector\\Tests\\` mapped to `tests/`.
- **WordPress Coding Standards** for braces, spacing, and naming (`snake_case` functions, `PascalCase` classes).
- **PHPStan level 8** with WordPress stubs from `szepeviktor/phpstan-wordpress`. No `@phpstan-ignore` without a comment explaining why.
- **`declare(strict_types=1);`** at the top of every PHP file in `src/`.
- **Final classes by default.** Open for extension only when there's a concrete reason.
- **No globals.** Inject dependencies through constructors. The DI wiring lives in a single bootstrap class.

### 4.2 REST endpoints

- Namespace: `wp-ai-connector/v1`.
- One controller class per resource (`PostsController`, `OrdersController`, …).
- Every endpoint validates input with `rest_validate_request_arg` or an explicit schema.
- Every endpoint returns either a `WP_REST_Response` or a `WP_Error`. Never raw arrays.
- HATEOAS links (`_links.self` at minimum) on every entity response.
- Pagination on collection responses uses standard `X-WP-Total` / `X-WP-TotalPages` headers and a `?page=` / `?per_page=` query string.

### 4.3 Security

- All inputs sanitised before use; all outputs escaped at the boundary.
- Capability checks via `current_user_can()` *and* the key's scope — both must pass.
- SQL via `$wpdb->prepare()` only. The generic `/query` endpoint enforces the keyword deny-list before execution.
- Secrets (API keys) stored hashed in the database; the plaintext is shown once at generation time and then is unrecoverable.

## 5. Commits

- **Conventional Commits** prefixes: `chore:`, `feat:`, `docs:`, `ci:`, `fix:`, `refactor:`, `test:`, `style:`.
- One concern per commit. A linter config change does not belong in the same commit as a feature.
- Commit title under ~72 characters; body wraps at ~72.
- The body explains *why* if the *what* isn't obvious from the diff.
- **Do not add `Co-Authored-By: Claude` (or any AI assistant) trailers.** Commits should appear authored by the human contributor alone.
- Reference issues with `Refs #N` or `Closes #N` when applicable.

## 6. Pull requests

- Branch from `main`. Topic branch names use kebab-case: `feat/posts-controller`, `fix/key-scope-bypass`.
- Open the PR with the template filled in. The checklist must be honest — don't tick "tests pass" if you didn't run them.
- Squash-merge by default. The PR title becomes the merge commit title, so write it cleanly.
- Add a `CHANGELOG.md` entry under `[Unreleased]` for any user-visible change.

## 7. What is *not* in scope right now

To avoid scope creep during pre-alpha:

- Frontend / Gutenberg blocks. The plugin has no end-user-facing UI on the site itself.
- Custom auth providers beyond the per-user API key. OAuth, SAML, etc. are post-1.0.
- Outbound calls to AI providers. The plugin is a pure server — AI clients call us, we never call them.
- Plugin marketplace integrations beyond Woo / Yoast / ACF in the initial module set.

If a contribution touches any of these, discuss in an issue before writing code.

## 8. Communication

- Conversation language: any (Ukrainian, English, etc.). Code, comments, docs, and commit messages: **English**.
- When asking the human for input, ask **one question at a time** with concrete options where possible.
- When proposing changes, **explain the trade-off** and recommend a default. Do not present a menu without an opinion.
- Be precise about uncertainty. "I think X" vs. "X is documented at Y" are different statements.

## 9. Repository layout (target)

```
wp-ai-connector/
├── .github/                     # workflows, issue + PR templates
├── docs/superpowers/specs/      # design specifications (must exist before implementation)
├── src/                         # PSR-4 source (WPAIConnector\)
│   ├── Core/                    # plugin bootstrap, container, router glue
│   ├── Auth/                    # API key management, capability scoping
│   ├── REST/                    # base controllers, response envelope, manifest
│   └── Modules/                 # auto-detected modules
│       ├── Core/                # WP posts, users, options, SQL
│       ├── WooCommerce/         # optional, loads only if WC is active
│       ├── Yoast/               # optional, loads only if Yoast is active
│       └── ACF/                 # optional, loads only if ACF is active
├── tests/
│   ├── Unit/                    # mirrors src/
│   └── Integration/             # real DB, real WP
├── languages/                   # .pot file + translations
├── wp-ai-connector.php          # plugin entry point
└── readme.txt                   # wp.org plugin directory listing
```

Folders not yet created are placeholders until their first commit lands.

## 10. When in doubt

- Read this file first.
- Read the latest spec in `docs/superpowers/specs/`.
- Ask the human. One concrete question is better than three speculative paragraphs.

---

*Last updated: 2026-05-18 — initial scaffold session.*

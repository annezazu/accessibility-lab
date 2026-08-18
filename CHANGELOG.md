# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/), and will adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - TBD

### Added

- `validation_api_register_namespace()` global function so a plugin can register a human-readable display name once, instead of repeating `plugin_title` on every check.
- Optional `title` field on check registration, separate from the `name` slug, used to improve readability in the Validation settings table.
- `docs/features/validation-api.md` — a full integration guide covering the scope/severity model, registering namespaces and checks in PHP, the JS filter contract for all three scopes, and patterns for loading validation filter scripts.

### Changed

- Validation Settings screen rebuilt as a single top-level `DataViews` table (replacing the previous per-namespace submenu pages), aligned with how WordPress core builds its own DataViews-based admin screens (`@wordpress/admin-ui`'s `Page` component, inline critical CSS to avoid layout shift, loading state).
- Introduced `Check_Key`, a stable per-check id (scope + namespace + name + target) used for override storage, REST `id` fields, and DataViews row ids — replacing a hand-built string key that could collide when the same check name was registered on two different block types.
- `Rest_Controller` rewritten on top of `WP_REST_Controller` with a public item schema; override updates are now cross-checked against the live registry's current configurable checks, discarding overrides for checks that no longer exist or aren't configurable.
- Severity override sentinel renamed from `'disabled'` to `'none'` in stored settings — **note:** no migration path was added for existing stored options using the old value.

### Fixed

- `editor.validateBlock` now also receives `clientId`, so checks can inspect a block's inner-block structure, not just its attributes.
- Fixed a persistent "Updating failed" notice that remained visible in the editor after the validation error that triggered it was resolved.
- Autosave and preview saves are now exempted from the validation publish-lock safety net, preventing spurious failure notices during those flows.
- Reduced the Validation Settings bundle from ~1.9MB to ~520KB by importing `@wordpress/dataviews` from its package root instead of the `/wp` subpath, guarded with a `try`/`catch` and a new `ErrorBoundary` component since this relies on core's private-APIs allowlist.

### Developer

- Added a PHPCS + PHPStan baseline (`composer.json`, `phpcs.xml.dist`, `phpstan.neon.dist`) for PHP linting and static analysis.
- Added ESLint, Stylelint, and `lint-staged` configuration for JS/CSS linting (`eslint.config.js`, `.stylelintrc.json`).
- Added `.editorconfig` and `.nvmrc` to pin editor formatting and the Node version across the toolchain.
- Added a Husky pre-commit hook running lint-staged and PHPStan — currently disabled pending team review, since enabling it now would block commits against the existing codebase's lint state.
- Added a CI GitHub Actions workflow (`.github/workflows/ci.yml`) running PHPCS/PHPStan across PHP 8.1–8.4 and JS/CSS lint + build — currently manual-trigger only (`workflow_dispatch`), pending review of the intended `push`/`pull_request` triggers.
- Added GitHub issue templates (bug report, feature request), a pull request template, and a base `CONTRIBUTING.md` guide for contributors.
# Contributing Guidelines

Welcome to the Accessibility Lab plugin! Here you'll find information on how to get started contributing to the plugin.

## Getting Started

### Prerequisites

- Composer
- Node.js — 24.21.0 or newer (the `24` line, matching WordPress core; the exact version is pinned in `.nvmrc`)
- npm — 11.19.0 or newer (the `11` line; `engines` + `.npmrc` `engine-strict` enforce this, and `devEngines` in `package.json` is the source of truth)
- Docker, only if you plan to use the bundled `wp-env` environment

### Local development setup

1. **Clone the repository:**

The repository *is* the plugin directory, so the simplest setup is to clone it
straight into the `wp-content/plugins/` of a WordPress install you already have
(WordPress Studio, Local, or any other local server):

```bash
cd path/to/wp-content/plugins
git clone https://github.com/WordPress/accessibility-lab.git accessibility-lab
cd accessibility-lab
```

2. **Install dependencies and build assets:**

If you use `nvm`, run `nvm install && nvm use` first to switch to the pinned
Node version. `npm install` will refuse to run on an unsupported Node or npm
(`engine-strict`).

```bash
nvm install && nvm use
composer install && npm ci && npm run build
npx husky
```

`npx husky` enables the [pre-commit hook](#quality-checks). You only need to
run it once per clone.

3. **Activate the plugin:**

Through WordPress admin or via WP-CLI:

```bash
wp plugin activate accessibility-lab
```

Alternatively, `npm run env:start` spins up a throwaway Docker-based WordPress
with the plugin mounted and debugging enabled. See the
[README](README.md) for the full list of scripts.

### Quality checks

The codebase is held to ESLint, Stylelint, PHPCS (WordPress Coding Standards)
and PHPStan (level 8). Before you open a pull request, run the full suite and
auto-fix what can be fixed:

```bash
# Lint everything — JS, CSS, PHPCS, PHPStan
npm run lint

# Auto-fix what can be fixed
npm run format
```

The same checks are enforced automatically:

- **On commit:** the pre-commit hook lints your staged files with ESLint,
  Stylelint and PHPCS, then runs PHPStan across the whole project, since it
  needs the full codebase for context. If any check fails, the commit is
  aborted.
- **In CI:** every pull request and every push to `main` runs PHPCS and PHPStan
  against PHP 8.1–8.4, plus ESLint, Stylelint, a `package-lock.json` sync check
  and the production build on the pinned Node version.

If a rule is genuinely wrong for a specific line, suppress it for that line
only, name the rule, and give the reason, so reviewers can check it. The same
pattern works with `eslint-disable-next-line` and `stylelint-disable-next-line`:

```php
// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- array key in a check definition, not a query arg.
```

There is no automated test suite yet. Please describe how you verified your
change in the pull request's testing instructions.

### Coding standards

All code must follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/). This ensures consistency across the WordPress ecosystem and makes the codebase maintainable.

### PHP compatibility

The minimum supported PHP version is **8.1**. The codebase uses `readonly`
properties, constructor promotion, and enum-style class constants accordingly.

### WordPress compatibility

The plugin requires **WordPress 6.5** or higher. Ensure all WordPress functions
and hooks used are available in that version.

### Naming conventions

The following conventions must be followed for consistency and autoloading:

- Namespaces follow the pattern `AccessibilityLab\{Component}`.
- Autoloading is PSR-4-ish (see `includes/autoload.php`): **the file name matches
  the class name** — `Bucket.php` contains `Bucket`. This project does *not* use
  WordPress core's `class-bucket.php` convention, which is why
  `WordPress.Files.FileName` is excluded in `phpcs.xml.dist`.
- Classes use WordPress naming conventions with underscores where useful for
  readability (e.g. `Settings_Page`, `Abstract_Module`).
- Modules extend `AccessibilityLab\Abstracts\Abstract_Module` and live under
  `includes/Modules/Features/` or `includes/Modules/Experiments/`.

### Documentation standards

Every class, method, and property needs a docblock with a summary line — PHPCS
runs the `WordPress-Docs` ruleset, so a missing one fails the pre-commit hook
and CI. Use
explicit type hints on parameters and return values, and document array shapes
with generics (e.g. `array<string, Abstract_Module>`) so PHPStan can check them
at level 8.

```php
/**
 * Holds every registered module and their enabled/disabled state.
 */
final class Registry {

	/**
	 * Registered modules, keyed by id.
	 *
	 * @var array<string, Abstract_Module>
	 */
	private array $modules = array();

	/**
	 * Register a module. No-ops on duplicate id or invalid bucket/track.
	 *
	 * @param Abstract_Module $module Module instance to register.
	 */
	public function register( Abstract_Module $module ): void {
		// Implementation
	}
}
```

### Internationalization

All user-facing strings must be translatable using WordPress i18n functions,
with the `accessibility-lab` text domain:

```php
// Good
__( 'Hello World', 'accessibility-lab' );
esc_html__( 'Hello World', 'accessibility-lab' );

// Bad
echo 'Hello World';
```

Strings with placeholders need a `translators:` comment immediately above the
`__()` call.

## Guidelines

- As with all WordPress projects, we want to ensure a welcoming environment for everyone. With that in mind, all contributors are expected to follow our [Code of Conduct](https://make.wordpress.org/handbook/community-code-of-conduct/).
- All WordPress projects are licensed under the GPLv2+, and all contributions to the Accessibility Lab plugin will be released under the GPLv2+ license. You maintain copyright over any contribution you make, and by submitting a pull request, you are agreeing to release that contribution under the GPLv2+ license.

---

## Additional resources

- [README](README.md) — architecture, the Feature/Experiment and Core-track/Practical model, registering your own modules and validation checks, and the full script reference
- [CHANGELOG](CHANGELOG.md) — release history

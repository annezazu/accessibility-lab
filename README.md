# Accessibility Lab

Umbrella WordPress plugin for accessibility **Features** and **Experiments**, each labelled with a **Track** describing whether it is aimed at Core adoption or shipped as a standalone practical tool.

## Two orthogonal dimensions

Every module answers two questions:

**Bucket** — shape/maturity:
- **Features** — practical, stable tools. Safe to rely on.
- **Experiments** — architecturally hard changes gathering real-world data before Core commits.

**Track** — intent to reach WordPress Core:
- **Core-track** — this module exists to inform a Core proposal.
- **Practical** — shipped as a stable tool, no Core promise implied.

Either bucket can live on either track. Credits are optional metadata any module can attach when adopted from a community source.

## First-party modules

| Module | Bucket | Track | Adopted from |
|---|---|---|---|
| Skip-link generator | Feature | Practical | wp-accessibility (Joe Dolson) |
| Media Library: add view options | Feature | Practical | WordPress core / Trac #65775 |
| Heading-order validation | Experiment | TBD | — |
| Block Validation Framework | Experiment | Core-track | validation-api (Troy Chaplin) |
| Core block accessibility rules | Feature | Practical | validation-api-core-blocks (Troy Chaplin) |
| Validation settings | Feature | Practical | validation-api-settings (Troy Chaplin) |

## Block validation subsystem

Three of the first-party modules compose into a full block-editor validation subsystem:

1. **Block Validation Framework** — a plugin API (`validation_api_register_block_check()`, `validation_api_register_meta_check()`, `validation_api_register_editor_check()`), a `core/validation` data store, real-time debounced validation, publish locking, a Validation sidebar, and REST introspection at `GET /wp-validation/v1/checks`.
2. **Core block accessibility rules** — WCAG-oriented checks for `core/image`, `core/button`, `core/table`, `core/heading`, `core/gallery`, plus a required post/page title editor check.
3. **Validation settings** — admin UI that auto-generates one settings page per registered check namespace. Third-party plugins that register checks get their own settings page under **A11y Lab: Validation** with zero admin-menu code.

Third parties integrate by calling the same `validation_api_register_*` functions and hooking the `editor.validateBlock` / `editor.validateMeta` / `editor.validateEditor` JS filters.

## Development

### Setup

Node is pinned via `.nvmrc`. Use it — the toolchain requires Node 22.12+:

```bash
nvm use
npm install
composer install
```

> On older Node, `npm run lint:js` fails with `ERR_REQUIRE_ESM` pointing at
> `@wordpress/theme/prebuilt/js/design-tokens.mjs`. That package is ESM-only and
> reaches ESLint through `@wordpress/eslint-plugin`; the error names a file in
> `node_modules`, not your Node version, so it's easy to misread.

### Running it locally

This repo *is* the plugin directory, so the simplest setup is to clone it into
the `wp-content/plugins/` of a WordPress install you already have — WordPress
Studio, Local, or any other local server — then activate it from the Plugins
screen:

```bash
cd path/to/wp-content/plugins
git clone <repo-url> accessibility-lab
```

Alternatively, `.wp-env.json` in the repo root mounts this directory into a
throwaway Docker-based WordPress (latest core, `WP_DEBUG` and `SCRIPT_DEBUG`
already on). Requires Docker to be running:

```bash
npm run env:start   # http://localhost:8888, admin/password
npm run env:stop
npm run env:clean   # reset the database
```

### Git hooks

`npm install` installs the git hooks via the `prepare` script. On commit,
`.husky/pre-commit` runs `lint-staged` (JS/CSS/PHP, scoped to staged files) and
then PHPStan across the whole project — PHPStan can't be scoped to staged files
because it needs whole-codebase context to resolve types.

### Scripts

| Script | Runs | What it does |
|---|---|---|
| `npm run build` | `wp-scripts build` | Production build of all five entry points into `build/` |
| `npm start` | `wp-scripts start` | Watch-mode dev build |
| `npm run env:start` | `wp-env start` | Start the local Docker WordPress (needs Docker) |
| `npm run env:stop` | `wp-env stop` | Stop it |
| `npm run env:clean` | `wp-env clean` | Reset its database |
| `npm run lint` | `npm-run-all lint:*` | Runs all four linters below |
| `npm run lint:js` | `wp-scripts lint-js` | ESLint over `src/` |
| `npm run lint:css` | `wp-scripts lint-style` | Stylelint over SCSS |
| `npm run lint:php` | `composer run lint` | PHPCS (WordPress Coding Standards) |
| `npm run lint:phpstan` | `composer phpstan` | PHPStan static analysis at level 8 |
| `npm run format` | `npm-run-all format:*` | Runs all three formatters below |
| `npm run format:js` | `wp-scripts lint-js --fix` | Auto-fix JS/TS |
| `npm run format:css` | `wp-scripts lint-style --fix` | Auto-fix SCSS |
| `npm run format:php` | `composer format` | PHPCBF auto-fix |
| `npm run packages-update` | `wp-scripts packages-update` | Bump `@wordpress/*` dependencies |
| `npm run plugin-zip` | `wp-scripts plugin-zip` | Build a distributable zip |
| `npm run prepare` | `husky` | Installs git hooks; runs automatically on `npm install` |

The PHP scripts delegate to Composer, which can also be called directly:

| Composer script | Runs |
|---|---|
| `composer lint` | `phpcs` |
| `composer format` | `phpcbf` |
| `composer phpstan` | `phpstan analyse --memory-limit=2048M` |

## Registering a module (third-party)

```php
add_action( 'accessibility_lab_register_modules', function ( \AccessibilityLab\Registry $registry ) {
	$registry->register( new My_A11y_Module() );
} );
```

Your class extends `AccessibilityLab\Abstracts\Abstract_Module` and declares:

- `bucket()` — `Bucket::FEATURE` or `Bucket::EXPERIMENT`
- `track()` — `Track::CORE_TRACK` or `Track::PRACTICAL` (defaults to Practical)
- optional `credits()` — attach a `Credits` object when adopting from an existing plugin

## Registering a validation check (third-party)

```php
add_action( 'init', function () {
	if ( ! function_exists( 'validation_api_register_block_check' ) ) {
		return; // Framework module not active.
	}
	validation_api_register_block_check( 'my-plugin/my-block', [
		'namespace'    => 'my-plugin',
		'name'         => 'has_title',
		'level'        => 'error',
		'description'  => 'This block must have a title.',
		'error_msg'    => 'Title is required.',
		'warning_msg'  => 'Consider adding a title.',
		'plugin_title' => 'My Plugin',
	] );
} );
```

And the matching JS filter in your editor bundle:

```javascript
import { addFilter } from '@wordpress/hooks';
addFilter(
	'editor.validateBlock',
	'my-plugin/title-check',
	( isValid, blockType, attributes, checkName ) => {
		if ( blockType !== 'my-plugin/my-block' ) return isValid;
		if ( checkName === 'has_title' ) return !! attributes.title?.trim();
		return isValid;
	}
);
```

## Structure

```
accessibility-lab.php        Plugin bootstrap
uninstall.php                Removes options and calls each module's on_uninstall()
phpstan-bootstrap.php        Analysis-only constant stub (see phpstan.neon.dist)
includes/
  autoload.php               PSR-4 autoloader for AccessibilityLab\*
  Plugin.php                 Singleton bootstrapper
  Bucket.php                 FEATURE | EXPERIMENT constants
  Track.php                  CORE_TRACK | PRACTICAL constants
  Credits.php                Optional attribution value object
  Registry.php               Module registry + settings persistence
  Abstracts/Abstract_Module.php
  Admin/Settings_Page.php    Registers Settings → Accessibility Lab
  REST/Modules_Controller.php  /accessibility-lab/v1/modules
  Modules/
    Features/
      Skip_Link_Generator.php
      Media_Library_View_Config.php
      Core_Block_Validation_Rules.php
      Validation_Settings.php
      ValidationSettings/
        Admin_Pages.php          Auto per-namespace submenu generator
        Level_Override.php       Hooks validation_api_check_level
        Rest_Controller.php      /accessibility-lab/v1/validation-settings
    Experiments/
      Heading_Order.php
      Block_Validation_Framework.php
      BlockValidation/
        Check_Registry.php       In-memory check store
        Global_Functions.php     validation_api_register_* globals
        Rest_Controller.php      /wp-validation/v1/checks
src/
  settings/index.tsx             Main lab settings React app
  editor/framework/              Validation runtime (store, sidebar, publish lock)
  editor/core-block-rules/       Per-block JS validators
  validation-settings/index.tsx  Auto per-namespace admin React app
webpack.config.js                Multi-entry build config
```

## Attribution

The block validation subsystem is a port of Troy Chaplin's validation plugins:

- [validation-api](https://github.com/troychaplin/validation-api) — framework
- [validation-api-core-blocks](https://github.com/troychaplin/validation-api-core-blocks) — core block rules
- [validation-api-settings](https://github.com/troychaplin/validation-api-settings) — admin UI

Additional rules (alt-length warning, alt/caption match, non-descriptive alt patterns, gallery inheritance, required post/page title) are adapted from [block-accessibility-checks](https://github.com/troychaplin/block-accessibility-checks).


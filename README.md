# Accessibility Lab

Umbrella WordPress plugin for accessibility **Features** and **Experiments**, each labelled with a **Track** describing whether it is aimed at Core adoption or shipped as a standalone practical tool.

## Two dimensions for modules

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
| Media Library: disable infinite scroll by default | Feature | Practical | WordPress core |
| Heading-order validation | Experiment | TBD | — |
| Block Validation Framework | Experiment | Core-track | validation-api (Troy Chaplin) |
| Core block accessibility rules | Feature | Practical | validation-api-core-blocks (Troy Chaplin) |
| Validation settings | Feature | Practical | validation-api-settings (Troy Chaplin) |

## Block validation subsystem

Three of the first-party modules compose into a full block-editor validation subsystem:

1. **Block Validation Framework** — a plugin API (`validation_api_register_block_check()`, `validation_api_register_meta_check()`, `validation_api_register_editor_check()`), a `core/validation` data store, real-time debounced validation, publish locking, a Validation sidebar, and REST introspection at `GET /wp-validation/v1/checks`.
2. **Core block accessibility rules** — WCAG-oriented checks for `core/image`, `core/button`, `core/table`, `core/heading`, `core/gallery`, plus a required post/page title editor check.
3. **Validation settings** — a single **Validation** admin page listing every registered check in a DataViews table, with search, sorting, pagination, and filters for check type, registering plugin, and severity. Admins set each check's severity to Error, Warning, or Disabled, and reset overridden checks back to their registered default. Third-party plugins that register checks appear in that table automatically, with zero admin-menu code.

Third parties integrate by calling the same `validation_api_register_*` functions and hooking the `editor.validateBlock` / `editor.validateMeta` / `editor.validateEditor` JS filters.

Severity overrides are stored in the `validation_api_settings` option, keyed by check id (scope, namespace, target, and check name — so the same check name registered against two block types is configured independently). Checks registered with `'configurable' => false` are omitted from the table and cannot be overridden.

The settings screen is built on `@wordpress/dataviews`, imported from the `@wordpress/dataviews/wp` subpath — the build intended for plugins compiled with `@wordpress/scripts`. WordPress registers no `wp-dataviews` script or style handle, so the package, its stylesheet, and the `@wordpress/theme` design tokens it depends on all ship inside the plugin bundle. That makes `build/validation-settings.js` large (~1.9 MB); it loads on that one admin screen only.

## Development

```bash
npm install
composer install       # future — no PHP deps yet
npm run build          # multi-entry build to build/*.js
```

Then symlink the folder into `wp-content/plugins/` (or point wp-env at it) and activate.

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
		'title'        => 'Block title required',
		'level'        => 'error',
		'description'  => 'This block must have a title.',
		'error_msg'    => 'Title is required.',
		'warning_msg'  => 'Consider adding a title.',
		'plugin_title' => 'My Plugin',
	] );
} );
```

`name` is the slug: it identifies the check in the override key and is the value passed to the JS filter, so keep it stable. `title` is the human label shown on the Validation settings page and can be reworded freely; it falls back to `name` when omitted.

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
      Media_Library_Infinite_Scroll_Optout.php
      Core_Block_Validation_Rules.php
      CoreBlockRules/...         (per-block PHP registration in one file per group)
      Validation_Settings.php
      ValidationSettings/
        Admin_Pages.php          Registers the Validation admin page
        Level_Override.php       Hooks validation_api_check_level
        Rest_Controller.php      /accessibility-lab/v1/validation-settings
    Experiments/
      Heading_Order.php
      Block_Validation_Framework.php
      BlockValidation/
        Check_Registry.php       In-memory check store
        Check_Key.php            Stable check id / override key
        Global_Functions.php     validation_api_register_* globals
        Rest_Controller.php      /wp-validation/v1/checks
src/
  settings/index.tsx             Main lab settings React app
  editor/framework/              Validation runtime (store, sidebar, publish lock)
  editor/core-block-rules/       Per-block JS validators
  validation-settings/           DataViews admin app for severity overrides
webpack.config.js                Multi-entry build config
```

## Attribution

The block validation subsystem is a port of Troy Chaplin's validation plugins:

- [validation-api](https://github.com/troychaplin/validation-api) — framework
- [validation-api-core-blocks](https://github.com/troychaplin/validation-api-core-blocks) — core block rules
- [validation-api-settings](https://github.com/troychaplin/validation-api-settings) — admin UI

Additional rules (alt-length warning, alt/caption match, non-descriptive alt patterns, gallery inheritance, required post/page title) are adapted from [block-accessibility-checks](https://github.com/troychaplin/block-accessibility-checks).


# Validation API

Real-time content validation in the block editor. Any plugin can register checks against **block attributes**, **post meta**, or the **document as a whole**; the editor runs them as the user types, flags what fails, and blocks publishing on errors.

## Contents

- [Overview](#overview)
- [Using the Validation API](#using-the-validation-api)
- [Integrating with the Validation API](#integrating-with-the-validation-api)
  - [Naming your plugin](#1-naming-your-plugin)
  - [Registering a check](#2-registering-a-check)
  - [The JavaScript filter contract](#3-the-javascript-filter-contract)
  - [Validating block attributes](#4-validating-block-attributes)
  - [Validating inner block structure](#5-validating-inner-block-structure)
  - [Validating post meta](#6-validating-post-meta)
  - [Validating editor concerns](#7-validating-editor-concerns)
  - [Enqueuing your script](#8-enqueuing-your-script)
- [Reference](#reference)

---

## Overview

A check is declared in PHP and implemented in JavaScript. PHP registration tells the system *what* the check is — its name, its severity, the messages to show. The JavaScript filter decides *whether the content passes*. Splitting it this way is what lets the settings screen list and configure checks without loading any editor code.

Every check has a **scope**, which determines what it validates and which filter runs it:

| Scope | Validates | Registered with |
|---|---|---|
| `block` | A block's attributes, or its inner block structure | `validation_api_register_block_check()` |
| `meta` | A post meta value | `validation_api_register_meta_check()` |
| `editor` | The document as a whole | `validation_api_register_editor_check()` |

And a **severity**:

| Level | Effect |
|---|---|
| `error` | Flagged in red, and **publishing is blocked** until resolved |
| `warning` | Flagged in yellow; publishing is allowed |
| `none` | Disabled — the check does not run |

The plugin declares a starting severity; a site administrator can override it per check. Overrides live in the `validation_api_settings` option and never modify the registration itself, so a plugin update can change its own defaults and every un-overridden check follows along.

### The three modules

The subsystem is three separately toggleable modules under **Settings → Accessibility Lab**:

1. **Block Validation Framework** — the registration API, the `accessibility-lab/validation` data store, the editor runtime, the Validation sidebar, publish locking, and REST introspection. Everything else depends on it.
2. **Core block accessibility rules** — WCAG checks for `core/image`, `core/button`, `core/table`, `core/heading` and `core/gallery`, plus required post and page titles. An optional consumer of the framework, and a working reference.
3. **Validation settings** — the admin screen for adjusting severities.

Third-party checks need only the framework. If it's disabled, the `validation_api_register_*` functions don't exist, which is why every example below guards with `function_exists()`.

---

## Using the Validation API

### In the editor

As you edit, failing checks surface in three places:

- **On the block.** Blocks with a problem get a coloured border — red for errors, yellow for warnings — via the `validation-api-block-error` and `validation-api-block-warning` classes.
- **In the Validation sidebar.** Open it from the editor's options menu. Errors and warnings are listed in separate panels, each naming the problem and linking to the block it came from.
- **On the publish button.** While any error is unresolved, saving and publishing are blocked. Warnings never block publishing — they're advisory.

The editor `<body>` also carries `has-validation-errors` or `has-validation-warnings`, which a theme or plugin can hook for its own styling.

### Adjusting severity

**Validation** in the admin menu lists every check registered on the site — first-party and third-party alike — in one table. For each check you can set the level to **Error**, **Warning**, or **Disabled**.

The table supports search across check names, descriptions and targets, plus filters for check type, registering plugin, and current level. Changes are staged locally; nothing is written until **Save changes**, and navigating away with unsaved edits prompts first.

Any check you've moved off its registered default offers **Reset to default** in its row actions. This works on a multi-row selection too, so a plugin's checks can be reverted in bulk.

Checks registered with `'configurable' => false` are deliberately absent — the registering plugin has marked them as not adjustable.

---

## Integrating with the Validation API

Register checks on `init`, and always guard on the framework being active.

### 1. Naming your plugin

Every check belongs to a **namespace** — a slug identifying your plugin. Declare its display name once and every check under it is credited to it in the settings table:

```php
add_action( 'init', function () {
    if ( ! function_exists( 'validation_api_register_namespace' ) ) {
        return; // Framework module not active.
    }

    validation_api_register_namespace( 'my-plugin', array(
        'title' => __( 'My Plugin', 'my-plugin' ),
    ) );
} );
```

Order doesn't matter — this may run before or after your checks. Skip it and your checks are credited to the raw slug (`my-plugin`) instead of a readable name.

### 2. Registering a check

All three registration functions take a target and an argument array:

| Argument | Required | Description |
|---|---|---|
| `namespace` | yes | Your plugin's slug. Groups checks and scopes the stored override key. |
| `name` | yes | Machine name of the check. **This is the value dispatched to your JS filter**, and part of the override key — keep it stable. |
| `title` | no | Human label shown in the settings table. Falls back to `name`, so set it: `check_button_link` reads far worse than "Button link required". |
| `level` | no | Starting severity: `error` (default), `warning`, or `none`. |
| `description` | no | What the check enforces. Shown in the settings table. |
| `error_msg` | no | Message shown when failing at error level. |
| `warning_msg` | no | Message shown when failing at warning level. |
| `configurable` | no | `false` hides the check from the settings screen and prevents overrides. Defaults to `true`. |
| `meta_key` | meta scope | Which meta field the check applies to. |
| `plugin_title` | no | Overrides the namespace title for this one check. Rarely needed. |

`name` and `title` do different jobs. `name` is a slug the code dispatches on; `title` is prose for humans and can be reworded freely without invalidating anyone's saved overrides.

### 3. The JavaScript filter contract

Registration alone does nothing — a check with no filter always passes. Implement the logic by hooking the filter for its scope:

| Filter | Arguments |
|---|---|
| `editor.validateBlock` | `( isValid, blockName, attributes, checkName, check, clientId )` |
| `editor.validateMeta` | `( isValid, metaKey, value, check )` |
| `editor.validateEditor` | `( isValid, checkName, check )` |

Every filter returns a boolean: `true` if the content passes, `false` if it fails.

Two rules matter:

**Return `isValid` unchanged for anything you don't own.** Every filter on a hook sees every check on that hook, including other plugins'. Returning a bare `true`/`false` for a check that isn't yours will clobber another plugin's result.

**Editor-scope filters get no document state.** `editor.validateEditor` receives only the check name — read whatever you need from the editor stores yourself, as shown below.

The `check` argument is the full registered definition (`name`, `namespace`, `level`, `post_type`, and so on), useful when one filter handles several related checks.

### 4. Validating block attributes

The common case: assert something about a block's attributes.

```php
validation_api_register_block_check( 'my-plugin/callout', array(
    'namespace'   => 'my-plugin',
    'name'        => 'check_callout_heading',
    'title'       => __( 'Callout heading required', 'my-plugin' ),
    'level'       => 'error',
    'description' => __( 'Callouts must have a heading so they appear in the document outline.', 'my-plugin' ),
    'error_msg'   => __( 'This callout is missing a heading.', 'my-plugin' ),
    'warning_msg' => __( 'Consider adding a heading to this callout.', 'my-plugin' ),
) );
```

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
    'editor.validateBlock',
    'my-plugin/validation',
    ( isValid, blockName, attributes, checkName ) => {
        if ( blockName !== 'my-plugin/callout' ) {
            return isValid; // Not our block — leave the result alone.
        }

        switch ( checkName ) {
            case 'check_callout_heading':
                return !! String( attributes.heading ?? '' ).trim();

            default:
                return isValid; // Not our check.
        }
    }
);
```

Guarding on `blockName` first and using `switch ( checkName )` keeps one filter handling every check for a block. See [src/editor/core-block-rules/button.ts](../../src/editor/core-block-rules/button.ts) for the same shape in production.

### 5. Validating inner block structure

Attributes can't describe what's nested inside a block. For that, use the `clientId` argument to read the block itself:

```php
validation_api_register_block_check( 'my-plugin/callout', array(
    'namespace'   => 'my-plugin',
    'name'        => 'check_callout_content',
    'title'       => __( 'Callout content', 'my-plugin' ),
    'level'       => 'warning',
    'description' => __( 'A callout should contain one or two paragraphs.', 'my-plugin' ),
    'warning_msg' => __( 'Callouts read best with one or two paragraphs.', 'my-plugin' ),
) );
```

```js
import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';

addFilter(
    'editor.validateBlock',
    'my-plugin/validation-structure',
    ( isValid, blockName, attributes, checkName, check, clientId ) => {
        if ( blockName !== 'my-plugin/callout' || checkName !== 'check_callout_content' ) {
            return isValid;
        }

        const innerBlocks =
            select( blockEditorStore ).getBlock( clientId )?.innerBlocks ?? [];
        const paragraphs = innerBlocks.filter(
            ( block ) => block.name === 'core/paragraph'
        ).length;

        return paragraphs >= 1 && paragraphs <= 2;
    }
);
```

`clientId` is the last argument, so a filter that doesn't need it can simply omit it from its parameter list.

### 6. Validating post meta

Meta checks pair a post type with a `meta_key`:

```php
validation_api_register_meta_check( 'book', array(
    'namespace'   => 'my-plugin',
    'name'        => 'required',
    'title'       => __( 'Book ISBN', 'my-plugin' ),
    'meta_key'    => 'book_isbn',
    'level'       => 'error',
    'description' => __( 'The ISBN of the book.', 'my-plugin' ),
    'error_msg'   => __( 'ISBN is required.', 'my-plugin' ),
    'warning_msg' => __( 'ISBN is recommended.', 'my-plugin' ),
) );
```

```js
import { addFilter } from '@wordpress/hooks';

addFilter(
    'editor.validateMeta',
    'my-plugin/validation-meta',
    ( isValid, metaKey, value, check ) => {
        if ( metaKey !== 'book_isbn' || check.name !== 'required' ) {
            return isValid;
        }
        return !! String( value ?? '' ).trim();
    }
);
```

No post type check is needed — the framework only invokes the filter for checks registered against the post type being edited. Note that `name` can repeat across meta fields (`required` here), because the stored override key includes the post type and meta key as well; guard on `metaKey` to tell them apart.

### 7. Validating editor concerns

Editor checks validate the document as a whole. They're registered against a post type, or `*` for every post type:

```php
validation_api_register_editor_check( 'post', array(
    'namespace'   => 'my-plugin',
    'name'        => 'first_block_heading',
    'title'       => __( 'Document starts with a heading', 'my-plugin' ),
    'level'       => 'warning',
    'description' => __( 'Posts should open with a heading.', 'my-plugin' ),
    'warning_msg' => __( 'Consider starting this post with a heading.', 'my-plugin' ),
) );
```

The filter receives no document state, so read it from the stores:

```js
import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';

addFilter(
    'editor.validateEditor',
    'my-plugin/validation-editor',
    ( isValid, checkName ) => {
        if ( checkName !== 'first_block_heading' ) {
            return isValid;
        }

        const blocks = select( blockEditorStore ).getBlocks();
        if ( ! blocks.length ) {
            return true; // Nothing written yet — don't nag.
        }

        return blocks[ 0 ].name === 'core/heading';
    }
);
```

Post attributes come from the editor store instead — `select( editorStore ).getEditedPostAttribute( 'title' )` and friends. [src/editor/core-block-rules/post-title.ts](../../src/editor/core-block-rules/post-title.ts) shows that variant.

### 8. Enqueuing your script

Your filters must be registered before the runtime reads them, so depend on the framework's script handle:

```php
add_action( 'enqueue_block_editor_assets', function () {
    $asset = require plugin_dir_path( __FILE__ ) . 'build/editor.asset.php';
    $deps  = $asset['dependencies'];

    // Only add the dependency if the framework is actually registered.
    // WordPress refuses to enqueue a script whose dependencies are missing,
    // so an unconditional dependency would break your script entirely
    // whenever the module is disabled.
    if ( wp_script_is( 'accessibility-lab-validation-framework', 'registered' ) ) {
        $deps[] = 'accessibility-lab-validation-framework';
    }

    wp_enqueue_script(
        'my-plugin-editor',
        plugin_dir_url( __FILE__ ) . 'build/editor.js',
        $deps,
        $asset['version'],
        true
    );
} );
```

[includes/Modules/Features/Core_Block_Validation_Rules.php](../../includes/Modules/Features/Core_Block_Validation_Rules.php) uses exactly this pattern.

---

## Reference

### Global functions

| Function | Purpose |
|---|---|
| `validation_api_register_namespace( $namespace, $args )` | Declare a plugin's display name |
| `validation_api_register_block_check( $block_type, $args )` | Register a block-scope check |
| `validation_api_register_meta_check( $post_type, $args )` | Register a meta-scope check |
| `validation_api_register_editor_check( $post_type, $args )` | Register an editor-scope check (`*` for all post types) |
| `accessibility_lab_validation_check_registry()` | The shared registry, or `null` when the framework is inactive |

### REST

`GET /wp-validation/v1/checks` — every registered check, grouped by scope, requires `edit_posts`. Each check carries its registration arguments plus:

- `id` — stable identifier, also the override key
- `resolved_level` — effective severity after admin overrides
- `plugin_title` — resolved display name

`GET`/`POST /accessibility-lab/v1/validation-settings` — read and write the override map, requires `manage_options`.

### Data store

`accessibility-lab/validation` exposes `getIssues`, `getIssuesForBlock( clientId )`, `hasErrors`, `hasWarnings`, `errorCount` and `warningCount` — useful for building your own UI on top of the current validation state.

### PHP filter

`validation_api_check_level` — filters a check's severity at resolve time, receiving `( string $level, array $context )` where the context carries `scope`, `namespace`, `name`, `block_type`, `post_type` and `meta_key`. This is the hook the settings module itself uses to apply stored overrides.

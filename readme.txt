=== Accessibility Lab ===
Contributors: wordpressdotorg
Tags: accessibility, a11y, wcag, experiments
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Community plugin for accessibility features and experiments.

== Description ==

Accessibility Lab bundles accessibility modules from the community and from the WordPress core track on a single settings page:

* **Stable features** — practical accessibility tools folded in from the community with full credit to their original authors. Safe to rely on.
* **Experiments** — surfaced as experiments while gathering real-world data before Core commits to them. Unstable; settings and output may change.
* **Core Track** — modules headed for consideration in WordPress core. Where an accepted approach exists, prefer that in production.

Toggle any module on or off from **Settings → Accessibility Lab**. Preferences save automatically. Modules that depend on another (e.g. Core block accessibility rules → Block Validation Framework) render nested and disable together.

Included modules:

* Media Library: add view options — in-modal popover for infinite scroll, thumbnail density, always-show file names, and items-per-page. Per-user preferences.
* Block Validation Framework — third-party plugins can register real-time validation checks for blocks, post meta, and editor-level document concerns.
* Core block accessibility rules — WCAG-oriented validation for the image, button, table, heading, and gallery core blocks, plus required post/page titles.
* Validation settings — admin UI for overriding the severity of every registered validation check.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/accessibility-lab` or install through the Plugins screen.
2. Activate the plugin.
3. Visit **Settings → Accessibility Lab** and enable the modules you want.

== Changelog ==

= 0.1.0 =
* Initial scaffold: module registry, DataForm settings page, REST endpoint, media-library view-options popover, block validation framework, and core-block WCAG rules.

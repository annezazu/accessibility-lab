# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/), and will adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - TBD

### Added

- Linting and static analysis: ESLint, Stylelint, PHP_CodeSniffer (WordPress Coding Standards) and PHPStan at level 8, all runnable through `npm run lint` and `npm run format`.
- Continuous integration via GitHub Actions, running PHPCS and PHPStan against PHP 8.1 through 8.4 alongside JavaScript and CSS linting and a production build.
- A pre-commit hook that lints staged files and runs PHPStan across the project, so failures surface before review.
- Scripts for the bundled `wp-env` environment (`env:start`, `env:stop`, `env:clean`), giving contributors a disposable WordPress install with debugging enabled.
- Contributor documentation: contributing guidelines, a pull request template, and this changelog.
- A pinned Node version (`.nvmrc`) and shared editor settings (`.editorconfig`).
- A declared minimum PHP version of 8.1 in `composer.json`.

### Changed

- Reformatted the JavaScript, TypeScript, SCSS and PHP source to the WordPress Coding Standards, and documented every PHP class, method and property.
- Expanded the README with a full script reference, the Node version requirement, and instructions for running the plugin locally.
- Narrowed the `credits()` return type on modules that always supply attribution.

### Fixed

- The Media Library view-preference handler now sanitises the submitted value before use.
- Uninstalling no longer assigns to WordPress's global `$plugin` variable.
- Corrected CSS selector specificity ordering in the Media Library view options, so the thumbnail density rules apply as intended.
- Replaced an invalid design-system colour token in the validation settings table with the intended value, restoring the muted styling of the description text.
- Removed an unused registry dependency from the settings page, along with several unused variables and imports across the editor scripts.
- Corrected the supported PHP range in CI, which previously claimed 8.0 despite the plugin requiring 8.1.
- Committed the dependency lock files, without which continuous integration could not install dependencies.

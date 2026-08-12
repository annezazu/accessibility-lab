<?php
/**
 * Auto-generates one admin submenu per registered check namespace.
 *
 * Structure:
 *   Settings > Accessibility Lab           (existing lab settings)
 *   Settings > Accessibility Lab: Validation  (parent for validation)
 *     ├── <namespace 1>
 *     ├── <namespace 2>
 *     └── ...
 *
 * Each submenu deep-links into the same React app with ?namespace=<slug>.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features\ValidationSettings;

use AccessibilityLab\Modules\Experiments\Block_Validation_Framework;

final class Admin_Pages {

	public const PARENT_SLUG = 'accessibility-lab-validation';
	public const HOOK_PREFIX = 'accessibility-lab_page_';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu(): void {
		$registry = Block_Validation_Framework::shared_registry();
		if ( ! $registry ) {
			return;
		}

		$namespaces = $this->collect_namespaces( $registry );
		if ( empty( $namespaces ) ) {
			return;
		}

		// Parent menu under the "Accessibility Lab" top-level (added by
		// Settings_Page under Settings → …). We add our own parent submenu.
		add_menu_page(
			__( 'Accessibility Lab: Validation', 'accessibility-lab' ),
			__( 'Validation', 'accessibility-lab' ),
			'manage_options',
			self::PARENT_SLUG,
			array( $this, 'render' ),
			'dashicons-yes-alt',
			81
		);

		$first = true;
		foreach ( $namespaces as $slug => $label ) {
			// The `namespace` value is supplied by third-party plugins via
			// `validation_api_register_block_check`. Sanitize before using
			// it in a menu slug so a malformed value can't create a slug
			// with unexpected characters.
			$safe_slug = sanitize_key( $slug );
			add_submenu_page(
				self::PARENT_SLUG,
				$label,
				$label,
				'manage_options',
				$first ? self::PARENT_SLUG : self::PARENT_SLUG . '-' . $safe_slug,
				array( $this, 'render' )
			);
			$first = false;
		}

		// Remove the auto-duplicated first submenu label so it uses the
		// namespace name of the first registered plugin.
		global $submenu;
		if ( isset( $submenu[ self::PARENT_SLUG ][0] ) ) {
			$first_ns = array_key_first( $namespaces );
			$submenu[ self::PARENT_SLUG ][0][0] = $namespaces[ $first_ns ];
		}
	}

	public function render(): void {
		echo '<div id="accessibility-lab-validation-settings"></div>';
	}

	public function enqueue( string $hook ): void {
		if ( 0 !== strpos( $hook, 'toplevel_page_' . self::PARENT_SLUG )
			&& 0 !== strpos( $hook, 'a11y-lab-validation_page_' )
			&& false === strpos( $hook, self::PARENT_SLUG ) ) {
			return;
		}

		$asset_file = ACCESSIBILITY_LAB_DIR . '/build/validation-settings.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'accessibility-lab-validation-settings',
			ACCESSIBILITY_LAB_URL . 'build/validation-settings.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? ACCESSIBILITY_LAB_VERSION,
			true
		);

		$style_file = ACCESSIBILITY_LAB_DIR . '/build/style-validation-settings.css';
		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'accessibility-lab-validation-settings',
				ACCESSIBILITY_LAB_URL . 'build/style-validation-settings.css',
				array( 'wp-components' ),
				$asset['version'] ?? ACCESSIBILITY_LAB_VERSION
			);
		}
	}

	/**
	 * @return array<string, string> Map of namespace slug -> human label.
	 */
	private function collect_namespaces( $registry ): array {
		$out = array();
		foreach ( $registry->all_by_scope() as $scope => $checks ) {
			foreach ( $checks as $check ) {
				$ns = (string) ( $check['namespace'] ?? '' );
				if ( '' === $ns || isset( $out[ $ns ] ) ) {
					continue;
				}
				$label = (string) ( $check['plugin_title'] ?? '' );
				if ( '' === $label ) {
					$label = $ns;
				}
				$out[ $ns ] = $label;
			}
		}
		ksort( $out );
		return $out;
	}
}

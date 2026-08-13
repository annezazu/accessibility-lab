<?php
/**
 * Admin settings page (mounts the React DataForm).
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Admin;

/**
 * Admin settings page (mounts the React DataForm).
 *
 * Takes no registry: the React app fetches module data over REST via
 * AccessibilityLab\REST\Modules_Controller rather than server-side.
 */
final class Settings_Page {

	private const SLUG = 'accessibility-lab';

	/**
	 * Hook the admin menu and asset registration.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the Settings > Accessibility Lab submenu page.
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'Accessibility Lab', 'accessibility-lab' ),
			__( 'Accessibility Lab', 'accessibility-lab' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the mount point for the React settings app.
	 */
	public function render(): void {
		// The React app renders the full page (title + description) using
		// the `Page` component from @wordpress/admin-ui, so we only need a
		// bare mount point here.
		echo '<div id="accessibility-lab-settings"></div>';
	}

	/**
	 * Enqueue the settings script/style on our own admin screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		$asset_file = ACCESSIBILITY_LAB_DIR . '/build/settings.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'accessibility-lab-settings',
			ACCESSIBILITY_LAB_URL . 'build/settings.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? ACCESSIBILITY_LAB_VERSION,
			true
		);

		wp_set_script_translations( 'accessibility-lab-settings', 'accessibility-lab' );

		// DataForm chrome (cards, field descriptions, etc.) ships with the
		// @wordpress/dataviews package. WordPress registers it as
		// `wp-dataviews` but doesn't auto-enqueue it for arbitrary admin
		// screens — do it here so the DataForm renders with its intended
		// spacing/typography.
		wp_enqueue_style( 'wp-dataviews' );

		// wp-scripts emits SCSS chunks as build/style-<entry>.css.
		$style_file = ACCESSIBILITY_LAB_DIR . '/build/style-settings.css';
		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'accessibility-lab-settings',
				ACCESSIBILITY_LAB_URL . 'build/style-settings.css',
				array( 'wp-components', 'dashicons' ),
				$asset['version'] ?? ACCESSIBILITY_LAB_VERSION
			);
		}
	}
}

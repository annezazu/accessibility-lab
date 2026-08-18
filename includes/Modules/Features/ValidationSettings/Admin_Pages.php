<?php
/**
 * Admin page for validation severity overrides.
 *
 * A single top-level "Validation" page listing every registered check in a
 * DataViews table. The registering plugin is a filterable column rather than
 * a separate page, so a site with checks from several plugins still has one
 * place to configure all of them.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features\ValidationSettings;

use AccessibilityLab\Modules\Experiments\Block_Validation_Framework;
use WP_Block_Type_Registry;

final class Admin_Pages {

	public const SLUG = 'accessibility-lab-validation';

	private string $page_hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu(): void {
		if ( ! Block_Validation_Framework::shared_registry() ) {
			return;
		}

		$this->page_hook = (string) add_menu_page(
			__( 'Accessibility Lab: Validation', 'accessibility-lab' ),
			__( 'Validation', 'accessibility-lab' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-yes-alt',
			81
		);
	}

	public function render(): void {
		// Critical styles are inlined rather than shipped in the bundled
		// stylesheet so they apply before it loads, avoiding a layout shift on
		// first paint. This mirrors how core sets up its own full-width
		// DataViews admin screens.
		?>
		<style>
			#wpwrap { overflow-y: auto; }
			body.js #wpcontent { padding-inline-start: 0; }
			body.js #wpbody-content { padding-bottom: 0; }
			body.js #wpfooter { display: none; }

			@media (min-width: 782px) {
				#wpwrap { overflow-y: initial; }
			}
		</style>
		<?php
		// The React app renders the page chrome with the `Page` component
		// from @wordpress/admin-ui, so this is only a mount point.
		echo '<div id="accessibility-lab-validation-settings"></div>';
	}

	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->page_hook || $hook_suffix !== $this->page_hook ) {
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

		wp_set_script_translations( 'accessibility-lab-validation-settings', 'accessibility-lab' );

		// Checks identify their target by slug (`core/image`, `post`). Ship
		// the human labels alongside so the table can read as prose without
		// a second round trip per row.
		wp_add_inline_script(
			'accessibility-lab-validation-settings',
			'window.accessibilityLabValidation = ' . wp_json_encode(
				array(
					'blockTitles'    => $this->get_block_titles(),
					'postTypeLabels' => $this->get_post_type_labels(),
				)
			) . ';',
			'before'
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
	 * @return array<string, string> Block type name -> human title.
	 */
	private function get_block_titles(): array {
		$out = array();
		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			$title = isset( $block_type->title ) ? (string) $block_type->title : '';
			if ( '' !== $title ) {
				$out[ (string) $name ] = $title;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, string> Post type name -> singular label.
	 */
	private function get_post_type_labels(): array {
		$out = array();
		foreach ( get_post_types( array(), 'objects' ) as $name => $post_type ) {
			$label = isset( $post_type->labels->singular_name ) ? (string) $post_type->labels->singular_name : '';
			if ( '' !== $label ) {
				$out[ (string) $name ] = $label;
			}
		}
		return $out;
	}
}

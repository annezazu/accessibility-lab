<?php
/**
 * Feature: Core Block Validation Rules.
 *
 * Registers WCAG-oriented validation checks against WordPress core blocks
 * using the Block Validation Framework module. Ports the rule set from
 * troychaplin/validation-api-core-blocks plus the extras from
 * troychaplin/block-accessibility-checks (alt-length warning, alt/caption
 * duplication check, non-descriptive alt patterns, gallery inheritance,
 * required post/page title).
 *
 * Silently no-ops if the framework module isn't active.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features;

use AccessibilityLab\Abstracts\Abstract_Module;
use AccessibilityLab\Bucket;
use AccessibilityLab\Credits;
use AccessibilityLab\Track;

/**
 * Feature: Core Block Validation Rules.
 */
final class Core_Block_Validation_Rules extends Abstract_Module {

	private const NS = 'accessibility-lab-core-blocks';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'core_block_validation_rules';
	}

	/**
	 * Module bucket.
	 *
	 * @return string
	 */
	public function bucket(): string {
		return Bucket::FEATURE;
	}

	/**
	 * Module track.
	 *
	 * @return string
	 */
	public function track(): string {
		return Track::PRACTICAL;
	}

	/**
	 * Module display name.
	 *
	 * @return string
	 */
	public function name(): string {
		return __( 'Core block accessibility rules', 'accessibility-lab' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'WCAG-oriented validation checks for the image, button, table, heading, and gallery core blocks, plus required post/page titles. Requires the Block Validation Framework module.', 'accessibility-lab' );
	}

	/**
	 * Attribution for this module's origin.
	 *
	 * @return Credits
	 */
	public function credits(): Credits {
		return new Credits(
			author: 'Troy Chaplin',
			source_plugin_slug: 'validation-api-core-blocks',
			source_plugin_url: 'https://github.com/troychaplin/validation-api-core-blocks',
			license: 'GPL-2.0-or-later'
		);
	}

	/**
	 * Register the validation checks on `init` and hook editor asset loading.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_checks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 20 );
	}

	/**
	 * Enqueue the core-block-rules editor script, depending on the
	 * validation-framework bundle when it's registered.
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = ACCESSIBILITY_LAB_DIR . '/build/core-block-rules.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		$deps = $asset['dependencies'] ?? array();
		// Depend on the framework bundle so filters register first. Skip the
		// dep if the framework isn't registered — otherwise WordPress emits
		// a "dependencies that are not registered" notice and the script
		// fails to enqueue at all.
		if (
			wp_script_is( 'accessibility-lab-validation-framework', 'registered' )
			&& ! in_array( 'accessibility-lab-validation-framework', $deps, true )
		) {
			$deps[] = 'accessibility-lab-validation-framework';
		}

		wp_enqueue_script(
			'accessibility-lab-core-block-rules',
			ACCESSIBILITY_LAB_URL . 'build/core-block-rules.js',
			$deps,
			$asset['version'] ?? ACCESSIBILITY_LAB_VERSION,
			true
		);
	}

	/**
	 * Register every check this module defines. No-ops when the Block
	 * Validation Framework module isn't active.
	 */
	public function register_checks(): void {
		if ( ! function_exists( 'validation_api_register_block_check' ) ) {
			return;
		}

		$this->register_image_checks();
		$this->register_button_checks();
		$this->register_table_checks();
		$this->register_heading_checks();
		$this->register_editor_checks();
	}

	/**
	 * Register alt-text checks for core/image, mirrored onto core/gallery.
	 */
	private function register_image_checks(): void {
		validation_api_register_block_check(
			'core/image',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_image_alt_text',
				'level'        => 'error',
				'description'  => __( 'Images must have alt text unless marked decorative.', 'accessibility-lab' ),
				'error_msg'    => __( 'This image is missing alt text.', 'accessibility-lab' ),
				'warning_msg'  => __( 'Consider adding alt text to this image.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
		validation_api_register_block_check(
			'core/image',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_image_alt_text_length',
				'level'        => 'warning',
				'description'  => __( 'Alt text should be 125 characters or fewer.', 'accessibility-lab' ),
				'error_msg'    => __( 'Alt text exceeds 125 characters.', 'accessibility-lab' ),
				'warning_msg'  => __( 'Alt text exceeds 125 characters. Consider shortening.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
		validation_api_register_block_check(
			'core/image',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_image_alt_caption_match',
				'level'        => 'error',
				'description'  => __( 'Alt text and caption must not be identical.', 'accessibility-lab' ),
				'error_msg'    => __( 'Alt text is identical to the caption; screen readers will hear the same text twice.', 'accessibility-lab' ),
				'warning_msg'  => __( 'Alt text matches the caption.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
		validation_api_register_block_check(
			'core/image',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_image_alt_text_patterns',
				'level'        => 'error',
				'description'  => __( 'Alt text must be descriptive; filenames and phrases like "image of" are rejected.', 'accessibility-lab' ),
				'error_msg'    => __( 'Alt text is not descriptive (filename or generic phrase detected).', 'accessibility-lab' ),
				'warning_msg'  => __( 'Alt text may not be descriptive.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);

		// Gallery: same checks applied to each image inside a gallery block.
		foreach ( array( 'check_image_alt_text', 'check_image_alt_text_length', 'check_image_alt_caption_match', 'check_image_alt_text_patterns' ) as $check_name ) {
			validation_api_register_block_check(
				'core/gallery',
				array(
					'namespace'    => self::NS,
					'name'         => 'gallery_' . $check_name,
					'level'        => 'warning',
					'description'  => __( 'Applies image accessibility checks to every image in a gallery.', 'accessibility-lab' ),
					'error_msg'    => __( 'One or more gallery images fail an accessibility check.', 'accessibility-lab' ),
					'warning_msg'  => __( 'One or more gallery images have accessibility warnings.', 'accessibility-lab' ),
					'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
				)
			);
		}
	}

	/**
	 * Register text/link checks for core/button.
	 */
	private function register_button_checks(): void {
		validation_api_register_block_check(
			'core/button',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_button_text',
				'level'        => 'error',
				'description'  => __( 'Buttons must have descriptive text.', 'accessibility-lab' ),
				'error_msg'    => __( 'This button has no text content.', 'accessibility-lab' ),
				'warning_msg'  => __( 'Consider adding descriptive text to this button.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
		validation_api_register_block_check(
			'core/button',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_button_link',
				'level'        => 'error',
				'description'  => __( 'Buttons with navigation intent must have a valid URL.', 'accessibility-lab' ),
				'error_msg'    => __( 'This button link is missing or invalid.', 'accessibility-lab' ),
				'warning_msg'  => __( 'This button link may be invalid.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
	}

	/**
	 * Register the header-cell check for core/table.
	 */
	private function register_table_checks(): void {
		validation_api_register_block_check(
			'core/table',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_table_headers',
				'level'        => 'error',
				'description'  => __( 'Tables must have a header section or first-row header cells.', 'accessibility-lab' ),
				'error_msg'    => __( 'This table has no headers; screen readers cannot navigate it.', 'accessibility-lab' ),
				'warning_msg'  => __( 'Consider adding table headers.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
	}

	/**
	 * Register the rank-skip check for core/heading.
	 */
	private function register_heading_checks(): void {
		validation_api_register_block_check(
			'core/heading',
			array(
				'namespace'    => self::NS,
				'name'         => 'check_heading_rank',
				'level'        => 'error',
				'description'  => __( 'Heading levels must not skip ranks (e.g. H2 → H4).', 'accessibility-lab' ),
				'error_msg'    => __( 'This heading skips a level.', 'accessibility-lab' ),
				'warning_msg'  => __( 'This heading may skip a level.', 'accessibility-lab' ),
				'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
			)
		);
	}

	/**
	 * Register the required-title editor-scope check for post and page.
	 */
	private function register_editor_checks(): void {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			validation_api_register_editor_check(
				$post_type,
				array(
					'namespace'    => self::NS,
					'name'         => 'post_title_required_' . $post_type,
					'level'        => 'error',
					'description'  => sprintf(
						/* translators: %s: post type slug. */
						__( 'Requires a title on the %s.', 'accessibility-lab' ),
						$post_type
					),
					'error_msg'    => __( 'A title is required.', 'accessibility-lab' ),
					'warning_msg'  => __( 'A title is recommended.', 'accessibility-lab' ),
					'plugin_title' => __( 'Accessibility Lab: core blocks', 'accessibility-lab' ),
				)
			);
		}
	}
}

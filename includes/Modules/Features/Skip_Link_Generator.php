<?php
/**
 * Feature: Skip-link generator (stub).
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features;

use AccessibilityLab\Abstracts\Abstract_Module;
use AccessibilityLab\Bucket;
use AccessibilityLab\Credits;

/**
 * Feature: Skip-link generator (stub).
 */
final class Skip_Link_Generator extends Abstract_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'skip_link_generator';
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
	 * Module display name.
	 *
	 * @return string
	 */
	public function name(): string {
		return __( 'Skip-link generator', 'accessibility-lab' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Injects an accessible "Skip to content" link at the top of every page.', 'accessibility-lab' );
	}

	/**
	 * Attribution for this module's origin.
	 *
	 * @return Credits
	 */
	public function credits(): Credits {
		return new Credits(
			author: 'Joe Dolson',
			source_plugin_slug: 'wp-accessibility',
			source_plugin_url: 'https://wordpress.org/plugins/wp-accessibility/',
			license: 'GPL-2.0-or-later'
		);
	}

	/**
	 * Register hooks. Currently a no-op stub.
	 */
	public function boot(): void {
		// v0.1 stub — full implementation lands in a follow-up module PR.
	}
}

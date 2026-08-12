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

final class Skip_Link_Generator extends Abstract_Module {

	public function id(): string {
		return 'skip_link_generator';
	}

	public function bucket(): string {
		return Bucket::FEATURE;
	}

	public function name(): string {
		return __( 'Skip-link generator', 'accessibility-lab' );
	}

	public function description(): string {
		return __( 'Injects an accessible "Skip to content" link at the top of every page.', 'accessibility-lab' );
	}

	public function credits(): ?Credits {
		return new Credits(
			author: 'Joe Dolson',
			source_plugin_slug: 'wp-accessibility',
			source_plugin_url: 'https://wordpress.org/plugins/wp-accessibility/',
			license: 'GPL-2.0-or-later'
		);
	}

	public function boot(): void {
		// v0.1 stub — full implementation lands in a follow-up module PR.
	}
}

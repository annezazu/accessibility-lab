<?php
/**
 * Experiment: Heading-order validation (stub).
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Experiments;

use AccessibilityLab\Abstracts\Abstract_Module;
use AccessibilityLab\Bucket;
use AccessibilityLab\Track;

final class Heading_Order extends Abstract_Module {

	public function id(): string {
		return 'heading_order';
	}

	public function bucket(): string {
		return Bucket::FEATURE;
	}

	public function track(): string {
		return Track::PRACTICAL;
	}

	public function name(): string {
		return __( 'Heading-order validation', 'accessibility-lab' );
	}

	public function description(): string {
		return __( 'Warns in the editor when heading levels skip (e.g. H2 → H4). Output may change.', 'accessibility-lab' );
	}

	public function boot(): void {
		// v0.1 stub — full implementation lands in a follow-up module PR.
	}
}

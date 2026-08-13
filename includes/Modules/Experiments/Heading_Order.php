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

/**
 * Experiment: Heading-order validation (stub).
 */
final class Heading_Order extends Abstract_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'heading_order';
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
		return __( 'Heading-order validation', 'accessibility-lab' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Warns in the editor when heading levels skip (e.g. H2 → H4). Output may change.', 'accessibility-lab' );
	}

	/**
	 * Register hooks. Currently a no-op stub.
	 */
	public function boot(): void {
		// v0.1 stub — full implementation lands in a follow-up module PR.
	}
}

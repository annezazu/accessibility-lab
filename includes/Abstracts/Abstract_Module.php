<?php
/**
 * Base class for every Accessibility Lab module.
 *
 * Each module answers two orthogonal questions:
 *
 * - bucket(): FEATURE or EXPERIMENT — shape/maturity.
 * - track():  CORE_TRACK or PRACTICAL — intent to reach WordPress Core.
 *
 * Credits are optional metadata; attach them when the module was adopted from
 * a community source, regardless of bucket or track.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Abstracts;

use AccessibilityLab\Credits;
use AccessibilityLab\Track;

abstract class Abstract_Module {

	abstract public function id(): string;

	/**
	 * @return string Bucket::FEATURE or Bucket::EXPERIMENT.
	 */
	abstract public function bucket(): string;

	/**
	 * @return string Track::CORE_TRACK or Track::PRACTICAL.
	 *
	 * Default: PRACTICAL. Override in modules whose reason for existing is
	 * to gather data toward a Core proposal.
	 */
	public function track(): string {
		return Track::PRACTICAL;
	}

	abstract public function name(): string;

	abstract public function description(): string;

	public function default_enabled(): bool {
		return false;
	}

	public function credits(): ?Credits {
		return null;
	}

	/**
	 * Register hooks. Only called when the module is enabled.
	 */
	abstract public function boot(): void;

	/**
	 * Called when the user turns the module off. MUST leave the site working.
	 */
	public function on_disable(): void {}

	/**
	 * Called from uninstall.php. Remove options / drop-ins / custom tables.
	 */
	public function on_uninstall(): void {}
}

<?php
/**
 * Module track taxonomy.
 *
 * A module's "track" answers: is this on a path toward WordPress Core, or is
 * it a practical tool we don't expect Core to ship? Orthogonal to the
 * Feature/Experiment bucket — either bucket can live on either track.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

/**
 * Module track constants: CORE_TRACK (aiming at WordPress Core) vs PRACTICAL.
 */
final class Track {
	public const CORE_TRACK = 'core-track';
	public const PRACTICAL  = 'practical';

	/**
	 * Whether a value is one of the declared track constants.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	public static function is_valid( string $value ): bool {
		return self::CORE_TRACK === $value || self::PRACTICAL === $value;
	}
}

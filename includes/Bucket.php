<?php
/**
 * Module bucket taxonomy.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

/**
 * Module bucket constants: FEATURE (shipped) vs EXPERIMENT (in progress).
 */
final class Bucket {
	public const FEATURE    = 'feature';
	public const EXPERIMENT = 'experiment';

	/**
	 * Whether a value is one of the declared bucket constants.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	public static function is_valid( string $value ): bool {
		return self::FEATURE === $value || self::EXPERIMENT === $value;
	}
}

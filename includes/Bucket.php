<?php
/**
 * Module bucket taxonomy.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

final class Bucket {
	public const FEATURE    = 'feature';
	public const EXPERIMENT = 'experiment';

	public static function is_valid( string $value ): bool {
		return self::FEATURE === $value || self::EXPERIMENT === $value;
	}
}

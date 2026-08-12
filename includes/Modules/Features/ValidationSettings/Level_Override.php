<?php
/**
 * Applies stored severity overrides to registered validation checks.
 *
 * Options are stored in the `validation_api_settings` option key. The stored
 * structure is a flat map, keyed by `scope:namespace:name`, of override
 * levels ('error', 'warning', 'disabled').
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features\ValidationSettings;

final class Level_Override {

	public const OPTION_KEY = 'validation_api_settings';

	public function register(): void {
		add_filter( 'validation_api_check_level', array( $this, 'apply' ), 10, 2 );
	}

	/**
	 * @param string               $level
	 * @param array<string, mixed> $context
	 */
	public function apply( string $level, array $context ): string {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			return $level;
		}
		$key = sprintf(
			'%s:%s:%s',
			(string) ( $context['scope'] ?? '' ),
			(string) ( $context['namespace'] ?? '' ),
			(string) ( $context['name'] ?? '' )
		);
		if ( ! isset( $stored[ $key ] ) ) {
			return $level;
		}
		$override = (string) $stored[ $key ];
		if ( 'disabled' === $override ) {
			return 'none';
		}
		if ( 'error' === $override || 'warning' === $override ) {
			return $override;
		}
		return $level;
	}
}

<?php
/**
 * Applies stored severity overrides to registered validation checks.
 *
 * Overrides live in the `validation_api_settings` option as a flat map of
 * check key => level ('error', 'warning', 'none'). Keys are built by
 * Check_Key, so they include the check's target and one check name
 * registered against two block types gets two independent overrides.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features\ValidationSettings;

use AccessibilityLab\Modules\Experiments\BlockValidation\Check_Key;

final class Level_Override {

	public const OPTION_KEY = 'validation_api_settings';

	public const LEVELS = array( 'error', 'warning', 'none' );

	/**
	 * Cached option value. The filter runs once per check per resolve, so
	 * hitting the options API each time is wasteful.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $overrides = null;

	public function register(): void {
		add_filter( 'validation_api_check_level', array( $this, 'apply' ), 10, 2 );
	}

	/**
	 * @param string               $level
	 * @param array<string, mixed> $context
	 */
	public function apply( string $level, array $context ): string {
		$overrides = $this->overrides();
		$key       = Check_Key::from_context( $context );

		if ( ! isset( $overrides[ $key ] ) ) {
			return $level;
		}

		$override = (string) $overrides[ $key ];

		return in_array( $override, self::LEVELS, true ) ? $override : $level;
	}

	/**
	 * @return array<string, string>
	 */
	private function overrides(): array {
		if ( null === $this->overrides ) {
			$stored          = get_option( self::OPTION_KEY, array() );
			$this->overrides = is_array( $stored ) ? $stored : array();
		}
		return $this->overrides;
	}
}

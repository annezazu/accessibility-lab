<?php
/**
 * Builds the stable identifier for a registered validation check.
 *
 * The same string serves three purposes, so it must be derivable from both a
 * registry record and a `validation_api_check_level` filter context:
 *
 *   - the key an override is stored under in the settings option,
 *   - the `id` sent to the admin app over REST,
 *   - the DataViews item id for a table row.
 *
 * The target (block type / post type / meta key) is part of the key. Without
 * it, one check name registered against two block types would share a single
 * override.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Experiments\BlockValidation;

final class Check_Key {

	/**
	 * Separator between key segments. Double underscore because block types
	 * and meta keys may contain `/`, `-` and single underscores.
	 */
	private const SEP = '__';

	/**
	 * Key for a normalised registry record.
	 *
	 * @param array<string, mixed> $check
	 */
	public static function from_check( array $check ): string {
		return self::build(
			(string) ( $check['scope'] ?? '' ),
			(string) ( $check['namespace'] ?? '' ),
			(string) ( $check['name'] ?? '' ),
			(string) ( $check['block_type'] ?? '' ),
			(string) ( $check['post_type'] ?? '' ),
			(string) ( $check['meta_key'] ?? '' )
		);
	}

	/**
	 * Key for a `validation_api_check_level` filter context.
	 *
	 * @param array<string, mixed> $context
	 */
	public static function from_context( array $context ): string {
		return self::build(
			(string) ( $context['scope'] ?? '' ),
			(string) ( $context['namespace'] ?? '' ),
			(string) ( $context['name'] ?? '' ),
			(string) ( $context['block_type'] ?? '' ),
			(string) ( $context['post_type'] ?? '' ),
			(string) ( $context['meta_key'] ?? '' )
		);
	}

	private static function build(
		string $scope,
		string $namespace,
		string $name,
		string $block_type,
		string $post_type,
		string $meta_key
	): string {
		switch ( $scope ) {
			case Check_Registry::SCOPE_BLOCK:
				$parts = array( $scope, $namespace, $block_type, $name );
				break;
			case Check_Registry::SCOPE_META:
				$parts = array( $scope, $namespace, $post_type, $meta_key, $name );
				break;
			case Check_Registry::SCOPE_EDITOR:
				$parts = array( $scope, $namespace, $post_type, $name );
				break;
			default:
				$parts = array( $scope, $namespace, $name );
				break;
		}
		return implode( self::SEP, $parts );
	}
}

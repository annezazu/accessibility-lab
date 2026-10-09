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

/**
 * Derives the canonical key for a validation check.
 *
 * Stateless; every method is static. The two public entry points exist because
 * the same key has to be produced from two differently-shaped inputs.
 */
final class Check_Key {

	/**
	 * Separator between key segments. Double underscore because block types
	 * and meta keys may contain `/`, `-` and single underscores.
	 */
	private const SEP = '__';

	/**
	 * Key for a normalized registry record.
	 *
	 * @param array<string, mixed> $check Normalized check record.
	 * @return string The check's canonical key.
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
	 * @param array<string, mixed> $context Filter context passed alongside the level.
	 * @return string The check's canonical key.
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

	/**
	 * Assemble the key from its parts.
	 *
	 * Which parts participate depends on the scope: block checks key off the
	 * block type, meta checks off the post type and meta key, editor checks
	 * off the post type alone.
	 *
	 * @param string $scope           One of the Check_Registry SCOPE_* constants.
	 * @param string $check_namespace Namespace slug the check was registered under.
	 * @param string $name            Check slug.
	 * @param string $block_type      Block type for block-scope checks.
	 * @param string $post_type       Post type for meta- and editor-scope checks.
	 * @param string $meta_key        Meta key for meta-scope checks.
	 * @return string The check's canonical key.
	 */
	private static function build(
		string $scope,
		string $check_namespace,
		string $name,
		string $block_type,
		string $post_type,
		string $meta_key
	): string {
		switch ( $scope ) {
			case Check_Registry::SCOPE_BLOCK:
				$parts = array( $scope, $check_namespace, $block_type, $name );
				break;
			case Check_Registry::SCOPE_META:
				$parts = array( $scope, $check_namespace, $post_type, $meta_key, $name );
				break;
			case Check_Registry::SCOPE_EDITOR:
				$parts = array( $scope, $check_namespace, $post_type, $name );
				break;
			default:
				$parts = array( $scope, $check_namespace, $name );
				break;
		}
		return implode( self::SEP, $parts );
	}
}

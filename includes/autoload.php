<?php
/**
 * PSR-4-ish autoloader for the AccessibilityLab namespace.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'AccessibilityLab\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

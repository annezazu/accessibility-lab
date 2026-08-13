<?php
/**
 * PSR-4-ish autoloader for the AccessibilityLab namespace.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'AccessibilityLab\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

<?php
/**
 * Uninstall handler.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/autoload.php';

$accessibility_lab = \AccessibilityLab\Plugin::instance();
$accessibility_lab->boot(); // Registers modules so on_uninstall() can run.

foreach ( $accessibility_lab->registry->all() as $module ) {
	$module->on_uninstall();
}

delete_option( \AccessibilityLab\Registry::OPTION_KEY );

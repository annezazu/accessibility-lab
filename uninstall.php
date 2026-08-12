<?php
/**
 * Uninstall handler.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/autoload.php';

$plugin = \AccessibilityLab\Plugin::instance();
$plugin->boot(); // Registers modules so on_uninstall() can run.

foreach ( $plugin->registry->all() as $module ) {
	$module->on_uninstall();
}

delete_option( \AccessibilityLab\Registry::OPTION_KEY );

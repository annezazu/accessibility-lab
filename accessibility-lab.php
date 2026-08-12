<?php
/**
 * Plugin Name:       Accessibility Lab
 * Plugin URI:        https://github.com/WordPress/accessibility-lab
 * Description:       An umbrella of accessibility Features (adopted from the community) and Experiments (Core-track).
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            WordPress Accessibility Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       accessibility-lab
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

define( 'ACCESSIBILITY_LAB_VERSION', '0.1.0' );
define( 'ACCESSIBILITY_LAB_FILE', __FILE__ );
define( 'ACCESSIBILITY_LAB_DIR', __DIR__ );
define( 'ACCESSIBILITY_LAB_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/autoload.php';

add_action(
	'plugins_loaded',
	static function (): void {
		\AccessibilityLab\Plugin::instance()->boot();
	},
	5
);

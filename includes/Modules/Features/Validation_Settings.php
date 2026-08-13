<?php
/**
 * Feature: Validation Settings.
 *
 * Ports troychaplin/validation-api-settings and adds automatic per-namespace
 * settings-page generation. Admins can override each registered check's
 * severity (error / warning / disabled).
 *
 * Silently no-ops if the Block Validation Framework module isn't active.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features;

use AccessibilityLab\Abstracts\Abstract_Module;
use AccessibilityLab\Bucket;
use AccessibilityLab\Credits;
use AccessibilityLab\Track;
use AccessibilityLab\Modules\Features\ValidationSettings\Admin_Pages;
use AccessibilityLab\Modules\Features\ValidationSettings\Level_Override;
use AccessibilityLab\Modules\Features\ValidationSettings\Rest_Controller;

/**
 * Feature: Validation Settings.
 */
final class Validation_Settings extends Abstract_Module {

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'validation_settings';
	}

	/**
	 * Module bucket.
	 *
	 * @return string
	 */
	public function bucket(): string {
		return Bucket::FEATURE;
	}

	/**
	 * Module track.
	 *
	 * @return string
	 */
	public function track(): string {
		return Track::PRACTICAL;
	}

	/**
	 * Module display name.
	 *
	 * @return string
	 */
	public function name(): string {
		return __( 'Validation settings', 'accessibility-lab' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Admin UI for overriding the severity of every registered validation check. Adds an auto-generated settings page for each plugin namespace that registers checks. Requires the Block Validation Framework module.', 'accessibility-lab' );
	}

	/**
	 * Attribution for this module's origin.
	 *
	 * @return Credits
	 */
	public function credits(): Credits {
		return new Credits(
			author: 'Troy Chaplin',
			source_plugin_slug: 'validation-api-settings',
			source_plugin_url: 'https://github.com/troychaplin/validation-api-settings',
			license: 'GPL-2.0-or-later'
		);
	}

	/**
	 * Register hooks for the severity-override filter, REST routes, and admin pages.
	 */
	public function boot(): void {
		( new Level_Override() )->register();
		( new Rest_Controller() )->register();
		( new Admin_Pages() )->register();
	}
}

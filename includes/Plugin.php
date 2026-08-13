<?php
/**
 * Plugin bootstrapper.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab;

use AccessibilityLab\Admin\Settings_Page;
use AccessibilityLab\Modules\Experiments\Block_Validation_Framework;
use AccessibilityLab\Modules\Experiments\Heading_Order;
use AccessibilityLab\Modules\Features\Core_Block_Validation_Rules;
use AccessibilityLab\Modules\Features\Media_Library_View_Config;
use AccessibilityLab\Modules\Features\Skip_Link_Generator;
use AccessibilityLab\Modules\Features\Validation_Settings;
use AccessibilityLab\REST\Modules_Controller;

/**
 * Singleton entry point that registers modules and boots the plugin.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Module registry for this plugin instance.
	 *
	 * @var Registry
	 */
	public readonly Registry $registry;

	/**
	 * Get (creating if necessary) the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Private: use instance() to obtain the singleton.
	 */
	private function __construct() {
		$this->registry = new Registry();
	}

	/**
	 * Register first-party modules, let third parties register their own,
	 * boot every enabled module, and wire up the settings admin/REST surfaces.
	 */
	public function boot(): void {
		$this->register_first_party_modules();
		$this->registry->set_dependencies(
			array(
				'core_block_validation_rules' => 'block_validation_framework',
				'validation_settings'         => 'block_validation_framework',
			)
		);

		/**
		 * Fires when third parties may register their own modules.
		 *
		 * @param Registry $registry
		 */
		do_action( 'accessibility_lab_register_modules', $this->registry );

		$this->registry->boot_enabled();

		( new Settings_Page( $this->registry ) )->register();
		( new Modules_Controller( $this->registry ) )->register();

		// Register the modules-settings option with core so it's visible
		// via `GET /wp/v2/settings`, has a sanitize callback that runs on
		// every write path (not just our REST endpoint), and shows up in
		// WP-CLI. The schema mirrors what the Registry actually stores.
		add_action( 'init', array( $this, 'register_option' ) );
	}

	/**
	 * Register the modules-settings option with core (see boot() for why).
	 */
	public function register_option(): void {
		register_setting(
			'accessibility_lab',
			Registry::OPTION_KEY,
			array(
				'type'              => 'object',
				'description'       => __( 'Which Accessibility Lab modules are enabled.', 'accessibility-lab' ),
				'default'           => array(),
				'show_in_rest'      => false, // Written via our own controller; hide from generic settings endpoint.
				'sanitize_callback' => static function ( $value ): array {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$out = array();
					foreach ( $value as $id => $enabled ) {
						$out[ (string) $id ] = (bool) $enabled;
					}
					return $out;
				},
			)
		);
	}

	/**
	 * Register every module shipped with this plugin.
	 */
	private function register_first_party_modules(): void {
		$this->registry->register( new Skip_Link_Generator() );
		$this->registry->register( new Media_Library_View_Config() );
		$this->registry->register( new Heading_Order() );
		$this->registry->register( new Block_Validation_Framework() );
		$this->registry->register( new Core_Block_Validation_Rules() );
		$this->registry->register( new Validation_Settings() );
	}
}

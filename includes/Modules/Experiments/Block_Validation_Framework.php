<?php
/**
 * Experiment: Block Validation Framework.
 *
 * Ports troychaplin/validation-api as an internal module. Provides:
 *
 * - A PHP registry of validation checks (block / meta / editor scopes).
 * - Global registration functions used by third-party plugins.
 * - A REST endpoint (GET /wp-validation/v1/checks) for introspection.
 * - A JS runtime that wires editor.validateBlock/Meta/Editor filters into
 *   a @wordpress/data store, shows border indicators, renders a
 *   Validation sidebar, and locks post save on error-level failures.
 *
 * Attribution: adapted from Troy Chaplin's validation-api plugin —
 * https://github.com/troychaplin/validation-api
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Experiments;

use AccessibilityLab\Abstracts\Abstract_Module;
use AccessibilityLab\Bucket;
use AccessibilityLab\Track;
use AccessibilityLab\Modules\Experiments\BlockValidation\Check_Registry;
use AccessibilityLab\Modules\Experiments\BlockValidation\Rest_Controller;

/**
 * Module wiring for the block-validation framework.
 *
 * Owns the single shared Check_Registry, defines the global registration
 * functions third parties call, exposes the REST introspection route, and
 * hands the registered checks to the editor as block editor settings.
 */
final class Block_Validation_Framework extends Abstract_Module {

	/**
	 * The one registry every caller registers into.
	 *
	 * Static because the global `validation_api_register_*` functions have no
	 * module instance to reach through. Null until `boot()` runs, which is why
	 * `shared_registry()` is nullable.
	 *
	 * @var Check_Registry|null
	 */
	private static ?Check_Registry $shared_registry = null;

	/**
	 * Module id used as the settings key and dependency reference.
	 */
	public function id(): string {
		return 'block_validation_framework';
	}

	/**
	 * Shape and maturity of this module.
	 *
	 * @return string Bucket::FEATURE or Bucket::EXPERIMENT.
	 */
	public function bucket(): string {
		return Bucket::FEATURE;
	}

	/**
	 * Whether this module is aimed at WordPress Core.
	 *
	 * @return string Track::CORE_TRACK or Track::PRACTICAL.
	 */
	public function track(): string {
		return Track::PRACTICAL;
	}

	/**
	 * Human-readable module name shown on the settings screen.
	 */
	public function name(): string {
		return __( 'Block Validation Framework', 'accessibility-lab' );
	}

	/**
	 * Human-readable module description shown on the settings screen.
	 */
	public function description(): string {
		return __( 'Framework letting any plugin register real-time validation checks for blocks, post meta, and editor-level document concerns.', 'accessibility-lab' );
	}

	/**
	 * Register hooks. Only called when the module is enabled.
	 */
	public function boot(): void {
		self::$shared_registry ??= new Check_Registry();

		require_once __DIR__ . '/BlockValidation/Global_Functions.php';

		( new Rest_Controller( self::$shared_registry ) )->register();

		add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_settings' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * The shared registry, for the global registration functions.
	 *
	 * @return Check_Registry|null Null when the module hasn't booted.
	 */
	public static function shared_registry(): ?Check_Registry {
		return self::$shared_registry;
	}

	/**
	 * Hand the registered checks to the editor via block editor settings.
	 *
	 * Each check is decorated on read with its `resolved_level` and the
	 * `plugin_title` it should be credited to, so the JS runtime doesn't have
	 * to re-resolve either.
	 *
	 * @param array<string, mixed> $settings Block editor settings.
	 * @return array<string, mixed> Settings with `validationApi` added.
	 */
	public function inject_editor_settings( array $settings ): array {
		if ( ! self::$shared_registry ) {
			return $settings;
		}
		$scoped = self::$shared_registry->all_by_scope();
		foreach ( $scoped as $scope => $checks ) {
			foreach ( $checks as $i => $check ) {
				$scoped[ $scope ][ $i ]['resolved_level'] = self::$shared_registry->resolve_level( $check );
				$scoped[ $scope ][ $i ]['plugin_title']   = self::$shared_registry->resolve_plugin_title( $check );
			}
		}
		$settings['validationApi'] = array(
			'checks' => $scoped,
		);
		return $settings;
	}

	/**
	 * Enqueue the validation runtime script and its styles in the editor.
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = ACCESSIBILITY_LAB_DIR . '/build/validation-framework.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'accessibility-lab-validation-framework',
			ACCESSIBILITY_LAB_URL . 'build/validation-framework.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? ACCESSIBILITY_LAB_VERSION,
			true
		);

		$style_file = ACCESSIBILITY_LAB_DIR . '/build/style-validation-framework.css';
		if ( file_exists( $style_file ) ) {
			wp_enqueue_style(
				'accessibility-lab-validation-framework',
				ACCESSIBILITY_LAB_URL . 'build/style-validation-framework.css',
				array(),
				$asset['version'] ?? ACCESSIBILITY_LAB_VERSION
			);
		}
	}
}

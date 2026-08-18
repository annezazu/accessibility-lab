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

final class Block_Validation_Framework extends Abstract_Module {

	private static ?Check_Registry $shared_registry = null;

	public function id(): string {
		return 'block_validation_framework';
	}

	public function bucket(): string {
		return Bucket::FEATURE;
	}

	public function track(): string {
		return Track::PRACTICAL;
	}

	public function name(): string {
		return __( 'Block Validation Framework', 'accessibility-lab' );
	}

	public function description(): string {
		return __( 'Framework letting any plugin register real-time validation checks for blocks, post meta, and editor-level document concerns.', 'accessibility-lab' );
	}

	public function boot(): void {
		self::$shared_registry ??= new Check_Registry();

		require_once __DIR__ . '/BlockValidation/Global_Functions.php';

		( new Rest_Controller( self::$shared_registry ) )->register();

		add_filter( 'block_editor_settings_all', array( $this, 'inject_editor_settings' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public static function shared_registry(): ?Check_Registry {
		return self::$shared_registry;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
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


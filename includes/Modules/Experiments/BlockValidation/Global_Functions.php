<?php
/**
 * Global registration functions for the block-validation framework.
 *
 * Third-party plugins call these on `init`. They resolve to the module's
 * Check_Registry instance held by the framework module. Defined only when
 * the Block Validation Framework module is active.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

use AccessibilityLab\Modules\Experiments\Block_Validation_Framework;
use AccessibilityLab\Modules\Experiments\BlockValidation\Check_Registry;

if ( ! function_exists( 'accessibility_lab_validation_check_registry' ) ) {
	/**
	 * Public accessor for the framework's shared check registry.
	 * Returns null when the Block Validation Framework module is not booted.
	 */
	function accessibility_lab_validation_check_registry(): ?Check_Registry {
		return Block_Validation_Framework::shared_registry();
	}
}

if ( ! function_exists( 'validation_api_register_block_check' ) ) {
	/**
	 * Register a block-scope validation check.
	 *
	 * @param string               $block_type e.g. 'core/image'.
	 * @param array<string, mixed> $args       namespace, name, level, error_msg, ...
	 */
	function validation_api_register_block_check( string $block_type, array $args ): void {
		$registry = accessibility_lab_validation_check_registry();
		if ( $registry instanceof Check_Registry ) {
			$registry->register_block( $block_type, $args );
		}
	}
}

if ( ! function_exists( 'validation_api_register_meta_check' ) ) {
	/**
	 * Register a meta-scope validation check.
	 *
	 * @param string               $post_type
	 * @param array<string, mixed> $args
	 */
	function validation_api_register_meta_check( string $post_type, array $args ): void {
		$registry = accessibility_lab_validation_check_registry();
		if ( $registry instanceof Check_Registry ) {
			$registry->register_meta( $post_type, $args );
		}
	}
}

if ( ! function_exists( 'validation_api_register_editor_check' ) ) {
	/**
	 * Register an editor-scope validation check.
	 *
	 * @param string               $post_type Use '*' to apply everywhere.
	 * @param array<string, mixed> $args
	 */
	function validation_api_register_editor_check( string $post_type, array $args ): void {
		$registry = accessibility_lab_validation_check_registry();
		if ( $registry instanceof Check_Registry ) {
			$registry->register_editor( $post_type, $args );
		}
	}
}

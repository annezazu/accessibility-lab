<?php
/**
 * In-memory registry of validation checks (block / meta / editor).
 *
 * Populated at `init` by first- and third-party callers through the global
 * `validation_api_register_*` functions defined in Global_Functions.php.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Experiments\BlockValidation;

/**
 * In-memory registry of validation checks (block / meta / editor).
 */
final class Check_Registry {

	public const SCOPE_BLOCK  = 'block';
	public const SCOPE_META   = 'meta';
	public const SCOPE_EDITOR = 'editor';

	/**
	 * Registered checks, keyed by scope.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $checks = array(
		self::SCOPE_BLOCK  => array(),
		self::SCOPE_META   => array(),
		self::SCOPE_EDITOR => array(),
	);

	/**
	 * Register a block-scope check.
	 *
	 * @param string               $block_type e.g. 'core/image'.
	 * @param array<string, mixed> $args       Check definition.
	 */
	public function register_block( string $block_type, array $args ): void {
		$check                               = $this->normalise( $args, self::SCOPE_BLOCK );
		$check['block_type']                 = $block_type;
		$this->checks[ self::SCOPE_BLOCK ][] = $check;
	}

	/**
	 * Register a meta-scope check.
	 *
	 * @param string               $post_type e.g. 'post'.
	 * @param array<string, mixed> $args      Check definition (must include `meta_key`).
	 */
	public function register_meta( string $post_type, array $args ): void {
		$check              = $this->normalise( $args, self::SCOPE_META );
		$check['post_type'] = $post_type;
		// This is a key in our own in-memory check-definition array, not a
		// database query argument.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$check['meta_key']                  = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
		$this->checks[ self::SCOPE_META ][] = $check;
	}

	/**
	 * Register an editor-scope check.
	 *
	 * @param string               $post_type e.g. 'post' or '*' for all.
	 * @param array<string, mixed> $args      Check definition.
	 */
	public function register_editor( string $post_type, array $args ): void {
		$check                                = $this->normalise( $args, self::SCOPE_EDITOR );
		$check['post_type']                   = $post_type;
		$this->checks[ self::SCOPE_EDITOR ][] = $check;
	}

	/**
	 * Every registered block-scope check.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function block_checks(): array {
		return $this->checks[ self::SCOPE_BLOCK ];
	}

	/**
	 * Every registered meta-scope check.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function meta_checks(): array {
		return $this->checks[ self::SCOPE_META ];
	}

	/**
	 * Every registered editor-scope check.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function editor_checks(): array {
		return $this->checks[ self::SCOPE_EDITOR ];
	}

	/**
	 * Every registered check, keyed by scope.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function all_by_scope(): array {
		return $this->checks;
	}

	/**
	 * Every unique namespace across all scopes.
	 *
	 * @return array<int, string>
	 */
	public function namespaces(): array {
		$out = array();
		foreach ( $this->checks as $bucket ) {
			foreach ( $bucket as $check ) {
				$ns = (string) ( $check['namespace'] ?? '' );
				if ( '' !== $ns ) {
					$out[ $ns ] = true;
				}
			}
		}
		return array_keys( $out );
	}

	/**
	 * Apply the `validation_api_check_level` filter to a check's declared level.
	 *
	 * @param array<string, mixed> $check Check definition, as built by normalise().
	 */
	public function resolve_level( array $check ): string {
		$level   = (string) ( $check['level'] ?? 'error' );
		$context = array(
			'scope'      => (string) ( $check['scope'] ?? '' ),
			'namespace'  => (string) ( $check['namespace'] ?? '' ),
			'name'       => (string) ( $check['name'] ?? '' ),
			'block_type' => (string) ( $check['block_type'] ?? '' ),
			'post_type'  => (string) ( $check['post_type'] ?? '' ),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Array key in our own in-memory check definition, not a query arg.
			'meta_key'   => (string) ( $check['meta_key'] ?? '' ),
		);
		/** This filter is documented in accessibility-lab.php */
		return (string) apply_filters( 'validation_api_check_level', $level, $context );
	}

	/**
	 * Fill in a check definition's shared fields with sane defaults.
	 *
	 * @param array<string, mixed> $args  Raw check definition passed by the caller.
	 * @param string               $scope One of the SCOPE_* constants.
	 * @return array<string, mixed>
	 */
	private function normalise( array $args, string $scope ): array {
		return array(
			'scope'        => $scope,
			'namespace'    => isset( $args['namespace'] ) ? (string) $args['namespace'] : 'unknown',
			'name'         => isset( $args['name'] ) ? (string) $args['name'] : '',
			'level'        => isset( $args['level'] ) ? (string) $args['level'] : 'error',
			'description'  => isset( $args['description'] ) ? (string) $args['description'] : '',
			'error_msg'    => isset( $args['error_msg'] ) ? (string) $args['error_msg'] : '',
			'warning_msg'  => isset( $args['warning_msg'] ) ? (string) $args['warning_msg'] : '',
			'plugin_title' => isset( $args['plugin_title'] ) ? (string) $args['plugin_title'] : '',
			'configurable' => ! isset( $args['configurable'] ) || (bool) $args['configurable'],
		);
	}
}

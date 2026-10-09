<?php
/**
 * REST introspection for the Block Validation Framework module.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Experiments\BlockValidation;

use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes the registered validation checks over the REST API.
 *
 * Read-only introspection for the editor and the admin app: one route,
 * `GET /wp-validation/v1/checks`, returning every registered check with its
 * resolved level and crediting plugin title.
 */
final class Rest_Controller {

	private const NS = 'wp-validation/v1';

	/**
	 * Constructor.
	 *
	 * @param Check_Registry $registry Registry holding the checks to expose.
	 */
	public function __construct( private readonly Check_Registry $registry ) {}

	/**
	 * Hook route registration onto `rest_api_init`.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the `/checks` route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/checks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_checks' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Every registered check, grouped by scope, plus the known namespaces.
	 *
	 * Each check is decorated on read with its canonical `id`, its
	 * `resolved_level` (after the `validation_api_check_level` filter), and the
	 * `plugin_title` it should be credited to.
	 *
	 * @return WP_REST_Response Response carrying `checks` and `namespaces`.
	 */
	public function get_checks(): WP_REST_Response {
		$scoped = $this->registry->all_by_scope();
		foreach ( $scoped as $scope => $checks ) {
			foreach ( $checks as $i => $check ) {
				$scoped[ $scope ][ $i ]['id']             = Check_Key::from_check( $check );
				$scoped[ $scope ][ $i ]['resolved_level'] = $this->registry->resolve_level( $check );
				$scoped[ $scope ][ $i ]['plugin_title']   = $this->registry->resolve_plugin_title( $check );
			}
		}
		return rest_ensure_response(
			array(
				'checks'     => $scoped,
				'namespaces' => $this->registry->namespaces(),
			)
		);
	}
}

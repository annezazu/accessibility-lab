<?php
/**
 * REST introspection for the block-validation framework.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Experiments\BlockValidation;

use WP_REST_Response;
use WP_REST_Server;

/**
 * REST introspection for the block-validation framework.
 */
final class Rest_Controller {

	private const NS = 'wp-validation/v1';

	/**
	 * Construct the controller.
	 *
	 * @param Check_Registry $registry Shared check registry to expose.
	 */
	public function __construct( private readonly Check_Registry $registry ) {}

	/**
	 * Hook route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the /checks route.
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
	 * GET /checks — every registered check, with severity overrides resolved.
	 *
	 * @return WP_REST_Response
	 */
	public function get_checks(): WP_REST_Response {
		$scoped = $this->registry->all_by_scope();
		foreach ( $scoped as $scope => $checks ) {
			foreach ( $checks as $i => $check ) {
				$scoped[ $scope ][ $i ]['resolved_level'] = $this->registry->resolve_level( $check );
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

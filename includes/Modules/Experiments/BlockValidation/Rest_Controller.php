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

final class Rest_Controller {

	private const NS = 'wp-validation/v1';

	public function __construct( private readonly Check_Registry $registry ) {}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

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

	public function get_checks(): WP_REST_Response {
		$scoped = $this->registry->all_by_scope();
		foreach ( $scoped as $scope => $checks ) {
			foreach ( $checks as $i => $check ) {
				$scoped[ $scope ][ $i ]['id']             = Check_Key::from_check( $check );
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

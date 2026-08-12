<?php
/**
 * REST controller for reading/writing severity overrides.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features\ValidationSettings;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Rest_Controller {

	private const NS = 'accessibility-lab/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/validation-settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => array(
						'overrides' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	public function permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_settings(): WP_REST_Response {
		$stored = get_option( Level_Override::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return rest_ensure_response( array( 'overrides' => (object) $stored ) );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$incoming = (array) $request->get_param( 'overrides' );
		$clean    = array();
		foreach ( $incoming as $key => $value ) {
			$value = (string) $value;
			if ( in_array( $value, array( 'error', 'warning', 'disabled' ), true ) ) {
				$clean[ (string) $key ] = $value;
			}
		}
		update_option( Level_Override::OPTION_KEY, $clean, false );
		return rest_ensure_response( array( 'overrides' => (object) $clean ) );
	}
}

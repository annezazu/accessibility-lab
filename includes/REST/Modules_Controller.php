<?php
/**
 * REST controller powering the settings page.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\REST;

use AccessibilityLab\Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller powering the settings page's module list and toggles.
 */
final class Modules_Controller {

	private const NS  = 'accessibility-lab/v1';
	private const CAP = 'manage_options';

	/**
	 * Construct the controller.
	 *
	 * @param Registry $registry Module registry to read from and write to.
	 */
	public function __construct( private readonly Registry $registry ) {}

	/**
	 * Hook route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the /modules GET and POST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/modules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_modules' ),
					'permission_callback' => array( $this, 'permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => array(
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback for both /modules routes.
	 *
	 * @return bool|WP_Error
	 */
	public function permissions(): bool|WP_Error {
		if ( ! current_user_can( self::CAP ) ) {
			return new WP_Error(
				'accessibility_lab_forbidden',
				__( 'You do not have permission to manage Accessibility Lab.', 'accessibility-lab' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * GET /modules — list every registered module with its enabled state.
	 *
	 * @return WP_REST_Response
	 */
	public function get_modules(): WP_REST_Response {
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * POST /modules — persist enabled/disabled changes.
	 *
	 * @param WP_REST_Request $request Request carrying a `settings` map.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$incoming = (array) $request->get_param( 'settings' );
		$clean    = array();
		foreach ( $incoming as $id => $enabled ) {
			$clean[ (string) $id ] = (bool) $enabled;
		}
		$this->registry->update_settings( $clean );
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Build the response payload shared by both routes.
	 *
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		$settings = $this->registry->settings();
		$modules  = array();
		foreach ( $this->registry->all() as $id => $module ) {
			$modules[] = array(
				'id'          => $id,
				'bucket'      => $module->bucket(),
				'track'       => $module->track(),
				'name'        => $module->name(),
				'description' => $module->description(),
				'enabled'     => (bool) ( $settings[ $id ] ?? false ),
				'credits'     => $module->credits()?->to_array(),
			);
		}
		return array(
			'modules'      => $modules,
			'settings'     => (object) $settings,
			'dependencies' => (object) $this->registry->dependencies(),
		);
	}
}

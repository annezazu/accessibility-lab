<?php
/**
 * REST controller for reading/writing severity overrides.
 *
 * @package AccessibilityLab
 */

declare( strict_types = 1 );

namespace AccessibilityLab\Modules\Features\ValidationSettings;

use AccessibilityLab\Modules\Experiments\Block_Validation_Framework;
use AccessibilityLab\Modules\Experiments\BlockValidation\Check_Key;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Rest_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'accessibility-lab/v1';
		$this->rest_base = 'validation-settings';
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'update_items_permissions_check' ),
					'args'                => array(
						'overrides' => array(
							'description' => __( 'Map of check key to severity level.', 'accessibility-lab' ),
							'type'        => 'object',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->permissions_check();
	}

	/**
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function update_items_permissions_check( $request ) {
		return $this->permissions_check();
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_items( $request ): WP_REST_Response {
		return rest_ensure_response( array( 'overrides' => (object) $this->stored() ) );
	}

	/**
	 * Replaces the whole override map.
	 *
	 * @param WP_REST_Request $request
	 */
	public function update_items( $request ): WP_REST_Response {
		$clean = $this->sanitize_overrides( (array) $request->get_param( 'overrides' ) );

		update_option( Level_Override::OPTION_KEY, $clean, false );

		return rest_ensure_response( array( 'overrides' => (object) $clean ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'validation-settings',
			'type'       => 'object',
			'properties' => array(
				'overrides' => array(
					'description'          => __( 'Map of check key to severity level. Keys not matching a registered check are discarded.', 'accessibility-lab' ),
					'type'                 => 'object',
					'context'              => array( 'edit' ),
					'additionalProperties' => array(
						'type' => 'string',
						'enum' => Level_Override::LEVELS,
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * @return true|WP_Error
	 */
	private function permissions_check() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to manage validation settings.', 'accessibility-lab' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function stored(): array {
		$stored = get_option( Level_Override::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Keeps only recognised levels against keys that match a check currently
	 * registered as configurable. This both rejects junk and prunes keys left
	 * behind by checks that have since been removed or renamed.
	 *
	 * @param array<string, mixed> $incoming
	 * @return array<string, string>
	 */
	private function sanitize_overrides( array $incoming ): array {
		$known = $this->known_keys();
		$clean = array();

		foreach ( $incoming as $key => $value ) {
			$key   = (string) $key;
			$value = (string) $value;

			if ( ! isset( $known[ $key ] ) ) {
				continue;
			}
			if ( ! in_array( $value, Level_Override::LEVELS, true ) ) {
				continue;
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Keys of every configurable check in the live registry.
	 *
	 * @return array<string, true>
	 */
	private function known_keys(): array {
		$registry = Block_Validation_Framework::shared_registry();
		if ( ! $registry ) {
			return array();
		}

		$keys = array();
		foreach ( $registry->all_by_scope() as $checks ) {
			foreach ( $checks as $check ) {
				if ( empty( $check['configurable'] ) ) {
					continue;
				}
				$keys[ Check_Key::from_check( $check ) ] = true;
			}
		}

		return $keys;
	}
}

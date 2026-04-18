<?php
/**
 * REST endpoints: /locations, /locations/{slug}
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\LocationRepository;
use WPAIL\Support\RelationshipHelper;

class LocationsController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/locations', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/locations/(?P<slug>[a-z0-9-]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'slug' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					],
				],
			],
		] );
	}

	public function get_items( $request ) {
		$repo      = new LocationRepository();
		$locations = $repo->get_all();

		$data = array_map( fn( $l ) => $l->to_summary_array(), $locations );

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}

	public function get_item( $request ) {
		$repo     = new LocationRepository();
		$location = $repo->find_by_slug( $request->get_param( 'slug' ) );

		if ( null === $location ) {
			return $this->not_found( 'Location not found.' );
		}

		return $this->success(
			$location->to_public_array(
				services: RelationshipHelper::resolve_summaries( $location->related_service_ids ),
				proof:    RelationshipHelper::resolve_summaries( $location->local_proof_ids ),
			)
		);
	}
}

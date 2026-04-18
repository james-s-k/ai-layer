<?php
/**
 * REST endpoint: /proof
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\ProofRepository;
use WPAIL\Support\RelationshipHelper;

class ProofController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/proof', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'service' => [
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );
	}

	public function get_items( $request ) {
		$repo       = new ProofRepository();
		$service_id = (int) $request->get_param( 'service' );

		$proof = $service_id > 0
			? $repo->find_by_service( $service_id )
			: $repo->get_all();

		$data = array_map(
			fn( $p ) => $p->to_public_array(
				services:  RelationshipHelper::resolve_summaries( $p->related_service_ids ),
				locations: RelationshipHelper::resolve_summaries( $p->related_location_ids ),
			),
			$proof
		);

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}
}

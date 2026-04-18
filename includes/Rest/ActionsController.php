<?php
/**
 * REST endpoint: /actions
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\ActionRepository;
use WPAIL\Support\RelationshipHelper;

class ActionsController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/actions', [
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
		$repo       = new ActionRepository();
		$service_id = (int) $request->get_param( 'service' );

		$actions = $service_id > 0
			? $repo->find_by_service( $service_id )
			: $repo->get_all();

		$data = array_map(
			fn( $a ) => $a->to_public_array(
				services:  RelationshipHelper::resolve_summaries( $a->related_service_ids ),
				locations: RelationshipHelper::resolve_summaries( $a->related_location_ids ),
			),
			$actions
		);

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}
}

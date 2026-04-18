<?php
/**
 * REST endpoint: /faqs
 *
 * Supports filtering by:
 *   ?service={id}
 *   ?location={id}
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\FaqRepository;
use WPAIL\Support\RelationshipHelper;

class FaqsController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/faqs', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'service'  => [
						'description'       => 'Filter by service post ID.',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'location' => [
						'description'       => 'Filter by location post ID.',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );
	}

	public function get_items( $request ) {
		$repo       = new FaqRepository();
		$service_id = (int) $request->get_param( 'service' );
		$location_id= (int) $request->get_param( 'location' );

		if ( $service_id > 0 ) {
			$faqs = $repo->find_by_service( $service_id );
		} elseif ( $location_id > 0 ) {
			$faqs = $repo->find_by_location( $location_id );
		} else {
			$faqs = $repo->get_all();
		}

		$data = array_map(
			fn( $f ) => $f->to_public_array(
				services:  RelationshipHelper::resolve_summaries( $f->related_service_ids ),
				locations: RelationshipHelper::resolve_summaries( $f->related_location_ids ),
			),
			$faqs
		);

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}
}

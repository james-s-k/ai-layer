<?php
/**
 * REST endpoints: /services, /services/{slug}
 *
 * GET /wp-json/ai-layer/v1/services
 * GET /wp-json/ai-layer/v1/services/{slug}
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\ServiceRepository;
use WPAIL\Support\RelationshipHelper;

class ServicesController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/services', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/services/(?P<slug>[a-z0-9-]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'slug' => [
						'description'       => 'Service slug.',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					],
				],
			],
		] );
	}

	public function get_items( $request ) {
		$repo     = new ServiceRepository();
		$services = $repo->get_all();

		$data = array_map(
			fn( $s ) => $s->to_summary_array(),
			$services
		);

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}

	public function get_item( $request ) {
		$repo    = new ServiceRepository();
		$service = $repo->find_by_slug( $request->get_param( 'slug' ) );

		if ( null === $service ) {
			return $this->not_found( 'Service not found.' );
		}

		return $this->success(
			$service->to_public_array(
				faqs:      RelationshipHelper::resolve_summaries( $service->related_faq_ids ),
				proof:     RelationshipHelper::resolve_summaries( $service->related_proof_ids ),
				actions:   RelationshipHelper::resolve_summaries( $service->related_action_ids ),
				locations: RelationshipHelper::resolve_summaries( $service->related_location_ids ),
			)
		);
	}
}

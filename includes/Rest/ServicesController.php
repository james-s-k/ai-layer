<?php
/**
 * REST endpoints: /services, /services/{slug}
 *
 * GET    /wp-json/ai-layer/v1/services           — list all services
 * POST   /wp-json/ai-layer/v1/services           — create a service (auth required)
 * GET    /wp-json/ai-layer/v1/services/{slug}    — get service detail
 * PATCH  /wp-json/ai-layer/v1/services/{slug}    — update a service (auth required)
 * DELETE /wp-json/ai-layer/v1/services/{slug}    — delete a service (auth required)
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\ServiceRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class ServicesController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/services', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
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
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'slug' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_title' ],
				],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'slug' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_title' ],
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

		return $this->success( $this->resolve( $service ) );
	}

	public function create_item( $request ) {
		$params = (array) ( $request->get_json_params() ?? [] );
		$title  = sanitize_text_field( $params['title'] ?? '' );

		if ( '' === $title ) {
			return $this->bad_request( 'title is required.' );
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'wpail_service',
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = Sanitizer::sanitize_fields( $params, FieldDefinitions::service() );
		RelationshipHelper::save_meta( $post_id, $meta );
		RelationshipSync::sync( $post_id, 'wpail_service', [], $meta );

		$service = ( new ServiceRepository() )->find_by_id( $post_id );
		return $this->created( $service ? $this->resolve( $service ) : null );
	}

	public function update_item( $request ) {
		$repo    = new ServiceRepository();
		$service = $repo->find_by_slug( $request->get_param( 'slug' ) );

		if ( null === $service ) {
			return $this->not_found( 'Service not found.' );
		}

		$post_id  = $service->id;
		$params   = (array) ( $request->get_json_params() ?? [] );
		$old_meta = RelationshipHelper::get_meta( $post_id );

		if ( isset( $params['title'] ) ) {
			wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $params['title'] ) ] );
		}

		$new_meta = array_merge( $old_meta, $this->sanitize_partial( $params, FieldDefinitions::service() ) );
		RelationshipHelper::save_meta( $post_id, $new_meta );
		RelationshipSync::sync( $post_id, 'wpail_service', $old_meta, $new_meta );

		$updated = $repo->find_by_id( $post_id );
		return $this->success( $updated ? $this->resolve( $updated ) : null );
	}

	public function delete_item( $request ) {
		$repo    = new ServiceRepository();
		$service = $repo->find_by_slug( $request->get_param( 'slug' ) );

		if ( null === $service ) {
			return $this->not_found( 'Service not found.' );
		}

		$post_id  = $service->id;
		$old_meta = RelationshipHelper::get_meta( $post_id );
		RelationshipSync::sync( $post_id, 'wpail_service', $old_meta, [] );
		wp_delete_post( $post_id, true );

		return $this->success( [ 'deleted' => true, 'id' => $post_id ] );
	}

	private function resolve( $service ): array {
		return $service->to_public_array(
			faqs:      RelationshipHelper::resolve_summaries( $service->related_faq_ids ),
			proof:     RelationshipHelper::resolve_summaries( $service->related_proof_ids ),
			actions:   RelationshipHelper::resolve_summaries( $service->related_action_ids ),
			locations: RelationshipHelper::resolve_summaries( $service->related_location_ids ),
		);
	}
}

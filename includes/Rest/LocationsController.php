<?php
/**
 * REST endpoints: /locations, /locations/{slug}
 *
 * GET    /wp-json/ai-layer/v1/locations          — list all locations
 * POST   /wp-json/ai-layer/v1/locations          — create a location (auth required)
 * GET    /wp-json/ai-layer/v1/locations/{slug}   — get location detail
 * PATCH  /wp-json/ai-layer/v1/locations/{slug}   — update a location (auth required)
 * DELETE /wp-json/ai-layer/v1/locations/{slug}   — delete a location (auth required)
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\LocationRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class LocationsController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/locations', [
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

		register_rest_route( $this->namespace, '/locations/(?P<slug>[a-z0-9-]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'slug' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_title' ],
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

		return $this->success( $this->resolve( $location ) );
	}

	public function create_item( $request ) {
		$params = (array) ( $request->get_json_params() ?? [] );
		$title  = sanitize_text_field( $params['title'] ?? '' );

		if ( '' === $title ) {
			return $this->bad_request( 'title is required.' );
		}

		$cap = $this->assert_can_create_post_type( 'wpail_location' );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'wpail_location',
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = Sanitizer::sanitize_fields( $params, FieldDefinitions::location() );
		RelationshipHelper::save_meta( $post_id, $meta );
		RelationshipSync::sync( $post_id, 'wpail_location', [], $meta );

		$location = ( new LocationRepository() )->find_by_id( $post_id );
		return $this->created( $location ? $this->resolve( $location ) : null );
	}

	public function update_item( $request ) {
		$repo     = new LocationRepository();
		$location = $repo->find_by_slug( $request->get_param( 'slug' ) );

		if ( null === $location ) {
			return $this->not_found( 'Location not found.' );
		}

		$post_id  = $location->id;
		$cap      = $this->assert_can_edit_post( $post_id );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$params   = (array) ( $request->get_json_params() ?? [] );
		$old_meta = RelationshipHelper::get_meta( $post_id );

		if ( isset( $params['title'] ) ) {
			wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $params['title'] ) ] );
		}

		$new_meta = array_merge( $old_meta, $this->sanitize_partial( $params, FieldDefinitions::location() ) );
		RelationshipHelper::save_meta( $post_id, $new_meta );
		RelationshipSync::sync( $post_id, 'wpail_location', $old_meta, $new_meta );

		$updated = $repo->find_by_id( $post_id );
		return $this->success( $updated ? $this->resolve( $updated ) : null );
	}

	public function delete_item( $request ) {
		$repo     = new LocationRepository();
		$location = $repo->find_by_slug( $request->get_param( 'slug' ) );

		if ( null === $location ) {
			return $this->not_found( 'Location not found.' );
		}

		$post_id  = $location->id;
		$cap      = $this->assert_can_delete_post( $post_id );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$old_meta = RelationshipHelper::get_meta( $post_id );
		RelationshipSync::sync( $post_id, 'wpail_location', $old_meta, [] );
		wp_delete_post( $post_id, true );

		return $this->success( [ 'deleted' => true, 'id' => $post_id ] );
	}

	private function resolve( $location ): array {
		return $location->to_public_array(
			services: RelationshipHelper::resolve_summaries( $location->related_service_ids ),
			proof:    RelationshipHelper::resolve_summaries( $location->local_proof_ids ),
		);
	}
}

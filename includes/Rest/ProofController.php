<?php
/**
 * REST endpoints: /proof, /proof/{id}
 *
 * GET    /wp-json/ai-layer/v1/proof             — list all proof items (filterable by service)
 * POST   /wp-json/ai-layer/v1/proof             — create a proof item (auth required)
 * GET    /wp-json/ai-layer/v1/proof/{id}        — get single proof item
 * PATCH  /wp-json/ai-layer/v1/proof/{id}        — update a proof item (auth required)
 * DELETE /wp-json/ai-layer/v1/proof/{id}        — delete a proof item (auth required)
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\ProofRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;
use WPAIL\Analytics\AuditLogger;

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
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/proof/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
				],
			],
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
				],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'sanitize_callback' => 'absint' ],
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

		$data = array_map( fn( $p ) => $this->resolve( $p ), $proof );

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}

	public function get_item( $request ) {
		$repo  = new ProofRepository();
		$proof = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $proof ) {
			return $this->not_found( 'Proof item not found.' );
		}

		return $this->success( $this->resolve( $proof ) );
	}

	public function create_item( $request ) {
		$params = (array) ( $request->get_json_params() ?? [] );
		$title  = sanitize_text_field( $params['title'] ?? $params['headline'] ?? '' );

		if ( '' === $title ) {
			return $this->bad_request( 'title (or headline) is required.' );
		}

		$cap = $this->assert_can_create_post_type( 'wpail_proof' );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'wpail_proof',
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = Sanitizer::sanitize_fields( $params, FieldDefinitions::proof() );
		RelationshipHelper::save_meta( $post_id, $meta );
		RelationshipSync::sync( $post_id, 'wpail_proof', [], $meta );

		$proof = ( new ProofRepository() )->find_by_id( $post_id );
		AuditLogger::log( AuditLogger::ACTION_CREATE, 'wpail_proof', $post_id );
		return $this->created( $proof ? $this->resolve( $proof ) : null );
	}

	public function update_item( $request ) {
		$repo  = new ProofRepository();
		$proof = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $proof ) {
			return $this->not_found( 'Proof item not found.' );
		}

		$post_id  = $proof->id;
		$cap      = $this->assert_can_edit_post( $post_id );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$params   = (array) ( $request->get_json_params() ?? [] );
		$old_meta = RelationshipHelper::get_meta( $post_id );

		if ( isset( $params['title'] ) ) {
			wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $params['title'] ) ] );
		}

		$new_meta = array_merge( $old_meta, $this->sanitize_partial( $params, FieldDefinitions::proof() ) );
		RelationshipHelper::save_meta( $post_id, $new_meta );
		RelationshipSync::sync( $post_id, 'wpail_proof', $old_meta, $new_meta );

		$updated = $repo->find_by_id( $post_id );
		AuditLogger::log( AuditLogger::ACTION_UPDATE, 'wpail_proof', $post_id );
		return $this->success( $updated ? $this->resolve( $updated ) : null );
	}

	public function delete_item( $request ) {
		$repo  = new ProofRepository();
		$proof = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $proof ) {
			return $this->not_found( 'Proof item not found.' );
		}

		$post_id  = $proof->id;
		$cap      = $this->assert_can_delete_post( $post_id );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$old_meta = RelationshipHelper::get_meta( $post_id );
		RelationshipSync::sync( $post_id, 'wpail_proof', $old_meta, [] );
		wp_delete_post( $post_id, true );
		AuditLogger::log( AuditLogger::ACTION_DELETE, 'wpail_proof', $post_id );

		return $this->success( [ 'deleted' => true, 'id' => $post_id ] );
	}

	private function resolve( $proof ): array {
		return $proof->to_public_array(
			services:  RelationshipHelper::resolve_summaries( $proof->related_service_ids ),
			locations: RelationshipHelper::resolve_summaries( $proof->related_location_ids ),
		);
	}
}

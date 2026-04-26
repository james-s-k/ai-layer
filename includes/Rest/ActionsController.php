<?php
/**
 * REST endpoints: /actions, /actions/{id}
 *
 * GET    /wp-json/ai-layer/v1/actions           — list all actions (filterable by service)
 * POST   /wp-json/ai-layer/v1/actions           — create an action (auth required)
 * GET    /wp-json/ai-layer/v1/actions/{id}      — get single action
 * PATCH  /wp-json/ai-layer/v1/actions/{id}      — update an action (auth required)
 * DELETE /wp-json/ai-layer/v1/actions/{id}      — delete an action (auth required)
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\ActionRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

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
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/actions/(?P<id>\d+)', [
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
		$repo       = new ActionRepository();
		$service_id = (int) $request->get_param( 'service' );

		$actions = $service_id > 0
			? $repo->find_by_service( $service_id )
			: $repo->get_all();

		$data = array_map( fn( $a ) => $this->resolve( $a ), $actions );

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}

	public function get_item( $request ) {
		$repo   = new ActionRepository();
		$action = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $action ) {
			return $this->not_found( 'Action not found.' );
		}

		return $this->success( $this->resolve( $action ) );
	}

	public function create_item( $request ) {
		$params = (array) ( $request->get_json_params() ?? [] );
		// Use explicit title if given, otherwise fall back to label (the CTA text).
		$title  = sanitize_text_field( $params['title'] ?? $params['label'] ?? '' );

		if ( '' === $title ) {
			return $this->bad_request( 'title (or label) is required.' );
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'wpail_action',
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = Sanitizer::sanitize_fields( $params, FieldDefinitions::action() );
		RelationshipHelper::save_meta( $post_id, $meta );
		RelationshipSync::sync( $post_id, 'wpail_action', [], $meta );

		$action = ( new ActionRepository() )->find_by_id( $post_id );
		return $this->created( $action ? $this->resolve( $action ) : null );
	}

	public function update_item( $request ) {
		$repo   = new ActionRepository();
		$action = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $action ) {
			return $this->not_found( 'Action not found.' );
		}

		$post_id  = $action->id;
		$params   = (array) ( $request->get_json_params() ?? [] );
		$old_meta = RelationshipHelper::get_meta( $post_id );

		if ( isset( $params['title'] ) ) {
			wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $params['title'] ) ] );
		}

		$new_meta = array_merge( $old_meta, $this->sanitize_partial( $params, FieldDefinitions::action() ) );
		RelationshipHelper::save_meta( $post_id, $new_meta );
		RelationshipSync::sync( $post_id, 'wpail_action', $old_meta, $new_meta );

		$updated = $repo->find_by_id( $post_id );
		return $this->success( $updated ? $this->resolve( $updated ) : null );
	}

	public function delete_item( $request ) {
		$repo   = new ActionRepository();
		$action = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $action ) {
			return $this->not_found( 'Action not found.' );
		}

		$post_id  = $action->id;
		$old_meta = RelationshipHelper::get_meta( $post_id );
		RelationshipSync::sync( $post_id, 'wpail_action', $old_meta, [] );
		wp_delete_post( $post_id, true );

		return $this->success( [ 'deleted' => true, 'id' => $post_id ] );
	}

	private function resolve( $action ): array {
		return $action->to_public_array(
			services:  RelationshipHelper::resolve_summaries( $action->related_service_ids ),
			locations: RelationshipHelper::resolve_summaries( $action->related_location_ids ),
		);
	}
}

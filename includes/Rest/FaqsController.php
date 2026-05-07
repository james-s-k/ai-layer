<?php
/**
 * REST endpoints: /faqs, /faqs/{id}
 *
 * GET    /wp-json/ai-layer/v1/faqs              — list all FAQs (filterable by service/location)
 * POST   /wp-json/ai-layer/v1/faqs              — create a FAQ (auth required)
 * GET    /wp-json/ai-layer/v1/faqs/{id}         — get single FAQ
 * PATCH  /wp-json/ai-layer/v1/faqs/{id}         — update a FAQ (auth required)
 * DELETE /wp-json/ai-layer/v1/faqs/{id}         — delete a FAQ (auth required)
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
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;
use WPAIL\Analytics\AuditLogger;

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
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/faqs/(?P<id>\d+)', [
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
		$repo        = new FaqRepository();
		$service_id  = (int) $request->get_param( 'service' );
		$location_id = (int) $request->get_param( 'location' );

		if ( $service_id > 0 ) {
			$faqs = $repo->find_by_service( $service_id );
		} elseif ( $location_id > 0 ) {
			$faqs = $repo->find_by_location( $location_id );
		} else {
			$faqs = $repo->get_all();
		}

		$data = array_map( fn( $f ) => $this->resolve( $f ), $faqs );

		return $this->success( $data, [ 'count' => count( $data ) ] );
	}

	public function get_item( $request ) {
		$repo = new FaqRepository();
		$faq  = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $faq ) {
			return $this->not_found( 'FAQ not found.' );
		}

		return $this->success( $this->resolve( $faq ) );
	}

	public function create_item( $request ) {
		$params   = (array) ( $request->get_json_params() ?? [] );
		$question = sanitize_text_field( $params['question'] ?? '' );

		if ( '' === $question ) {
			return $this->bad_request( 'question is required.' );
		}
		if ( empty( trim( $params['short_answer'] ?? '' ) ) ) {
			return $this->bad_request( 'short_answer is required.' );
		}

		$cap = $this->assert_can_create_post_type( 'wpail_faq' );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'wpail_faq',
			'post_title'  => $question,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = Sanitizer::sanitize_fields( $params, FieldDefinitions::faq() );
		RelationshipHelper::save_meta( $post_id, $meta );
		RelationshipSync::sync( $post_id, 'wpail_faq', [], $meta );

		$faq = ( new FaqRepository() )->find_by_id( $post_id );
		AuditLogger::log( AuditLogger::ACTION_CREATE, 'wpail_faq', $post_id );
		return $this->created( $faq ? $this->resolve( $faq ) : null );
	}

	public function update_item( $request ) {
		$repo = new FaqRepository();
		$faq  = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $faq ) {
			return $this->not_found( 'FAQ not found.' );
		}

		$post_id  = $faq->id;
		$cap      = $this->assert_can_edit_post( $post_id );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$params   = (array) ( $request->get_json_params() ?? [] );
		$old_meta = RelationshipHelper::get_meta( $post_id );

		// Allow updating the question (which also updates the post title).
		if ( isset( $params['question'] ) ) {
			$new_q = sanitize_text_field( $params['question'] );
			wp_update_post( [ 'ID' => $post_id, 'post_title' => $new_q ] );
		}

		$new_meta = array_merge( $old_meta, $this->sanitize_partial( $params, FieldDefinitions::faq() ) );
		RelationshipHelper::save_meta( $post_id, $new_meta );
		RelationshipSync::sync( $post_id, 'wpail_faq', $old_meta, $new_meta );

		$updated = $repo->find_by_id( $post_id );
		AuditLogger::log( AuditLogger::ACTION_UPDATE, 'wpail_faq', $post_id );
		return $this->success( $updated ? $this->resolve( $updated ) : null );
	}

	public function delete_item( $request ) {
		$repo = new FaqRepository();
		$faq  = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $faq ) {
			return $this->not_found( 'FAQ not found.' );
		}

		$post_id  = $faq->id;
		$cap      = $this->assert_can_delete_post( $post_id );
		if ( is_wp_error( $cap ) ) {
			return $cap;
		}

		$old_meta = RelationshipHelper::get_meta( $post_id );
		RelationshipSync::sync( $post_id, 'wpail_faq', $old_meta, [] );
		wp_delete_post( $post_id, true );
		AuditLogger::log( AuditLogger::ACTION_DELETE, 'wpail_faq', $post_id );

		return $this->success( [ 'deleted' => true, 'id' => $post_id ] );
	}

	private function resolve( $faq ): array {
		return $faq->to_public_array(
			services:  RelationshipHelper::resolve_summaries( $faq->related_service_ids ),
			locations: RelationshipHelper::resolve_summaries( $faq->related_location_ids ),
		);
	}
}

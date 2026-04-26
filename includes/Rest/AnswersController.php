<?php
/**
 * REST endpoints: /answers, /answers/{id}
 *
 * GET    /wp-json/ai-layer/v1/answers              — query engine (requires ?query=; Pro)
 *                                                     OR list authored answers (no ?query)
 * POST   /wp-json/ai-layer/v1/answers              — create an authored answer (auth required)
 * GET    /wp-json/ai-layer/v1/answers/{id}         — get a single authored answer
 * PATCH  /wp-json/ai-layer/v1/answers/{id}         — update an authored answer (auth required)
 * DELETE /wp-json/ai-layer/v1/answers/{id}         — delete an authored answer (auth required)
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\AnswerRepository;
use WPAIL\Support\AnswerEngine;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;
use WPAIL\Licensing\Features;
use WPAIL\Licensing\License;

class AnswersController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/answers', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				// Permission check is conditional: the ?query engine requires Pro;
				// listing authored answers has no Pro gate.
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'query'    => [
						'description'       => 'Natural language query. When present, runs the answer engine (Pro). When absent, lists all authored answers.',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'service'  => [
						'description'       => 'Service ID hint for the query engine.',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'location' => [
						'description'       => 'Location ID hint for the query engine.',
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

		register_rest_route( $this->namespace, '/answers/(?P<id>\d+)', [
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

	/**
	 * Permission check for GET /answers.
	 * Running the engine requires Pro; listing authored answers does not.
	 */
	public function get_items_permissions_check( $request ): bool|\WP_Error {
		$query = trim( (string) $request->get_param( 'query' ) );

		if ( '' !== $query && ! Features::answers_enabled() ) {
			return new \WP_Error(
				'upgrade_required',
				__( 'The /answers query engine requires AI Layer Pro.', 'ai-ready-layer' ),
				[
					'status'      => 402,
					'upgrade_url' => License::upgrade_url(),
				]
			);
		}

		return true;
	}

	/**
	 * GET /answers — branches on whether ?query is present.
	 */
	public function get_items( $request ) {
		$query = trim( (string) $request->get_param( 'query' ) );

		if ( '' !== $query ) {
			return $this->run_query( $query, $request );
		}

		return $this->list_authored();
	}

	private function list_authored(): \WP_REST_Response {
		$answers = ( new AnswerRepository() )->get_all();
		$data    = array_map( fn( $a ) => $this->resolve( $a ), $answers );
		return $this->success( $data, [ 'count' => count( $data ) ] );
	}

	private function run_query( string $query, $request ): \WP_REST_Response {
		$result = ( new AnswerEngine() )->query(
			$query,
			(int) $request->get_param( 'service' ),
			(int) $request->get_param( 'location' )
		);

		if ( null === $result ) {
			return $this->not_found( 'No matching answer found for this query.' );
		}

		return $this->success( $result );
	}

	public function get_item( $request ) {
		$answer = ( new AnswerRepository() )->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $answer ) {
			return $this->not_found( 'Answer not found.' );
		}

		return $this->success( $this->resolve( $answer ) );
	}

	public function create_item( $request ) {
		$params       = (array) ( $request->get_json_params() ?? [] );
		$short_answer = trim( $params['short_answer'] ?? '' );

		if ( '' === $short_answer ) {
			return $this->bad_request( 'short_answer is required.' );
		}

		// Derive an internal post title from the first query pattern or the answer itself.
		$patterns    = $this->normalise_patterns( $params['query_patterns'] ?? [] );
		$post_title  = sanitize_text_field( $params['title'] ?? ( $patterns[0] ?? substr( $short_answer, 0, 80 ) ) );

		$post_id = wp_insert_post( [
			'post_type'   => 'wpail_answer',
			'post_title'  => $post_title,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$params = $this->coerce_patterns( $params );
		$meta   = Sanitizer::sanitize_partial( $params, FieldDefinitions::answer() );
		RelationshipHelper::save_meta( $post_id, $meta );
		RelationshipSync::sync( $post_id, 'wpail_answer', [], $meta );

		$answer = ( new AnswerRepository() )->find_by_id( $post_id );
		return $this->created( $answer ? $this->resolve( $answer ) : null );
	}

	public function update_item( $request ) {
		$repo   = new AnswerRepository();
		$answer = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $answer ) {
			return $this->not_found( 'Answer not found.' );
		}

		$post_id  = $answer->post_id;
		$params   = (array) ( $request->get_json_params() ?? [] );
		$old_meta = RelationshipHelper::get_meta( $post_id );

		if ( isset( $params['title'] ) ) {
			wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $params['title'] ) ] );
		}

		$params   = $this->coerce_patterns( $params );
		$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $params, FieldDefinitions::answer() ) );
		RelationshipHelper::save_meta( $post_id, $new_meta );
		RelationshipSync::sync( $post_id, 'wpail_answer', $old_meta, $new_meta );

		$updated = $repo->find_by_id( $post_id );
		return $this->success( $updated ? $this->resolve( $updated ) : null );
	}

	public function delete_item( $request ) {
		$answer = ( new AnswerRepository() )->find_by_id( (int) $request->get_param( 'id' ) );

		if ( null === $answer ) {
			return $this->not_found( 'Answer not found.' );
		}

		$post_id  = $answer->post_id;
		$old_meta = RelationshipHelper::get_meta( $post_id );
		RelationshipSync::sync( $post_id, 'wpail_answer', $old_meta, [] );
		wp_delete_post( $post_id, true );

		return $this->success( [ 'deleted' => true, 'id' => $post_id ] );
	}

	// ------------------------------------------------------------------
	// Helpers.
	// ------------------------------------------------------------------

	private function resolve( $answer ): array {
		return [
			'id'             => $answer->post_id,
			'short_answer'   => $answer->short_answer,
			'long_answer'    => $answer->long_answer,
			'confidence'     => $answer->confidence,
			'query_patterns' => $answer->query_patterns,
			'services'       => RelationshipHelper::resolve_summaries( $answer->related_service_ids ),
			'locations'      => RelationshipHelper::resolve_summaries( $answer->related_location_ids ),
			'next_actions'   => RelationshipHelper::resolve_summaries( $answer->next_action_ids ),
			'source_faqs'    => RelationshipHelper::resolve_summaries( $answer->source_faq_ids ),
		];
	}

	/** Allow query_patterns to arrive as an array; FieldDefinitions stores it as textarea (newline string). */
	private function coerce_patterns( array $params ): array {
		if ( isset( $params['query_patterns'] ) && is_array( $params['query_patterns'] ) ) {
			$params['query_patterns'] = implode( "\n", $params['query_patterns'] );
		}
		return $params;
	}

	/** @return array<string> */
	private function normalise_patterns( mixed $patterns ): array {
		if ( is_array( $patterns ) ) {
			return array_values( array_filter( array_map( 'trim', $patterns ) ) );
		}
		if ( is_string( $patterns ) && '' !== trim( $patterns ) ) {
			return array_values( array_filter( array_map( 'trim', explode( "\n", $patterns ) ) ) );
		}
		return [];
	}
}

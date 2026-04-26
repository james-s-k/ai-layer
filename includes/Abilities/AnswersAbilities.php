<?php
/**
 * MCP abilities: Authored Answers (list, get, create, update, delete) + query engine.
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\AnswerRepository;
use WPAIL\Support\AnswerEngine;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;
use WPAIL\Licensing\Features;

class AnswersAbilities {

	public function register(): void {
		$this->register_list();
		$this->register_get();
		$this->register_create();
		$this->register_update();
		$this->register_delete();
		$this->register_query();
	}

	private function register_list(): void {
		wp_register_ability( 'ai-layer/list-answers', [
			'label'       => 'List Authored Answers',
			'description' => 'Returns all manually-authored AI Layer answers. These are guaranteed responses that take priority over the auto-assembly engine when a query matches their patterns.',
			'input_schema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'execute_callback'    => function ( array $input ): array {
				$answers = ( new AnswerRepository() )->get_all();
				return array_map( fn( $a ) => $this->resolve( $a ), $answers );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_get(): void {
		wp_register_ability( 'ai-layer/get-answer', [
			'label'       => 'Get Authored Answer',
			'description' => 'Returns a single authored answer by ID, including its query patterns, confidence, and related entities.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The answer post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$answer = ( new AnswerRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $answer ) {
					throw new \RuntimeException( 'Answer not found: ' . ( $input['id'] ?? '' ) );
				}
				return $this->resolve( $answer );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_create(): void {
		wp_register_ability( 'ai-layer/create-answer', [
			'label'       => 'Create Authored Answer',
			'description' => 'Creates a new manually-authored answer. When an incoming query matches any of the query_patterns, this answer is returned immediately at highest priority — bypassing the auto-assembly engine.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'short_answer' ],
				'properties' => $this->field_schema(),
			],
			'execute_callback'    => function ( array $input ): array {
				$short_answer = trim( $input['short_answer'] ?? '' );
				if ( '' === $short_answer ) {
					throw new \InvalidArgumentException( 'short_answer is required.' );
				}

				$patterns   = $this->normalise_patterns( $input['query_patterns'] ?? [] );
				$post_title = sanitize_text_field( $input['title'] ?? ( $patterns[0] ?? substr( $short_answer, 0, 80 ) ) );

				$post_id = wp_insert_post( [
					'post_type'   => 'wpail_answer',
					'post_title'  => $post_title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				], true );

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( $post_id->get_error_message() );
				}

				$input = $this->coerce_patterns( $input );
				$meta  = Sanitizer::sanitize_partial( $input, FieldDefinitions::answer() );
				RelationshipHelper::save_meta( $post_id, $meta );
				RelationshipSync::sync( $post_id, 'wpail_answer', [], $meta );

				$answer = ( new AnswerRepository() )->find_by_id( $post_id );
				if ( null === $answer ) {
					throw new \RuntimeException( 'Answer created but could not be retrieved.' );
				}
				return $this->resolve( $answer );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_update(): void {
		wp_register_ability( 'ai-layer/update-answer', [
			'label'       => 'Update Authored Answer',
			'description' => 'Partially updates an authored answer by ID. Only supplied fields are changed.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge(
					[ 'id' => [ 'type' => 'integer', 'description' => 'The answer post ID.' ] ],
					$this->field_schema()
				),
			],
			'execute_callback'    => function ( array $input ): array {
				$repo   = new AnswerRepository();
				$answer = $repo->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $answer ) {
					throw new \RuntimeException( 'Answer not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $answer->post_id;
				$old_meta = RelationshipHelper::get_meta( $post_id );

				if ( isset( $input['title'] ) ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $input['title'] ) ] );
				}

				$input    = $this->coerce_patterns( $input );
				$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $input, FieldDefinitions::answer() ) );
				RelationshipHelper::save_meta( $post_id, $new_meta );
				RelationshipSync::sync( $post_id, 'wpail_answer', $old_meta, $new_meta );

				$updated = $repo->find_by_id( $post_id );
				if ( null === $updated ) {
					throw new \RuntimeException( 'Answer updated but could not be retrieved.' );
				}
				return $this->resolve( $updated );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_delete(): void {
		wp_register_ability( 'ai-layer/delete-answer', [
			'label'       => 'Delete Authored Answer',
			'description' => 'Permanently deletes an authored answer by ID.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The answer post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$answer = ( new AnswerRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $answer ) {
					throw new \RuntimeException( 'Answer not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $answer->post_id;
				$old_meta = RelationshipHelper::get_meta( $post_id );
				RelationshipSync::sync( $post_id, 'wpail_answer', $old_meta, [] );
				wp_delete_post( $post_id, true );

				return [ 'deleted' => true, 'id' => $post_id ];
			},
			'permission_callback' => fn() => current_user_can( 'delete_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => true, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_query(): void {
		wp_register_ability( 'ai-layer/query-answers', [
			'label'       => 'Query Answer Engine',
			'description' => 'Runs a natural-language query through the AI Layer rules-based answer engine. Returns a structured answer assembled from your authored answers, FAQs, services, locations, proof, and actions — no external AI call required.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'query' ],
				'properties' => [
					'query'    => [ 'type' => 'string', 'description' => 'The natural-language question (e.g. "Do you offer SEO in London?").' ],
					'service'  => [ 'type' => 'integer', 'description' => 'Optional service ID hint to bias the result.' ],
					'location' => [ 'type' => 'integer', 'description' => 'Optional location ID hint to bias the result.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$query = sanitize_text_field( $input['query'] ?? '' );
				if ( '' === $query ) {
					throw new \InvalidArgumentException( 'query is required.' );
				}

				$result = ( new AnswerEngine() )->query(
					$query,
					(int) ( $input['service']  ?? 0 ),
					(int) ( $input['location'] ?? 0 )
				);

				if ( null === $result ) {
					throw new \RuntimeException( 'No matching answer found for: ' . $query );
				}

				return $result;
			},
			'permission_callback' => fn() => Features::answers_enabled(),
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
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

	private function coerce_patterns( array $input ): array {
		if ( isset( $input['query_patterns'] ) && is_array( $input['query_patterns'] ) ) {
			$input['query_patterns'] = implode( "\n", $input['query_patterns'] );
		}
		return $input;
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

	/** @return array<string,mixed> */
	private function field_schema(): array {
		return [
			'title'            => [ 'type' => 'string', 'description' => 'Internal label for this answer (defaults to the first query pattern or a truncation of short_answer).' ],
			'short_answer'     => [ 'type' => 'string', 'description' => '1–2 sentence answer returned as the primary response.' ],
			'long_answer'      => [ 'type' => 'string', 'description' => 'Optional extended answer.' ],
			'confidence'       => [ 'type' => 'string', 'enum' => [ 'high', 'medium', 'low' ] ],
			'query_patterns'   => [
				'description' => 'Trigger phrases — when an incoming query contains any of these, this answer is returned immediately. Pass as an array of strings or a single newline-separated string.',
				'oneOf'       => [
					[ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					[ 'type' => 'string' ],
				],
			],
			'related_services'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Service post IDs to include as context.' ],
			'related_locations' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Location post IDs to include as context.' ],
			'next_actions'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Action post IDs to attach as suggested next steps.' ],
			'source_faq_ids'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'FAQ post IDs this answer was derived from (for reference).' ],
		];
	}
}

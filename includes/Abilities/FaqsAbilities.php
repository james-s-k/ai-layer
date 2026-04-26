<?php
/**
 * MCP abilities: FAQs (list, get, create, update, delete).
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\FaqRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class FaqsAbilities {

	public function register(): void {
		$this->register_list();
		$this->register_get();
		$this->register_create();
		$this->register_update();
		$this->register_delete();
	}

	private function register_list(): void {
		wp_register_ability( 'ai-layer/list-faqs', [
			'label'       => 'List FAQs',
			'description' => 'Returns published AI Layer FAQs. Optionally filter by service ID or location ID.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'service'  => [ 'type' => 'integer', 'description' => 'Filter by service post ID.' ],
					'location' => [ 'type' => 'integer', 'description' => 'Filter by location post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$repo        = new FaqRepository();
				$service_id  = (int) ( $input['service']  ?? 0 );
				$location_id = (int) ( $input['location'] ?? 0 );

				if ( $service_id > 0 ) {
					$faqs = $repo->find_by_service( $service_id );
				} elseif ( $location_id > 0 ) {
					$faqs = $repo->find_by_location( $location_id );
				} else {
					$faqs = $repo->get_all();
				}

				return array_map( fn( $f ) => $this->resolve( $f ), $faqs );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_get(): void {
		wp_register_ability( 'ai-layer/get-faq', [
			'label'       => 'Get FAQ',
			'description' => 'Returns full detail for a single FAQ by its post ID.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The FAQ post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$faq = ( new FaqRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $faq ) {
					throw new \RuntimeException( 'FAQ not found: ' . ( $input['id'] ?? '' ) );
				}
				return $this->resolve( $faq );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_create(): void {
		wp_register_ability( 'ai-layer/create-faq', [
			'label'       => 'Create FAQ',
			'description' => 'Creates a new AI Layer FAQ. Both question and short_answer are required.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'question', 'short_answer' ],
				'properties' => $this->field_schema(),
			],
			'execute_callback'    => function ( array $input ): array {
				$question     = sanitize_text_field( $input['question'] ?? '' );
				$short_answer = trim( $input['short_answer'] ?? '' );

				if ( '' === $question ) {
					throw new \InvalidArgumentException( 'question is required.' );
				}
				if ( '' === $short_answer ) {
					throw new \InvalidArgumentException( 'short_answer is required.' );
				}

				$post_id = wp_insert_post( [
					'post_type'   => 'wpail_faq',
					'post_title'  => $question,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				], true );

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( $post_id->get_error_message() );
				}

				$meta = Sanitizer::sanitize_partial( $input, FieldDefinitions::faq() );
				RelationshipHelper::save_meta( $post_id, $meta );
				RelationshipSync::sync( $post_id, 'wpail_faq', [], $meta );

				$faq = ( new FaqRepository() )->find_by_id( $post_id );
				if ( null === $faq ) {
					throw new \RuntimeException( 'FAQ created but could not be retrieved.' );
				}
				return $this->resolve( $faq );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_update(): void {
		wp_register_ability( 'ai-layer/update-faq', [
			'label'       => 'Update FAQ',
			'description' => 'Partially updates an existing FAQ by ID. Updating "question" also updates the post title.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge(
					[ 'id' => [ 'type' => 'integer', 'description' => 'The FAQ post ID.' ] ],
					$this->field_schema()
				),
			],
			'execute_callback'    => function ( array $input ): array {
				$repo = new FaqRepository();
				$faq  = $repo->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $faq ) {
					throw new \RuntimeException( 'FAQ not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $faq->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );

				if ( isset( $input['question'] ) ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $input['question'] ) ] );
				}

				$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $input, FieldDefinitions::faq() ) );
				RelationshipHelper::save_meta( $post_id, $new_meta );
				RelationshipSync::sync( $post_id, 'wpail_faq', $old_meta, $new_meta );

				$updated = $repo->find_by_id( $post_id );
				if ( null === $updated ) {
					throw new \RuntimeException( 'FAQ updated but could not be retrieved.' );
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
		wp_register_ability( 'ai-layer/delete-faq', [
			'label'       => 'Delete FAQ',
			'description' => 'Permanently deletes a FAQ by ID and removes all bidirectional relationship references.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The FAQ post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$faq = ( new FaqRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $faq ) {
					throw new \RuntimeException( 'FAQ not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $faq->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );
				RelationshipSync::sync( $post_id, 'wpail_faq', $old_meta, [] );
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

	private function resolve( $faq ): array {
		return $faq->to_public_array(
			services:  RelationshipHelper::resolve_summaries( $faq->related_service_ids ),
			locations: RelationshipHelper::resolve_summaries( $faq->related_location_ids ),
		);
	}

	/** @return array<string,mixed> */
	private function field_schema(): array {
		return [
			'question'          => [ 'type' => 'string', 'description' => 'The question as a user would naturally ask it.' ],
			'short_answer'      => [ 'type' => 'string', 'description' => '1–2 sentence answer returned directly in /answers responses.' ],
			'long_answer'       => [ 'type' => 'string', 'description' => 'Optional extended answer with supporting detail.' ],
			'status'            => [ 'type' => 'string', 'enum' => [ 'published', 'draft', 'private' ] ],
			'is_public'         => [ 'type' => 'boolean' ],
			'priority'          => [ 'type' => 'integer', 'description' => 'Higher number = ranked higher in answer matching.' ],
			'intent_tags'       => [ 'type' => 'string', 'description' => 'Comma-separated intent tags (e.g. pricing, timescales).' ],
			'related_services'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Service post IDs.' ],
			'related_locations' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Location post IDs.' ],
		];
	}
}

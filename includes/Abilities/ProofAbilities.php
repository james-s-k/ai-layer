<?php
/**
 * MCP abilities: Proof & Trust (list, get, create, update, delete).
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\ProofRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class ProofAbilities {

	public function register(): void {
		$this->register_list();
		$this->register_get();
		$this->register_create();
		$this->register_update();
		$this->register_delete();
	}

	private function register_list(): void {
		wp_register_ability( 'ai-layer/list-proof', [
			'label'       => 'List Proof & Trust',
			'description' => 'Returns published AI Layer proof and trust signals. Optionally filter by service ID.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'service' => [ 'type' => 'integer', 'description' => 'Filter by service post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$repo       = new ProofRepository();
				$service_id = (int) ( $input['service'] ?? 0 );

				$proof = $service_id > 0
					? $repo->find_by_service( $service_id )
					: $repo->get_all();

				return array_map( fn( $p ) => $this->resolve( $p ), $proof );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_get(): void {
		wp_register_ability( 'ai-layer/get-proof-item', [
			'label'       => 'Get Proof Item',
			'description' => 'Returns full detail for a single proof or trust signal by its post ID.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The proof post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$proof = ( new ProofRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $proof ) {
					throw new \RuntimeException( 'Proof item not found: ' . ( $input['id'] ?? '' ) );
				}
				return $this->resolve( $proof );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_create(): void {
		wp_register_ability( 'ai-layer/create-proof-item', [
			'label'       => 'Create Proof Item',
			'description' => 'Creates a new AI Layer proof or trust signal (testimonial, accreditation, statistic, award, case study, or media mention).',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'title' ],
				'properties' => $this->field_schema(),
			],
			'execute_callback'    => function ( array $input ): array {
				$title = sanitize_text_field( $input['title'] ?? $input['headline'] ?? '' );
				if ( '' === $title ) {
					throw new \InvalidArgumentException( 'title (or headline) is required.' );
				}

				$post_id = wp_insert_post( [
					'post_type'   => 'wpail_proof',
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				], true );

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( $post_id->get_error_message() );
				}

				$meta = Sanitizer::sanitize_partial( $input, FieldDefinitions::proof() );
				RelationshipHelper::save_meta( $post_id, $meta );
				RelationshipSync::sync( $post_id, 'wpail_proof', [], $meta );

				$proof = ( new ProofRepository() )->find_by_id( $post_id );
				if ( null === $proof ) {
					throw new \RuntimeException( 'Proof item created but could not be retrieved.' );
				}
				return $this->resolve( $proof );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_update(): void {
		wp_register_ability( 'ai-layer/update-proof-item', [
			'label'       => 'Update Proof Item',
			'description' => 'Partially updates an existing proof item by ID. Only supplied fields are changed.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge(
					[ 'id' => [ 'type' => 'integer', 'description' => 'The proof post ID.' ] ],
					$this->field_schema()
				),
			],
			'execute_callback'    => function ( array $input ): array {
				$repo  = new ProofRepository();
				$proof = $repo->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $proof ) {
					throw new \RuntimeException( 'Proof item not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $proof->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );

				if ( isset( $input['title'] ) ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $input['title'] ) ] );
				}

				$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $input, FieldDefinitions::proof() ) );
				RelationshipHelper::save_meta( $post_id, $new_meta );
				RelationshipSync::sync( $post_id, 'wpail_proof', $old_meta, $new_meta );

				$updated = $repo->find_by_id( $post_id );
				if ( null === $updated ) {
					throw new \RuntimeException( 'Proof item updated but could not be retrieved.' );
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
		wp_register_ability( 'ai-layer/delete-proof-item', [
			'label'       => 'Delete Proof Item',
			'description' => 'Permanently deletes a proof item by ID and removes all bidirectional relationship references.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The proof post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$proof = ( new ProofRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $proof ) {
					throw new \RuntimeException( 'Proof item not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $proof->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );
				RelationshipSync::sync( $post_id, 'wpail_proof', $old_meta, [] );
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

	private function resolve( $proof ): array {
		return $proof->to_public_array(
			services:  RelationshipHelper::resolve_summaries( $proof->related_service_ids ),
			locations: RelationshipHelper::resolve_summaries( $proof->related_location_ids ),
		);
	}

	/** @return array<string,mixed> */
	private function field_schema(): array {
		return [
			'title'             => [ 'type' => 'string', 'description' => 'Display title (also accepted as "headline").' ],
			'proof_type'        => [ 'type' => 'string', 'enum' => [ 'testimonial', 'accreditation', 'statistic', 'award', 'case_study', 'media_mention' ] ],
			'headline'          => [ 'type' => 'string', 'description' => 'Key claim or pull-quote.' ],
			'content'           => [ 'type' => 'string', 'description' => 'Full text of the testimonial, case study, or proof item.' ],
			'source_name'       => [ 'type' => 'string', 'description' => 'Person, organisation, or publication this comes from.' ],
			'source_context'    => [ 'type' => 'string', 'description' => 'Role, company, or where the review appeared.' ],
			'rating'            => [ 'type' => 'integer', 'description' => 'Star rating 1–5 (testimonials only).' ],
			'is_public'         => [ 'type' => 'boolean' ],
			'related_services'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Service post IDs.' ],
			'related_locations' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Location post IDs.' ],
		];
	}
}

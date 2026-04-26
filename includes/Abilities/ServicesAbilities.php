<?php
/**
 * MCP abilities: Services (list, get, create, update, delete).
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\ServiceRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class ServicesAbilities {

	public function register(): void {
		$this->register_list();
		$this->register_get();
		$this->register_create();
		$this->register_update();
		$this->register_delete();
	}

	private function register_list(): void {
		wp_register_ability( 'ai-layer/list-services', [
			'label'       => 'List Services',
			'description' => 'Returns all published AI Layer services as summaries (id, slug, name).',
			'input_schema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'execute_callback'    => function ( array $input ): array {
				$services = ( new ServiceRepository() )->get_all();
				return array_map( fn( $s ) => $s->to_summary_array(), $services );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_get(): void {
		wp_register_ability( 'ai-layer/get-service', [
			'label'       => 'Get Service',
			'description' => 'Returns full detail for a single service by slug, including related FAQs, proof, actions, and locations.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => [
					'slug' => [ 'type' => 'string', 'description' => 'The service URL slug.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$service = ( new ServiceRepository() )->find_by_slug( sanitize_title( $input['slug'] ?? '' ) );
				if ( null === $service ) {
					throw new \RuntimeException( 'Service not found: ' . ( $input['slug'] ?? '' ) );
				}
				return $this->resolve( $service );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_create(): void {
		wp_register_ability( 'ai-layer/create-service', [
			'label'       => 'Create Service',
			'description' => 'Creates a new AI Layer service. Bidirectional relationships are synced automatically.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'title' ],
				'properties' => $this->field_schema( required: true ),
			],
			'execute_callback'    => function ( array $input ): array {
				$title = sanitize_text_field( $input['title'] ?? '' );
				if ( '' === $title ) {
					throw new \InvalidArgumentException( 'title is required.' );
				}

				$post_id = wp_insert_post( [
					'post_type'   => 'wpail_service',
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				], true );

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( $post_id->get_error_message() );
				}

				$meta = Sanitizer::sanitize_partial( $input, FieldDefinitions::service() );
				RelationshipHelper::save_meta( $post_id, $meta );
				RelationshipSync::sync( $post_id, 'wpail_service', [], $meta );

				$service = ( new ServiceRepository() )->find_by_id( $post_id );
				if ( null === $service ) {
					throw new \RuntimeException( 'Service created but could not be retrieved.' );
				}
				return $this->resolve( $service );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_update(): void {
		wp_register_ability( 'ai-layer/update-service', [
			'label'       => 'Update Service',
			'description' => 'Partially updates an existing service by slug. Only supplied fields are changed. Relationship changes are synced bidirectionally.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => array_merge(
					[ 'slug' => [ 'type' => 'string', 'description' => 'The service URL slug.' ] ],
					$this->field_schema( required: false )
				),
			],
			'execute_callback'    => function ( array $input ): array {
				$repo    = new ServiceRepository();
				$service = $repo->find_by_slug( sanitize_title( $input['slug'] ?? '' ) );
				if ( null === $service ) {
					throw new \RuntimeException( 'Service not found: ' . ( $input['slug'] ?? '' ) );
				}

				$post_id  = $service->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );

				if ( isset( $input['title'] ) ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $input['title'] ) ] );
				}

				$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $input, FieldDefinitions::service() ) );
				RelationshipHelper::save_meta( $post_id, $new_meta );
				RelationshipSync::sync( $post_id, 'wpail_service', $old_meta, $new_meta );

				$updated = $repo->find_by_id( $post_id );
				if ( null === $updated ) {
					throw new \RuntimeException( 'Service updated but could not be retrieved.' );
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
		wp_register_ability( 'ai-layer/delete-service', [
			'label'       => 'Delete Service',
			'description' => 'Permanently deletes a service by slug and cleans up all bidirectional relationship references.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => [
					'slug' => [ 'type' => 'string', 'description' => 'The service URL slug.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$service = ( new ServiceRepository() )->find_by_slug( sanitize_title( $input['slug'] ?? '' ) );
				if ( null === $service ) {
					throw new \RuntimeException( 'Service not found: ' . ( $input['slug'] ?? '' ) );
				}

				$post_id  = $service->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );
				RelationshipSync::sync( $post_id, 'wpail_service', $old_meta, [] );
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

	private function resolve( $service ): array {
		return $service->to_public_array(
			faqs:      RelationshipHelper::resolve_summaries( $service->related_faq_ids ),
			proof:     RelationshipHelper::resolve_summaries( $service->related_proof_ids ),
			actions:   RelationshipHelper::resolve_summaries( $service->related_action_ids ),
			locations: RelationshipHelper::resolve_summaries( $service->related_location_ids ),
		);
	}

	/** @return array<string,mixed> */
	private function field_schema( bool $required ): array {
		return [
			'title'             => [ 'type' => 'string', 'description' => 'Service name.' ],
			'category'          => [ 'type' => 'string', 'description' => 'Broad category (e.g. Marketing, Legal).' ],
			'status'            => [ 'type' => 'string', 'enum' => [ 'active', 'inactive', 'coming_soon' ] ],
			'short_summary'     => [ 'type' => 'string', 'description' => '1–3 sentence description.' ],
			'long_summary'      => [ 'type' => 'string' ],
			'customer_types'    => [ 'type' => 'string', 'description' => 'Comma-separated customer types.' ],
			'service_modes'     => [ 'type' => 'array', 'items' => [ 'type' => 'string', 'enum' => [ 'in_person', 'remote', 'mobile' ] ] ],
			'keywords'          => [ 'type' => 'string', 'description' => 'Comma-separated keywords for answer engine matching.' ],
			'pricing_type'      => [ 'type' => 'string', 'enum' => [ 'fixed', 'hourly', 'monthly_retainer', 'quote', 'free' ] ],
			'from_price'        => [ 'type' => 'number', 'description' => 'Starting price.' ],
			'currency'          => [ 'type' => 'string', 'description' => '3-letter currency code, e.g. GBP.' ],
			'price_notes'       => [ 'type' => 'string' ],
			'available'         => [ 'type' => 'boolean' ],
			'benefits'          => [ 'type' => 'string', 'description' => 'One benefit per line.' ],
			'linked_page_url'   => [ 'type' => 'string', 'description' => 'URL of the service page on your website.' ],
			'related_faqs'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'FAQ post IDs.' ],
			'related_proof'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Proof post IDs.' ],
			'related_actions'   => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Action post IDs.' ],
			'related_locations' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Location post IDs.' ],
		];
	}
}

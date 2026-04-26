<?php
/**
 * MCP abilities: Locations (list, get, create, update, delete).
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\LocationRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class LocationsAbilities {

	public function register(): void {
		$this->register_list();
		$this->register_get();
		$this->register_create();
		$this->register_update();
		$this->register_delete();
	}

	private function register_list(): void {
		wp_register_ability( 'ai-layer/list-locations', [
			'label'       => 'List Locations',
			'description' => 'Returns all published AI Layer locations as summaries (id, slug, name).',
			'input_schema' => [ 'type' => 'object', 'properties' => new \stdClass() ],
			'execute_callback'    => function ( array $input ): array {
				$locations = ( new LocationRepository() )->get_all();
				return array_map( fn( $l ) => $l->to_summary_array(), $locations );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_get(): void {
		wp_register_ability( 'ai-layer/get-location', [
			'label'       => 'Get Location',
			'description' => 'Returns full detail for a single location by slug, including related services and local proof.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => [
					'slug' => [ 'type' => 'string', 'description' => 'The location URL slug.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$location = ( new LocationRepository() )->find_by_slug( sanitize_title( $input['slug'] ?? '' ) );
				if ( null === $location ) {
					throw new \RuntimeException( 'Location not found: ' . ( $input['slug'] ?? '' ) );
				}
				return $this->resolve( $location );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_create(): void {
		wp_register_ability( 'ai-layer/create-location', [
			'label'       => 'Create Location',
			'description' => 'Creates a new AI Layer location. Bidirectional relationships are synced automatically.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'title' ],
				'properties' => $this->field_schema(),
			],
			'execute_callback'    => function ( array $input ): array {
				$title = sanitize_text_field( $input['title'] ?? '' );
				if ( '' === $title ) {
					throw new \InvalidArgumentException( 'title is required.' );
				}

				$post_id = wp_insert_post( [
					'post_type'   => 'wpail_location',
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				], true );

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( $post_id->get_error_message() );
				}

				$meta = Sanitizer::sanitize_partial( $input, FieldDefinitions::location() );
				RelationshipHelper::save_meta( $post_id, $meta );
				RelationshipSync::sync( $post_id, 'wpail_location', [], $meta );

				$location = ( new LocationRepository() )->find_by_id( $post_id );
				if ( null === $location ) {
					throw new \RuntimeException( 'Location created but could not be retrieved.' );
				}
				return $this->resolve( $location );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_update(): void {
		wp_register_ability( 'ai-layer/update-location', [
			'label'       => 'Update Location',
			'description' => 'Partially updates an existing location by slug. Only supplied fields are changed.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => array_merge(
					[ 'slug' => [ 'type' => 'string', 'description' => 'The location URL slug.' ] ],
					$this->field_schema()
				),
			],
			'execute_callback'    => function ( array $input ): array {
				$repo     = new LocationRepository();
				$location = $repo->find_by_slug( sanitize_title( $input['slug'] ?? '' ) );
				if ( null === $location ) {
					throw new \RuntimeException( 'Location not found: ' . ( $input['slug'] ?? '' ) );
				}

				$post_id  = $location->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );

				if ( isset( $input['title'] ) ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $input['title'] ) ] );
				}

				$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $input, FieldDefinitions::location() ) );
				RelationshipHelper::save_meta( $post_id, $new_meta );
				RelationshipSync::sync( $post_id, 'wpail_location', $old_meta, $new_meta );

				$updated = $repo->find_by_id( $post_id );
				if ( null === $updated ) {
					throw new \RuntimeException( 'Location updated but could not be retrieved.' );
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
		wp_register_ability( 'ai-layer/delete-location', [
			'label'       => 'Delete Location',
			'description' => 'Permanently deletes a location by slug and removes all bidirectional relationship references.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'slug' ],
				'properties' => [
					'slug' => [ 'type' => 'string', 'description' => 'The location URL slug.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$location = ( new LocationRepository() )->find_by_slug( sanitize_title( $input['slug'] ?? '' ) );
				if ( null === $location ) {
					throw new \RuntimeException( 'Location not found: ' . ( $input['slug'] ?? '' ) );
				}

				$post_id  = $location->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );
				RelationshipSync::sync( $post_id, 'wpail_location', $old_meta, [] );
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

	private function resolve( $location ): array {
		return $location->to_public_array(
			services: RelationshipHelper::resolve_summaries( $location->related_service_ids ),
			proof:    RelationshipHelper::resolve_summaries( $location->local_proof_ids ),
		);
	}

	/** @return array<string,mixed> */
	private function field_schema(): array {
		return [
			'title'             => [ 'type' => 'string', 'description' => 'Location name (e.g. London).' ],
			'location_type'     => [ 'type' => 'string', 'enum' => [ 'town', 'city', 'county', 'region', 'postcode_area', 'country' ] ],
			'region'            => [ 'type' => 'string', 'description' => 'Broader region (e.g. Greater London).' ],
			'country'           => [ 'type' => 'string', 'description' => '2-letter country code, e.g. GB.' ],
			'postcode_prefixes' => [ 'type' => 'string', 'description' => 'Comma-separated prefixes, e.g. SW1, EC1.' ],
			'is_primary'        => [ 'type' => 'boolean', 'description' => 'Mark as the primary trading location.' ],
			'service_radius_km' => [ 'type' => 'number', 'description' => 'Service radius in kilometres.' ],
			'summary'           => [ 'type' => 'string', 'description' => 'Brief description of this location.' ],
			'linked_page_url'   => [ 'type' => 'string', 'description' => 'URL of the location page on your website.' ],
			'related_services'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Service post IDs available at this location.' ],
			'local_proof'       => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Proof post IDs specific to this location.' ],
		];
	}
}

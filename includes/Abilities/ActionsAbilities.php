<?php
/**
 * MCP abilities: Actions / CTAs (list, get, create, update, delete).
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\ActionRepository;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;
use WPAIL\Support\Sanitizer;

class ActionsAbilities {

	public function register(): void {
		$this->register_list();
		$this->register_get();
		$this->register_create();
		$this->register_update();
		$this->register_delete();
	}

	private function register_list(): void {
		wp_register_ability( 'ai-layer/list-actions', [
			'label'       => 'List Actions',
			'description' => 'Returns published AI Layer calls-to-action. Optionally filter by service ID.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'service' => [ 'type' => 'integer', 'description' => 'Filter by service post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$repo       = new ActionRepository();
				$service_id = (int) ( $input['service'] ?? 0 );

				$actions = $service_id > 0
					? $repo->find_by_service( $service_id )
					: $repo->get_all();

				return array_map( fn( $a ) => $this->resolve( $a ), $actions );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_get(): void {
		wp_register_ability( 'ai-layer/get-action', [
			'label'       => 'Get Action',
			'description' => 'Returns full detail for a single call-to-action by its post ID.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The action post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$action = ( new ActionRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $action ) {
					throw new \RuntimeException( 'Action not found: ' . ( $input['id'] ?? '' ) );
				}
				return $this->resolve( $action );
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_create(): void {
		wp_register_ability( 'ai-layer/create-action', [
			'label'       => 'Create Action',
			'description' => 'Creates a new AI Layer call-to-action. The title field sets the internal name; label is the user-facing CTA text.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'title' ],
				'properties' => $this->field_schema(),
			],
			'execute_callback'    => function ( array $input ): array {
				$title = sanitize_text_field( $input['title'] ?? $input['label'] ?? '' );
				if ( '' === $title ) {
					throw new \InvalidArgumentException( 'title (or label) is required.' );
				}

				$post_id = wp_insert_post( [
					'post_type'   => 'wpail_action',
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				], true );

				if ( is_wp_error( $post_id ) ) {
					throw new \RuntimeException( $post_id->get_error_message() );
				}

				$meta = Sanitizer::sanitize_partial( $input, FieldDefinitions::action() );
				RelationshipHelper::save_meta( $post_id, $meta );
				RelationshipSync::sync( $post_id, 'wpail_action', [], $meta );

				$action = ( new ActionRepository() )->find_by_id( $post_id );
				if ( null === $action ) {
					throw new \RuntimeException( 'Action created but could not be retrieved.' );
				}
				return $this->resolve( $action );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => false ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}

	private function register_update(): void {
		wp_register_ability( 'ai-layer/update-action', [
			'label'       => 'Update Action',
			'description' => 'Partially updates an existing call-to-action by ID. Only supplied fields are changed.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge(
					[ 'id' => [ 'type' => 'integer', 'description' => 'The action post ID.' ] ],
					$this->field_schema()
				),
			],
			'execute_callback'    => function ( array $input ): array {
				$repo   = new ActionRepository();
				$action = $repo->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $action ) {
					throw new \RuntimeException( 'Action not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $action->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );

				if ( isset( $input['title'] ) ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => sanitize_text_field( $input['title'] ) ] );
				}

				$new_meta = array_merge( $old_meta, Sanitizer::sanitize_partial( $input, FieldDefinitions::action() ) );
				RelationshipHelper::save_meta( $post_id, $new_meta );
				RelationshipSync::sync( $post_id, 'wpail_action', $old_meta, $new_meta );

				$updated = $repo->find_by_id( $post_id );
				if ( null === $updated ) {
					throw new \RuntimeException( 'Action updated but could not be retrieved.' );
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
		wp_register_ability( 'ai-layer/delete-action', [
			'label'       => 'Delete Action',
			'description' => 'Permanently deletes a call-to-action by ID and removes all bidirectional relationship references.',
			'input_schema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'The action post ID.' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$action = ( new ActionRepository() )->find_by_id( (int) ( $input['id'] ?? 0 ) );
				if ( null === $action ) {
					throw new \RuntimeException( 'Action not found: ' . ( $input['id'] ?? '' ) );
				}

				$post_id  = $action->id;
				$old_meta = RelationshipHelper::get_meta( $post_id );
				RelationshipSync::sync( $post_id, 'wpail_action', $old_meta, [] );
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

	private function resolve( $action ): array {
		return $action->to_public_array(
			services:  RelationshipHelper::resolve_summaries( $action->related_service_ids ),
			locations: RelationshipHelper::resolve_summaries( $action->related_location_ids ),
		);
	}

	/** @return array<string,mixed> */
	private function field_schema(): array {
		return [
			'title'             => [ 'type' => 'string', 'description' => 'Internal name for the action.' ],
			'action_type'       => [ 'type' => 'string', 'enum' => [ 'call', 'email', 'book', 'quote', 'visit', 'download', 'chat' ] ],
			'label'             => [ 'type' => 'string', 'description' => 'User-facing CTA text, e.g. "Book a free call".' ],
			'description'       => [ 'type' => 'string', 'description' => 'Optional extra detail about what happens when taken.' ],
			'phone'             => [ 'type' => 'string', 'description' => 'Required when method is phone.' ],
			'url'               => [ 'type' => 'string', 'description' => 'Required when method is link or form.' ],
			'method'            => [ 'type' => 'string', 'enum' => [ 'link', 'phone', 'form', 'email' ] ],
			'is_public'         => [ 'type' => 'boolean' ],
			'related_services'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Service post IDs. Leave empty for a global action.' ],
			'related_locations' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Location post IDs.' ],
		];
	}
}

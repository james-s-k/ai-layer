<?php
/**
 * Bidirectional relationship sync.
 *
 * When a post_ids field is saved, this class writes the inverse reference
 * into each target post's meta blob — and removes it when the link is dropped.
 *
 * Only relationship pairs where both sides have a defined field are synced.
 * Relationships that have no inverse field (e.g. answer.related_services)
 * are left as one-directional.
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

class RelationshipSync {

	private static bool $syncing = false;

	/**
	 * Inverse relationship map.
	 *
	 * Format: source_post_type => [ field_key => [ target_post_type, inverse_field ] ]
	 *
	 * Each pair is declared from both sides so sync works regardless of which
	 * post was saved.
	 *
	 * @return array<string, array<string, array{string, string}>>
	 */
	private static function map(): array {
		return [
			'wpail_service' => [
				'related_faqs'      => [ 'wpail_faq',      'related_services' ],
				'related_proof'     => [ 'wpail_proof',    'related_services' ],
				'related_actions'   => [ 'wpail_action',   'related_services' ],
				'related_locations' => [ 'wpail_location', 'related_services' ],
			],
			'wpail_location' => [
				'related_services' => [ 'wpail_service', 'related_locations' ],
				'local_proof'      => [ 'wpail_proof',   'related_locations' ],
			],
			'wpail_faq' => [
				'related_services' => [ 'wpail_service', 'related_faqs' ],
			],
			'wpail_proof' => [
				'related_services'  => [ 'wpail_service',  'related_proof' ],
				'related_locations' => [ 'wpail_location', 'local_proof'   ],
			],
			'wpail_action' => [
				'related_services' => [ 'wpail_service', 'related_actions' ],
			],
		];
	}

	/**
	 * Diff old vs new data and sync inverse references on affected target posts.
	 *
	 * @param int                  $post_id   The post that was just saved.
	 * @param string               $post_type Its post type.
	 * @param array<string, mixed> $old_data  Meta blob before the save.
	 * @param array<string, mixed> $new_data  Meta blob after the save.
	 */
	public static function sync( int $post_id, string $post_type, array $old_data, array $new_data ): void {
		if ( self::$syncing ) {
			return;
		}

		$map = self::map();
		if ( ! isset( $map[ $post_type ] ) ) {
			return;
		}

		self::$syncing = true;
		try {
			foreach ( $map[ $post_type ] as $field_key => [ $target_post_type, $inverse_field ] ) {
				$old_ids = array_map( 'intval', (array) ( $old_data[ $field_key ] ?? [] ) );
				$new_ids = array_map( 'intval', (array) ( $new_data[ $field_key ] ?? [] ) );

				foreach ( array_diff( $new_ids, $old_ids ) as $target_id ) {
					self::add_inverse( $target_id, $target_post_type, $inverse_field, $post_id );
				}
				foreach ( array_diff( $old_ids, $new_ids ) as $target_id ) {
					self::remove_inverse( $target_id, $target_post_type, $inverse_field, $post_id );
				}
			}
		} finally {
			self::$syncing = false;
		}
	}

	private static function add_inverse( int $target_id, string $target_post_type, string $inverse_field, int $source_id ): void {
		$post = get_post( $target_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $target_post_type ) {
			return;
		}

		$meta        = RelationshipHelper::get_meta( $target_id );
		$current_ids = array_map( 'intval', (array) ( $meta[ $inverse_field ] ?? [] ) );

		if ( in_array( $source_id, $current_ids, true ) ) {
			return;
		}

		$meta[ $inverse_field ] = array_values( array_unique( array_merge( $current_ids, [ $source_id ] ) ) );
		RelationshipHelper::save_meta( $target_id, $meta );
	}

	private static function remove_inverse( int $target_id, string $target_post_type, string $inverse_field, int $source_id ): void {
		$post = get_post( $target_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $target_post_type ) {
			return;
		}

		$meta        = RelationshipHelper::get_meta( $target_id );
		$current_ids = array_map( 'intval', (array) ( $meta[ $inverse_field ] ?? [] ) );
		$filtered    = array_values( array_filter( $current_ids, fn( int $id ) => $id !== $source_id ) );

		if ( count( $filtered ) === count( $current_ids ) ) {
			return;
		}

		$meta[ $inverse_field ] = $filtered;
		RelationshipHelper::save_meta( $target_id, $meta );
	}
}

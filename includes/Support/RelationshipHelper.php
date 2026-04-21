<?php
/**
 * Relationship resolution helpers.
 *
 * Relationships are stored as arrays of post IDs in the _wpail_data JSON blob.
 * This helper resolves those IDs into lightweight summary objects suitable
 * for embedding in REST responses.
 *
 * Deliberately kept simple in v1. Future: replace with a dedicated
 * relationship cache or graph layer if query volume demands it.
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

class RelationshipHelper {

	/**
	 * Resolve a list of post IDs to summary arrays.
	 * Returns only published posts to avoid leaking drafts.
	 *
	 * @param array<int> $ids
	 * @return array<array{id: int, title: string, slug: string}>
	 */
	public static function resolve_summaries( array $ids ): array {
		if ( empty( $ids ) ) {
			return [];
		}

		$results = [];
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
				$results[] = [
					'id'    => $post->ID,
					'title' => $post->post_title,
					'slug'  => $post->post_name,
				];
			}
		}
		return $results;
	}

	/**
	 * Resolve IDs to full data arrays via a repository callback.
	 *
	 * @param array<int> $ids
	 * @param callable   $loader  fn(int $id): ?array — returns canonical array or null.
	 * @return array<array<string, mixed>>
	 */
	public static function resolve_full( array $ids, callable $loader ): array {
		$results = [];
		foreach ( $ids as $id ) {
			$data = $loader( (int) $id );
			if ( null !== $data ) {
				$results[] = $data;
			}
		}
		return $results;
	}

	/**
	 * Extract the wpail_data blob from a post.
	 *
	 * @param int $post_id
	 * @return array<string, mixed>
	 */
	public static function get_meta( int $post_id ): array {
		$raw = get_post_meta( $post_id, WPAIL_META_KEY, true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Save the wpail_data blob for a post.
	 *
	 * @param int                  $post_id
	 * @param array<string, mixed> $data
	 */
	public static function save_meta( int $post_id, array $data ): void {
		// Store schema version in the blob so future migrations can act on it.
		$data['_schema_version'] = WPAIL_VERSION;
		update_post_meta( $post_id, WPAIL_META_KEY, wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) );
	}
}

<?php
/**
 * Location data access.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\LocationModel;
use WPAIL\Transformers\LocationTransformer;
use WPAIL\PostTypes\LocationPostType;

class LocationRepository {

	/** @return array<LocationModel> */
	public function get_all(): array {
		$posts = get_posts( [
			'post_type'      => LocationPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		return array_map(
			fn( \WP_Post $p ) => LocationTransformer::from_post( $p ),
			$posts
		);
	}

	public function find_by_slug( string $slug ): ?LocationModel {
		$posts = get_posts( [
			'post_type'      => LocationPostType::POST_TYPE,
			'post_status'    => 'publish',
			'name'           => sanitize_title( $slug ),
			'posts_per_page' => 1,
		] );

		return empty( $posts ) ? null : LocationTransformer::from_post( $posts[0] );
	}

	public function find_by_id( int $id ): ?LocationModel {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || LocationPostType::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		return LocationTransformer::from_post( $post );
	}

	/**
	 * Find locations matching a query term (name, region, postcode prefix).
	 *
	 * @param string $term Normalised query term.
	 * @return array<LocationModel>
	 */
	public function find_by_term( string $term ): array {
		$term    = strtolower( trim( $term ) );
		$results = [];

		foreach ( $this->get_all() as $location ) {
			if (
				str_contains( strtolower( $location->name ), $term ) ||
				str_contains( strtolower( $location->region ), $term ) ||
				in_array( strtolower( $term ), array_map( 'strtolower', $location->postcode_prefixes ), true )
			) {
				$results[] = $location;
			}
		}

		// Primary locations ranked first.
		usort( $results, fn( $a, $b ) => (int) $b->is_primary <=> (int) $a->is_primary );

		return $results;
	}
}

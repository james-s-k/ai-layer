<?php
/**
 * Service data access.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\ServiceModel;
use WPAIL\Transformers\ServiceTransformer;
use WPAIL\PostTypes\ServicePostType;

class ServiceRepository {

	/**
	 * Return all published services.
	 *
	 * @return array<ServiceModel>
	 */
	public function get_all(): array {
		$posts = get_posts( [
			'post_type'      => ServicePostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		return array_map(
			fn( \WP_Post $p ) => ServiceTransformer::from_post( $p ),
			$posts
		);
	}

	/**
	 * Find a service by its slug.
	 */
	public function find_by_slug( string $slug ): ?ServiceModel {
		$posts = get_posts( [
			'post_type'      => ServicePostType::POST_TYPE,
			'post_status'    => 'publish',
			'name'           => sanitize_title( $slug ),
			'posts_per_page' => 1,
		] );

		if ( empty( $posts ) ) {
			return null;
		}

		return ServiceTransformer::from_post( $posts[0] );
	}

	/**
	 * Find a service by post ID.
	 */
	public function find_by_id( int $id ): ?ServiceModel {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || ServicePostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		if ( 'publish' !== $post->post_status ) {
			return null;
		}

		return ServiceTransformer::from_post( $post );
	}

	/**
	 * Find services that cover a given location ID.
	 *
	 * @return array<ServiceModel>
	 */
	public function find_by_location( int $location_id ): array {
		return array_filter(
			$this->get_all(),
			fn( ServiceModel $s ) => in_array( $location_id, $s->related_location_ids, true )
		);
	}

	/**
	 * Find services matching keyword/synonym terms.
	 *
	 * @param array<string> $terms Normalised query terms.
	 * @return array<ServiceModel>
	 */
	public function find_by_terms( array $terms ): array {
		if ( empty( $terms ) ) {
			return [];
		}

		$results = [];

		foreach ( $this->get_all() as $service ) {
			$score = $this->score_service( $service, $terms );
			if ( $score > 0 ) {
				$results[] = [ 'model' => $service, 'score' => $score ];
			}
		}

		usort( $results, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		return array_map( fn( $r ) => $r['model'], $results );
	}

	/**
	 * Score a service against a set of query terms.
	 * Simple keyword matching — expandable later.
	 */
	private function score_service( ServiceModel $service, array $terms ): int {
		$score        = 0;
		$search_terms = array_map( 'strtolower', $terms );

		$title_words   = array_map( 'strtolower', explode( ' ', $service->name ) );
		$all_keywords  = array_map( 'strtolower', $service->keywords );
		$all_synonyms  = array_map( 'strtolower', $service->synonyms );

		foreach ( $search_terms as $term ) {
			// Name match = highest value.
			foreach ( $title_words as $word ) {
				if ( str_contains( $word, $term ) || str_contains( $term, $word ) ) {
					$score += 10;
				}
			}
			// Keyword match.
			foreach ( $all_keywords as $kw ) {
				if ( str_contains( $kw, $term ) || str_contains( $term, $kw ) ) {
					$score += 5;
				}
			}
			// Synonym match.
			foreach ( $all_synonyms as $syn ) {
				if ( str_contains( $syn, $term ) || str_contains( $term, $syn ) ) {
					$score += 3;
				}
			}
		}

		return $score;
	}
}

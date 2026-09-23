<?php
/**
 * Answer data access.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\AnswerModel;
use WPAIL\Support\PublicEntityVisibility;
use WPAIL\Transformers\AnswerTransformer;
use WPAIL\PostTypes\AnswerPostType;

class AnswerRepository {

	/**
	 * Find a manually-authored answer matching a query string.
	 * Checks query_patterns stored in the post meta.
	 *
	 * @return AnswerModel|null  Returns the first match, or null.
	 */
	public function find_by_query( string $query ): ?AnswerModel {
		$query   = strtolower( trim( $query ) );
		$all     = $this->get_all();

		foreach ( $all as $answer ) {
			foreach ( $answer->query_patterns as $pattern ) {
				$pattern = strtolower( trim( $pattern ) );
				if ( '' === $pattern ) {
					continue;
				}
				if ( self::pattern_matches( $query, $pattern ) ) {
					return $answer;
				}
			}
		}

		return null;
	}

	public function find_by_id( int $id ): ?AnswerModel {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || AnswerPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return AnswerTransformer::from_post( $post );
	}

	public function find_public_by_id( int $id ): ?AnswerModel {
		if ( ! PublicEntityVisibility::post_is_published( $id ) ) {
			return null;
		}

		return $this->find_by_id( $id );
	}

	/** @return array<AnswerModel> */
	public function get_all(): array {
		$posts = get_posts( [
			'post_type'      => AnswerPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		] );

		return array_map(
			fn( \WP_Post $p ) => AnswerTransformer::from_post( $p ),
			$posts
		);
	}

	private static function pattern_matches( string $query, string $pattern ): bool {
		if ( str_contains( $pattern, '*' ) ) {
			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/iu';
			return (bool) preg_match( $regex, $query );
		}

		return str_contains( $query, $pattern ) || str_contains( $pattern, $query );
	}
}

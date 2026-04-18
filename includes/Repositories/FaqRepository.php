<?php
/**
 * FAQ data access.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\FaqModel;
use WPAIL\Transformers\FaqTransformer;
use WPAIL\PostTypes\FaqPostType;

class FaqRepository {

	/** @return array<FaqModel> */
	public function get_all( bool $public_only = true ): array {
		$posts = get_posts( [
			'post_type'      => FaqPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		] );

		$models = array_map(
			fn( \WP_Post $p ) => FaqTransformer::from_post( $p ),
			$posts
		);

		if ( $public_only ) {
			$models = array_values(
				array_filter( $models, fn( FaqModel $f ) => $f->is_public && 'published' === $f->status )
			);
		}

		return $models;
	}

	public function find_by_id( int $id ): ?FaqModel {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || FaqPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return FaqTransformer::from_post( $post );
	}

	/**
	 * Find FAQs related to a given service.
	 *
	 * @return array<FaqModel>
	 */
	public function find_by_service( int $service_id ): array {
		return array_values( array_filter(
			$this->get_all(),
			fn( FaqModel $f ) => in_array( $service_id, $f->related_service_ids, true )
		) );
	}

	/**
	 * Find FAQs related to a given location.
	 *
	 * @return array<FaqModel>
	 */
	public function find_by_location( int $location_id ): array {
		return array_values( array_filter(
			$this->get_all(),
			fn( FaqModel $f ) => in_array( $location_id, $f->related_location_ids, true )
		) );
	}

	/**
	 * Find FAQs matching query terms.
	 * Checks question text, short answer, and intent tags.
	 *
	 * @param array<string> $terms
	 * @return array<FaqModel> Sorted by relevance score, then priority.
	 */
	public function find_by_terms( array $terms ): array {
		if ( empty( $terms ) ) {
			return [];
		}

		$results = [];

		foreach ( $this->get_all() as $faq ) {
			$score = $this->score_faq( $faq, $terms );
			if ( $score > 0 ) {
				$results[] = [ 'model' => $faq, 'score' => $score + $faq->priority ];
			}
		}

		usort( $results, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		return array_map( fn( $r ) => $r['model'], $results );
	}

	private function score_faq( FaqModel $faq, array $terms ): int {
		$score = 0;
		$terms = array_map( 'strtolower', $terms );

		$question_lower = strtolower( $faq->question );
		$answer_lower   = strtolower( $faq->short_answer );
		$tags_lower     = array_map( 'strtolower', $faq->intent_tags );

		foreach ( $terms as $term ) {
			if ( str_contains( $question_lower, $term ) ) {
				$score += 10;
			}
			if ( str_contains( $answer_lower, $term ) ) {
				$score += 3;
			}
			foreach ( $tags_lower as $tag ) {
				if ( str_contains( $tag, $term ) ) {
					$score += 5;
				}
			}
		}

		return $score;
	}
}

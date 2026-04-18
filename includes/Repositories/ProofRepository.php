<?php
/**
 * Proof / Trust Signal data access.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\ProofModel;
use WPAIL\Transformers\ProofTransformer;
use WPAIL\PostTypes\ProofPostType;

class ProofRepository {

	/** @return array<ProofModel> */
	public function get_all( bool $public_only = true ): array {
		$posts = get_posts( [
			'post_type'      => ProofPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$models = array_map(
			fn( \WP_Post $p ) => ProofTransformer::from_post( $p ),
			$posts
		);

		if ( $public_only ) {
			$models = array_values( array_filter( $models, fn( ProofModel $p ) => $p->is_public ) );
		}

		return $models;
	}

	public function find_by_id( int $id ): ?ProofModel {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || ProofPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return ProofTransformer::from_post( $post );
	}

	/** @return array<ProofModel> */
	public function find_by_service( int $service_id ): array {
		return array_values( array_filter(
			$this->get_all(),
			fn( ProofModel $p ) => in_array( $service_id, $p->related_service_ids, true )
		) );
	}
}

<?php
/**
 * Action data access.
 *
 * @package WPAIL\Repositories
 */

declare(strict_types=1);

namespace WPAIL\Repositories;

use WPAIL\Models\ActionModel;
use WPAIL\Transformers\ActionTransformer;
use WPAIL\PostTypes\ActionPostType;

class ActionRepository {

	/** @return array<ActionModel> */
	public function get_all( bool $public_only = true ): array {
		$posts = get_posts( [
			'post_type'      => ActionPostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$models = array_map(
			fn( \WP_Post $p ) => ActionTransformer::from_post( $p ),
			$posts
		);

		if ( $public_only ) {
			$models = array_values( array_filter( $models, fn( ActionModel $a ) => $a->is_public ) );
		}

		return $models;
	}

	public function find_by_id( int $id ): ?ActionModel {
		$post = get_post( $id );

		if ( ! $post instanceof \WP_Post || ActionPostType::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return ActionTransformer::from_post( $post );
	}

	/** @return array<ActionModel> */
	public function find_by_service( int $service_id ): array {
		return array_values( array_filter(
			$this->get_all(),
			fn( ActionModel $a ) => in_array( $service_id, $a->related_service_ids, true )
		) );
	}

	/**
	 * Find the most appropriate global actions (not service/location specific).
	 *
	 * @return array<ActionModel>
	 */
	public function get_global(): array {
		return array_values( array_filter(
			$this->get_all(),
			fn( ActionModel $a ) => empty( $a->related_service_ids ) && empty( $a->related_location_ids )
		) );
	}
}

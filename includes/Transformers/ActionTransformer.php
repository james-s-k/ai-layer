<?php
/**
 * Transforms WP_Post → ActionModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\ActionModel;
use WPAIL\Support\RelationshipHelper;

class ActionTransformer {

	public static function from_post( \WP_Post $post ): ActionModel {
		$data = RelationshipHelper::get_meta( $post->ID );

		$s  = fn( string $k, string $d = '' ) => (string) ( $data[ $k ] ?? $d );
		$ai = fn( string $k ) => is_array( $data[ $k ] ?? null )
		        ? array_values( array_filter( array_map( 'absint', $data[ $k ] ) ) )
		        : [];

		return new ActionModel(
			id:                   $post->ID,
			slug:                 $post->post_name,
			action_type:          $s( 'action_type' ),
			label:                $s( 'label' ) ?: $post->post_title,
			description:          $s( 'description' ),
			phone:                $s( 'phone' ),
			url:                  $s( 'url' ),
			method:               $s( 'method' ),
			availability_rule:    $s( 'availability_rule' ),
			related_service_ids:  $ai( 'related_services' ),
			related_location_ids: $ai( 'related_locations' ),
			is_public:            isset( $data['is_public'] ) ? (bool) $data['is_public'] : true,
		);
	}
}

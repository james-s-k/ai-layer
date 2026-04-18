<?php
/**
 * Transforms WP_Post → FaqModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\FaqModel;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\Sanitizer;

class FaqTransformer {

	public static function from_post( \WP_Post $post ): FaqModel {
		$data = RelationshipHelper::get_meta( $post->ID );

		$s  = fn( string $k, string $d = '' ) => (string) ( $data[ $k ] ?? $d );
		$ai = fn( string $k ) => is_array( $data[ $k ] ?? null )
		        ? array_values( array_filter( array_map( 'absint', $data[ $k ] ) ) )
		        : [];

		return new FaqModel(
			id:                   $post->ID,
			slug:                 $post->post_name,
			question:             $s( 'question' ) ?: $post->post_title,
			short_answer:         $s( 'short_answer' ),
			long_answer:          $s( 'long_answer' ),
			status:               $s( 'status', 'published' ),
			related_service_ids:  $ai( 'related_services' ),
			related_location_ids: $ai( 'related_locations' ),
			intent_tags:          Sanitizer::csv_to_array( $s( 'intent_tags' ) ),
			priority:             (int) ( $data['priority'] ?? 0 ),
			is_public:            isset( $data['is_public'] ) ? (bool) $data['is_public'] : true,
		);
	}
}

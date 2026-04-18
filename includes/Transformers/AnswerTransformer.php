<?php
/**
 * Transforms WP_Post → AnswerModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\AnswerModel;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\Sanitizer;

class AnswerTransformer {

	public static function from_post( \WP_Post $post ): AnswerModel {
		$data = RelationshipHelper::get_meta( $post->ID );

		$s  = fn( string $k, string $d = '' ) => (string) ( $data[ $k ] ?? $d );
		$ai = fn( string $k ) => is_array( $data[ $k ] ?? null )
		        ? array_values( array_filter( array_map( 'absint', $data[ $k ] ) ) )
		        : [];

		return new AnswerModel(
			short_answer:          $s( 'short_answer' ),
			long_answer:           $s( 'long_answer' ),
			confidence:            $s( 'confidence', 'high' ),
			source:                'manual',
			query_patterns:        Sanitizer::lines_to_array( $s( 'query_patterns' ) ),
			related_service_ids:   $ai( 'related_services' ),
			related_location_ids:  $ai( 'related_locations' ),
			next_action_ids:       $ai( 'next_actions' ),
			source_faq_ids:        $ai( 'source_faq_ids' ),
			post_id:               $post->ID,
		);
	}
}

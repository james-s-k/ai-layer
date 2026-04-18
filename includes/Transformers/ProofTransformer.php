<?php
/**
 * Transforms WP_Post → ProofModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\ProofModel;
use WPAIL\Support\RelationshipHelper;

class ProofTransformer {

	public static function from_post( \WP_Post $post ): ProofModel {
		$data = RelationshipHelper::get_meta( $post->ID );

		$s  = fn( string $k, string $d = '' ) => (string) ( $data[ $k ] ?? $d );
		$f  = fn( string $k ) => isset( $data[ $k ] ) && is_numeric( $data[ $k ] ) ? (float) $data[ $k ] : null;
		$ai = fn( string $k ) => is_array( $data[ $k ] ?? null )
		        ? array_values( array_filter( array_map( 'absint', $data[ $k ] ) ) )
		        : [];

		return new ProofModel(
			id:                   $post->ID,
			slug:                 $post->post_name,
			proof_type:           $s( 'proof_type' ),
			headline:             $s( 'headline' ) ?: $post->post_title,
			content:              $s( 'content' ),
			source_name:          $s( 'source_name' ),
			source_context:       $s( 'source_context' ),
			rating:               $f( 'rating' ),
			related_service_ids:  $ai( 'related_services' ),
			related_location_ids: $ai( 'related_locations' ),
			is_public:            isset( $data['is_public'] ) ? (bool) $data['is_public'] : true,
		);
	}
}

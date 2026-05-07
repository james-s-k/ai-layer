<?php
/**
 * Transforms WP_Post → LocationModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\LocationModel;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\Sanitizer;

class LocationTransformer {

	public static function from_post( \WP_Post $post ): LocationModel {
		$data = RelationshipHelper::get_meta( $post->ID );

		$s  = fn( string $k, string $d = '' ) => (string) ( $data[ $k ] ?? $d );
		$b  = fn( string $k ) => isset( $data[ $k ] ) ? (bool) $data[ $k ] : false;
		$f  = fn( string $k ) => isset( $data[ $k ] ) && is_numeric( $data[ $k ] ) ? (float) $data[ $k ] : null;
		$ai = fn( string $k ) => is_array( $data[ $k ] ?? null )
		        ? array_values( array_filter( array_map( 'absint', $data[ $k ] ) ) )
		        : [];

		return new LocationModel(
			id:                  $post->ID,
			slug:                $post->post_name,
			name:                $post->post_title,
			location_type:       $s( 'location_type' ),
			region:              $s( 'region' ),
			country:             $s( 'country', 'GB' ),
			postcode_prefixes:   Sanitizer::csv_to_array( $s( 'postcode_prefixes' ) ),
			is_primary:          $b( 'is_primary' ),
			service_radius_km:   $f( 'service_radius_km' ),
			summary:             $s( 'summary' ),
			related_service_ids: $ai( 'related_services' ),
			local_proof_ids:     $ai( 'local_proof' ),
			linked_page_url:     $s( 'linked_page_url' ),
			modified_at:         gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $post->post_modified_gmt ) ),
		);
	}
}

<?php
/**
 * Transforms WP_Post → ServiceModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\ServiceModel;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\Sanitizer;

class ServiceTransformer {

	public static function from_post( \WP_Post $post ): ServiceModel {
		$data = RelationshipHelper::get_meta( $post->ID );

		$s  = fn( string $k, string $d = '' ) => (string) ( $data[ $k ] ?? $d );
		$b  = fn( string $k, bool $d = false ) => isset( $data[ $k ] ) ? (bool) $data[ $k ] : $d;
		$f  = fn( string $k ) => isset( $data[ $k ] ) && is_numeric( $data[ $k ] ) ? (float) $data[ $k ] : null;
		$a  = fn( string $k ) => is_array( $data[ $k ] ?? null ) ? array_values( $data[ $k ] ) : [];
		$ai = fn( string $k ) => is_array( $data[ $k ] ?? null )
		        ? array_values( array_filter( array_map( 'absint', $data[ $k ] ) ) )
		        : [];

		// Scalar CSV fields stored as strings are expanded to arrays.
		$csv = fn( string $k ) => Sanitizer::csv_to_array( $s( $k ) );

		return new ServiceModel(
			id:                   $post->ID,
			slug:                 $post->post_name,
			name:                 $post->post_title,
			category:             $s( 'category' ),
			status:               $s( 'status', 'active' ),
			short_summary:        $s( 'short_summary' ),
			long_summary:         $s( 'long_summary' ),
			customer_types:       $csv( 'customer_types' ),
			service_modes:        $a( 'service_modes' ),
			keywords:             $csv( 'keywords' ),
			synonyms:             $csv( 'synonyms' ),
			common_problems:      $s( 'common_problems' ),
			pricing_type:         $s( 'pricing_type' ),
			from_price:           $f( 'from_price' ),
			currency:             $s( 'currency', 'GBP' ),
			price_notes:          $s( 'price_notes' ),
			available:            $b( 'available', true ),
			benefits:             Sanitizer::lines_to_array( $s( 'benefits' ) ),
			related_faq_ids:      $ai( 'related_faqs' ),
			related_proof_ids:    $ai( 'related_proof' ),
			related_action_ids:   $ai( 'related_actions' ),
			related_location_ids: $ai( 'related_locations' ),
			linked_page_url:      $s( 'linked_page_url' ),
			schema_type:          $s( 'schema_type' ),
			modified_at:          gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $post->post_modified_gmt ) ),
		);
	}
}

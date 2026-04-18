<?php
/**
 * Transforms raw options data into a BusinessModel.
 *
 * @package WPAIL\Transformers
 */

declare(strict_types=1);

namespace WPAIL\Transformers;

use WPAIL\Models\BusinessModel;
use WPAIL\Support\Sanitizer;

class BusinessTransformer {

	/**
	 * Build a BusinessModel from a stored options array.
	 *
	 * @param array<string, mixed> $data
	 */
	public static function from_options( array $data ): BusinessModel {
		$s = fn( string $k ) => (string) ( $data[ $k ] ?? '' );
		$i = fn( string $k ) => isset( $data[ $k ] ) && is_numeric( $data[ $k ] ) ? (int) $data[ $k ] : null;
		$a = fn( string $k ) => is_array( $data[ $k ] ?? null ) ? $data[ $k ] : [];

		return new BusinessModel(
			name:             $s( 'name' ),
			legal_name:       $s( 'legal_name' ),
			business_type:    $s( 'business_type' ),
			subtype:          $s( 'subtype' ),
			short_summary:    $s( 'short_summary' ),
			long_summary:     $s( 'long_summary' ),
			brand_tone:       $s( 'brand_tone' ),
			founded_year:     $i( 'founded_year' ),
			phone:            $s( 'phone' ),
			email:            $s( 'email' ),
			website:          $s( 'website' ),
			address_line1:    $s( 'address_line1' ),
			address_line2:    $s( 'address_line2' ),
			city:             $s( 'city' ),
			county:           $s( 'county' ),
			postcode:         $s( 'postcode' ),
			country:          $s( 'country' ) ?: 'GB',
			opening_hours:    $s( 'opening_hours' ),
			service_modes:    $a( 'service_modes' ),
			trust_summary:    $s( 'trust_summary' ),
			social_facebook:  $s( 'social_facebook' ),
			social_twitter:   $s( 'social_twitter' ),
			social_linkedin:  $s( 'social_linkedin' ),
			social_instagram: $s( 'social_instagram' ),
			social_youtube:   $s( 'social_youtube' ),
		);
	}
}

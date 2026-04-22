<?php
/**
 * Extracts business profile data from Yoast SEO (and Yoast Local SEO if active).
 *
 * @package WPAIL\Setup\Sources
 */

declare(strict_types=1);

namespace WPAIL\Setup\Sources;

class YoastSource {

	public function is_available(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	public function label(): string {
		return 'Yoast SEO';
	}

	public function description(): string {
		if ( defined( 'WPSEO_LOCAL_VERSION' ) ) {
			return 'Business name, social profiles, and local business details from Yoast SEO + Local SEO.';
		}
		return 'Business name and social profiles from Yoast SEO › Search Appearance.';
	}

	/**
	 * @return array<string, array{value: string, source: string}>
	 */
	public function extract_profile(): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		$suggestions = [];

		// Company name from Knowledge Graph.
		$titles       = get_option( 'wpseo_titles', [] );
		$company_name = $titles['company_name'] ?? '';
		if ( $company_name ) {
			$suggestions['name'] = [
				'value'  => $company_name,
				'source' => 'Yoast SEO › Search Appearance › Company Name',
			];
		}

		// Social profiles.
		$social     = get_option( 'wpseo_social', [] );
		$social_map = [
			'facebook_site' => [ 'field' => 'social_facebook',  'source' => 'Yoast SEO › Social › Facebook' ],
			'twitter_site'  => [ 'field' => 'social_twitter',   'source' => 'Yoast SEO › Social › Twitter / X' ],
			'linkedin_url'  => [ 'field' => 'social_linkedin',  'source' => 'Yoast SEO › Social › LinkedIn' ],
			'instagram_url' => [ 'field' => 'social_instagram', 'source' => 'Yoast SEO › Social › Instagram' ],
			'youtube_url'   => [ 'field' => 'social_youtube',   'source' => 'Yoast SEO › Social › YouTube' ],
		];

		foreach ( $social_map as $yoast_key => $map ) {
			$value = $social[ $yoast_key ] ?? '';
			if ( $value ) {
				$suggestions[ $map['field'] ] = [
					'value'  => $value,
					'source' => $map['source'],
				];
			}
		}

		// Yoast Local SEO plugin (separate purchase).
		if ( defined( 'WPSEO_LOCAL_VERSION' ) ) {
			$local     = get_option( 'wpseo_local', [] );
			$local_map = [
				'location_phone'     => [ 'field' => 'phone',         'source' => 'Yoast Local SEO › Phone' ],
				'location_email'     => [ 'field' => 'email',         'source' => 'Yoast Local SEO › Email' ],
				'location_address'   => [ 'field' => 'address_line1', 'source' => 'Yoast Local SEO › Address' ],
				'location_address_2' => [ 'field' => 'address_line2', 'source' => 'Yoast Local SEO › Address 2' ],
				'location_city'      => [ 'field' => 'city',          'source' => 'Yoast Local SEO › City' ],
				'location_state'     => [ 'field' => 'county',        'source' => 'Yoast Local SEO › State / County' ],
				'location_zipcode'   => [ 'field' => 'postcode',      'source' => 'Yoast Local SEO › Postcode' ],
				'location_country'   => [ 'field' => 'country',       'source' => 'Yoast Local SEO › Country' ],
			];

			foreach ( $local_map as $yoast_key => $map ) {
				$value = $local[ $yoast_key ] ?? '';
				if ( $value ) {
					$suggestions[ $map['field'] ] = [
						'value'  => $value,
						'source' => $map['source'],
					];
				}
			}
		}

		return $suggestions;
	}
}

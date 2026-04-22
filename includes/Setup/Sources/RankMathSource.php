<?php
/**
 * Extracts business profile data from Rank Math SEO.
 *
 * @package WPAIL\Setup\Sources
 */

declare(strict_types=1);

namespace WPAIL\Setup\Sources;

class RankMathSource {

	public function is_available(): bool {
		return defined( 'RANK_MATH_VERSION' );
	}

	public function label(): string {
		return 'Rank Math SEO';
	}

	public function description(): string {
		return 'Business name and social profiles from Rank Math › Titles & Meta › Local SEO.';
	}

	/**
	 * @return array<string, array{value: string, source: string}>
	 */
	public function extract_profile(): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		$suggestions = [];
		$general     = get_option( 'rank_math_general', [] );

		if ( ! is_array( $general ) ) {
			return [];
		}

		$name = $general['knowledgegraph_name'] ?? '';
		if ( $name ) {
			$suggestions['name'] = [
				'value'  => $name,
				'source' => 'Rank Math › Titles & Meta › Knowledge Graph Name',
			];
		}

		$social_map = [
			'social_url_facebook'  => [ 'field' => 'social_facebook',  'source' => 'Rank Math › Social › Facebook' ],
			'social_url_twitter'   => [ 'field' => 'social_twitter',   'source' => 'Rank Math › Social › Twitter / X' ],
			'social_url_linkedin'  => [ 'field' => 'social_linkedin',  'source' => 'Rank Math › Social › LinkedIn' ],
			'social_url_instagram' => [ 'field' => 'social_instagram', 'source' => 'Rank Math › Social › Instagram' ],
			'social_url_youtube'   => [ 'field' => 'social_youtube',   'source' => 'Rank Math › Social › YouTube' ],
		];

		foreach ( $social_map as $rm_key => $map ) {
			$value = $general[ $rm_key ] ?? '';
			if ( $value ) {
				$suggestions[ $map['field'] ] = [
					'value'  => $value,
					'source' => $map['source'],
				];
			}
		}

		return $suggestions;
	}
}

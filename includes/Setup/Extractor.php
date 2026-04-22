<?php
/**
 * Aggregates suggestions from all available data sources.
 *
 * Sources run in ascending priority order — later sources overwrite earlier
 * ones for the same field, so more authoritative sources (Yoast with Local
 * SEO data) win over less specific ones (WordPress core settings).
 *
 * @package WPAIL\Setup
 */

declare(strict_types=1);

namespace WPAIL\Setup;

use WPAIL\Setup\Sources\WordPressSource;
use WPAIL\Setup\Sources\YoastSource;
use WPAIL\Setup\Sources\RankMathSource;
use WPAIL\Setup\Sources\WooCommerceSource;

class Extractor {

	/** @var array<string, WordPressSource|YoastSource|RankMathSource|WooCommerceSource> */
	private array $sources;

	public function __construct() {
		$this->sources = [
			'wordpress'   => new WordPressSource(),
			'yoast'       => new YoastSource(),
			'rank_math'   => new RankMathSource(),
			'woocommerce' => new WooCommerceSource(),
		];
	}

	/**
	 * All sources, keyed by slug.
	 *
	 * @return array<string, WordPressSource|YoastSource|RankMathSource|WooCommerceSource>
	 */
	public function get_sources(): array {
		return $this->sources;
	}

	/**
	 * Merged profile suggestions. Priority order (ascending):
	 *   WordPress → Rank Math → Yoast
	 *
	 * @return array<string, array{value: string, source: string}>
	 */
	public function get_profile_suggestions(): array {
		$suggestions = [];

		foreach ( [ 'wordpress', 'rank_math', 'yoast' ] as $key ) {
			$source = $this->sources[ $key ];
			if ( $source->is_available() ) {
				foreach ( $source->extract_profile() as $field => $suggestion ) {
					$suggestions[ $field ] = $suggestion;
				}
			}
		}

		return $suggestions;
	}

	public function has_woocommerce_products(): bool {
		return $this->sources['woocommerce']->is_available();
	}
}

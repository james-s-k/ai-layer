<?php
/**
 * Rank Math SEO integration.
 *
 * @package WPAIL\Integrations
 */

declare(strict_types=1);

namespace WPAIL\Integrations;

class RankMathIntegration {

	public function register(): void {
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

		add_filter( 'wpail_schema_conflict_detected', '__return_true' );
	}
}

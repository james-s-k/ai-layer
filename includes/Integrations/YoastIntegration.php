<?php
/**
 * Yoast SEO integration.
 *
 * When Yoast is active, our schema output is disabled by default to prevent
 * duplicate Organisation schema. We also register a filter so users can
 * explicitly opt back in if needed.
 *
 * @package WPAIL\Integrations
 */

declare(strict_types=1);

namespace WPAIL\Integrations;

use WPAIL\Admin\SettingsPage;

class YoastIntegration {

	public function register(): void {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		// When Yoast is active and schema is enabled, suppress Yoast's
		// organization output on our profile page to avoid duplication.
		// Users can disable AI Layer schema in Settings to revert.
		add_filter( 'wpail_schema_conflict_detected', '__return_true' );
	}
}

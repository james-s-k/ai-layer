<?php
/**
 * Feature flags — the single point of contact for all Pro checks.
 *
 * The rest of the codebase calls Features::, never License:: or wpail_fs()
 * directly. This keeps licensing logic in one place and makes future changes
 * (new features, different plan tiers) a one-file edit.
 *
 * @package WPAIL\Licensing
 */

declare(strict_types=1);

namespace WPAIL\Licensing;

class Features {

	// ------------------------------------------------------------------
	// Current Pro features
	// ------------------------------------------------------------------

	/**
	 * Answers CPT, /answers endpoint, and the rules-based answer engine.
	 */
	public static function answers_enabled(): bool {
		return License::is_pro();
	}

	// ------------------------------------------------------------------
	// Reserved for future Pro features — add methods here as you build them.
	// Each method stays false in free and delegates to License::is_pro()
	// (or a more granular plan check) in pro.
	// ------------------------------------------------------------------

	/**
	 * Advanced schema controls (custom types per service, per-page overrides).
	 */
	public static function advanced_schema_enabled(): bool {
		return License::is_pro();
	}

	/**
	 * Answer analytics and query logging.
	 */
	public static function analytics_enabled(): bool {
		return License::is_pro();
	}

	/**
	 * OpenAPI / JSON schema export of the REST layer.
	 */
	public static function openapi_export_enabled(): bool {
		return License::is_pro();
	}

	/**
	 * AI-assisted answer drafting.
	 */
	public static function ai_drafting_enabled(): bool {
		return License::is_pro();
	}

	/**
	 * Agency / multi-site features.
	 */
	public static function agency_enabled(): bool {
		return License::is_pro();
	}
}

<?php
/**
 * Cache-Control helpers for discovery and manifest endpoints.
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

use WPAIL\Admin\SettingsPage;

class EndpointCache {

	private const DEFAULT_MAX_AGE = 3600;

	public static function max_age(): int {
		$ttl = (int) SettingsPage::get( SettingsPage::SETTING_ENDPOINT_CACHE_TTL, 0 );
		return $ttl > 0 ? $ttl : self::DEFAULT_MAX_AGE;
	}

	public static function header_value(): string {
		return 'public, max-age=' . self::max_age();
	}
}

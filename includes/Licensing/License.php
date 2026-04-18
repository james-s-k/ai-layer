<?php
/**
 * Thin wrapper around the Freemius SDK.
 *
 * Nothing outside this class and FreemiusBootstrap.php should call wpail_fs()
 * directly. Everything else in the codebase calls Features::.
 *
 * @package WPAIL\Licensing
 */

declare(strict_types=1);

namespace WPAIL\Licensing;

class License {

	/**
	 * Whether the current site has an active Pro licence.
	 * Returns false safely when the Freemius SDK is not yet present.
	 */
	public static function is_pro(): bool {
		if ( ! function_exists( 'wpail_fs' ) ) {
			return false;
		}

		$fs = wpail_fs();

		return $fs instanceof \Freemius && $fs->can_use_premium_code();
	}

	/**
	 * URL for the Freemius checkout / upgrade page.
	 * Falls back to a placeholder when the SDK is unavailable.
	 */
	public static function upgrade_url(): string {
		if ( function_exists( 'wpail_fs' ) ) {
			$fs = wpail_fs();
			if ( $fs instanceof \Freemius ) {
				return $fs->get_upgrade_url();
			}
		}

		return admin_url( 'admin.php?page=wpail_dashboard-pricing' );
	}

	/**
	 * URL for the Freemius pricing / plan comparison page.
	 */
	public static function pricing_url(): string {
		if ( function_exists( 'wpail_fs' ) ) {
			$fs = wpail_fs();
			if ( $fs instanceof \Freemius ) {
				return $fs->get_pricing_url();
			}
		}

		return admin_url( 'admin.php?page=wpail_dashboard-pricing' );
	}
}

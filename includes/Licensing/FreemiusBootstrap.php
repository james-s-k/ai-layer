<?php
/**
 * Freemius SDK bootstrap.
 *
 * Defines the global wpail_fs() accessor and initialises the Freemius SDK.
 * This file intentionally declares no namespace so wpail_fs() lives in the
 * global namespace, which is the Freemius-recommended pattern.
 *
 * Must be required directly from ai-layer.php — not autoloaded — before
 * plugins_loaded fires.
 *
 * IMPORTANT: Replace PLACEHOLDER_PRODUCT_ID and PLACEHOLDER_PUBLIC_KEY with
 * your real credentials from freemius.com before going live.
 */

if ( defined( 'ABSPATH' ) && ! function_exists( 'wpail_fs' ) ) {

	/**
	 * Returns the initialised Freemius instance, or null if the SDK is not
	 * yet present (safe during development before the SDK is downloaded).
	 *
	 * @return \Freemius|null
	 */
	function wpail_fs(): ?\Freemius {
		global $wpail_fs;

		if ( ! isset( $wpail_fs ) ) {
			$sdk = WPAIL_PLUGIN_DIR . 'freemius/start.php';

			// Fail gracefully when the SDK hasn't been downloaded yet.
			if ( ! file_exists( $sdk ) ) {
				return null;
			}

			require_once $sdk;

			$wpail_fs = fs_dynamic_init( [
				// ----------------------------------------------------------------
				// Replace these two values with your Freemius product credentials.
				// ----------------------------------------------------------------
				'id'         => 'PLACEHOLDER_PRODUCT_ID',
				'public_key' => 'pk_PLACEHOLDER_PUBLIC_KEY',
				// ----------------------------------------------------------------

				'slug'           => 'ai-layer',
				'premium_slug'   => 'ai-layer-pro',
				'type'           => 'plugin',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => true,

				'trial' => [
					'days'               => 14,
					'is_require_payment' => false,
				],

				'menu' => [
					'slug'       => 'wpail_dashboard',
					'first-path' => 'admin.php?page=wpail_dashboard',
					'account'    => true,
					'support'    => false,
					'contact'    => false,
					'network'    => true,
				],
			] );
		}

		return $wpail_fs;
	}

	// Initialise immediately so Freemius hooks are registered before plugins_loaded.
	wpail_fs();
	do_action( 'wpail_fs_loaded' );
}

<?php
/**
 * Injects AI Layer discovery Link and X-AI-Layer HTTP headers on frontend responses.
 *
 * @package WPAIL\Discovery
 */

declare(strict_types=1);

namespace WPAIL\Discovery;

class HttpHeadersInjector {

	/**
	 * Register the send_headers action.
	 */
	public function register(): void {
		add_action( 'send_headers', [ $this, 'inject' ] );
	}

	/**
	 * Output AI Layer discovery headers on frontend pages.
	 */
	public function inject(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! \WPAIL\Admin\SettingsPage::get( \WPAIL\Admin\SettingsPage::SETTING_HTTP_HEADERS_ENABLED, true ) ) {
			return;
		}

		$base     = rtrim( rest_url( WPAIL_REST_NS ), '/' );
		$manifest = $base . '/manifest';
		$openapi  = $base . '/openapi';

		header( 'Link: <' . $manifest . '>; rel="service"' );
		header( 'Link: <' . $openapi . '>; rel="service-desc"', false );
		header( 'X-AI-Layer: ' . $manifest, false );
	}
}

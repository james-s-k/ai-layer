<?php
/**
 * Serves the /ai-layer-sitemap.xml virtual URL and injects into Yoast's sitemap index.
 *
 * Uses REQUEST_URI detection rather than WordPress query vars because Yoast SEO intercepts
 * any URL matching the pattern `{type}-sitemap.xml` via its own routing before WordPress
 * query vars are populated, which prevents the standard rewrite/query_var approach from
 * working on sites where Yoast is active.
 *
 * @package WPAIL\Discovery
 */

declare(strict_types=1);

namespace WPAIL\Discovery;

class SitemapController {

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect',   [ $this, 'maybe_serve' ], 1 );
		add_filter( 'wpseo_sitemap_index', [ $this, 'inject_yoast_sitemap' ] );
	}

	/**
	 * Serve the sitemap XML when the request URI matches /ai-layer-sitemap.xml.
	 *
	 * Intentionally runs at priority 1 (before Yoast's own template_redirect handlers)
	 * and checks REQUEST_URI directly rather than relying on get_query_var(), which
	 * Yoast's sitemap routing overwrites for any URL ending in -{type}-sitemap.xml.
	 */
	public function maybe_serve(): void {
		if ( ! $this->is_sitemap_request() ) {
			return;
		}

		if ( ! \WPAIL\Admin\SettingsPage::get( \WPAIL\Admin\SettingsPage::SETTING_SITEMAP_ENABLED, true ) ) {
			status_header( 404 );
			exit;
		}

		$xml = $this->build_sitemap();

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;

		exit;
	}

	/**
	 * Check whether the current request is for /ai-layer-sitemap.xml.
	 */
	private function is_sitemap_request(): bool {
		$uri       = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
		$home_path = rtrim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
		$relative  = substr( $uri, strlen( $home_path ) );

		return '/ai-layer-sitemap.xml' === $relative;
	}

	/**
	 * Build the XML sitemap string for all AI Layer REST endpoints and discovery pages.
	 *
	 * @return string
	 */
	private function build_sitemap(): string {
		$urls = [
			// REST endpoints.
			rest_url( WPAIL_REST_NS . '/manifest' ),
			rest_url( WPAIL_REST_NS . '/openapi' ),
			rest_url( WPAIL_REST_NS . '/profile' ),
			rest_url( WPAIL_REST_NS . '/services' ),
			rest_url( WPAIL_REST_NS . '/locations' ),
			rest_url( WPAIL_REST_NS . '/faqs' ),
			rest_url( WPAIL_REST_NS . '/proof' ),
			rest_url( WPAIL_REST_NS . '/actions' ),
			rest_url( WPAIL_REST_NS . '/answers' ),
			// Discovery pages.
			home_url( '/ai-layer' ),
			home_url( '/ai-layer.md' ),
			home_url( '/.well-known/ai-layer' ),
		];

		$entries = '';
		foreach ( $urls as $url ) {
			$entries .= "\t<url>\n";
			$entries .= "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
			$entries .= "\t\t<changefreq>daily</changefreq>\n";
			$entries .= "\t</url>\n";
		}

		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
			. $entries
			. '</urlset>';
	}

	/**
	 * Inject the AI Layer sitemap into Yoast SEO's sitemap index.
	 *
	 * @param string $xml Yoast's existing sitemap index XML.
	 * @return string
	 */
	public function inject_yoast_sitemap( string $xml ): string {
		if ( ! \WPAIL\Admin\SettingsPage::get( \WPAIL\Admin\SettingsPage::SETTING_SITEMAP_ENABLED, true ) ) {
			return $xml;
		}

		$sitemap_url = home_url( '/ai-layer-sitemap.xml' );
		$xml        .= '<sitemap><loc>' . esc_url( $sitemap_url ) . '</loc></sitemap>';
		return $xml;
	}
}

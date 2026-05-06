<?php
/**
 * Injects AI Layer discovery directives into robots.txt.
 *
 * Uses REQUEST_URI detection at template_redirect priority 1 rather than relying solely
 * on the robots_txt filter, because Apache serves a physical robots.txt file before
 * WordPress runs — bypassing the filter entirely. By intercepting at template_redirect
 * (once the physical file has been removed), we own the output regardless of how
 * WordPress's rewrite rules handle the /robots.txt URL.
 *
 * @package WPAIL\Discovery
 */

declare(strict_types=1);

namespace WPAIL\Discovery;

class RobotsInjector {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init',              [ $this, 'maybe_remove_physical_robots' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ], 1 );
		add_filter( 'robots_txt',        [ $this, 'inject' ], 10, 2 );
	}

	/**
	 * Delete an empty physical robots.txt so subsequent requests route through WordPress.
	 *
	 * An empty file (e.g. left by Yoast's file editor) blocks the filter with no benefit.
	 */
	public function maybe_remove_physical_robots(): void {
		$path = ABSPATH . 'robots.txt';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
		if ( file_exists( $path ) && 0 === (int) filesize( $path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $path );
		}
	}

	/**
	 * Serve robots.txt when the request URI matches, regardless of WordPress's rewrite routing.
	 *
	 * Calls WordPress's native do_robots(), which applies the robots_txt filter (including inject()).
	 */
	public function maybe_serve(): void {
		if ( ! $this->is_robots_request() ) {
			return;
		}

		do_robots();
		exit;
	}

	/**
	 * Check whether the current request is for /robots.txt.
	 */
	private function is_robots_request(): bool {
		$uri       = isset( $_SERVER['REQUEST_URI'] ) ? strtok( $_SERVER['REQUEST_URI'], '?' ) : '';
		$home_path = rtrim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
		$relative  = substr( $uri, strlen( $home_path ) );

		return '/robots.txt' === $relative;
	}

	/**
	 * Append AI Layer discovery lines to the robots.txt output.
	 *
	 * @param string     $output  Current robots.txt content.
	 * @param string|int $public  Whether the site is set to public (WordPress passes '0'/'1').
	 * @return string
	 */
	public function inject( string $output, $public ): string {
		if ( ! $public ) {
			return $output;
		}

		if ( ! \WPAIL\Admin\SettingsPage::get( \WPAIL\Admin\SettingsPage::SETTING_ROBOTS_INJECTION_ENABLED, true ) ) {
			return $output;
		}

		$base        = rtrim( rest_url( WPAIL_REST_NS ), '/' );
		$discovery   = home_url( '/ai-layer' );
		$manifest    = $base . '/manifest';
		$openapi     = $base . '/openapi';

		$output .= "\n# AI Layer — structured business data endpoints\n";
		$output .= 'AI-Layer: ' . $discovery . "\n";
		$output .= 'AI-Layer-Manifest: ' . $manifest . "\n";
		$output .= 'AI-Layer-OpenAPI: ' . $openapi . "\n";

		return $output;
	}
}

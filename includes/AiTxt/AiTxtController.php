<?php
/**
 * Registers and serves the /ai.txt virtual route.
 *
 * Returns 404 when the feature is disabled. Serves text/plain with a
 * one-hour transient cache when enabled.
 *
 * @package WPAIL\AiTxt
 */

declare(strict_types=1);

namespace WPAIL\AiTxt;

class AiTxtController {

	public function register(): void {
		add_action( 'init',              [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
	}

	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^ai\.txt$', 'index.php?wpail_aitxt=1', 'top' );
	}

	/** @param array<string> $vars */
	public function add_query_var( array $vars ): array {
		$vars[] = 'wpail_aitxt';
		return $vars;
	}

	public function maybe_serve(): void {
		if ( ! get_query_var( 'wpail_aitxt' ) ) {
			return;
		}

		if ( ! AiTxtSettings::get( 'enabled', false ) ) {
			status_header( 404 );
			exit;
		}

		// Bail if a physical file exists — the web server would already have served it.
		if ( file_exists( ABSPATH . 'ai.txt' ) ) {
			status_header( 404 );
			exit;
		}

		$content = $this->get_cached_content();

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}

	private function get_cached_content(): string {
		$cached = get_transient( 'wpail_aitxt_content' );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$content = ( new AiTxtGenerator() )->generate();
		set_transient( 'wpail_aitxt_content', $content, HOUR_IN_SECONDS );
		return $content;
	}

	public static function flush_cache(): void {
		delete_transient( 'wpail_aitxt_content' );
	}

	/** @return bool True if a physical ai.txt file exists at the site root. */
	public static function has_physical_file(): bool {
		return file_exists( ABSPATH . 'ai.txt' );
	}
}

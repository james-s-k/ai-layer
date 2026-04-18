<?php
/**
 * Registers the virtual /llms.txt route and serves generated content.
 *
 * @package WPAIL\LLMsTxt
 */

declare(strict_types=1);

namespace WPAIL\LLMsTxt;

class LLMsTxtController {

	private Generator $generator;
	private ConflictDetector $detector;

	public function __construct() {
		$this->generator = new Generator();
		$this->detector  = new ConflictDetector();
	}

	public function register(): void {
		add_action( 'init',              [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
	}

	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?wpail_llmstxt=1', 'top' );
	}

	/** @param array<string> $vars */
	public function add_query_var( array $vars ): array {
		$vars[] = 'wpail_llmstxt';
		return $vars;
	}

	public function maybe_serve(): void {
		if ( ! get_query_var( 'wpail_llmstxt' ) ) {
			return;
		}

		if ( ! LLMsTxtSettings::get( 'enabled', false ) ) {
			status_header( 404 );
			exit;
		}

		// If a physical file exists the web server already served it before
		// WordPress was reached — this guard handles edge cases only.
		if ( $this->detector->has_physical_file() ) {
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
		$cached = get_transient( 'wpail_llmstxt_content' );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$content = $this->generator->generate();
		set_transient( 'wpail_llmstxt_content', $content, HOUR_IN_SECONDS );
		return $content;
	}

	public static function flush_cache(): void {
		delete_transient( 'wpail_llmstxt_content' );
	}
}

<?php
/**
 * Serves /ai-layer/knowledge (HTML) and /ai-layer/knowledge.md (Markdown).
 *
 * @package WPAIL\Discovery
 */

declare(strict_types=1);

namespace WPAIL\Discovery;

use WPAIL\Admin\SettingsPage;
use WPAIL\Support\EndpointCache;

class KnowledgePage {

	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
		add_action( 'save_post', [ $this, 'maybe_flush_cache' ], 10, 2 );
	}

	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^ai-layer/knowledge\.md$', 'index.php?wpail_knowledge=md', 'top' );
		add_rewrite_rule( '^ai-layer/knowledge$', 'index.php?wpail_knowledge=html', 'top' );
	}

	/** @param array<string> $vars */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'wpail_knowledge';
		return $vars;
	}

	public function maybe_serve(): void {
		$format = get_query_var( 'wpail_knowledge' );
		if ( ! $format || ! self::is_enabled() ) {
			if ( $format ) {
				status_header( 404 );
				exit;
			}
			return;
		}

		if ( 'md' === $format ) {
			$this->serve_markdown();
			return;
		}

		$this->serve_html();
	}

	public static function is_enabled(): bool {
		return (bool) SettingsPage::get( SettingsPage::SETTING_KNOWLEDGE_PAGE_ENABLED, true );
	}

	public static function is_noindex(): bool {
		return self::is_enabled()
			&& (bool) SettingsPage::get( SettingsPage::SETTING_KNOWLEDGE_PAGE_NOINDEX, SettingsPage::DEFAULT_KNOWLEDGE_PAGE_NOINDEX );
	}

	public static function is_indexable(): bool {
		return self::is_enabled() && ! self::is_noindex();
	}

	public static function html_url(): string {
		return home_url( '/ai-layer/knowledge' );
	}

	public static function markdown_url(): string {
		return home_url( '/ai-layer/knowledge.md' );
	}

	public static function flush_cache(): void {
		delete_transient( 'wpail_knowledge_html' );
		delete_transient( 'wpail_knowledge_md' );
	}

	public function maybe_flush_cache( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || ! str_starts_with( $post->post_type, 'wpail_' ) ) {
			return;
		}

		self::flush_cache();
	}

	private function serve_html(): void {
		$content = get_transient( 'wpail_knowledge_html' );
		if ( ! is_string( $content ) || '' === $content ) {
			$content = ( new KnowledgePageGenerator() )->generate_html();
			set_transient( 'wpail_knowledge_html', $content, EndpointCache::max_age() );
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: ' . EndpointCache::header_value() );
		if ( self::is_noindex() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}

	private function serve_markdown(): void {
		$content = get_transient( 'wpail_knowledge_md' );
		if ( ! is_string( $content ) || '' === $content ) {
			$content = ( new KnowledgePageGenerator() )->generate_markdown();
			set_transient( 'wpail_knowledge_md', $content, EndpointCache::max_age() );
		}

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: ' . EndpointCache::header_value() );
		if ( self::is_noindex() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}
}

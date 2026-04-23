<?php
/**
 * Registers and serves the /.well-known/ai-layer discovery endpoint.
 *
 * Follows RFC 8615 well-known URI conventions. Agents and crawlers that
 * understand the AI Layer protocol can fetch this JSON document to
 * discover all available endpoints without prior knowledge of the site.
 *
 * @package WPAIL\WellKnown
 */

declare(strict_types=1);

namespace WPAIL\WellKnown;

use WPAIL\Admin\SettingsPage;

class AiLayerController {

	private AiLayerGenerator $generator;

	public function __construct() {
		$this->generator = new AiLayerGenerator();
	}

	public function register(): void {
		add_action( 'init',              [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
	}

	public function add_rewrite_rule(): void {
		add_rewrite_rule( '^\.well-known/ai-layer$', 'index.php?wpail_wellknown_ai=1', 'top' );
	}

	/** @param array<string> $vars */
	public function add_query_var( array $vars ): array {
		$vars[] = 'wpail_wellknown_ai';
		return $vars;
	}

	public function maybe_serve(): void {
		if ( ! get_query_var( 'wpail_wellknown_ai' ) ) {
			return;
		}

		// Disabled when the user has chosen llms.txt-only discovery mode.
		if ( SettingsPage::get( SettingsPage::SETTING_AI_DISCOVERY_MODE, SettingsPage::AI_DISCOVERY_WELL_KNOWN ) === SettingsPage::AI_DISCOVERY_LLMSTXT ) {
			status_header( 404 );
			exit;
		}

		$data = $this->get_cached_data();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** @return array<string, mixed> */
	private function get_cached_data(): array {
		$cached = get_transient( 'wpail_wellknown_content' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$data = $this->generator->generate();
		set_transient( 'wpail_wellknown_content', $data, HOUR_IN_SECONDS );
		return $data;
	}

	public static function flush_cache(): void {
		delete_transient( 'wpail_wellknown_content' );
	}
}

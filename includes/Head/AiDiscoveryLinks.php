<?php
/**
 * Outputs AI discovery <link> tags in the page <head>.
 *
 * Signals to crawlers and future agents where to find machine-readable
 * business data without requiring them to know the URLs in advance.
 *
 * @package WPAIL\Head
 */

declare(strict_types=1);

namespace WPAIL\Head;

use WPAIL\Admin\SettingsPage;
use WPAIL\LLMsTxt\LLMsTxtSettings;

class AiDiscoveryLinks {

	public function register(): void {
		add_action( 'wp_head', [ $this, 'output_links' ], 1 );
	}

	public function output_links(): void {
		if ( ! SettingsPage::get( SettingsPage::SETTING_HEAD_LINKS_ENABLED, true ) ) {
			return;
		}

		$manifest = rtrim( rest_url( WPAIL_REST_NS ), '/' ) . '/manifest';
		$openapi  = rtrim( rest_url( WPAIL_REST_NS ), '/' ) . '/openapi';

		printf(
			'<link rel="alternate" type="application/json" href="%s">' . "\n",
			esc_url( $manifest )
		);

		printf(
			'<link rel="service-desc" type="application/vnd.oai.openapi+json" href="%s">' . "\n",
			esc_url( $openapi )
		);

		$discovery_mode = SettingsPage::get(
			SettingsPage::SETTING_AI_DISCOVERY_MODE,
			SettingsPage::AI_DISCOVERY_WELL_KNOWN
		);

		if ( $discovery_mode === SettingsPage::AI_DISCOVERY_WELL_KNOWN ) {
			printf(
				'<link rel="ai-layer" href="%s" type="application/json">' . "\n",
				esc_url( home_url( '/.well-known/ai-layer' ) )
			);
		}

		if ( LLMsTxtSettings::get( 'enabled', false ) ) {
			printf(
				'<link rel="llms-txt" href="%s" type="text/plain">' . "\n",
				esc_url( home_url( '/llms.txt' ) )
			);
		}

		$this->output_data_catalog_jsonld( $manifest );
	}

	private function output_data_catalog_jsonld( string $manifest_url ): void {
		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'WebSite',
			'mainEntity' => [
				'@type' => 'DataCatalog',
				'name'  => 'AI Layer — ' . get_bloginfo( 'name' ),
				'url'   => $manifest_url,
			],
		];

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
		);
	}
}

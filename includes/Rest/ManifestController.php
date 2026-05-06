<?php
/**
 * REST endpoint: GET /
 *
 * Returns the canonical AI Layer discovery manifest.
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Repositories\BusinessRepository;
use WPAIL\Admin\SettingsPage;
use WPAIL\LLMsTxt\LLMsTxtSettings;
use WPAIL\AiTxt\AiTxtSettings;

class ManifestController extends BaseController {

	/**
	 * Register the manifest route.
	 */
	public function register_routes(): void {
		register_rest_route(
			WPAIL_REST_NS,
			'/manifest',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_manifest' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Return the discovery manifest.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_manifest(): \WP_REST_Response {
		$repo    = new BusinessRepository();
		$profile = $repo->get();

		$name        = $profile->name ?: get_bloginfo( 'name' );
		$description = $profile->short_summary ?: get_bloginfo( 'description' );
		$website     = $profile->website ?: home_url();
		$base        = rtrim( rest_url( WPAIL_REST_NS ), '/' );

		$manifest = [
			'name'             => $name,
			'description'      => $description,
			'website'          => $website,
			'version'          => '1.0',
			'ai_layer_version' => WPAIL_VERSION,
			'language'         => str_replace( '_', '-', get_locale() ),
			'updated_at'       => $this->get_updated_at(),
			'entities'         => $this->build_entities( $base ),
			'discovery'        => $this->build_discovery( $base ),
			'relationships'    => [
				'services.related_faqs'      => true,
				'services.related_locations' => true,
				'services.related_proof'     => true,
				'services.related_actions'   => true,
				'locations.related_services' => true,
				'faqs.related_services'      => true,
				'proof.related_services'     => true,
			],
			'query'            => [
				'supports_semantic_answers' => true,
				'supports_filters'          => true,
				'supports_keyword_search'   => true,
				'answer_endpoint'           => $base . '/answers?query={question}',
			],
			'authentication'   => [
				'required'         => false,
				'write_required'   => true,
				'write_method'     => 'WordPress Application Passwords (HTTP Basic Auth)',
				'write_capability' => 'edit_posts',
			],
		];

		$response = new \WP_REST_Response( $manifest, 200 );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}

	/**
	 * Get the ISO 8601 timestamp of the most recently modified WPAIL post.
	 *
	 * @return string
	 */
	private function get_updated_at(): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type IN (%s,%s,%s,%s,%s,%s) AND post_status = 'publish'",
				...[ 'wpail_service', 'wpail_location', 'wpail_faq', 'wpail_proof', 'wpail_action', 'wpail_answer' ]
			)
		);

		return $max ? gmdate( 'c', strtotime( $max ) ) : gmdate( 'c' );
	}

	/**
	 * Build the entities map, conditionally adding products.
	 *
	 * @param string $base REST base URL without trailing slash.
	 * @return array<string, string>
	 */
	private function build_entities( string $base ): array {
		$entities = [
			'profile'   => $base . '/profile',
			'services'  => $base . '/services',
			'locations' => $base . '/locations',
			'faqs'      => $base . '/faqs',
			'proof'     => $base . '/proof',
			'actions'   => $base . '/actions',
			'answers'   => $base . '/answers',
		];

		if ( SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED ) && class_exists( 'WooCommerce' ) ) {
			$entities['products'] = $base . '/products';
		}

		return $entities;
	}

	/**
	 * Build the discovery map, conditionally adding llms_txt and ai_txt.
	 *
	 * @param string $base REST base URL without trailing slash.
	 * @return array<string, string>
	 */
	private function build_discovery( string $base ): array {
		$discovery = [
			'openapi'        => $base . '/openapi',
			'well_known'     => home_url( '/.well-known/ai-layer' ),
			'discovery_page' => home_url( '/ai-layer' ),
			'sitemap'        => home_url( '/ai-layer-sitemap.xml' ),
		];

		if ( LLMsTxtSettings::get( 'enabled', false ) ) {
			$discovery['llms_txt'] = home_url( '/llms.txt' );
		}

		if ( AiTxtSettings::get( 'enabled', false ) ) {
			$discovery['ai_txt'] = home_url( '/ai.txt' );
		}

		return $discovery;
	}
}

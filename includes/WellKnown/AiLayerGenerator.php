<?php
/**
 * Builds the /.well-known/ai-layer JSON discovery document.
 *
 * This is the single source of truth for all AI Layer endpoints and
 * capabilities. llms.txt links here; agents query here directly.
 *
 * @package WPAIL\WellKnown
 */

declare(strict_types=1);

namespace WPAIL\WellKnown;

use WPAIL\Repositories\BusinessRepository;
use WPAIL\Admin\SettingsPage;
use WPAIL\Licensing\Features;

class AiLayerGenerator {

	private BusinessRepository $business_repo;

	public function __construct() {
		$this->business_repo = new BusinessRepository();
	}

	/** @return array<string, mixed> */
	public function generate(): array {
		$profile  = $this->business_repo->get();
		$api_base = rtrim( rest_url( WPAIL_REST_NS ), '/' );

		$name        = ! empty( $profile->name ) ? $profile->name : get_bloginfo( 'name' );
		$description = ! empty( $profile->short_summary ) ? $profile->short_summary : get_bloginfo( 'description' );

		$endpoints = [
			[
				'path'        => '/profile',
				'url'         => $api_base . '/profile',
				'description' => 'Business name, contact details, and description.',
				'methods'     => [ 'GET' ],
			],
			[
				'path'        => '/services',
				'url'         => $api_base . '/services',
				'description' => 'Services and products offered.',
				'methods'     => [ 'GET' ],
			],
			[
				'path'        => '/locations',
				'url'         => $api_base . '/locations',
				'description' => 'Locations and service areas.',
				'methods'     => [ 'GET' ],
			],
			[
				'path'        => '/faqs',
				'url'         => $api_base . '/faqs',
				'description' => 'Frequently asked questions and answers.',
				'methods'     => [ 'GET' ],
			],
			[
				'path'        => '/proof',
				'url'         => $api_base . '/proof',
				'description' => 'Testimonials, case studies, and accreditations.',
				'methods'     => [ 'GET' ],
			],
			[
				'path'        => '/actions',
				'url'         => $api_base . '/actions',
				'description' => 'Recommended next steps and calls to action.',
				'methods'     => [ 'GET' ],
			],
		];

		if ( SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED ) && class_exists( 'WooCommerce' ) ) {
			$endpoints[] = [
				'path'        => '/products',
				'url'         => $api_base . '/products',
				'description' => 'Product catalogue with pricing and availability.',
				'methods'     => [ 'GET' ],
				'params'      => [
					'per_page' => 'integer — products per page (max 100, default 20)',
					'page'     => 'integer — page number',
					'category' => 'string — filter by category slug',
				],
			];
			$endpoints[] = [
				'path'        => '/products/{slug}',
				'url'         => $api_base . '/products/{slug}',
				'description' => 'Full detail for a single product.',
				'methods'     => [ 'GET' ],
			];
		}

		if ( Features::answers_enabled() ) {
			$endpoints[] = [
				'path'        => '/answers',
				'url'         => $api_base . '/answers',
				'description' => 'Natural language question answering.',
				'methods'     => [ 'GET' ],
				'params'      => [
					'query' => 'string — the question to answer',
				],
			];
		}

		return [
			'schema_version' => '1.0',
			'manifest'       => $api_base . '/manifest',
			'openapi'        => $api_base . '/openapi',
			'name'           => $name,
			'description'    => $description ?: '',
			'api'            => [
				'base'      => $api_base,
				'endpoints' => $endpoints,
			],
			'llms_txt'       => home_url( '/llms.txt' ),
		];
	}
}

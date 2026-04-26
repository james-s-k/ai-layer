<?php
/**
 * MCP abilities: Business Profile (get, update).
 *
 * @package WPAIL\Abilities
 */

declare(strict_types=1);

namespace WPAIL\Abilities;

use WPAIL\Repositories\BusinessRepository;

class ProfileAbilities {

	public function register(): void {
		wp_register_ability( 'ai-layer/get-profile', [
			'label'       => 'Get Business Profile',
			'description' => 'Returns the full AI Layer business profile: name, contact details, address, opening hours, service modes, social links, and trust summary.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => new \stdClass(),
			],
			'execute_callback'    => function ( array $input ): array {
				return ( new BusinessRepository() )->get()->to_public_array();
			},
			'permission_callback' => fn() => true,
			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );

		wp_register_ability( 'ai-layer/update-profile', [
			'label'       => 'Update Business Profile',
			'description' => 'Partially updates the AI Layer business profile. Only fields included in the request are changed; omitted fields are left as-is.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'name'             => [ 'type' => 'string', 'description' => 'Business trading name.' ],
					'legal_name'       => [ 'type' => 'string', 'description' => 'Registered legal name if different from trading name.' ],
					'short_summary'    => [ 'type' => 'string', 'description' => '1–2 sentence description used in API responses and llms.txt.' ],
					'long_summary'     => [ 'type' => 'string', 'description' => 'Extended business description.' ],
					'business_type'    => [ 'type' => 'string', 'description' => 'Schema.org type: LocalBusiness, Organization, ProfessionalService, etc.' ],
					'subtype'          => [ 'type' => 'string', 'description' => 'Industry or niche (e.g. Digital Agency, Solicitor).' ],
					'phone'            => [ 'type' => 'string', 'description' => 'Primary phone number.' ],
					'email'            => [ 'type' => 'string', 'description' => 'Primary email address.' ],
					'website'          => [ 'type' => 'string', 'description' => 'Website URL.' ],
					'address_line1'    => [ 'type' => 'string' ],
					'address_line2'    => [ 'type' => 'string' ],
					'city'             => [ 'type' => 'string' ],
					'county'           => [ 'type' => 'string' ],
					'postcode'         => [ 'type' => 'string' ],
					'country'          => [ 'type' => 'string', 'description' => '2-letter ISO country code, e.g. GB.' ],
					'opening_hours'    => [ 'type' => 'string', 'description' => 'Plain-text opening hours, e.g. Mon–Fri 9am–5pm.' ],
					'service_modes'    => [ 'type' => 'array', 'items' => [ 'type' => 'string', 'enum' => [ 'in_person', 'remote', 'mobile' ] ] ],
					'trust_summary'    => [ 'type' => 'string', 'description' => 'Brief statement of why customers trust you.' ],
					'founded_year'     => [ 'type' => 'integer' ],
					'social_facebook'  => [ 'type' => 'string' ],
					'social_twitter'   => [ 'type' => 'string' ],
					'social_linkedin'  => [ 'type' => 'string' ],
					'social_instagram' => [ 'type' => 'string' ],
					'social_youtube'   => [ 'type' => 'string' ],
				],
			],
			'execute_callback'    => function ( array $input ): array {
				$repo    = new BusinessRepository();
				$current = $repo->get_raw();
				$repo->save( array_merge( $current, $input ) );
				return $repo->get()->to_public_array();
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => [
				'annotations' => [ 'readonly' => false, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}

<?php
/**
 * Canonical Business model.
 *
 * This is a plain PHP value object — not a WP_Post, not a raw options array.
 * All REST responses and schema output should be built from this model,
 * not from raw WordPress data.
 *
 * @package WPAIL\Models
 */

declare(strict_types=1);

namespace WPAIL\Models;

class BusinessModel {

	public function __construct(
		// Identity
		public readonly string  $name            = '',
		public readonly string  $legal_name      = '',
		public readonly string  $business_type   = '',
		public readonly string  $subtype         = '',
		public readonly string  $short_summary   = '',
		public readonly string  $long_summary    = '',
		public readonly string  $brand_tone      = '',   // private field
		public readonly ?int    $founded_year    = null,

		// Contact
		public readonly string  $phone           = '',
		public readonly string  $email           = '',
		public readonly string  $website         = '',

		// Address
		public readonly string  $address_line1   = '',
		public readonly string  $address_line2   = '',
		public readonly string  $city            = '',
		public readonly string  $county          = '',
		public readonly string  $postcode        = '',
		public readonly string  $country         = 'GB',

		// Operations
		public readonly string  $opening_hours   = '',
		/** @var array<string> */
		public readonly array   $service_modes   = [],
		public readonly string  $trust_summary   = '',

		// Social
		public readonly string  $social_facebook  = '',
		public readonly string  $social_twitter   = '',
		public readonly string  $social_linkedin  = '',
		public readonly string  $social_instagram = '',
		public readonly string  $social_youtube   = '',
	) {}

	/**
	 * Convert to a public-safe array for REST responses.
	 * Private fields (brand_tone) are excluded.
	 *
	 * @return array<string, mixed>
	 */
	public function to_public_array(): array {
		return [
			'name'           => $this->name,
			'legal_name'     => $this->legal_name,
			'business_type'  => $this->business_type,
			'subtype'        => $this->subtype,
			'short_summary'  => $this->short_summary,
			'long_summary'   => $this->long_summary,
			'founded_year'   => $this->founded_year,
			'contact'        => [
				'phone'   => $this->phone,
				'email'   => $this->email,
				'website' => $this->website,
			],
			'address'        => [
				'line1'    => $this->address_line1,
				'line2'    => $this->address_line2,
				'city'     => $this->city,
				'county'   => $this->county,
				'postcode' => $this->postcode,
				'country'  => $this->country,
			],
			'opening_hours'  => $this->opening_hours,
			'service_modes'  => $this->service_modes,
			'trust_summary'  => $this->trust_summary,
			'social'         => array_filter( [
				'facebook'  => $this->social_facebook,
				'twitter'   => $this->social_twitter,
				'linkedin'  => $this->social_linkedin,
				'instagram' => $this->social_instagram,
				'youtube'   => $this->social_youtube,
			] ),
		];
	}

	/**
	 * Full array including private fields — for internal/admin use only.
	 *
	 * @return array<string, mixed>
	 */
	public function to_full_array(): array {
		return array_merge( $this->to_public_array(), [
			'brand_tone' => $this->brand_tone,
		] );
	}
}

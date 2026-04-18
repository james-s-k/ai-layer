<?php
/**
 * Central field definitions for all object types.
 *
 * This is the single source of truth for what fields exist, their types,
 * defaults, sanitisation callbacks, and visibility. By centralising this:
 *
 * - Meta boxes render fields from the definition (no duplication).
 * - Transformers know exactly what to extract.
 * - Migrations can diff definitions across versions.
 * - REST responses are deterministic.
 *
 * Field types understood by the meta box renderer:
 *   text, textarea, url, email, tel, number, select, checkboxes, checkbox, post_ids
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

class FieldDefinitions {

	const VISIBILITY_PUBLIC  = 'public';
	const VISIBILITY_PRIVATE = 'private';
	const VISIBILITY_AI_ONLY = 'ai_only';  // Reserved for future use.

	// ------------------------------------------------------------------
	// Business profile (stored in wp_options).
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function business(): array {
		return [
			// Identity
			'name'             => [ 'type' => 'text',       'label' => 'Business Name',         'required' => true,  'visibility' => self::VISIBILITY_PUBLIC ],
			'legal_name'       => [ 'type' => 'text',       'label' => 'Legal Name',             'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'business_type'    => [ 'type' => 'select',     'label' => 'Business Type',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'options' => [ '' => '— Select —', 'LocalBusiness' => 'Local Business', 'Organization' => 'Organization', 'ProfessionalService' => 'Professional Service', 'HomeAndConstructionBusiness' => 'Home & Construction', 'LegalService' => 'Legal Service', 'HealthAndBeautyBusiness' => 'Health & Beauty', 'FoodEstablishment' => 'Food / Restaurant', 'Other' => 'Other' ] ],
			'subtype'          => [ 'type' => 'text',       'label' => 'Subtype / Industry',     'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'help' => 'e.g. Plumber, Digital Agency, Solicitor' ],
			'short_summary'    => [ 'type' => 'textarea',   'label' => 'Short Summary',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'help' => '1–2 sentences. Used in API responses and schema output.' ],
			'long_summary'     => [ 'type' => 'textarea',   'label' => 'Long Summary',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'brand_tone'       => [ 'type' => 'text',       'label' => 'Brand Tone',             'required' => false, 'visibility' => self::VISIBILITY_PRIVATE,
			                        'help' => 'e.g. professional, friendly, technical. Internal only — for future AI guidance.' ],
			'founded_year'     => [ 'type' => 'number',     'label' => 'Founded Year',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			// Contact
			'phone'            => [ 'type' => 'tel',        'label' => 'Primary Phone',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'email'            => [ 'type' => 'email',      'label' => 'Primary Email',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'website'          => [ 'type' => 'url',        'label' => 'Website URL',            'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			// Address
			'address_line1'    => [ 'type' => 'text',       'label' => 'Address Line 1',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'address_line2'    => [ 'type' => 'text',       'label' => 'Address Line 2',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'city'             => [ 'type' => 'text',       'label' => 'City / Town',            'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'county'           => [ 'type' => 'text',       'label' => 'County / State',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'postcode'         => [ 'type' => 'text',       'label' => 'Postcode / ZIP',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'country'          => [ 'type' => 'text',       'label' => 'Country',                'required' => false, 'visibility' => self::VISIBILITY_PUBLIC, 'default' => 'GB' ],
			// Operations
			'opening_hours'    => [ 'type' => 'textarea',   'label' => 'Opening Hours',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'help' => 'Plain text description. e.g. Mon–Fri 9am–5pm, Sat 9am–1pm' ],
			'service_modes'    => [ 'type' => 'checkboxes', 'label' => 'Service Modes',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'options' => [ 'in_person' => 'In Person', 'remote' => 'Remote / Online', 'mobile' => 'Mobile / On-site Visit' ] ],
			'trust_summary'    => [ 'type' => 'textarea',   'label' => 'Trust Summary',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'help' => 'Brief statement of why customers trust this business.' ],
			// Social
			'social_facebook'  => [ 'type' => 'url',        'label' => 'Facebook URL',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'social_twitter'   => [ 'type' => 'url',        'label' => 'Twitter / X URL',        'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'social_linkedin'  => [ 'type' => 'url',        'label' => 'LinkedIn URL',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'social_instagram' => [ 'type' => 'url',        'label' => 'Instagram URL',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
			'social_youtube'   => [ 'type' => 'url',        'label' => 'YouTube URL',            'required' => false, 'visibility' => self::VISIBILITY_PUBLIC ],
		];
	}

	// ------------------------------------------------------------------
	// Services CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function service(): array {
		return [
			'category'          => [ 'type' => 'text',       'label' => 'Category',              'visibility' => self::VISIBILITY_PUBLIC ],
			'status'            => [ 'type' => 'select',     'label' => 'Status',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'active' => 'Active', 'inactive' => 'Inactive', 'coming_soon' => 'Coming Soon' ], 'default' => 'active' ],
			'short_summary'     => [ 'type' => 'textarea',   'label' => 'Short Summary',         'visibility' => self::VISIBILITY_PUBLIC ],
			'long_summary'      => [ 'type' => 'textarea',   'label' => 'Long Summary',          'visibility' => self::VISIBILITY_PUBLIC ],
			'customer_types'    => [ 'type' => 'text',       'label' => 'Customer Types',        'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => 'Comma-separated. e.g. homeowners, SMEs, landlords' ],
			'service_modes'     => [ 'type' => 'checkboxes', 'label' => 'Service Modes',         'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'in_person' => 'In Person', 'remote' => 'Remote / Online', 'mobile' => 'Mobile / On-site' ] ],
			'keywords'          => [ 'type' => 'text',       'label' => 'Keywords',              'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => 'Comma-separated. Used in answer matching.' ],
			'synonyms'          => [ 'type' => 'text',       'label' => 'Synonyms',              'visibility' => self::VISIBILITY_PRIVATE,
			                         'help' => 'Alternative names for this service. Comma-separated.' ],
			'common_problems'   => [ 'type' => 'textarea',   'label' => 'Common Problems',       'visibility' => self::VISIBILITY_PRIVATE,
			                         'help' => 'Problems this service solves. Used for intent matching.' ],
			'pricing_type'      => [ 'type' => 'select',     'label' => 'Pricing Type',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'fixed' => 'Fixed Price', 'hourly' => 'Hourly Rate', 'quote' => 'Quote on Request', 'free' => 'Free' ] ],
			'from_price'        => [ 'type' => 'number',     'label' => 'From Price',            'visibility' => self::VISIBILITY_PUBLIC ],
			'currency'          => [ 'type' => 'text',       'label' => 'Currency',              'visibility' => self::VISIBILITY_PUBLIC, 'default' => 'GBP' ],
			'price_notes'       => [ 'type' => 'text',       'label' => 'Price Notes',           'visibility' => self::VISIBILITY_PUBLIC ],
			'available'         => [ 'type' => 'checkbox',   'label' => 'Currently Available',   'visibility' => self::VISIBILITY_PUBLIC, 'default' => true ],
			'benefits'          => [ 'type' => 'textarea',   'label' => 'Key Benefits',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => 'One benefit per line.' ],
			'related_faqs'      => [ 'type' => 'post_ids',   'label' => 'Related FAQs',          'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_faq' ],
			'related_proof'     => [ 'type' => 'post_ids',   'label' => 'Related Proof',         'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_proof' ],
			'related_actions'   => [ 'type' => 'post_ids',   'label' => 'Related Actions',       'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_action' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Available Locations',   'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location' ],
			'linked_page_url'   => [ 'type' => 'url',        'label' => 'Linked Page URL',       'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => 'Optional link to the main page for this service on your website.' ],
			'schema_type'       => [ 'type' => 'select',     'label' => 'Schema.org Type',       'visibility' => self::VISIBILITY_PRIVATE,
			                         'options' => [ '' => 'None / Inherit', 'Service' => 'Service', 'ProfessionalService' => 'ProfessionalService', 'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness' ] ],
		];
	}

	// ------------------------------------------------------------------
	// Locations CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function location(): array {
		return [
			'location_type'     => [ 'type' => 'select',     'label' => 'Location Type',         'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'town' => 'Town', 'city' => 'City', 'county' => 'County', 'region' => 'Region', 'postcode_area' => 'Postcode Area', 'country' => 'Country' ] ],
			'region'            => [ 'type' => 'text',       'label' => 'Region',                'visibility' => self::VISIBILITY_PUBLIC ],
			'country'           => [ 'type' => 'text',       'label' => 'Country',               'visibility' => self::VISIBILITY_PUBLIC, 'default' => 'GB' ],
			'postcode_prefixes' => [ 'type' => 'text',       'label' => 'Postcode Prefixes',     'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => 'Comma-separated. e.g. SW1, EC1, W1' ],
			'is_primary'        => [ 'type' => 'checkbox',   'label' => 'Primary Location',      'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => "Mark as the business's primary trading location." ],
			'service_radius_km' => [ 'type' => 'number',     'label' => 'Service Radius (km)',   'visibility' => self::VISIBILITY_PUBLIC ],
			'summary'           => [ 'type' => 'textarea',   'label' => 'Summary',               'visibility' => self::VISIBILITY_PUBLIC ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Services Available Here', 'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service' ],
			'local_proof'       => [ 'type' => 'post_ids',   'label' => 'Local Proof / Reviews', 'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_proof' ],
			'linked_page_url'   => [ 'type' => 'url',        'label' => 'Linked Page URL',       'visibility' => self::VISIBILITY_PUBLIC ],
		];
	}

	// ------------------------------------------------------------------
	// FAQs CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function faq(): array {
		return [
			'question'          => [ 'type' => 'text',       'label' => 'Question',              'visibility' => self::VISIBILITY_PUBLIC, 'required' => true ],
			'short_answer'      => [ 'type' => 'textarea',   'label' => 'Short Answer',          'visibility' => self::VISIBILITY_PUBLIC, 'required' => true,
			                         'help' => '1–2 sentences. Returned in /answers responses.' ],
			'long_answer'       => [ 'type' => 'textarea',   'label' => 'Long Answer',           'visibility' => self::VISIBILITY_PUBLIC ],
			'status'            => [ 'type' => 'select',     'label' => 'Status',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'published' => 'Published', 'draft' => 'Draft', 'private' => 'Private' ], 'default' => 'published' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location' ],
			'intent_tags'       => [ 'type' => 'text',       'label' => 'Intent Tags',           'visibility' => self::VISIBILITY_PRIVATE,
			                         'help' => 'Comma-separated. e.g. pricing, availability, how-it-works' ],
			'priority'          => [ 'type' => 'number',     'label' => 'Priority',              'visibility' => self::VISIBILITY_PRIVATE, 'default' => 0,
			                         'help' => 'Higher = shown first in answer matching.' ],
			'is_public'         => [ 'type' => 'checkbox',   'label' => 'Publicly Visible',      'visibility' => self::VISIBILITY_PUBLIC, 'default' => true ],
		];
	}

	// ------------------------------------------------------------------
	// Proof CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function proof(): array {
		return [
			'proof_type'        => [ 'type' => 'select',     'label' => 'Type',                  'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'testimonial' => 'Testimonial', 'accreditation' => 'Accreditation', 'statistic' => 'Statistic', 'award' => 'Award', 'case_study' => 'Case Study', 'media_mention' => 'Media Mention' ] ],
			'headline'          => [ 'type' => 'text',       'label' => 'Headline',              'visibility' => self::VISIBILITY_PUBLIC ],
			'content'           => [ 'type' => 'textarea',   'label' => 'Content',               'visibility' => self::VISIBILITY_PUBLIC ],
			'source_name'       => [ 'type' => 'text',       'label' => 'Source Name',           'visibility' => self::VISIBILITY_PUBLIC ],
			'source_context'    => [ 'type' => 'text',       'label' => 'Source Context',        'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => 'e.g. "via Google Reviews", "Managing Director, Acme Ltd"' ],
			'rating'            => [ 'type' => 'number',     'label' => 'Rating (1–5)',           'visibility' => self::VISIBILITY_PUBLIC ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location' ],
			'is_public'         => [ 'type' => 'checkbox',   'label' => 'Publicly Visible',      'visibility' => self::VISIBILITY_PUBLIC, 'default' => true ],
		];
	}

	// ------------------------------------------------------------------
	// Actions CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function action(): array {
		return [
			'action_type'       => [ 'type' => 'select',     'label' => 'Action Type',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'call' => 'Call', 'email' => 'Email', 'book' => 'Book / Schedule', 'quote' => 'Request Quote', 'visit' => 'Visit Page', 'download' => 'Download', 'chat' => 'Chat' ] ],
			'label'             => [ 'type' => 'text',       'label' => 'Button Label',          'visibility' => self::VISIBILITY_PUBLIC, 'required' => true,
			                         'help' => 'e.g. "Call us now", "Get a free quote"' ],
			'description'       => [ 'type' => 'text',       'label' => 'Description',           'visibility' => self::VISIBILITY_PUBLIC ],
			'phone'             => [ 'type' => 'tel',        'label' => 'Phone Number',          'visibility' => self::VISIBILITY_PUBLIC ],
			'url'               => [ 'type' => 'url',        'label' => 'URL',                   'visibility' => self::VISIBILITY_PUBLIC ],
			'method'            => [ 'type' => 'select',     'label' => 'Method',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'link' => 'Link', 'phone' => 'Phone', 'form' => 'Form', 'email' => 'Email' ] ],
			'availability_rule' => [ 'type' => 'text',       'label' => 'Availability Rule',     'visibility' => self::VISIBILITY_PRIVATE,
			                         'help' => 'e.g. Mon–Fri 9am–5pm. For future conditional display.' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location' ],
			'is_public'         => [ 'type' => 'checkbox',   'label' => 'Publicly Visible',      'visibility' => self::VISIBILITY_PUBLIC, 'default' => true ],
		];
	}

	// ------------------------------------------------------------------
	// Answers CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function answer(): array {
		return [
			'query_patterns'    => [ 'type' => 'textarea',   'label' => 'Query Patterns',        'visibility' => self::VISIBILITY_PRIVATE,
			                         'help' => 'One query pattern per line. Used for manual answer matching.' ],
			'short_answer'      => [ 'type' => 'textarea',   'label' => 'Short Answer',          'visibility' => self::VISIBILITY_PUBLIC ],
			'long_answer'       => [ 'type' => 'textarea',   'label' => 'Long Answer',           'visibility' => self::VISIBILITY_PUBLIC ],
			'confidence'        => [ 'type' => 'select',     'label' => 'Confidence',            'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low' ], 'default' => 'high' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location' ],
			'next_actions'      => [ 'type' => 'post_ids',   'label' => 'Next Actions',          'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_action' ],
			'source_faq_ids'    => [ 'type' => 'post_ids',   'label' => 'Source FAQs',           'visibility' => self::VISIBILITY_PRIVATE, 'post_type' => 'wpail_faq' ],
		];
	}

	// ------------------------------------------------------------------
	// Utility.
	// ------------------------------------------------------------------

	/**
	 * Return only public fields for a given type.
	 *
	 * @param string $type  business|service|location|faq|proof|action|answer
	 * @return array<string, array<string, mixed>>
	 */
	public static function public_fields( string $type ): array {
		$all = self::$type();
		return array_filter(
			$all,
			fn( $f ) => ( $f['visibility'] ?? self::VISIBILITY_PUBLIC ) === self::VISIBILITY_PUBLIC
		);
	}
}

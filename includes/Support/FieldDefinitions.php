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
			'name'             => [ 'type' => 'text',       'label' => 'Business Name',         'required' => true,  'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. Acme Digital Ltd' ],
			'legal_name'       => [ 'type' => 'text',       'label' => 'Legal Name',             'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. Acme Digital Limited',
			                        'help' => 'Only needed if your registered legal name differs from your trading name.' ],
			'business_type'    => [ 'type' => 'select',     'label' => 'Business Type',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'options' => [ '' => '— Select —', 'LocalBusiness' => 'Local Business', 'Organization' => 'Organization', 'ProfessionalService' => 'Professional Service', 'HomeAndConstructionBusiness' => 'Home & Construction', 'LegalService' => 'Legal Service', 'HealthAndBeautyBusiness' => 'Health & Beauty', 'FoodEstablishment' => 'Food / Restaurant', 'Other' => 'Other' ],
			                        'help' => 'Used for Schema.org markup. Choose the closest match to your business category.' ],
			'subtype'          => [ 'type' => 'text',       'label' => 'Subtype / Industry',     'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. Plumber, Digital Agency, Solicitor',
			                        'help' => 'A more specific description of your industry or niche.' ],
			'short_summary'    => [ 'type' => 'textarea',   'label' => 'Short Summary',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. We help small businesses rank higher on Google through ethical, results-driven SEO.',
			                        'help' => '1–2 sentences. Used in API responses, schema output, and llms.txt.' ],
			'long_summary'     => [ 'type' => 'textarea',   'label' => 'Long Summary',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'Extended description of your business — what you do, who you serve, and what sets you apart.' ],
			'brand_tone'       => [ 'type' => 'text',       'label' => 'Brand Tone',             'required' => false, 'visibility' => self::VISIBILITY_PRIVATE,
			                        'placeholder' => 'e.g. professional, friendly, technical',
			                        'help' => 'Internal only — describes your communication style for future AI guidance. Not included in API responses.' ],
			'founded_year'     => [ 'type' => 'number',     'label' => 'Founded Year',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => '2010' ],
			// Contact
			'phone'            => [ 'type' => 'tel',        'label' => 'Primary Phone',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. 01234 567890' ],
			'email'            => [ 'type' => 'email',      'label' => 'Primary Email',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. hello@yourbusiness.com' ],
			'website'          => [ 'type' => 'url',        'label' => 'Website URL',            'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'https://www.yourbusiness.com' ],
			// Address
			'address_line1'    => [ 'type' => 'text',       'label' => 'Address Line 1',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. 10 High Street' ],
			'address_line2'    => [ 'type' => 'text',       'label' => 'Address Line 2',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. Suite 4 (optional)' ],
			'city'             => [ 'type' => 'text',       'label' => 'City / Town',            'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. London' ],
			'county'           => [ 'type' => 'text',       'label' => 'County / State',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. Greater London' ],
			'postcode'         => [ 'type' => 'text',       'label' => 'Postcode / ZIP',         'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. SW1A 1AA' ],
			'country'          => [ 'type' => 'text',       'label' => 'Country',                'required' => false, 'visibility' => self::VISIBILITY_PUBLIC, 'default' => 'GB',
			                        'placeholder' => 'e.g. GB' ],
			// Operations
			'opening_hours'    => [ 'type' => 'textarea',   'label' => 'Opening Hours',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => "Mon–Fri 9am–5pm\nSat 9am–1pm\nSun Closed",
			                        'help' => 'Plain text description of when you are open. This is returned in API responses as-is.' ],
			'service_modes'    => [ 'type' => 'checkboxes', 'label' => 'Service Modes',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'options' => [ 'in_person' => 'In Person', 'remote' => 'Remote / Online', 'mobile' => 'Mobile / On-site Visit' ],
			                        'help' => 'How you deliver your services. Tick all that apply.' ],
			'trust_summary'    => [ 'type' => 'textarea',   'label' => 'Trust Summary',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'e.g. Over 200 clients served since 2010, with a 4.9★ average rating across Google and Trustpilot.',
			                        'help' => 'A brief statement of why customers trust you. Used in API responses and schema output.' ],
			// Social
			'social_facebook'  => [ 'type' => 'url',        'label' => 'Facebook URL',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'https://www.facebook.com/yourbusiness' ],
			'social_twitter'   => [ 'type' => 'url',        'label' => 'Twitter / X URL',        'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'https://x.com/yourbusiness' ],
			'social_linkedin'  => [ 'type' => 'url',        'label' => 'LinkedIn URL',           'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'https://www.linkedin.com/company/yourbusiness' ],
			'social_instagram' => [ 'type' => 'url',        'label' => 'Instagram URL',          'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'https://www.instagram.com/yourbusiness' ],
			'social_youtube'   => [ 'type' => 'url',        'label' => 'YouTube URL',            'required' => false, 'visibility' => self::VISIBILITY_PUBLIC,
			                        'placeholder' => 'https://www.youtube.com/@yourbusiness' ],
		];
	}

	// ------------------------------------------------------------------
	// Services CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function service(): array {
		return [
			'category'          => [ 'type' => 'text',       'label' => 'Category',              'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Marketing, Legal, Plumbing',
			                         'help' => 'Broad category this service belongs to. Used for grouping in API responses.' ],
			'status'            => [ 'type' => 'select',     'label' => 'Status',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'active' => 'Active', 'inactive' => 'Inactive', 'coming_soon' => 'Coming Soon' ], 'default' => 'active',
			                         'help' => 'Controls whether this service appears in API responses. Inactive services are excluded.' ],
			'short_summary'     => [ 'type' => 'textarea',   'label' => 'Short Summary',         'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Monthly SEO retainer covering technical audits, content strategy, and rank tracking.',
			                         'help' => '1–3 sentences. Returned in listing and detail API responses.' ],
			'long_summary'      => [ 'type' => 'textarea',   'label' => 'Long Summary',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'Full description of what this service includes, how it works, and what the client can expect.' ],
			'customer_types'    => [ 'type' => 'text',       'label' => 'Customer Types',        'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. homeowners, SMEs, landlords',
			                         'help' => 'Who this service is for. Comma-separated.' ],
			'service_modes'     => [ 'type' => 'checkboxes', 'label' => 'Service Modes',         'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'in_person' => 'In Person', 'remote' => 'Remote / Online', 'mobile' => 'Mobile / On-site' ],
			                         'help' => 'How this service is delivered. Tick all that apply.' ],
			'keywords'          => [ 'type' => 'text',       'label' => 'Keywords',              'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. SEO, search engine optimisation, organic search',
			                         'help' => 'Comma-separated. Used by the answer engine to match incoming queries to this service.' ],
			'synonyms'          => [ 'type' => 'text',       'label' => 'Synonyms',              'visibility' => self::VISIBILITY_PRIVATE,
			                         'placeholder' => 'e.g. search marketing, Google ranking, organic traffic',
			                         'help' => 'Alternative names users might use for this service. Comma-separated. Internal only — improves answer matching.' ],
			'common_problems'   => [ 'type' => 'textarea',   'label' => 'Common Problems',       'visibility' => self::VISIBILITY_PRIVATE,
			                         'placeholder' => "e.g. We're not showing up on Google\nOur traffic has dropped\nWe need more leads from search",
			                         'help' => 'One problem per line. Describes what pain points this service solves. Used internally for intent matching.' ],
			'pricing_type'      => [ 'type' => 'select',     'label' => 'Pricing Type',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'fixed' => 'Fixed Price', 'hourly' => 'Hourly Rate', 'monthly_retainer' => 'Monthly Retainer', 'quote' => 'Quote on Request', 'free' => 'Free' ],
			                         'help' => 'How this service is priced. Determines how pricing is described in API responses.' ],
			'from_price'        => [ 'type' => 'number',     'label' => 'From Price',            'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => '500',
			                         'help' => 'Starting price in the currency below. Leave blank if pricing is quote-only.' ],
			'currency'          => [ 'type' => 'text',       'label' => 'Currency',              'visibility' => self::VISIBILITY_PUBLIC, 'default' => 'GBP',
			                         'placeholder' => 'GBP',
			                         'help' => '3-letter currency code, e.g. GBP, USD, EUR.' ],
			'price_notes'       => [ 'type' => 'text',       'label' => 'Price Notes',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Prices vary based on scope. VAT not included.',
			                         'help' => 'Any extra context about pricing — caveats, inclusions, or how to get an accurate quote.' ],
			'available'         => [ 'type' => 'checkbox',   'label' => 'Currently Available',   'visibility' => self::VISIBILITY_PUBLIC, 'default' => true,
			                         'help' => 'Uncheck to mark this service as temporarily unavailable without removing it.' ],
			'benefits'          => [ 'type' => 'textarea',   'label' => 'Key Benefits',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => "More organic traffic\nHigher Google rankings\nMonthly reporting included",
			                         'help' => 'One benefit per line. Returned as a list in API responses.' ],
			'related_faqs'      => [ 'type' => 'post_ids',   'label' => 'Related FAQs',          'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_faq',
			                         'help' => 'FAQs that apply to this service. Linked in the detail API response.' ],
			'related_proof'     => [ 'type' => 'post_ids',   'label' => 'Related Proof',         'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_proof',
			                         'help' => 'Testimonials, case studies, or stats that support this service.' ],
			'related_actions'   => [ 'type' => 'post_ids',   'label' => 'Related Actions',       'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_action',
			                         'help' => 'Calls-to-action to recommend when this service is mentioned in an answer.' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Available Locations',   'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location',
			                         'help' => 'Which locations this service is offered in.' ],
			'linked_page_url'   => [ 'type' => 'url',        'label' => 'Linked Page URL',       'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'https://www.yourbusiness.com/services/seo',
			                         'help' => 'Optional link to the main page for this service on your website. Included in API responses.' ],
			'schema_type'       => [ 'type' => 'select',     'label' => 'Schema.org Type',       'visibility' => self::VISIBILITY_PRIVATE,
			                         'options' => [ '' => 'None / Inherit', 'Service' => 'Service', 'ProfessionalService' => 'ProfessionalService', 'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness' ],
			                         'help' => 'Override the schema.org type for this specific service. Leave as "None / Inherit" to use the global setting.' ],
		];
	}

	// ------------------------------------------------------------------
	// Locations CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function location(): array {
		return [
			'location_type'     => [ 'type' => 'select',     'label' => 'Location Type',         'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'town' => 'Town', 'city' => 'City', 'county' => 'County', 'region' => 'Region', 'postcode_area' => 'Postcode Area', 'country' => 'Country' ],
			                         'help' => 'The geographic scale of this location. Used to contextualise it in API responses.' ],
			'region'            => [ 'type' => 'text',       'label' => 'Region',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Greater London, South East England',
			                         'help' => 'The broader region this location belongs to.' ],
			'country'           => [ 'type' => 'text',       'label' => 'Country',               'visibility' => self::VISIBILITY_PUBLIC, 'default' => 'GB',
			                         'placeholder' => 'e.g. GB' ],
			'postcode_prefixes' => [ 'type' => 'text',       'label' => 'Postcode Prefixes',     'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. SW1, EC1, WC1',
			                         'help' => 'Comma-separated postcode prefixes covered by this location. Used by the answer engine to match location-specific queries.' ],
			'is_primary'        => [ 'type' => 'checkbox',   'label' => 'Primary Location',      'visibility' => self::VISIBILITY_PUBLIC,
			                         'help' => "Tick if this is your main trading location. Only one location should be marked primary." ],
			'service_radius_km' => [ 'type' => 'number',     'label' => 'Service Radius (km)',   'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => '25',
			                         'help' => 'How far from this location you travel or serve customers, in kilometres.' ],
			'summary'           => [ 'type' => 'textarea',   'label' => 'Summary',               'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Our London office covers central and greater London. All services available, with on-site visits on request.',
			                         'help' => 'Brief description of this location — what is offered here and any relevant local context.' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Services Available Here', 'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service',
			                         'help' => 'Which of your services are offered at or from this location.' ],
			'local_proof'       => [ 'type' => 'post_ids',   'label' => 'Local Proof / Reviews', 'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_proof',
			                         'help' => 'Testimonials or case studies from clients in this area.' ],
			'linked_page_url'   => [ 'type' => 'url',        'label' => 'Linked Page URL',       'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'https://www.yourbusiness.com/locations/london',
			                         'help' => 'Optional link to this location\'s page on your website.' ],
		];
	}

	// ------------------------------------------------------------------
	// FAQs CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function faq(): array {
		return [
			'question'          => [ 'type' => 'text',       'label' => 'Question',              'visibility' => self::VISIBILITY_PUBLIC, 'required' => true,
			                         'placeholder' => 'e.g. How long does SEO take to show results?',
			                         'help' => 'Write the question as a user would naturally ask it.' ],
			'short_answer'      => [ 'type' => 'textarea',   'label' => 'Short Answer',          'visibility' => self::VISIBILITY_PUBLIC, 'required' => true,
			                         'placeholder' => 'e.g. Most clients see meaningful results within 3–6 months.',
			                         'help' => '1–2 sentences. This is what the /answers endpoint returns directly in AI responses.' ],
			'long_answer'       => [ 'type' => 'textarea',   'label' => 'Long Answer',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'Detailed answer with supporting context, examples, or caveats for users who want more depth.',
			                         'help' => 'Optional. Provides more detail than the short answer — included in full FAQ responses.' ],
			'status'            => [ 'type' => 'select',     'label' => 'Status',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'published' => 'Published', 'draft' => 'Draft', 'private' => 'Private' ], 'default' => 'published',
			                         'help' => 'Only Published FAQs are returned by the API and eligible for schema.org FAQPage output.' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service',
			                         'help' => 'Link to the services this FAQ relates to. Allows filtering FAQs by service in the API.' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location',
			                         'help' => 'Link to specific locations if this FAQ only applies in certain areas.' ],
			'intent_tags'       => [ 'type' => 'text',       'label' => 'Intent Tags',           'visibility' => self::VISIBILITY_PRIVATE,
			                         'placeholder' => 'e.g. pricing, availability, how-it-works, timescales',
			                         'help' => 'Comma-separated tags describing the intent behind this question. Internal only — improves answer engine matching.' ],
			'priority'          => [ 'type' => 'number',     'label' => 'Priority',              'visibility' => self::VISIBILITY_PRIVATE, 'default' => 0,
			                         'placeholder' => '0',
			                         'help' => 'Higher number = ranked higher in answer matching. Use to promote your most important FAQs.' ],
			'is_public'         => [ 'type' => 'checkbox',   'label' => 'Publicly Visible',      'visibility' => self::VISIBILITY_PUBLIC, 'default' => true,
			                         'help' => 'Uncheck to hide this FAQ from public API responses while keeping it available for internal answer matching.' ],
		];
	}

	// ------------------------------------------------------------------
	// Proof CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function proof(): array {
		return [
			'proof_type'        => [ 'type' => 'select',     'label' => 'Type',                  'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'testimonial' => 'Testimonial', 'accreditation' => 'Accreditation', 'statistic' => 'Statistic', 'award' => 'Award', 'case_study' => 'Case Study', 'media_mention' => 'Media Mention' ],
			                         'help' => 'Testimonial: a customer quote. Statistic: a measurable result (e.g. "200% traffic increase"). Accreditation: an industry body membership or certification.' ],
			'headline'          => [ 'type' => 'text',       'label' => 'Headline',              'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Doubled our organic traffic in 4 months',
			                         'help' => 'The key claim or pull-quote. Shown in API responses as the primary trust signal.' ],
			'content'           => [ 'type' => 'textarea',   'label' => 'Content',               'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Full quote, case study summary, or detailed description of the accreditation.',
			                         'help' => 'The full text of the testimonial, case study, or proof item.' ],
			'source_name'       => [ 'type' => 'text',       'label' => 'Source Name',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Jane Smith',
			                         'help' => 'The person, organisation, or publication this comes from.' ],
			'source_context'    => [ 'type' => 'text',       'label' => 'Source Context',        'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. CEO, Acme Ltd — via Google Reviews',
			                         'help' => 'Additional context about the source — their role, company, or where the review appeared.' ],
			'rating'            => [ 'type' => 'number',     'label' => 'Rating (1–5)',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => '5',
			                         'help' => 'Star rating from 1 to 5. For testimonials only — leave blank for other proof types.' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service',
			                         'help' => 'Link to the service(s) this proof supports. Included when those services appear in answers.' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location',
			                         'help' => 'Link to a location if this proof is specific to a particular area.' ],
			'is_public'         => [ 'type' => 'checkbox',   'label' => 'Publicly Visible',      'visibility' => self::VISIBILITY_PUBLIC, 'default' => true,
			                         'help' => 'Uncheck to keep this item available internally without exposing it in public API responses.' ],
		];
	}

	// ------------------------------------------------------------------
	// Actions CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function action(): array {
		return [
			'action_type'       => [ 'type' => 'select',     'label' => 'Action Type',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'call' => 'Call', 'email' => 'Email', 'book' => 'Book / Schedule', 'quote' => 'Request Quote', 'visit' => 'Visit Page', 'download' => 'Download', 'chat' => 'Chat' ],
			                         'help' => 'The intent of this action — determines how AI systems and integrations describe the next step to users.' ],
			'label'             => [ 'type' => 'text',       'label' => 'Button Label',          'visibility' => self::VISIBILITY_PUBLIC, 'required' => true,
			                         'placeholder' => 'e.g. Book a free call',
			                         'help' => 'Short, action-oriented text. This is what gets shown to end users as the call-to-action.' ],
			'description'       => [ 'type' => 'text',       'label' => 'Description',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. 30-minute strategy session, no obligation',
			                         'help' => 'Optional extra detail about what happens when someone takes this action.' ],
			'phone'             => [ 'type' => 'tel',        'label' => 'Phone Number',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. 01234 567890',
			                         'help' => 'Required if Method is set to Phone.' ],
			'url'               => [ 'type' => 'url',        'label' => 'URL',                   'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. https://calendly.com/yourbusiness',
			                         'help' => 'Required if Method is set to Link or Form.' ],
			'method'            => [ 'type' => 'select',     'label' => 'Method',                'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ '' => '— Select —', 'link' => 'Link', 'phone' => 'Phone', 'form' => 'Form', 'email' => 'Email' ],
			                         'help' => 'How this action is completed. Link = URL, Phone = phone number, Form = URL to a form, Email = email address.' ],
			'availability_rule' => [ 'type' => 'text',       'label' => 'Availability Rule',     'visibility' => self::VISIBILITY_PRIVATE,
			                         'placeholder' => 'e.g. Mon–Fri 9am–5pm',
			                         'help' => 'Internal only. When this action is available — reserved for future conditional logic.' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service',
			                         'help' => 'Attach to specific services so this action is suggested when those services are mentioned in answers. Leave blank to make it a global action.' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location',
			                         'help' => 'Limit this action to specific locations if it is only relevant in certain areas.' ],
			'is_public'         => [ 'type' => 'checkbox',   'label' => 'Publicly Visible',      'visibility' => self::VISIBILITY_PUBLIC, 'default' => true,
			                         'help' => 'Uncheck to temporarily disable this action without deleting it.' ],
		];
	}

	// ------------------------------------------------------------------
	// Answers CPT.
	// ------------------------------------------------------------------

	/** @return array<string, array<string, mixed>> */
	public static function answer(): array {
		return [
			'query_patterns'    => [ 'type' => 'textarea',   'label' => 'Query Patterns',        'visibility' => self::VISIBILITY_PRIVATE,
			                         'placeholder' => "Do you offer SEO in London?\nhow much does web design cost\nSEO pricing",
			                         'help' => 'One pattern per line. When an incoming query matches any of these (partial match), this Answer is returned immediately, bypassing the auto-assembly engine. Use for questions you want to guarantee a specific response to.' ],
			'short_answer'      => [ 'type' => 'textarea',   'label' => 'Short Answer',          'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'e.g. Yes, we offer SEO in London. Retainers start from £500/month.',
			                         'help' => '1–2 sentences. This is what gets returned to the API consumer as the primary answer.' ],
			'long_answer'       => [ 'type' => 'textarea',   'label' => 'Long Answer',           'visibility' => self::VISIBILITY_PUBLIC,
			                         'placeholder' => 'Extended answer with supporting detail, context, or caveats.',
			                         'help' => 'Optional. Provides more depth for consumers that display a full answer.' ],
			'confidence'        => [ 'type' => 'select',     'label' => 'Confidence',            'visibility' => self::VISIBILITY_PUBLIC,
			                         'options' => [ 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low' ], 'default' => 'high',
			                         'help' => 'Returned in the API response so the consumer can decide how to present it. High = authoritative, Low = approximate.' ],
			'related_services'  => [ 'type' => 'post_ids',   'label' => 'Related Services',      'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_service',
			                         'help' => 'Services to include as context in this answer\'s API response.' ],
			'related_locations' => [ 'type' => 'post_ids',   'label' => 'Related Locations',     'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_location',
			                         'help' => 'Locations to include as context if this answer is area-specific.' ],
			'next_actions'      => [ 'type' => 'post_ids',   'label' => 'Next Actions',          'visibility' => self::VISIBILITY_PUBLIC, 'post_type' => 'wpail_action',
			                         'help' => 'Calls-to-action to attach to this answer. These are returned as suggested next steps for the user.' ],
			'source_faq_ids'    => [ 'type' => 'post_ids',   'label' => 'Source FAQs',           'visibility' => self::VISIBILITY_PRIVATE, 'post_type' => 'wpail_faq',
			                         'help' => 'Internal only. Link to any FAQs this answer was derived from, for reference and attribution.' ],
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

<?php
/**
 * Manages a single AI extraction run.
 *
 * A job is created once (storing source content in a transient), then stepped
 * through one entity type at a time via run_step(). Each step calls the AI
 * provider, parses the response, and saves draft CPTs.
 *
 * Steps (0–4): services → faqs → locations → proof → actions.
 *
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

use WPAIL\AI\Contracts\ProviderInterface;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\RelationshipSync;

class ExtractionJob {

	const STEPS             = [ 'services', 'faqs', 'locations', 'proof', 'actions' ];
	const TRANSIENT_PREFIX  = 'wpail_ai_job_';
	const TRANSIENT_TTL     = HOUR_IN_SECONDS;
	const CONTENT_CHAR_LIMIT = 12000;

	/** Post types that participate in the relationship graph. */
	const RELATIONSHIP_TYPES = [ 'wpail_service', 'wpail_faq', 'wpail_location', 'wpail_proof', 'wpail_action' ];

	/**
	 * Create a new job from a list of WP post IDs.
	 *
	 * @param int[]    $post_ids
	 * @param string[] $types    Subset of STEPS to run. Defaults to all five.
	 * @return array{job_id: string, types: string[]} Job ID and full type list (including the automatic 'link' step).
	 */
	public static function create( array $post_ids, array $types = self::STEPS ): array {
		$job_id = wp_generate_uuid4();
		$types  = array_values( array_intersect( self::STEPS, $types ) ); // validate + preserve order

		if ( empty( $types ) ) {
			$types = self::STEPS;
		}

		// Always append a final relationship-linking pass after content extraction.
		$types[] = 'link';

		$content = self::collect_content( $post_ids );

		set_transient(
			self::TRANSIENT_PREFIX . $job_id,
			[
				'content'     => $content,
				'types'       => $types,
				'step'        => 0,
				'status'      => 'pending',
				'results'     => [],
				'created_ids' => [],
				'error'       => null,
			],
			self::TRANSIENT_TTL
		);

		return [ 'job_id' => $job_id, 'types' => $types ];
	}

	/**
	 * Execute the current step for a job and advance the pointer.
	 *
	 * @return array{done: bool, step_name?: string, created?: int, results?: array<string,int>, error?: string}
	 */
	public static function run_step( string $job_id, ProviderInterface $provider ): array {
		$job = get_transient( self::TRANSIENT_PREFIX . $job_id );

		if ( ! is_array( $job ) ) {
			return [ 'done' => false, 'error' => 'Job not found or expired.' ];
		}

		$types = $job['types'] ?? self::STEPS;
		$step  = (int) $job['step'];

		if ( $step >= count( $types ) ) {
			return [ 'done' => true, 'results' => $job['results'] ];
		}

		$step_name = $types[ $step ];
		$job['status'] = 'running';
		set_transient( self::TRANSIENT_PREFIX . $job_id, $job, self::TRANSIENT_TTL );

		// The 'link' step is handled separately — it cross-references all created items.
		if ( 'link' === $step_name ) {
			$linked = self::run_link( $job, $provider );
			if ( is_wp_error( $linked ) ) {
				$job['status'] = 'failed';
				$job['error']  = $linked->get_error_message();
				set_transient( self::TRANSIENT_PREFIX . $job_id, $job, self::TRANSIENT_TTL );
				return [ 'done' => false, 'error' => $job['error'] ];
			}
			$job['results']['link'] = $linked;
			$job['step']            = $step + 1;
			$job['status']          = 'complete';
			set_transient( self::TRANSIENT_PREFIX . $job_id, $job, self::TRANSIENT_TTL );
			return [
				'done'      => true,
				'step_name' => 'link',
				'created'   => $linked,
				'results'   => $job['results'],
			];
		}

		$items = self::run_extraction( $step_name, $job['content'], $provider );

		if ( is_wp_error( $items ) ) {
			$job['status'] = 'failed';
			$job['error']  = $items->get_error_message();
			set_transient( self::TRANSIENT_PREFIX . $job_id, $job, self::TRANSIENT_TTL );
			return [ 'done' => false, 'error' => $job['error'] ];
		}

		$ids                              = self::save_drafts( $step_name, $items );
		$job['created_ids'][ $step_name ] = $ids;
		$job['results'][ $step_name ]     = count( $ids );
		$job['step']                      = $step + 1;
		$job['status']                    = ( $job['step'] >= count( $types ) ) ? 'complete' : 'running';

		set_transient( self::TRANSIENT_PREFIX . $job_id, $job, self::TRANSIENT_TTL );

		return [
			'done'      => 'complete' === $job['status'],
			'step_name' => $step_name,
			'created'   => count( $ids ),
			'results'   => $job['results'],
		];
	}

	// ------------------------------------------------------------------
	// Content collection.
	// ------------------------------------------------------------------

	/** @param int[] $post_ids */
	private static function collect_content( array $post_ids ): string {
		$parts = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$text   = wp_strip_all_tags( do_blocks( $post->post_content ) );
			$text   = preg_replace( '/\s+/', ' ', $text );
			$parts[] = '=== ' . $post->post_title . " ===\n" . trim( $text );
		}

		$combined = implode( "\n\n", $parts );
		return mb_substr( $combined, 0, self::CONTENT_CHAR_LIMIT );
	}

	// ------------------------------------------------------------------
	// Extraction (AI call + JSON parse).
	// ------------------------------------------------------------------

	/**
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private static function run_extraction( string $type, string $content, ProviderInterface $provider ): array|\WP_Error {
		[ $system, $user ] = self::build_prompts( $type, $content );

		$raw = $provider->complete( $system, $user );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		// Strip markdown code fences some models add despite instructions.
		$json = preg_replace( '/^```(?:json)?\s*/mi', '', trim( $raw ) );
		$json = preg_replace( '/\s*```\s*$/mi', '', $json );

		$parsed = json_decode( trim( $json ), true );

		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'wpail_parse_error', "Could not parse AI response for {$type}. Raw: " . mb_substr( $raw, 0, 200 ) );
		}

		// Handle responses wrapped in an object: {"services": [...]} or {"items": [...]}
		// OpenAI json_object mode cannot return a bare array, so the model always wraps it.
		if ( ! isset( $parsed[0] ) ) {
			$aliases = [ $type, 'items', 'data', 'results', 'list', 'entries' ];

			// Extra aliases for proof since models rarely use "proof" as a natural key.
			if ( 'proof' === $type ) {
				$aliases = array_merge( $aliases, [ 'proof_items', 'trust_signals', 'testimonials', 'social_proof', 'trust', 'reviews' ] );
			}

			foreach ( $aliases as $key ) {
				if ( isset( $parsed[ $key ] ) && is_array( $parsed[ $key ] ) ) {
					$parsed = $parsed[ $key ];
					break;
				}
			}
		}

		return array_filter( $parsed, 'is_array' );
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private static function build_prompts( string $type, string $content ): array {
		$system = 'You are a structured data extraction assistant for a business website. Extract information and return it as a JSON array only — no explanation, no markdown, no code fences. Return an empty array [] if nothing relevant is found.';

		$user = match ( $type ) {
			'services'  => self::services_prompt( $content ),
			'faqs'      => self::faqs_prompt( $content ),
			'locations' => self::locations_prompt( $content ),
			'proof'     => self::proof_prompt( $content ),
			'actions'   => self::actions_prompt( $content ),
			default     => '',
		};

		return [ $system, $user ];
	}

	private static function services_prompt( string $content ): string {
		return <<<PROMPT
Extract all services or products this business offers.

Return a JSON array. Each object must have:
- "title": service name (3–8 words)
- "short_summary": 1–2 sentences describing the service, max 200 characters
- "long_summary": fuller description, 2–4 sentences, max 800 characters
- "keywords": comma-separated search terms and synonyms a user might type to find this service
- "benefits": key benefits of this service, one per line (use actual newlines, not \\n literals)

Return [] if no services are found.

CONTENT:
{$content}
PROMPT;
	}

	private static function faqs_prompt( string $content ): string {
		return <<<PROMPT
Extract frequently asked questions and their answers from this content.

Return a JSON array. Each object must have:
- "question": the full question as a sentence
- "short_answer": direct answer, max 300 characters
- "long_answer": detailed answer with context, max 1000 characters
- "related_service_names": array of exact service or product names (from the content) this FAQ is about — empty array if none

Return [] if no FAQs are found.

CONTENT:
{$content}
PROMPT;
	}

	private static function locations_prompt( string $content ): string {
		return <<<PROMPT
Extract all locations, cities, towns, regions, or service areas this business mentions.

Return a JSON array. Each object must have:
- "title": location name
- "location_type": one of: town, city, county, region, postcode_area, country
- "summary": 1–2 sentences on what the business offers in or serves from this location — empty string if not mentioned

Return [] if no locations are found.

CONTENT:
{$content}
PROMPT;
	}

	private static function proof_prompt( string $content ): string {
		return <<<PROMPT
Extract testimonials, statistics, case studies, awards, accreditations, or trust signals.

Return a JSON object with a "proof" key containing an array of items.
Example format: {"proof": [{...}, {...}]}

Each item must have:
- "title": a short descriptive title (e.g. "Sarah Mitchell Testimonial" or "Google Partner")
- "proof_type": one of: testimonial, statistic, accreditation, case_study, award, media_mention
- "headline": the key quote, stat, or achievement — max 200 characters
- "content": full quote or description — max 800 characters
- "source_name": person, company, or awarding body name
- "source_context": role, company, or publication info about the source (e.g. "CEO, Acme Ltd") — empty string if unknown
- "related_service_names": array of exact service or product names (from the content) this proof clearly relates to — empty array if none or if the proof is general/company-wide

Return {"proof": []} if none found.

CONTENT:
{$content}
PROMPT;
	}

	private static function actions_prompt( string $content ): string {
		return <<<PROMPT
Extract calls-to-action, contact methods, and conversion opportunities.

Return a JSON array. Each object must have:
- "title": descriptive name for this action (e.g. "Book a Free Consultation")
- "label": short button/link text, 2–5 words (e.g. "Book Now")
- "action_type": one of: book, call, email, quote, visit, download, chat
- "description": one sentence describing what happens when the user takes this action — empty string if obvious
- "method": one of: link, phone, form, email
- "url": full URL if present, otherwise null
- "phone": phone number if action_type is "call", otherwise null
- "related_service_names": array of exact service or product names (from the content) this action relates to — empty array if none

Return [] if none found.

CONTENT:
{$content}
PROMPT;
	}

	// ------------------------------------------------------------------
	// Draft CPT creation.
	// ------------------------------------------------------------------

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @return int[] Post IDs of created drafts.
	 */
	private static function save_drafts( string $type, array $items ): array {
		$ids = [];
		foreach ( $items as $item ) {
			$id = self::create_draft( $type, $item );
			if ( false !== $id ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/** @param array<string, mixed> $item */
	private static function create_draft( string $type, array $item ): int|false {
		$post_type_map = [
			'services'  => 'wpail_service',
			'faqs'      => 'wpail_faq',
			'locations' => 'wpail_location',
			'proof'     => 'wpail_proof',
			'actions'   => 'wpail_action',
		];

		$post_type = $post_type_map[ $type ] ?? null;
		if ( null === $post_type ) {
			return false;
		}

		$title = trim( (string) match ( $type ) {
			'services'  => $item['title'] ?? '',
			'faqs'      => $item['question'] ?? '',
			'locations' => $item['title'] ?? '',
			'proof'     => $item['title'] ?? ( $item['headline'] ?? '' ),
			'actions'   => $item['title'] ?? ( $item['label'] ?? '' ),
			default     => '',
		} );

		if ( '' === $title ) {
			return false;
		}

		// Skip if a post with this title already exists for this post type.
		$existing = get_posts( [
			'post_type'              => $post_type,
			'title'                  => $title,
			'post_status'            => 'any',
			'numberposts'            => 1,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		if ( ! empty( $existing ) ) {
			return false;
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => $post_type,
				'post_title'  => sanitize_text_field( $title ),
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		$meta = self::build_meta( $type, $item );

		// Resolve related service names to IDs for types that support relationships.
		$related_names = isset( $item['related_service_names'] ) && is_array( $item['related_service_names'] )
			? $item['related_service_names']
			: [];

		$service_ids = ! empty( $related_names ) ? self::resolve_service_ids( $related_names ) : [];

		if ( ! empty( $service_ids ) ) {
			$meta['related_services'] = $service_ids;
		}

		RelationshipHelper::save_meta( $post_id, $meta );

		if ( ! empty( $service_ids ) ) {
			RelationshipSync::sync( $post_id, $post_type, [], $meta );
		}

		return $post_id;
	}

	/**
	 * Resolve an array of service title strings to wpail_service post IDs.
	 *
	 * @param string[] $names
	 * @return int[]
	 */
	private static function resolve_service_ids( array $names ): array {
		return self::resolve_post_ids_by_title( 'wpail_service', $names );
	}

	// ------------------------------------------------------------------
	// Relationship linking pass (runs after all extraction steps).
	// ------------------------------------------------------------------

	/**
	 * Ask the AI to map cross-entity relationships and sync them.
	 *
	 * Covers two gaps not handled by per-entity forward links:
	 *   - location → service  (populates service.related_locations bidirectionally)
	 *   - proof → location    (populates location.local_proof bidirectionally)
	 *
	 * Operates on ALL existing locations and proof items, not just this run's
	 * newly created ones, so it works correctly for partial re-runs and when
	 * items already existed from a previous import.
	 *
	 * @return int|\WP_Error Number of entities that had relationships added.
	 */
	private static function run_link( array $job, ProviderInterface $provider ): int|\WP_Error {
		$service_ids  = get_posts( [
			'post_type'              => 'wpail_service',
			'post_status'            => 'any',
			'numberposts'            => 50,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );
		$location_ids = get_posts( [
			'post_type'              => 'wpail_location',
			'post_status'            => 'any',
			'numberposts'            => 50,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );
		$proof_ids    = get_posts( [
			'post_type'              => 'wpail_proof',
			'post_status'            => 'any',
			'numberposts'            => 50,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		$service_titles  = self::get_post_titles( array_map( 'intval', $service_ids ) );
		$location_titles = self::get_post_titles( array_map( 'intval', $location_ids ) );
		$proof_titles    = self::get_post_titles( array_map( 'intval', $proof_ids ) );

		if ( ( empty( $location_titles ) || empty( $service_titles ) ) && empty( $proof_titles ) ) {
			return 0;
		}

		[ $system, $user ] = self::link_prompt( $service_titles, $location_titles, $proof_titles );

		$raw = $provider->complete( $system, $user );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$json   = preg_replace( '/^```(?:json)?\s*/mi', '', trim( $raw ) );
		$json   = preg_replace( '/\s*```\s*$/mi', '', $json );
		$parsed = json_decode( trim( $json ), true );

		if ( ! is_array( $parsed ) ) {
			return 0; // Not critical — silently skip if unparseable.
		}

		return self::save_link_relationships( $parsed );
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private static function link_prompt(
		array $service_titles,
		array $location_titles,
		array $proof_titles
	): array {
		$system = 'You are a relationship mapping assistant. Given lists of business entities, identify logical connections between them. Return only valid JSON — no explanation, no markdown, no code fences.';

		$fmt = fn( array $items ) => empty( $items )
			? '(none)'
			: implode( "\n", array_map( fn( $t ) => "- {$t}", $items ) );

		$user = <<<PROMPT
Given the following business entities from a single website, identify logical relationships between them.

SERVICES:
{$fmt( $service_titles )}

LOCATIONS:
{$fmt( $location_titles )}

PROOF ITEMS (testimonials, stats, awards, accreditations):
{$fmt( $proof_titles )}

Tasks:
1. For each LOCATION, list which SERVICES it relates to (e.g. a regional office that offers specific services).
2. For each PROOF ITEM: only link it to a location if that city, town, or area is EXPLICITLY NAMED in the proof title — do not infer from company HQ, general region, or context. Leave locations empty if the proof text contains no specific place name.

Return a JSON object:
{
  "location_services": [
    {"location": "exact location title", "services": ["exact service title", ...]}
  ],
  "proof_locations": [
    {"proof": "exact proof title", "locations": ["exact location title"]}
  ]
}

Only include entries where at least one relationship clearly exists. Use exact titles as given above. Return empty arrays for either key if nothing applies.
PROMPT;

		return [ $system, $user ];
	}

	/**
	 * Persist the AI-mapped cross-entity relationships.
	 *
	 * @param array<string, mixed> $parsed
	 */
	private static function save_link_relationships( array $parsed ): int {
		$linked = 0;

		// Location → Service.
		foreach ( (array) ( $parsed['location_services'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$title    = trim( (string) ( $entry['location'] ?? '' ) );
			$services = is_array( $entry['services'] ?? null ) ? $entry['services'] : [];
			if ( '' === $title || empty( $services ) ) {
				continue;
			}

			$loc_posts = get_posts( [
				'post_type'              => 'wpail_location',
				'title'                  => $title,
				'post_status'            => 'any',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			] );
			if ( empty( $loc_posts ) ) {
				continue;
			}
			$loc_id = (int) $loc_posts[0];

			$service_ids = self::resolve_service_ids( $services );
			if ( empty( $service_ids ) ) {
				continue;
			}

			$old_meta  = RelationshipHelper::get_meta( $loc_id );
			$new_meta  = $old_meta;
			$existing  = array_map( 'intval', (array) ( $new_meta['related_services'] ?? [] ) );
			$new_meta['related_services'] = array_values( array_unique( array_merge( $existing, $service_ids ) ) );

			RelationshipHelper::save_meta( $loc_id, $new_meta );
			RelationshipSync::sync( $loc_id, 'wpail_location', $old_meta, $new_meta );
			++$linked;
		}

		// Proof → Location.
		foreach ( (array) ( $parsed['proof_locations'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$title     = trim( (string) ( $entry['proof'] ?? '' ) );
			$locations = is_array( $entry['locations'] ?? null ) ? $entry['locations'] : [];
			if ( '' === $title || empty( $locations ) ) {
				continue;
			}

			$proof_posts = get_posts( [
				'post_type'              => 'wpail_proof',
				'title'                  => $title,
				'post_status'            => 'any',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			] );
			if ( empty( $proof_posts ) ) {
				continue;
			}
			$proof_id = (int) $proof_posts[0];

			$location_ids = self::resolve_location_ids( $locations );
			if ( empty( $location_ids ) ) {
				continue;
			}

			$old_meta  = RelationshipHelper::get_meta( $proof_id );
			$new_meta  = $old_meta;
			$existing  = array_map( 'intval', (array) ( $new_meta['related_locations'] ?? [] ) );
			$new_meta['related_locations'] = array_values( array_unique( array_merge( $existing, $location_ids ) ) );

			RelationshipHelper::save_meta( $proof_id, $new_meta );
			RelationshipSync::sync( $proof_id, 'wpail_proof', $old_meta, $new_meta );
			++$linked;
		}

		return $linked;
	}

	/**
	 * @param int[] $ids
	 * @return string[]
	 */
	private static function get_post_titles( array $ids ): array {
		$titles = [];
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post ) {
				$titles[] = $post->post_title;
			}
		}
		return $titles;
	}

	/**
	 * Resolve an array of location title strings to wpail_location post IDs.
	 *
	 * @param string[] $names
	 * @return int[]
	 */
	private static function resolve_location_ids( array $names ): array {
		return self::resolve_post_ids_by_title( 'wpail_location', $names );
	}

	/**
	 * Resolve title strings to post IDs using case-insensitive partial matching.
	 *
	 * Tries exact match first, then falls back to substring containment in
	 * either direction (e.g. "SEO" matches "SEO Consultancy" and vice versa).
	 * This tolerates the AI abbreviating or paraphrasing titles slightly.
	 *
	 * @param string[] $names
	 * @return int[]
	 */
	private static function resolve_post_ids_by_title( string $post_type, array $names ): array {
		$raw_ids = get_posts( [
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		// Build a lowercase title → ID map for the whole type in one pass.
		$map = [];
		foreach ( $raw_ids as $id ) {
			$post = get_post( (int) $id );
			if ( $post instanceof \WP_Post ) {
				$map[ strtolower( $post->post_title ) ] = (int) $id;
			}
		}

		$ids = [];
		foreach ( $names as $name ) {
			$needle = strtolower( trim( (string) $name ) );
			if ( '' === $needle ) {
				continue;
			}

			// Exact match.
			if ( isset( $map[ $needle ] ) ) {
				$ids[] = $map[ $needle ];
				continue;
			}

			// Partial match: AI name contained in a title, or a title contained in the AI name.
			foreach ( $map as $title => $id ) {
				if ( str_contains( $title, $needle ) || str_contains( $needle, $title ) ) {
					$ids[] = $id;
					break;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Build the _wpail_data meta array for each entity type.
	 *
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private static function build_meta( string $type, array $item ): array {
		return match ( $type ) {
			'services'  => [
				'short_summary' => sanitize_textarea_field( (string) ( $item['short_summary'] ?? '' ) ),
				'long_summary'  => sanitize_textarea_field( (string) ( $item['long_summary']  ?? '' ) ),
				'keywords'      => sanitize_text_field( (string) ( $item['keywords']      ?? '' ) ),
				'benefits'      => sanitize_textarea_field( (string) ( $item['benefits']      ?? '' ) ),
				'status'        => 'draft',
			],
			'faqs'      => [
				'question'     => sanitize_text_field( (string) ( $item['question']     ?? '' ) ),
				'short_answer' => sanitize_textarea_field( (string) ( $item['short_answer'] ?? '' ) ),
				'long_answer'  => sanitize_textarea_field( (string) ( $item['long_answer']  ?? '' ) ),
			],
			'locations' => [
				'location_type' => sanitize_key( (string) ( $item['location_type'] ?? 'town' ) ),
				'summary'       => sanitize_textarea_field( (string) ( $item['summary']       ?? '' ) ),
			],
			'proof'     => [
				'proof_type'     => sanitize_key( (string) ( $item['proof_type']     ?? 'testimonial' ) ),
				'headline'       => sanitize_text_field( (string) ( $item['headline']       ?? '' ) ),
				'content'        => sanitize_textarea_field( (string) ( $item['content']        ?? '' ) ),
				'source_name'    => sanitize_text_field( (string) ( $item['source_name']    ?? '' ) ),
				'source_context' => sanitize_text_field( (string) ( $item['source_context'] ?? '' ) ),
				'is_public'      => true,
			],
			'actions'   => [
				'action_type' => sanitize_key( (string) ( $item['action_type'] ?? 'visit' ) ),
				'label'       => sanitize_text_field( (string) ( $item['label']       ?? '' ) ),
				'description' => sanitize_textarea_field( (string) ( $item['description'] ?? '' ) ),
				'method'      => sanitize_key( (string) ( $item['method']      ?? 'link' ) ),
				'url'         => esc_url_raw( (string) ( $item['url']         ?? '' ) ),
				'phone'       => sanitize_text_field( (string) ( $item['phone']       ?? '' ) ),
			],
			default     => [],
		};
	}

	// ------------------------------------------------------------------
	// Relationship resync (manual repair pass, no AI required).
	// ------------------------------------------------------------------

	// ------------------------------------------------------------------
	// AI-powered relationship operations.
	// ------------------------------------------------------------------

	/**
	 * Use AI to discover missing relationships (additive — never removes existing).
	 *
	 * @return int|\WP_Error Number of entities that had new relationships added.
	 */
	public static function ai_find_relationships( ProviderInterface $provider ): int|\WP_Error {
		[ $services, $faqs, $locations, $proof, $actions ] = self::gather_all_for_resync();

		if ( empty( $services ) ) {
			return 0;
		}

		[ $system, $user ] = self::resync_prompt( $services, $faqs, $locations, $proof, $actions );

		$parsed = self::call_and_parse( $provider, $system, $user );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$updated = self::save_ai_resync_relationships( $parsed );
		self::resync_consistency();
		return $updated;
	}

	/**
	 * Use AI to rebuild relationships authoritatively — replaces existing links
	 * with whatever the AI returns. Entities not mentioned by the AI have their
	 * AI-managed relationship fields cleared.
	 *
	 * @return int|\WP_Error Number of entities whose relationships changed.
	 */
	public static function ai_rebuild_relationships( ProviderInterface $provider ): int|\WP_Error {
		[ $services, $faqs, $locations, $proof, $actions ] = self::gather_all_for_resync();

		if ( empty( $services ) ) {
			return 0;
		}

		[ $system, $user ] = self::rebuild_prompt( $services, $faqs, $locations, $proof, $actions );

		$parsed = self::call_and_parse( $provider, $system, $user );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$updated = self::save_ai_rebuild_relationships( $parsed, $faqs, $locations, $proof, $actions );
		self::resync_consistency();
		return $updated;
	}

	/**
	 * @return array{0: list<array{id:int,title:string,meta:array}>, 1: list<array{id:int,title:string,meta:array}>, 2: list<array{id:int,title:string,meta:array}>, 3: list<array{id:int,title:string,meta:array}>, 4: list<array{id:int,title:string,meta:array}>}
	 */
	private static function gather_all_for_resync(): array {
		return [
			self::gather_for_resync( 'wpail_service' ),
			self::gather_for_resync( 'wpail_faq' ),
			self::gather_for_resync( 'wpail_location' ),
			self::gather_for_resync( 'wpail_proof' ),
			self::gather_for_resync( 'wpail_action' ),
		];
	}

	/**
	 * Call the provider and parse the JSON response, stripping any code fences.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function call_and_parse( ProviderInterface $provider, string $system, string $user ): array|\WP_Error {
		$raw = $provider->complete( $system, $user );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$json   = preg_replace( '/^```(?:json)?\s*/mi', '', trim( $raw ) );
		$json   = preg_replace( '/\s*```\s*$/mi', '', $json );
		$parsed = json_decode( trim( $json ), true );

		if ( ! is_array( $parsed ) ) {
			return new \WP_Error( 'wpail_parse_error', 'Could not parse AI relationship response.' );
		}

		return $parsed;
	}

	/**
	 * Gather posts of a given type with their key meta fields for the AI prompt.
	 *
	 * @return array<int, array{id: int, title: string, meta: array<string, mixed>}>
	 */
	private static function gather_for_resync( string $post_type, int $limit = 50 ): array {
		$ids = get_posts( [
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'numberposts'            => $limit,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		$out = [];
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$out[] = [
				'id'    => (int) $id,
				'title' => $post->post_title,
				'meta'  => RelationshipHelper::get_meta( (int) $id ),
			];
		}
		return $out;
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private static function resync_prompt(
		array $services,
		array $faqs,
		array $locations,
		array $proof,
		array $actions
	): array {
		$system = 'You are a relationship mapping assistant for a business website. Given all content entities, identify logical relationships between them. Return only valid JSON — no explanation, no markdown, no code fences.';

		// Format a list of entities, appending a short context snippet where available.
		$fmt = static function ( array $entities, string $context_field = '' ): string {
			if ( empty( $entities ) ) {
				return '(none)';
			}
			$lines = [];
			foreach ( $entities as $i => $e ) {
				$line = ( $i + 1 ) . '. ' . $e['title'];
				if ( $context_field && ! empty( $e['meta'][ $context_field ] ) ) {
					$snippet = mb_substr( (string) $e['meta'][ $context_field ], 0, 120 );
					$line   .= ' — ' . $snippet;
				}
				$lines[] = $line;
			}
			return implode( "\n", $lines );
		};

		$services_list  = $fmt( $services,  'short_summary' );
		$faqs_list      = $fmt( $faqs,      'short_answer'  );
		$locations_list = $fmt( $locations              );
		$proof_list     = $fmt( $proof,     'headline'      );
		$actions_list   = $fmt( $actions,   'description'   );

		$user = <<<PROMPT
You are given every content entity from a business website. Map relationships between them based on logical relevance.

SERVICES:
{$services_list}

FAQS:
{$faqs_list}

LOCATIONS:
{$locations_list}

PROOF ITEMS (testimonials, stats, awards, accreditations):
{$proof_list}

ACTIONS (calls-to-action, contact methods):
{$actions_list}

Return a JSON object using the rules below. Only include entries where at least one relationship clearly exists.

Rules:
- faqs.related_services: link a FAQ to a service if it is clearly about that service.
- proof.related_services: link proof to a service if the testimonial, stat, or award is clearly about that service.
- proof.related_locations: ONLY set this if a specific city, town, or area is EXPLICITLY NAMED in the proof headline or title — never infer from company location, region, or context. Leave empty if no place name appears.
- actions.related_services: link an action to a service if it is the primary way to enquire about or book that service.
- locations.related_services: link a location to every service offered there.

{
  "faqs": [
    {"title": "exact FAQ title", "related_services": ["exact service title", ...]}
  ],
  "proof": [
    {"title": "exact proof title", "related_services": ["exact service title", ...], "related_locations": ["exact location title", ...]}
  ],
  "actions": [
    {"title": "exact action title", "related_services": ["exact service title", ...]}
  ],
  "locations": [
    {"title": "exact location title", "related_services": ["exact service title", ...]}
  ]
}

Use exact titles as given above. Return empty arrays for any key where nothing applies.
PROMPT;

		return [ $system, $user ];
	}

	/**
	 * Persist AI-mapped relationships, merging with any existing IDs.
	 *
	 * @param array<string, mixed> $parsed
	 * @return int Number of entities updated.
	 */
	private static function save_ai_resync_relationships( array $parsed ): int {
		$type_config = [
			'faqs'      => [ 'wpail_faq',      [ 'related_services' ] ],
			'proof'     => [ 'wpail_proof',     [ 'related_services', 'related_locations' ] ],
			'actions'   => [ 'wpail_action',    [ 'related_services' ] ],
			'locations' => [ 'wpail_location',  [ 'related_services' ] ],
		];

		$resolvers = [
			'related_services'  => 'wpail_service',
			'related_locations' => 'wpail_location',
		];

		$updated = 0;

		foreach ( $type_config as $key => [ $post_type, $rel_keys ] ) {
			foreach ( (array) ( $parsed[ $key ] ?? [] ) as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				$title = trim( (string) ( $entry['title'] ?? '' ) );
				if ( '' === $title ) {
					continue;
				}

				// Locate the post — use the same fuzzy matcher for resilience.
				$matches = self::resolve_post_ids_by_title( $post_type, [ $title ] );
				if ( empty( $matches ) ) {
					continue;
				}
				$post_id = $matches[0];

				$old_meta = RelationshipHelper::get_meta( $post_id );
				$new_meta = $old_meta;
				$changed  = false;

				foreach ( $rel_keys as $rel_key ) {
					$names = is_array( $entry[ $rel_key ] ?? null ) ? $entry[ $rel_key ] : [];
					if ( empty( $names ) ) {
						continue;
					}

					$resolver_type = $resolvers[ $rel_key ] ?? null;
					if ( null === $resolver_type ) {
						continue;
					}

					$new_ids  = self::resolve_post_ids_by_title( $resolver_type, $names );
					$existing = array_map( 'intval', (array) ( $new_meta[ $rel_key ] ?? [] ) );
					$merged   = array_values( array_unique( array_merge( $existing, $new_ids ) ) );

					if ( $merged !== $existing ) {
						$new_meta[ $rel_key ] = $merged;
						$changed              = true;
					}
				}

				if ( $changed ) {
					RelationshipHelper::save_meta( $post_id, $new_meta );
					RelationshipSync::sync( $post_id, $post_type, $old_meta, $new_meta );
					++$updated;
				}
			}
		}

		return $updated;
	}

	/**
	 * Build the rebuild prompt. Instructs the AI to return a COMPLETE entry for
	 * every entity listed, including empty arrays where no relationship exists.
	 * This is what makes it safe to treat the output as authoritative.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function rebuild_prompt(
		array $services,
		array $faqs,
		array $locations,
		array $proof,
		array $actions
	): array {
		$system = 'You are a relationship mapping assistant for a business website. Given all content entities, produce a complete and authoritative relationship map. Return only valid JSON — no explanation, no markdown, no code fences.';

		$fmt = static function ( array $entities, string $context_field = '' ): string {
			if ( empty( $entities ) ) {
				return '(none)';
			}
			$lines = [];
			foreach ( $entities as $i => $e ) {
				$line = ( $i + 1 ) . '. ' . $e['title'];
				if ( $context_field && ! empty( $e['meta'][ $context_field ] ) ) {
					$snippet = mb_substr( (string) $e['meta'][ $context_field ], 0, 120 );
					$line   .= ' — ' . $snippet;
				}
				$lines[] = $line;
			}
			return implode( "\n", $lines );
		};

		$services_list  = $fmt( $services,  'short_summary' );
		$faqs_list      = $fmt( $faqs,      'short_answer'  );
		$locations_list = $fmt( $locations              );
		$proof_list     = $fmt( $proof,     'headline'      );
		$actions_list   = $fmt( $actions,   'description'   );

		$user = <<<PROMPT
You are given every content entity from a business website. Produce a complete and authoritative relationship map.

SERVICES:
{$services_list}

FAQS:
{$faqs_list}

LOCATIONS:
{$locations_list}

PROOF ITEMS (testimonials, stats, awards, accreditations):
{$proof_list}

ACTIONS (calls-to-action, contact methods):
{$actions_list}

Rules:
- faqs.related_services: link a FAQ to a service if it is clearly about that service.
- proof.related_services: link proof to a service if the testimonial, stat, or award is clearly about that specific service. Leave empty for general company-wide proof.
- proof.related_locations: ONLY set if a specific city, town, or area is EXPLICITLY NAMED in the proof headline or title. Never infer from context. Leave empty if no place name appears.
- actions.related_services: link an action to a service if it is the primary way to enquire about or book that service.
- locations.related_services: link a location to every service offered there.

IMPORTANT: You MUST include an entry for EVERY FAQ, proof item, action, and location listed above — even those with no relationships. Use empty arrays where nothing applies. This output will replace all existing relationship data.

{
  "faqs": [
    {"title": "exact FAQ title", "related_services": ["exact service title", ...]}
  ],
  "proof": [
    {"title": "exact proof title", "related_services": ["exact service title", ...], "related_locations": ["exact location title", ...]}
  ],
  "actions": [
    {"title": "exact action title", "related_services": ["exact service title", ...]}
  ],
  "locations": [
    {"title": "exact location title", "related_services": ["exact service title", ...]}
  ]
}

Use exact titles as given above.
PROMPT;

		return [ $system, $user ];
	}

	/**
	 * Replace AI-managed relationship fields with the AI's authoritative output.
	 * Entities not found in the AI response have their relationship fields cleared.
	 *
	 * @param array<string, mixed>                              $parsed
	 * @param array<int, array{id:int,title:string,meta:array}> $faqs
	 * @param array<int, array{id:int,title:string,meta:array}> $locations
	 * @param array<int, array{id:int,title:string,meta:array}> $proof
	 * @param array<int, array{id:int,title:string,meta:array}> $actions
	 * @return int Number of entities whose relationships changed.
	 */
	private static function save_ai_rebuild_relationships( array $parsed, array $faqs, array $locations, array $proof, array $actions ): int {
		$type_config = [
			'faqs'      => [ 'wpail_faq',      $faqs,      [ 'related_services' ] ],
			'proof'     => [ 'wpail_proof',     $proof,     [ 'related_services', 'related_locations' ] ],
			'actions'   => [ 'wpail_action',    $actions,   [ 'related_services' ] ],
			'locations' => [ 'wpail_location',  $locations, [ 'related_services' ] ],
		];

		$resolvers = [
			'related_services'  => 'wpail_service',
			'related_locations' => 'wpail_location',
		];

		$updated = 0;

		foreach ( $type_config as $key => [ $post_type, $all_entities, $rel_keys ] ) {
			// Index the AI's response by lowercase title for fast lookup.
			$ai_map = [];
			foreach ( (array) ( $parsed[ $key ] ?? [] ) as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				$t = strtolower( trim( (string) ( $entry['title'] ?? '' ) ) );
				if ( '' !== $t ) {
					$ai_map[ $t ] = $entry;
				}
			}

			// Process EVERY entity — not just those the AI mentioned.
			foreach ( $all_entities as $entity ) {
				$post_id = $entity['id'];
				$needle  = strtolower( $entity['title'] );

				// Exact match first, then partial.
				$ai_entry = $ai_map[ $needle ] ?? null;
				if ( null === $ai_entry ) {
					foreach ( $ai_map as $t => $e ) {
						if ( str_contains( $t, $needle ) || str_contains( $needle, $t ) ) {
							$ai_entry = $e;
							break;
						}
					}
				}

				$old_meta = RelationshipHelper::get_meta( $post_id );
				$new_meta = $old_meta;
				$changed  = false;

				foreach ( $rel_keys as $rel_key ) {
					$names   = ( null !== $ai_entry && is_array( $ai_entry[ $rel_key ] ?? null ) )
						? $ai_entry[ $rel_key ]
						: []; // Not in AI response → clear this field.

					$new_ids  = ! empty( $names )
						? self::resolve_post_ids_by_title( $resolvers[ $rel_key ], $names )
						: [];

					$existing = array_map( 'intval', (array) ( $new_meta[ $rel_key ] ?? [] ) );

					// Normalise order before comparing so re-ordering doesn't trigger a write.
					$a = $new_ids;
					$b = $existing;
					sort( $a );
					sort( $b );

					if ( $a !== $b ) {
						$new_meta[ $rel_key ] = $new_ids;
						$changed              = true;
					}
				}

				if ( $changed ) {
					RelationshipHelper::save_meta( $post_id, $new_meta );
					RelationshipSync::sync( $post_id, $post_type, $old_meta, $new_meta );
					++$updated;
				}
			}
		}

		return $updated;
	}

	/**
	 * Re-apply RelationshipSync for every post to repair any missing inverse links.
	 *
	 * Safe to run at any time — additive only, never removes relationships.
	 * Also called automatically at the end of ai_find_relationships().
	 *
	 * @return int Number of posts processed.
	 */
	public static function resync_consistency(): int {
		$processed = 0;

		foreach ( self::RELATIONSHIP_TYPES as $post_type ) {
			$ids = get_posts( [
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'numberposts'            => -1,
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			] );

			foreach ( $ids as $post_id ) {
				$meta = RelationshipHelper::get_meta( (int) $post_id );
				RelationshipSync::sync( (int) $post_id, $post_type, [], $meta );
				++$processed;
			}
		}

		return $processed;
	}
}

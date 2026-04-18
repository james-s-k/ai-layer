<?php
/**
 * REST endpoint: /answers
 *
 * GET /wp-json/ai-layer/v1/answers?query=...
 * GET /wp-json/ai-layer/v1/answers?query=...&service={id}&location={id}
 *
 * Answer assembly pipeline (rules-based, v1):
 *
 * 1. Check for manual (authored) answer matching query patterns — highest priority.
 * 2. Detect service intent via keyword/synonym matching.
 * 3. Detect location intent via name/region/postcode matching.
 * 4. Find matching FAQs by query terms + service/location filter.
 * 5. Assemble answer from best FAQ + service + location.
 * 6. Attach relevant actions.
 * 7. Attach proof as supporting data.
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Models\AnswerModel;
use WPAIL\Models\FaqModel;
use WPAIL\Models\ServiceModel;
use WPAIL\Models\LocationModel;
use WPAIL\Repositories\AnswerRepository;
use WPAIL\Repositories\ServiceRepository;
use WPAIL\Repositories\LocationRepository;
use WPAIL\Repositories\FaqRepository;
use WPAIL\Repositories\ActionRepository;
use WPAIL\Repositories\ProofRepository;
use WPAIL\Support\RelationshipHelper;
use WPAIL\Support\Sanitizer;
use WPAIL\Licensing\Features;
use WPAIL\Licensing\License;

class AnswersController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/answers', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				// get_item_permissions_check runs before the callback and returns
				// a 402 WP_Error in free mode. `query` is intentionally NOT marked
				// required here — WP validates required params before permission
				// checks, which would produce a misleading 400 for free users.
				// The empty-query guard lives inside get_item for pro mode.
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'query'    => [
						'description'       => 'Natural language query.',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'service'  => [
						'description'       => 'Optional service ID hint.',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'location' => [
						'description'       => 'Optional location ID hint.',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );
	}

	/**
	 * Returns a 402 WP_Error in free mode so API consumers get a meaningful,
	 * actionable response rather than a generic 403. Pro mode passes through.
	 *
	 * @param \WP_REST_Request $request
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ): true|\WP_Error {
		if ( ! Features::answers_enabled() ) {
			return new \WP_Error(
				'upgrade_required',
				__( 'The /answers endpoint requires AI Layer Pro.', 'ai-ready-layer' ),
				[
					'status'      => 402,
					'upgrade_url' => License::upgrade_url(),
				]
			);
		}

		return true;
	}

	public function get_item( $request ) {
		// Guard for missing/empty query — enforced here (not via route args) so
		// the permission_callback Pro gate fires first for free-tier callers.
		$query = trim( (string) $request->get_param( 'query' ) );

		if ( '' === $query ) {
			return $this->bad_request( 'A query parameter is required.' );
		}

		$terms = $this->tokenise( $query );

		// --- Step 1: Manual answer match ---
		$answer_repo = new AnswerRepository();
		$manual      = $answer_repo->find_by_query( $query );

		if ( $manual !== null ) {
			return $this->build_response( $manual );
		}

		// --- Step 2: Detect service ---
		$service_id   = (int) $request->get_param( 'service' );
		$service_repo = new ServiceRepository();
		$service      = null;

		if ( $service_id > 0 ) {
			$service = $service_repo->find_by_id( $service_id );
		}

		if ( null === $service ) {
			$matched_services = $service_repo->find_by_terms( $terms );
			$service          = $matched_services[0] ?? null;
		}

		// --- Step 3: Detect location ---
		$location_id   = (int) $request->get_param( 'location' );
		$location_repo = new LocationRepository();
		$location      = null;

		if ( $location_id > 0 ) {
			$location = $location_repo->find_by_id( $location_id );
		}

		if ( null === $location ) {
			// Check each term for a location match.
			foreach ( $terms as $term ) {
				$matched = $location_repo->find_by_term( $term );
				if ( ! empty( $matched ) ) {
					$location = $matched[0];
					break;
				}
			}
		}

		// --- Step 4: Find matching FAQs ---
		$faq_repo = new FaqRepository();
		$faqs     = $faq_repo->find_by_terms( $terms );

		// Filter FAQs by service/location if detected.
		if ( $service !== null ) {
			$service_faqs = array_filter(
				$faqs,
				fn( FaqModel $f ) => in_array( $service->id, $f->related_service_ids, true )
			);
			if ( ! empty( $service_faqs ) ) {
				$faqs = array_values( $service_faqs );
			}
		}

		$best_faq = $faqs[0] ?? null;

		// --- Step 5: Assemble dynamic answer ---
		if ( $best_faq !== null ) {
			$answer = $this->assemble_from_faq( $best_faq, $service, $location );
		} elseif ( $service !== null ) {
			$answer = $this->assemble_from_service( $service, $location );
		} else {
			return $this->not_found( 'No matching answer found for this query.' );
		}

		return $this->build_response( $answer, $service, $location, $best_faq );
	}

	// ------------------------------------------------------------------
	// Assembly helpers.
	// ------------------------------------------------------------------

	/**
	 * Build an AnswerModel from a matched FAQ.
	 */
	private function assemble_from_faq( FaqModel $faq, ?ServiceModel $service, ?LocationModel $location ): AnswerModel {
		$action_repo    = new ActionRepository();
		$actions        = $service ? $action_repo->find_by_service( $service->id ) : $action_repo->get_global();
		$action_ids     = array_map( fn( $a ) => $a->id, $actions );

		return new AnswerModel(
			short_answer:         $faq->short_answer,
			long_answer:          $faq->long_answer ?: $faq->short_answer,
			confidence:           'high',
			source:               'faq',
			related_service_ids:  $service  ? [ $service->id ]  : [],
			related_location_ids: $location ? [ $location->id ] : [],
			next_action_ids:      array_slice( $action_ids, 0, 3 ),
			source_faq_ids:       [ $faq->id ],
		);
	}

	/**
	 * Build a fallback answer from service data when no FAQ matches.
	 */
	private function assemble_from_service( ServiceModel $service, ?LocationModel $location ): AnswerModel {
		$action_repo = new ActionRepository();
		$actions     = $action_repo->find_by_service( $service->id );
		$action_ids  = array_map( fn( $a ) => $a->id, $actions );

		$short = $service->short_summary ?: "We offer {$service->name}.";

		$long = $service->long_summary ?: $short;
		if ( $location !== null ) {
			$long .= " Available in {$location->name}.";
		}

		return new AnswerModel(
			short_answer:         $short,
			long_answer:          $long,
			confidence:           'medium',
			source:               'dynamic',
			related_service_ids:  [ $service->id ],
			related_location_ids: $location ? [ $location->id ] : [],
			next_action_ids:      array_slice( $action_ids, 0, 3 ),
		);
	}

	/**
	 * Build the REST response from an AnswerModel.
	 */
	private function build_response(
		AnswerModel   $answer,
		?ServiceModel  $service  = null,
		?LocationModel $location = null,
		?FaqModel      $faq      = null
	): \WP_REST_Response {
		$action_repo = new ActionRepository();
		$proof_repo  = new ProofRepository();

		$actions = array_map(
			fn( int $id ) => $action_repo->find_by_id( $id )?->to_summary_array(),
			$answer->next_action_ids
		);
		$actions = array_values( array_filter( $actions ) );

		$source_faqs = array_map(
			fn( int $id ) => ( new \WPAIL\Repositories\FaqRepository() )->find_by_id( $id )?->to_summary_array(),
			$answer->source_faq_ids
		);
		$source_faqs = array_values( array_filter( $source_faqs ) );

		// Proof supporting data — pull from service if available.
		$supporting_data = [];
		if ( $service !== null ) {
			$proof_items = $proof_repo->find_by_service( $service->id );
			foreach ( array_slice( $proof_items, 0, 3 ) as $p ) {
				$supporting_data[] = $p->to_summary_array();
			}
		}

		$service_arr  = $service  ? $service->to_summary_array()  : null;
		$location_arr = $location ? $location->to_summary_array() : null;

		$data = $answer->to_public_array(
			services:       $service_arr  ? [ $service_arr ]  : [],
			locations:      $location_arr ? [ $location_arr ] : [],
			actions:        $actions,
			source_faqs:    $source_faqs,
			supporting_data: $supporting_data,
		);

		return $this->success( $data );
	}

	/**
	 * Tokenise a query into searchable terms.
	 * Strips stop words and returns unique, lowercased tokens of 3+ chars.
	 *
	 * @return array<string>
	 */
	private function tokenise( string $query ): array {
		$stop_words = [
			'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any',
			'can', 'was', 'has', 'had', 'did', 'its', 'our', 'who', 'what',
			'this', 'that', 'with', 'your', 'they', 'from', 'have', 'will',
			'been', 'does', 'into', 'more', 'also', 'how', 'much', 'about',
			'is', 'in', 'do', 'to', 'of', 'it', 'be', 'as', 'at', 'by',
			'we', 'or', 'an', 'if', 'up', 'so', 'no', 'me', 'my',
		];

		$words = preg_split( '/\s+/', strtolower( $query ) ) ?: [];
		$words = array_filter( $words, fn( string $w ) => strlen( $w ) >= 3 && ! in_array( $w, $stop_words, true ) );

		return array_values( array_unique( $words ) );
	}
}

<?php
/**
 * Rules-based answer assembly engine.
 *
 * Extracted from AnswersController so both the REST endpoint and MCP abilities
 * can query it without duplicating the pipeline logic.
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

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

class AnswerEngine {

	/**
	 * Run the full answer pipeline.
	 *
	 * @return array<string,mixed>|null Assembled answer data, or null when nothing matches.
	 */
	public function query( string $query, int $service_id = 0, int $location_id = 0 ): ?array {
		if ( '' === trim( $query ) ) {
			return null;
		}

		$terms = $this->tokenise( $query );

		// Step 1: manual answer match.
		$manual = ( new AnswerRepository() )->find_by_query( $query );
		if ( $manual !== null ) {
			return $this->build_data( $manual );
		}

		// Step 2: detect service.
		$service_repo = new ServiceRepository();
		$service      = null;
		if ( $service_id > 0 ) {
			$service = $service_repo->find_by_id( $service_id );
		}
		if ( null === $service ) {
			$matched = $service_repo->find_by_terms( $terms );
			$service = $matched[0] ?? null;
		}

		// Step 3: detect location.
		$location_repo = new LocationRepository();
		$location      = null;
		if ( $location_id > 0 ) {
			$location = $location_repo->find_by_id( $location_id );
		}
		if ( null === $location ) {
			foreach ( $terms as $term ) {
				$matched = $location_repo->find_by_term( $term );
				if ( ! empty( $matched ) ) {
					$location = $matched[0];
					break;
				}
			}
		}

		// Step 4: find matching FAQs; narrow by service when possible.
		$faq_repo = new FaqRepository();
		$faqs     = $faq_repo->find_by_terms( $terms );
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

		// Step 5: assemble answer model.
		if ( $best_faq !== null ) {
			$answer = $this->from_faq( $best_faq, $service, $location );
		} elseif ( $service !== null ) {
			$answer = $this->from_service( $service, $location );
		} else {
			return null;
		}

		return $this->build_data( $answer, $service, $location, $best_faq );
	}

	// ------------------------------------------------------------------
	// Assembly helpers.
	// ------------------------------------------------------------------

	private function from_faq( FaqModel $faq, ?ServiceModel $service, ?LocationModel $location ): AnswerModel {
		$action_repo = new ActionRepository();
		$actions     = $service ? $action_repo->find_by_service( $service->id ) : $action_repo->get_global();
		$action_ids  = array_map( fn( $a ) => $a->id, $actions );

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

	private function from_service( ServiceModel $service, ?LocationModel $location ): AnswerModel {
		$action_repo = new ActionRepository();
		$actions     = $action_repo->find_by_service( $service->id );
		$action_ids  = array_map( fn( $a ) => $a->id, $actions );

		$short = $service->short_summary ?: "We offer {$service->name}.";
		$long  = $service->long_summary ?: $short;
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
	 * @return array<string,mixed>
	 */
	private function build_data(
		AnswerModel    $answer,
		?ServiceModel  $service  = null,
		?LocationModel $location = null,
		?FaqModel      $faq      = null
	): array {
		// Engine-discovered answers pass a single service/location; manual answers
		// may declare many — resolve all of them so the full context is surfaced.
		$services = $service ? [ $service ] : [];
		if ( empty( $services ) && ! empty( $answer->related_service_ids ) ) {
			$sr = new ServiceRepository();
			foreach ( $answer->related_service_ids as $sid ) {
				$s = $sr->find_by_id( $sid );
				if ( $s !== null ) {
					$services[] = $s;
				}
			}
		}

		$locations = $location ? [ $location ] : [];
		if ( empty( $locations ) && ! empty( $answer->related_location_ids ) ) {
			$lr = new LocationRepository();
			foreach ( $answer->related_location_ids as $lid ) {
				$l = $lr->find_by_id( $lid );
				if ( $l !== null ) {
					$locations[] = $l;
				}
			}
		}

		$action_repo = new ActionRepository();
		$proof_repo  = new ProofRepository();
		$faq_repo    = new FaqRepository();

		$actions = array_values( array_filter( array_map(
			fn( int $id ) => $action_repo->find_by_id( $id )?->to_summary_array(),
			$answer->next_action_ids
		) ) );

		$source_faqs = array_values( array_filter( array_map(
			fn( int $id ) => $faq_repo->find_by_id( $id )?->to_summary_array(),
			$answer->source_faq_ids
		) ) );

		$supporting_data = [];
		$seen_proof_ids  = [];
		foreach ( $services as $svc ) {
			foreach ( array_slice( $proof_repo->find_by_service( $svc->id ), 0, 3 ) as $p ) {
				if ( ! isset( $seen_proof_ids[ $p->id ] ) ) {
					$seen_proof_ids[ $p->id ] = true;
					$supporting_data[]        = $p->to_summary_array();
				}
			}
		}

		return $answer->to_public_array(
			services:        array_map( fn( ServiceModel $s ) => $s->to_summary_array(), $services ),
			locations:       array_map( fn( LocationModel $l ) => $l->to_summary_array(), $locations ),
			actions:         $actions,
			source_faqs:     $source_faqs,
			supporting_data: $supporting_data,
		);
	}

	/**
	 * Tokenise a query into searchable terms, stripping common stop words.
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
		$words = array_map( fn( string $w ) => trim( $w, '.,!?;:\'"()[]{}' ), $words );
		$words = array_filter( $words, fn( string $w ) => strlen( $w ) >= 3 && ! in_array( $w, $stop_words, true ) );

		return array_values( array_unique( $words ) );
	}
}

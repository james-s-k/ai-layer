<?php
/**
 * Generates the llms.txt file content from canonical plugin data.
 *
 * @package WPAIL\LLMsTxt
 */

declare(strict_types=1);

namespace WPAIL\LLMsTxt;

use WPAIL\Repositories\BusinessRepository;
use WPAIL\Licensing\Features;

class Generator {

	private BusinessRepository $business_repo;

	public function __construct() {
		$this->business_repo = new BusinessRepository();
	}

	public function generate(): string {
		$opts    = LLMsTxtSettings::get_all();
		$profile = $this->business_repo->get();
		$base    = rtrim( rest_url( WPAIL_REST_NS ), '/' );

		$lines = [];

		// H1 — business name.
		$name    = ! empty( $profile->name ) ? $profile->name : get_bloginfo( 'name' );
		$lines[] = "# {$name}";
		$lines[] = '';

		// Blockquote — short summary.
		$summary = ! empty( $profile->short_summary ) ? $profile->short_summary : get_bloginfo( 'description' );
		if ( $summary ) {
			$lines[] = "> {$summary}";
			$lines[] = '';
		}

		// Optional custom intro paragraph.
		$custom_intro = trim( $opts['custom_intro'] );
		if ( $custom_intro !== '' ) {
			$lines[] = $custom_intro;
			$lines[] = '';
		}

		// AI Layer endpoints section.
		if ( $opts['include_endpoints'] ) {
			$lines[] = '## AI Layer Structured Endpoints';
			$lines[] = '';
			$lines[] = 'Structured, machine-readable business data is available at the following endpoints:';
			$lines[] = '';
			$lines[] = "- [Business Profile]({$base}/profile): Business name, contact details, and description.";
			$lines[] = "- [Services]({$base}/services): Services and products offered.";
			$lines[] = "- [Locations]({$base}/locations): Locations and service areas.";
			$lines[] = "- [FAQs]({$base}/faqs): Frequently asked questions and answers.";
			$lines[] = "- [Proof & Trust]({$base}/proof): Testimonials, case studies, and accreditations.";
			$lines[] = "- [Actions]({$base}/actions): Recommended next steps and calls to action.";

			if ( $opts['include_answers'] && Features::answers_enabled() ) {
				$lines[] = "- [Answers]({$base}/answers?query=...): Natural language question answering.";
			}

			$lines[] = '';
		}

		// Optional key pages section.
		if ( $opts['include_pages'] ) {
			$page_lines = $this->parse_page_lines( $opts['custom_pages'] );
			if ( ! empty( $page_lines ) ) {
				$lines[] = '## Key Pages';
				$lines[] = '';
				foreach ( $page_lines as $page_line ) {
					$lines[] = "- {$page_line}";
				}
				$lines[] = '';
			}
		}

		// Notes section.
		$lines[] = '## Notes';
		$lines[] = '';
		$lines[] = 'This site exposes structured business data via AI Layer for machine-readable access by AI systems, agents, and search tools.';

		return implode( "\n", $lines ) . "\n";
	}

	/** @return array<string> */
	private function parse_page_lines( string $raw ): array {
		if ( trim( $raw ) === '' ) {
			return [];
		}
		return array_values( array_filter(
			array_map( 'trim', explode( "\n", $raw ) ),
			fn( string $line ) => $line !== ''
		) );
	}
}

<?php
/**
 * Outputs FAQPage JSON-LD schema.
 *
 * Only outputs public, published FAQs.
 *
 * @package WPAIL\Schema
 */

declare(strict_types=1);

namespace WPAIL\Schema;

use WPAIL\Repositories\FaqRepository;

class FaqPageSchema {

	public function output(): void {
		$repo = new FaqRepository();
		$faqs = $repo->get_all( public_only: true );

		if ( empty( $faqs ) ) {
			return;
		}

		$entities = array_map( fn( $faq ) => [
			'@type'          => 'Question',
			'name'           => $faq->question,
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => $faq->short_answer,
			],
		], $faqs );

		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}

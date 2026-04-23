<?php
/**
 * Generates the ai.txt file content from settings.
 *
 * Output format is robots.txt-inspired:
 *
 *   User-agent: *
 *   Allow: /
 *
 *   Training: disallow
 *   Attribution: required
 *
 *   User-agent: GPTBot
 *   Disallow: /
 *   Training: disallow
 *
 * @package WPAIL\AiTxt
 */

declare(strict_types=1);

namespace WPAIL\AiTxt;

class AiTxtGenerator {

	public function generate(): string {
		$s     = AiTxtSettings::get_all();
		$lines = [];

		// Global block.
		$lines[] = 'User-agent: *';
		$lines[] = $s['allow_crawling'] ? 'Allow: /' : 'Disallow: /';

		// Training and attribution directives.
		$lines[] = '';
		$lines[] = $s['allow_training'] ? 'Training: allow' : 'Training: disallow';
		if ( $s['require_attribution'] ) {
			$lines[] = 'Attribution: required';
		}

		// Agent-specific blocks — each block fully overrides the global * block for that agent.
		foreach ( $s['agents'] as $agent ) {
			$name = trim( (string) ( $agent['name'] ?? '' ) );
			if ( $name === '' ) {
				continue;
			}
			$allow                = (bool) ( $agent['allow'] ?? true );
			$allow_training       = (bool) ( $agent['allow_training'] ?? false );
			$require_attribution  = (bool) ( $agent['require_attribution'] ?? false );

			$lines[] = '';
			$lines[] = 'User-agent: ' . $name;
			$lines[] = $allow ? 'Allow: /' : 'Disallow: /';
			$lines[] = $allow_training ? 'Training: allow' : 'Training: disallow';
			if ( $require_attribution ) {
				$lines[] = 'Attribution: required';
			}
		}

		return implode( "\n", $lines ) . "\n";
	}
}

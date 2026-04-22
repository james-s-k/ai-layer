<?php
/**
 * Extracts business profile data from core WordPress settings.
 *
 * @package WPAIL\Setup\Sources
 */

declare(strict_types=1);

namespace WPAIL\Setup\Sources;

class WordPressSource {

	public function is_available(): bool {
		return true;
	}

	public function label(): string {
		return 'WordPress';
	}

	public function description(): string {
		return 'Site title, tagline, admin email, and site URL from Settings › General.';
	}

	/**
	 * @return array<string, array{value: string, source: string}>
	 */
	public function extract_profile(): array {
		$suggestions = [];

		$name = get_bloginfo( 'name' );
		if ( $name ) {
			$suggestions['name'] = [
				'value'  => $name,
				'source' => 'WordPress › Settings › Site Title',
			];
		}

		$tagline = get_bloginfo( 'description' );
		if ( $tagline ) {
			$suggestions['short_summary'] = [
				'value'  => $tagline,
				'source' => 'WordPress › Settings › Tagline',
			];
		}

		$email = get_option( 'admin_email' );
		if ( $email ) {
			$suggestions['email'] = [
				'value'  => $email,
				'source' => 'WordPress › Settings › Admin Email',
			];
		}

		$url = get_bloginfo( 'url' );
		if ( $url ) {
			$suggestions['website'] = [
				'value'  => $url,
				'source' => 'WordPress › Settings › Site URL',
			];
		}

		return $suggestions;
	}
}

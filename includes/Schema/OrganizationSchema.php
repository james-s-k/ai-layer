<?php
/**
 * Outputs Organization / LocalBusiness JSON-LD schema.
 *
 * Mapped cleanly from the canonical BusinessModel.
 * Only outputs fields that are actually populated to keep the output clean.
 *
 * @package WPAIL\Schema
 */

declare(strict_types=1);

namespace WPAIL\Schema;

use WPAIL\Admin\SettingsPage;
use WPAIL\Repositories\BusinessRepository;

class OrganizationSchema {

	public function output(): void {
		$repo    = new BusinessRepository();
		$profile = $repo->get();

		if ( '' === $profile->name ) {
			return;
		}

		$schema_type = SettingsPage::get( SettingsPage::SETTING_SCHEMA_ORG_TYPE, 'LocalBusiness' );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => $schema_type,
			'name'     => $profile->name,
		];

		if ( $profile->legal_name ) {
			$schema['legalName'] = $profile->legal_name;
		}

		if ( $profile->short_summary ) {
			$schema['description'] = $profile->short_summary;
		}

		if ( $profile->website ) {
			$schema['url'] = $profile->website;
		}

		if ( $profile->phone ) {
			$schema['telephone'] = $profile->phone;
		}

		if ( $profile->email ) {
			$schema['email'] = $profile->email;
		}

		if ( $profile->founded_year ) {
			$schema['foundingDate'] = (string) $profile->founded_year;
		}

		// Address.
		if ( $profile->city || $profile->address_line1 ) {
			$address = [
				'@type'           => 'PostalAddress',
				'addressLocality' => $profile->city,
				'addressRegion'   => $profile->county,
				'postalCode'      => $profile->postcode,
				'addressCountry'  => $profile->country,
			];

			if ( $profile->address_line1 ) {
				$address['streetAddress'] = trim( $profile->address_line1 . ' ' . $profile->address_line2 );
			}

			$schema['address'] = array_filter( $address, fn( $v ) => '' !== (string) $v );
		}

		// Social profiles.
		$social = array_filter( [
			$profile->social_facebook,
			$profile->social_twitter,
			$profile->social_linkedin,
			$profile->social_instagram,
			$profile->social_youtube,
		] );

		if ( ! empty( $social ) ) {
			$schema['sameAs'] = array_values( $social );
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}

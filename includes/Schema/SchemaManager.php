<?php
/**
 * Schema output manager.
 *
 * Conditionally outputs JSON-LD based on Settings.
 * Defers to integration classes when Yoast or Rank Math is active.
 *
 * @package WPAIL\Schema
 */

declare(strict_types=1);

namespace WPAIL\Schema;

use WPAIL\Admin\SettingsPage;

class SchemaManager {

	public function register(): void {
		add_action( 'wp_head', [ $this, 'maybe_output_schema' ], 90 );
	}

	public function maybe_output_schema(): void {
		// Respect the settings toggle.
		if ( ! SettingsPage::get( SettingsPage::SETTING_SCHEMA_ENABLED, false ) ) {
			return;
		}

		// Only output on front page / homepage for the org schema.
		if ( is_front_page() || is_home() ) {
			( new OrganizationSchema() )->output();
		}

		// FAQPage schema — respect the target-pages setting.
		if ( SettingsPage::get( SettingsPage::SETTING_SCHEMA_FAQ_ENABLED, false ) ) {
			$mode     = SettingsPage::get( SettingsPage::SETTING_SCHEMA_FAQ_PAGES_MODE, 'all' );
			$page_ids = array_map( 'intval', (array) SettingsPage::get( SettingsPage::SETTING_SCHEMA_FAQ_PAGE_IDS, [] ) );

			$should_output = ( 'all' === $mode ) || ( ! empty( $page_ids ) && is_page( $page_ids ) );

			if ( $should_output ) {
				( new FaqPageSchema() )->output();
			}
		}
	}
}

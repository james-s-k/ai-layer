<?php
/**
 * Enqueues admin CSS and JS.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue( string $hook ): void {
		// Only load on our pages and CPT edit screens.
		if ( ! $this->is_wpail_page( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'wpail-admin',
			WPAIL_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WPAIL_VERSION
		);

		wp_enqueue_script(
			'wpail-admin',
			WPAIL_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WPAIL_VERSION,
			true
		);
	}

	private function is_wpail_page( string $hook ): bool {
		// Our settings / profile pages.
		$wpail_pages = [
			'wpail_dashboard',
			'wpail_business_profile',
			'wpail_setup_wizard',
			'wpail_settings',
			'wpail_llmstxt',
			'wpail_aitxt',
			'wpail_answers_upgrade',
			'wpail_answer_test',
			'wpail_help',
		];
		foreach ( $wpail_pages as $page ) {
			if ( str_contains( $hook, $page ) ) {
				return true;
			}
		}

		// CPT edit screens.
		global $post_type;
		if ( isset( $post_type ) && str_starts_with( $post_type, 'wpail_' ) ) {
			return true;
		}

		return false;
	}
}

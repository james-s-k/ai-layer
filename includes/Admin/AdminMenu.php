<?php
/**
 * Registers the top-level admin menu and sub-menus.
 *
 * CPTs are attached to this menu via 'show_in_menu' => 'wpail_dashboard'.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Licensing\Features;

class AdminMenu {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menus' ] );
		add_action( 'admin_menu', [ $this, 'order_wpail_submenu' ], 9999 );
		add_filter( 'post_updated_messages', [ $this, 'post_updated_messages' ] );
	}

	public function add_menus(): void {
		// Top-level menu — renders the Overview page.
		add_menu_page(
			__( 'AI Layer',             'ai-ready-layer' ),
			__( 'AI Layer',             'ai-ready-layer' ),
			'manage_options',
			'wpail_dashboard',
			[ OverviewPage::class, 'render' ],
			'dashicons-networking',
			25
		);

		// Overview sub-menu (mirrors the top-level so the label reads "Overview" not "AI Layer").
		add_submenu_page(
			'wpail_dashboard',
			__( 'Overview',             'ai-ready-layer' ),
			__( 'Overview',             'ai-ready-layer' ),
			'manage_options',
			'wpail_dashboard',
			[ OverviewPage::class, 'render' ]
		);

		// Business Profile sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Business Profile',     'ai-ready-layer' ),
			__( 'Business Profile',     'ai-ready-layer' ),
			'manage_options',
			'wpail_business_profile',
			[ BusinessProfilePage::class, 'render' ]
		);

		// Setup Wizard sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Setup Wizard',         'ai-ready-layer' ),
			__( 'Setup Wizard',         'ai-ready-layer' ),
			'manage_options',
			'wpail_setup_wizard',
			[ SetupWizardPage::class, 'render' ]
		);

		// Settings sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Settings',             'ai-ready-layer' ),
			__( 'Settings',             'ai-ready-layer' ),
			'manage_options',
			'wpail_settings',
			[ SettingsPage::class, 'render' ]
		);

		// llms.txt sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'llms.txt',             'ai-ready-layer' ),
			__( 'llms.txt',             'ai-ready-layer' ),
			'manage_options',
			'wpail_llmstxt',
			[ LLMsTxtPage::class, 'render' ]
		);

		// In free: add a locked Answers placeholder so users can discover the
		// feature and reach the upgrade page. In pro the real CPT menu item is
		// registered automatically by WordPress via show_in_menu.
		if ( ! Features::answers_enabled() ) {
			add_submenu_page(
				'wpail_dashboard',
				__( 'Answers — Pro Feature', 'ai-ready-layer' ),
				// Translators: ★ is a visual Pro indicator, not translatable punctuation.
				__( 'Answers ★', 'ai-ready-layer' ),
				'manage_options',
				'wpail_answers_upgrade',
				[ UpgradePage::class, 'render_answers' ]
			);
		}
	}

	/**
	 * CPT sub-menus register on admin_menu before our add_menus callback, so they
	 * appear above Overview and Business Profile. Force order:
	 * Overview → Business Profile → middle (CPTs, Answers) → Settings → llms.txt
	 */
	public function order_wpail_submenu(): void {
		global $submenu;

		if ( empty( $submenu['wpail_dashboard'] ) || ! is_array( $submenu['wpail_dashboard'] ) ) {
			return;
		}

		$overview = [];
		$profile  = [];
		$wizard   = [];
		$middle   = [];
		$settings = [];
		$llmstxt  = [];

		foreach ( $submenu['wpail_dashboard'] as $item ) {
			if ( ! isset( $item[2] ) ) {
				$middle[] = $item;
				continue;
			}
			if ( 'wpail_dashboard' === $item[2] ) {
				$overview[] = $item;
			} elseif ( 'wpail_business_profile' === $item[2] ) {
				$profile[] = $item;
			} elseif ( 'wpail_setup_wizard' === $item[2] ) {
				$wizard[] = $item;
			} elseif ( 'wpail_settings' === $item[2] ) {
				$settings[] = $item;
			} elseif ( 'wpail_llmstxt' === $item[2] ) {
				$llmstxt[] = $item;
			} else {
				$middle[] = $item;
			}
		}

		if ( empty( $overview ) ) {
			return;
		}

		$submenu['wpail_dashboard'] = array_merge( $overview, $wizard, $profile, $middle, $settings, $llmstxt );
	}

	/**
	 * Customise post updated messages for our CPTs.
	 *
	 * @param array<string, array<int, string>> $messages
	 * @return array<string, array<int, string>>
	 */
	public function post_updated_messages( array $messages ): array {
		$cpts = [
			'wpail_service'  => [ __( 'Service saved.',  'ai-ready-layer' ), __( 'Service updated.', 'ai-ready-layer' ) ],
			'wpail_location' => [ __( 'Location saved.', 'ai-ready-layer' ), __( 'Location updated.','ai-ready-layer' ) ],
			'wpail_faq'      => [ __( 'FAQ saved.',      'ai-ready-layer' ), __( 'FAQ updated.',     'ai-ready-layer' ) ],
			'wpail_proof'    => [ __( 'Proof item saved.','ai-ready-layer' ), __( 'Proof updated.',   'ai-ready-layer' ) ],
			'wpail_action'   => [ __( 'Action saved.',   'ai-ready-layer' ), __( 'Action updated.',  'ai-ready-layer' ) ],
			'wpail_answer'   => [ __( 'Answer saved.',   'ai-ready-layer' ), __( 'Answer updated.',  'ai-ready-layer' ) ],
		];

		foreach ( $cpts as $post_type => [ $created, $updated ] ) {
			$messages[ $post_type ] = array_fill( 0, 11, '' );
			$messages[ $post_type ][1] = $updated;
			$messages[ $post_type ][6] = $created;
		}

		return $messages;
	}
}

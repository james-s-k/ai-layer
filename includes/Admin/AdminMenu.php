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
use WPAIL\Admin\AiImportPage;
use WPAIL\Admin\AiTxtPage;
use WPAIL\Admin\AnswerTestPage;
use WPAIL\Admin\HelpPage;
use WPAIL\Admin\AnalyticsPage;

class AdminMenu {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menus' ] );
		add_action( 'admin_menu', [ $this, 'order_wpail_submenu' ], 9999 );
		add_filter( 'post_updated_messages', [ $this, 'post_updated_messages' ] );
	}

	public function add_menus(): void {
		// Top-level menu — renders the Overview page.
		add_menu_page(
			__( 'AI Layer',             'ai-layer' ),
			__( 'AI Layer',             'ai-layer' ),
			'manage_options',
			'wpail_dashboard',
			[ OverviewPage::class, 'render' ],
			'dashicons-networking',
			25
		);

		// Overview sub-menu (mirrors the top-level so the label reads "Overview" not "AI Layer").
		add_submenu_page(
			'wpail_dashboard',
			__( 'Overview',             'ai-layer' ),
			__( 'Overview',             'ai-layer' ),
			'manage_options',
			'wpail_dashboard',
			[ OverviewPage::class, 'render' ]
		);

		// Business Profile sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Business Profile',     'ai-layer' ),
			__( 'Business Profile',     'ai-layer' ),
			'manage_options',
			'wpail_business_profile',
			[ BusinessProfilePage::class, 'render' ]
		);

		// Setup Wizard sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Setup Wizard',         'ai-layer' ),
			__( 'Setup Wizard',         'ai-layer' ),
			'manage_options',
			'wpail_setup_wizard',
			[ SetupWizardPage::class, 'render' ]
		);

		// AI Import sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'AI Import',            'ai-layer' ),
			__( 'AI Import',            'ai-layer' ),
			'manage_options',
			'wpail_ai_import',
			[ AiImportPage::class, 'render' ]
		);

		// Settings sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Settings',             'ai-layer' ),
			__( 'Settings',             'ai-layer' ),
			'manage_options',
			'wpail_settings',
			[ SettingsPage::class, 'render' ]
		);

		// llms.txt sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'llms.txt',             'ai-layer' ),
			__( 'llms.txt',             'ai-layer' ),
			'manage_options',
			'wpail_llmstxt',
			[ LLMsTxtPage::class, 'render' ]
		);

		// AI.txt sub-menu.
		add_submenu_page(
			'wpail_dashboard',
			__( 'ai.txt (Beta)',         'ai-layer' ),
			__( 'ai.txt (Beta)',         'ai-layer' ),
			'manage_options',
			'wpail_aitxt',
			[ AiTxtPage::class, 'render' ]
		);

		// Answer Engine test console.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Test Answer Engine',    'ai-layer' ),
			__( 'Test Answer Engine',    'ai-layer' ),
			'edit_posts',
			'wpail_answer_test',
			[ AnswerTestPage::class, 'render' ]
		);

		// Analytics dashboard.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Analytics',            'ai-layer' ),
			__( 'Analytics',            'ai-layer' ),
			'manage_options',
			'wpail_analytics',
			[ AnalyticsPage::class, 'render' ]
		);

		// Help & documentation.
		add_submenu_page(
			'wpail_dashboard',
			__( 'Help & Docs',           'ai-layer' ),
			__( 'Help & Docs',           'ai-layer' ),
			'manage_options',
			'wpail_help',
			[ HelpPage::class, 'render' ]
		);

		// In free: add a locked Answers placeholder so users can discover the
		// feature and reach the upgrade page. In pro the real CPT menu item is
		// registered automatically by WordPress via show_in_menu.
		if ( ! Features::answers_enabled() ) {
			add_submenu_page(
				'wpail_dashboard',
				__( 'Answers — Pro Feature', 'ai-layer' ),
				// Translators: ★ is a visual Pro indicator, not translatable punctuation.
				__( 'Answers ★', 'ai-layer' ),
				'manage_options',
				'wpail_answers_upgrade',
				[ UpgradePage::class, 'render_answers' ]
			);
		}
	}

	/**
	 * CPT sub-menus register on admin_menu before our add_menus callback, so they
	 * appear above Overview and Business Profile. Force order:
	 * Overview → Wizard → Profile → middle (CPTs, Answers) → Settings → llms.txt → AI.txt
	 */
	public function order_wpail_submenu(): void {
		global $submenu;

		if ( empty( $submenu['wpail_dashboard'] ) || ! is_array( $submenu['wpail_dashboard'] ) ) {
			return;
		}

		$overview   = [];
		$analytics  = [];
		$profile    = [];
		$wizard     = [];
		$aiimport   = [];
		$middle     = [];
		$settings   = [];
		$llmstxt    = [];
		$aitxt      = [];
		$answertest = [];
		$help       = [];

		foreach ( $submenu['wpail_dashboard'] as $item ) {
			if ( ! isset( $item[2] ) ) {
				$middle[] = $item;
				continue;
			}
			if ( 'wpail_dashboard' === $item[2] ) {
				$overview[] = $item;
			} elseif ( 'wpail_analytics' === $item[2] ) {
				$analytics[] = $item;
			} elseif ( 'wpail_business_profile' === $item[2] ) {
				$profile[] = $item;
			} elseif ( 'wpail_setup_wizard' === $item[2] ) {
				$wizard[] = $item;
			} elseif ( 'wpail_ai_import' === $item[2] ) {
				$aiimport[] = $item;
			} elseif ( 'wpail_settings' === $item[2] ) {
				$settings[] = $item;
			} elseif ( 'wpail_llmstxt' === $item[2] ) {
				$llmstxt[] = $item;
			} elseif ( 'wpail_aitxt' === $item[2] ) {
				$aitxt[] = $item;
			} elseif ( 'wpail_answer_test' === $item[2] ) {
				$answertest[] = $item;
			} elseif ( 'wpail_help' === $item[2] ) {
				$help[] = $item;
			} else {
				$middle[] = $item;
			}
		}

		if ( empty( $overview ) ) {
			return;
		}

		$submenu['wpail_dashboard'] = array_merge( $overview, $wizard, $aiimport, $settings, $profile, $middle, $llmstxt, $aitxt, $analytics, $answertest, $help );
	}

	/**
	 * Customise post updated messages for our CPTs.
	 *
	 * @param array<string, array<int, string>> $messages
	 * @return array<string, array<int, string>>
	 */
	public function post_updated_messages( array $messages ): array {
		$cpts = [
			'wpail_service'  => [ __( 'Service saved.',  'ai-layer' ), __( 'Service updated.', 'ai-layer' ) ],
			'wpail_location' => [ __( 'Location saved.', 'ai-layer' ), __( 'Location updated.','ai-layer' ) ],
			'wpail_faq'      => [ __( 'FAQ saved.',      'ai-layer' ), __( 'FAQ updated.',     'ai-layer' ) ],
			'wpail_proof'    => [ __( 'Proof item saved.','ai-layer' ), __( 'Proof updated.',   'ai-layer' ) ],
			'wpail_action'   => [ __( 'Action saved.',   'ai-layer' ), __( 'Action updated.',  'ai-layer' ) ],
			'wpail_answer'   => [ __( 'Answer saved.',   'ai-layer' ), __( 'Answer updated.',  'ai-layer' ) ],
		];

		foreach ( $cpts as $post_type => [ $created, $updated ] ) {
			$messages[ $post_type ] = array_fill( 0, 11, '' );
			$messages[ $post_type ][1] = $updated;
			$messages[ $post_type ][6] = $created;
		}

		return $messages;
	}
}

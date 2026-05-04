<?php
/**
 * Main plugin bootstrap and service locator.
 *
 * @package WPAIL\Core
 */

declare(strict_types=1);

namespace WPAIL\Core;

use WPAIL\Admin\AdminMenu;
use WPAIL\Admin\Assets;
use WPAIL\Admin\BusinessProfilePage;
use WPAIL\Admin\LLMsTxtPage;
use WPAIL\Admin\AiTxtPage;
use WPAIL\Admin\SetupWizardPage;
use WPAIL\Admin\SettingsPage;
use WPAIL\Admin\MetaBoxes\ServiceMetaBox;
use WPAIL\Admin\MetaBoxes\LocationMetaBox;
use WPAIL\Admin\MetaBoxes\FaqMetaBox;
use WPAIL\Admin\MetaBoxes\ProofMetaBox;
use WPAIL\Admin\MetaBoxes\ActionMetaBox;
use WPAIL\Admin\MetaBoxes\AnswerMetaBox;
use WPAIL\Licensing\Features;
use WPAIL\PostTypes\ServicePostType;
use WPAIL\PostTypes\LocationPostType;
use WPAIL\PostTypes\FaqPostType;
use WPAIL\PostTypes\ProofPostType;
use WPAIL\PostTypes\ActionPostType;
use WPAIL\PostTypes\AnswerPostType;
use WPAIL\Abilities\AbilitiesRegistrar;
use WPAIL\Rest\RestRegistrar;
use WPAIL\Schema\SchemaManager;
use WPAIL\Integrations\YoastIntegration;
use WPAIL\Integrations\RankMathIntegration;
use WPAIL\Head\AiDiscoveryLinks;
use WPAIL\LLMsTxt\LLMsTxtController;
use WPAIL\WellKnown\AiLayerController;
use WPAIL\AiTxt\AiTxtController;
use WPAIL\Shortcodes\AnswerConsoleShortcode;
use WPAIL\Analytics\AnalyticsLogger;
use WPAIL\Analytics\AnalyticsCleanup;
use WPAIL\Analytics\AnalyticsTable;
use WPAIL\Admin\AnalyticsPage;

/**
 * Central plugin class. Lightweight service locator.
 * One instance per request; services are instantiated once and cached.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	/** @var array<string, object> Registered service instances. */
	private array $services = [];

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all plugin subsystems in dependency order.
	 */
	public function boot(): void {
		$this->load_textdomain();
		$this->register_post_types();
		$this->register_admin();
		$this->register_rest();
		$this->register_abilities();
		$this->register_schema();
		$this->register_integrations();
		$this->register_llmstxt();
		$this->register_wellknown();
		$this->register_aitxt();
		$this->register_head_links();
		$this->register_shortcodes();
		$this->register_analytics();
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'ai-layer',
			false,
			dirname( WPAIL_PLUGIN_BASE ) . '/languages'
		);
	}

	private function register_post_types(): void {
		( new ServicePostType() )->register();
		( new LocationPostType() )->register();
		( new FaqPostType() )->register();
		( new ProofPostType() )->register();
		( new ActionPostType() )->register();
		( new AnswerPostType() )->register();

		// Flush rewrite rules on the first request after visibility settings change.
		// Priority 11 ensures CPTs have already registered with the new settings (priority 10).
		add_action( 'init', [ $this, 'maybe_flush_rewrites' ], 11 );
	}

	public function maybe_flush_rewrites(): void {
		if ( get_transient( 'wpail_flush_rewrite' ) ) {
			delete_transient( 'wpail_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	private function register_admin(): void {
		if ( ! is_admin() ) {
			return;
		}

		( new AdminMenu() )->register();
		( new BusinessProfilePage() )->register();
		( new SetupWizardPage() )->register();
		( new SettingsPage() )->register();
		( new Assets() )->register();

		( new ServiceMetaBox() )->register();
		( new LocationMetaBox() )->register();
		( new FaqMetaBox() )->register();
		( new ProofMetaBox() )->register();
		( new ActionMetaBox() )->register();

		// AnswerMetaBox is Pro-only — the CPT itself always registers for data
		// safety, but the edit screen meta box is only wired up in Pro.
		if ( Features::answers_enabled() ) {
			( new AnswerMetaBox() )->register();
		}

		( new LLMsTxtPage() )->register();
		( new AiTxtPage() )->register();
	}

	private function register_rest(): void {
		( new RestRegistrar() )->register();
	}

	private function register_abilities(): void {
		( new AbilitiesRegistrar() )->register();
	}

	private function register_schema(): void {
		( new SchemaManager() )->register();
	}

	private function register_integrations(): void {
		( new YoastIntegration() )->register();
		( new RankMathIntegration() )->register();
	}

	private function register_llmstxt(): void {
		( new LLMsTxtController() )->register();
	}

	private function register_wellknown(): void {
		( new AiLayerController() )->register();
	}

	private function register_aitxt(): void {
		( new AiTxtController() )->register();
	}

	private function register_head_links(): void {
		( new AiDiscoveryLinks() )->register();
	}

	private function register_shortcodes(): void {
		( new AnswerConsoleShortcode() )->register();
	}

	private function register_analytics(): void {
		// Ensure the table exists (handles upgrades where activate() wasn't re-run).
		AnalyticsTable::install();
		( new AnalyticsLogger() )->register();
		( new AnalyticsCleanup() )->register();
	}

	// -------------------------------------------------------------------
	// Service locator helpers.
	// -------------------------------------------------------------------

	public function bind( string $key, object $service ): void {
		$this->services[ $key ] = $service;
	}

	public function make( string $key ): ?object {
		return $this->services[ $key ] ?? null;
	}
}

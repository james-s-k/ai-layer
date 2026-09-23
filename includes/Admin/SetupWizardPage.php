<?php
/**
 * Setup Wizard admin page.
 *
 * Guides the user through pre-populating their AI Layer data from existing
 * WordPress settings and active plugins. Fully revisitable — no one-time gate.
 *
 * Steps:
 *   scan        — detect available sources, show summary of what was found
 *   profile     — review and selectively apply Business Profile suggestions
 *   woocommerce — enable /products endpoint (shown only when WooCommerce is active)
 *   discovery   — endpoint discovery mode, signals, llms.txt, and AI.txt
 *   ai          — configure AI provider (Connectors API or local keys) and model
 *   done        — choose AI import or manual entity setup (configuration only — not full onboarding)
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\AI\AiSettings;
use WPAIL\AI\ConnectorBridge;
use WPAIL\Setup\Extractor;
use WPAIL\Setup\SetupProgress;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\Sanitizer;
use WPAIL\Licensing\Features;
use WPAIL\LLMsTxt\ConflictDetector;
use WPAIL\LLMsTxt\LLMsTxtSettings;
use WPAIL\LLMsTxt\LLMsTxtController;
use WPAIL\AiTxt\AiTxtSettings;
use WPAIL\AiTxt\AiTxtController;
use WPAIL\WellKnown\AiLayerController;

class SetupWizardPage {

	const STEP_SCAN        = 'scan';
	const STEP_PROFILE     = 'profile';
	const STEP_WOOCOMMERCE = 'woocommerce';
	const STEP_DISCOVERY   = 'discovery';
	const STEP_AI_FILES    = 'ai_files';
	const STEP_AI          = 'ai';
	const STEP_DONE        = 'done';

	const NONCE_PROFILE     = 'wpail_wizard_profile';
	const NONCE_WOOCOMMERCE = 'wpail_wizard_woo';
	const NONCE_DISCOVERY   = 'wpail_wizard_discovery';
	const NONCE_AI          = 'wpail_wizard_ai';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	// ------------------------------------------------------------------
	// Form handlers.
	// ------------------------------------------------------------------

	public function handle_save(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) )
			: '';
		if ( 'POST' !== $method ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Each handler verifies its own nonce.
		$action = sanitize_key( wp_unslash( $_POST['wpail_wizard_action'] ?? '' ) );

		if ( 'apply_profile' === $action ) {
			$this->handle_profile_save();
		} elseif ( 'enable_products' === $action ) {
			$this->handle_enable_products();
		} elseif ( 'save_discovery' === $action ) {
			$this->handle_discovery_save();
		} elseif ( 'save_ai' === $action ) {
			$this->handle_ai_save();
		}
	}

	private function handle_profile_save(): void {
		if ( ! isset( $_POST['wpail_wizard_profile_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['wpail_wizard_profile_nonce'] ) ),
			self::NONCE_PROFILE
		) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = get_option( WPAIL_OPT_BUSINESS, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$fields     = FieldDefinitions::business();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above via wpail_wizard_profile_nonce.
		$to_apply   = array_keys( array_map( 'sanitize_key', (array) wp_unslash( $_POST['wpail_apply'] ?? [] ) ) );
		$raw_values = (array) wp_unslash( $_POST['wpail_suggestions'] ?? [] );

		foreach ( $to_apply as $key ) {
			if ( ! isset( $fields[ $key ] ) || ! isset( $raw_values[ $key ] ) ) {
				continue;
			}
			$type             = $fields[ $key ]['type'];
			$current[ $key ]  = Sanitizer::sanitize_by_type( $raw_values[ $key ], $type );
		}

		update_option( WPAIL_OPT_BUSINESS, $current );

		$extractor = new Extractor();
		$next_step = $extractor->has_woocommerce_products() ? self::STEP_WOOCOMMERCE : self::STEP_DISCOVERY;

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'wpail_setup_wizard', 'step' => $next_step, 'updated' => 'profile' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_enable_products(): void {
		if ( ! isset( $_POST['wpail_wizard_woo_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['wpail_wizard_woo_nonce'] ) ),
			self::NONCE_WOOCOMMERCE
		) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['wpail_enable_products'] ) ) {
			$settings = get_option( WPAIL_OPT_SETTINGS, [] );
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}
			$settings[ \WPAIL\Admin\SettingsPage::SETTING_PRODUCTS_ENABLED ] = true;
			update_option( WPAIL_OPT_SETTINGS, $settings );
		}

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DISCOVERY ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_discovery_save(): void {
		if ( ! isset( $_POST['wpail_wizard_discovery_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['wpail_wizard_discovery_nonce'] ) ),
			self::NONCE_DISCOVERY
		) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Discovery mode + head links (main settings option).
		$settings = get_option( WPAIL_OPT_SETTINGS, [] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}
		$mode = sanitize_key( $_POST['ai_discovery_mode'] ?? '' );
		$settings[ SettingsPage::SETTING_AI_DISCOVERY_MODE ] = in_array(
			$mode,
			[ SettingsPage::AI_DISCOVERY_WELL_KNOWN, SettingsPage::AI_DISCOVERY_LLMSTXT ],
			true
		) ? $mode : SettingsPage::AI_DISCOVERY_WELL_KNOWN;
		$settings[ SettingsPage::SETTING_HEAD_LINKS_ENABLED ]       = ! empty( $_POST['head_links_enabled'] );
		$settings[ SettingsPage::SETTING_ROBOTS_INJECTION_ENABLED ] = ! empty( $_POST['robots_injection_enabled'] );
		$settings[ SettingsPage::SETTING_HTTP_HEADERS_ENABLED ]     = ! empty( $_POST['http_headers_enabled'] );
		$settings[ SettingsPage::SETTING_AI_LAYER_PAGE_ENABLED ]    = ! empty( $_POST['ai_layer_page_enabled'] );
		$settings[ SettingsPage::SETTING_SITEMAP_ENABLED ]          = ! empty( $_POST['sitemap_enabled'] );
		update_option( WPAIL_OPT_SETTINGS, $settings );

		$llms            = LLMsTxtSettings::get_all();
		$llms['enabled'] = ! empty( $_POST['llmstxt_enabled'] );
		LLMsTxtSettings::save( $llms );

		$aitxt            = AiTxtSettings::get_all();
		$aitxt['enabled'] = ! empty( $_POST['aitxt_enabled'] );
		AiTxtSettings::save( $aitxt );

		LLMsTxtController::flush_cache();
		AiTxtController::flush_cache();
		AiLayerController::flush_cache();
		flush_rewrite_rules();

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_AI, 'updated' => 'discovery' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_ai_save(): void {
		if ( ! isset( $_POST['wpail_wizard_ai_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['wpail_wizard_ai_nonce'] ) ),
			self::NONCE_AI
		) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		AiSettings::save_from_request( (array) wp_unslash( $_POST ) );

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE, 'updated' => 'ai' ],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ------------------------------------------------------------------
	// Page render.
	// ------------------------------------------------------------------

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$extractor   = new Extractor();
		$sources     = $extractor->get_sources();
		$suggestions = $extractor->get_profile_suggestions();
		$current     = get_option( WPAIL_OPT_BUSINESS, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}

		$step      = sanitize_key( wp_unslash( $_GET['step'] ?? self::STEP_SCAN ) );
		$has_woo   = $extractor->has_woocommerce_products();
		$all_steps = self::build_steps( $has_woo );
		$setup     = SetupProgress::assess();

		// Legacy step slug — merged into Discovery.
		if ( self::STEP_AI_FILES === $step ) {
			$step = self::STEP_DISCOVERY;
		}

		if ( ! array_key_exists( $step, $all_steps ) ) {
			$step = self::STEP_SCAN;
		}

		?>
		<div class="wrap wpail-admin wpail-wizard">

			<div class="wpail-wizard__header">
				<span class="dashicons dashicons-database-import wpail-wizard__header-icon"></span>
				<div>
					<h1><?php esc_html_e( 'Setup Wizard', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'Auto-populate your AI Layer data from existing plugins and settings. Every suggestion needs your approval before anything is saved.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<nav class="wpail-wizard__steps" aria-label="<?php esc_attr_e( 'Wizard steps', 'ai-layer' ); ?>">
				<?php foreach ( $all_steps as $step_key => $step_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => $step_key ], admin_url( 'admin.php' ) ) ); ?>"
					   class="wpail-wizard__step<?php echo $step === $step_key ? ' is-active' : ''; ?>"
					   aria-current="<?php echo $step === $step_key ? 'step' : 'false'; ?>">
						<?php echo esc_html( $step_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php SetupProgress::render_widget( $setup ); ?>

			<?php
			if ( isset( $_GET['updated'] ) ) {
				$updated = sanitize_key( wp_unslash( $_GET['updated'] ) );
				echo '<div class="notice notice-success is-dismissible"><p>';
				if ( 'ai' === $updated ) {
					esc_html_e( 'AI settings saved.', 'ai-layer' );
				} elseif ( 'discovery' === $updated ) {
					esc_html_e( 'Discovery settings saved.', 'ai-layer' );
				} else {
					esc_html_e( 'Business Profile updated.', 'ai-layer' );
				}
				echo '</p></div>';
			}

			match ( $step ) {
				self::STEP_PROFILE     => self::render_profile( $suggestions, $current ),
				self::STEP_WOOCOMMERCE => self::render_woocommerce(),
				self::STEP_DISCOVERY   => self::render_discovery( $has_woo ),
				self::STEP_AI          => self::render_ai(),
				self::STEP_DONE        => self::render_done(),
				default                => self::render_scan( $sources, $suggestions, $has_woo ),
			};
			?>

		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Step renderers.
	// ------------------------------------------------------------------

	/**
	 * @param array<string, object> $sources
	 * @param array<string, array{value: string, source: string}> $suggestions
	 */
	private static function render_scan( array $sources, array $suggestions, bool $has_woo ): void {
		$profile_count = count( $suggestions );
		$total_found   = $profile_count + ( $has_woo ? 1 : 0 );
		?>
		<div class="wpail-wizard__body">

			<h2><?php esc_html_e( 'Detected sources', 'ai-layer' ); ?></h2>
			<p><?php esc_html_e( 'The wizard checks your installed plugins and WordPress settings for data it can use.', 'ai-layer' ); ?></p>

			<div class="wpail-wizard__sources">
				<?php foreach ( $sources as $source ) : ?>
					<div class="wpail-wizard__source <?php echo $source->is_available() ? 'is-available' : 'is-unavailable'; ?>">
						<span class="dashicons <?php echo $source->is_available() ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?> wpail-wizard__source-icon"></span>
						<div class="wpail-wizard__source-info">
							<strong><?php echo esc_html( $source->label() ); ?></strong>
							<span><?php echo esc_html( $source->description() ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<h2><?php esc_html_e( 'What we found', 'ai-layer' ); ?></h2>

			<?php if ( $total_found > 0 ) : ?>
				<ul class="wpail-wizard__found-list">
					<?php if ( $profile_count > 0 ) : ?>
						<li>
							<strong><?php echo esc_html( (string) $profile_count ); ?></strong>
							<?php echo esc_html(
								/* translators: %d: number of fields */
								_n( 'Business Profile field with a suggested value', 'Business Profile fields with suggested values', $profile_count, 'ai-layer' )
							); ?>
						</li>
					<?php endif; ?>
					<?php if ( $has_woo ) : ?>
						<li>
							<?php esc_html_e( 'WooCommerce is active — you can enable the AI Layer /products endpoint.', 'ai-layer' ); ?>
						</li>
					<?php endif; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No data could be detected automatically. You can still fill in your Business Profile and add entities manually.', 'ai-layer' ); ?></p>
			<?php endif; ?>

			<div class="wpail-wizard__nav">
				<?php if ( $profile_count > 0 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_PROFILE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Review Business Profile suggestions', 'ai-layer' ); ?> &rarr;
					</a>
				<?php elseif ( $has_woo ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_WOOCOMMERCE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Configure WooCommerce endpoint', 'ai-layer' ); ?> &rarr;
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Set up Business Profile manually', 'ai-layer' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_dashboard' ) ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Back to Overview', 'ai-layer' ); ?>
				</a>
			</div>

		</div>
		<?php
	}

	/**
	 * @param array<string, array{value: string, source: string}> $suggestions
	 * @param array<string, mixed> $current  Current saved Business Profile data.
	 */
	private static function render_profile( array $suggestions, array $current ): void {
		$fields = FieldDefinitions::business();

		if ( empty( $suggestions ) ) {
			echo '<div class="wpail-wizard__body">';
			echo '<p>' . esc_html__( 'No Business Profile suggestions were found from your installed plugins.', 'ai-layer' ) . '</p>';
			echo '<div class="wpail-wizard__nav">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ) . '" class="button button-primary">' . esc_html__( 'Edit Business Profile manually', 'ai-layer' ) . '</a>';
			echo '</div></div>';
			return;
		}
		?>
		<div class="wpail-wizard__body">

			<p>
				<?php esc_html_e( 'The values below were found in your existing settings. Tick the ones you want to apply and click Save. Fields that already have a value are unticked by default — tick them to overwrite.', 'ai-layer' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_PROFILE, 'wpail_wizard_profile_nonce' ); ?>
				<input type="hidden" name="wpail_wizard_action" value="apply_profile">

				<?php foreach ( $suggestions as $key => $suggestion ) :
					if ( ! isset( $fields[ $key ] ) ) continue;
					$def           = $fields[ $key ];
					$current_val   = (string) ( $current[ $key ] ?? '' );
					$has_value     = $current_val !== '';
					$default_check = ! $has_value;
					?>

					<input type="hidden"
					       name="wpail_suggestions[<?php echo esc_attr( $key ); ?>]"
					       value="<?php echo esc_attr( $suggestion['value'] ); ?>">

					<div class="wpail-wizard__field <?php echo $has_value ? 'has-current' : ''; ?>">
						<label class="wpail-wizard__field-check">
							<input type="checkbox"
							       name="wpail_apply[<?php echo esc_attr( $key ); ?>]"
							       value="1"
							       <?php checked( $default_check ); ?>>
							<span class="wpail-wizard__field-label"><?php echo esc_html( $def['label'] ); ?></span>
						</label>
						<div class="wpail-wizard__field-body">
							<div class="wpail-wizard__suggested">
								<span class="wpail-source-badge"><?php echo esc_html( $suggestion['source'] ); ?></span>
								<span class="wpail-wizard__suggested-value"><?php echo esc_html( $suggestion['value'] ); ?></span>
							</div>
							<?php if ( $has_value ) : ?>
								<div class="wpail-wizard__current">
									<span class="wpail-wizard__current-label"><?php esc_html_e( 'Current:', 'ai-layer' ); ?></span>
									<span class="wpail-wizard__current-value"><?php echo esc_html( $current_val ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>

				<?php endforeach; ?>

				<div class="wpail-wizard__nav">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save selected to Business Profile', 'ai-layer' ); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_SCAN ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-layer' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ); ?>"
					   class="wpail-wizard__text-link">
						<?php esc_html_e( 'Edit full Business Profile instead', 'ai-layer' ); ?>
					</a>
				</div>

			</form>
		</div>
		<?php
	}

	private static function render_woocommerce(): void {
		$already_enabled = (bool) SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED, false );
		?>
		<div class="wpail-wizard__body">

			<h2><?php esc_html_e( 'WooCommerce Products Endpoint', 'ai-layer' ); ?></h2>
			<p>
				<?php esc_html_e( 'AI Layer can expose your WooCommerce product catalogue through a dedicated read-only endpoint. This lets AI agents and search tools browse your products without any data duplication — it reads live from WooCommerce on every request.', 'ai-layer' ); ?>
			</p>

			<?php if ( $already_enabled ) : ?>
				<div class="notice notice-success inline">
					<p><?php esc_html_e( 'The /products endpoint is already enabled. You can manage it in Settings.', 'ai-layer' ); ?></p>
				</div>
				<div class="wpail-wizard__nav">
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DISCOVERY ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Continue', 'ai-layer' ); ?> &rarr;
					</a>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_PROFILE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-layer' ); ?>
					</a>
				</div>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE_WOOCOMMERCE, 'wpail_wizard_woo_nonce' ); ?>
					<input type="hidden" name="wpail_wizard_action" value="enable_products">

					<div class="wpail-wizard__field">
						<label class="wpail-wizard__field-check">
							<input type="checkbox" name="wpail_enable_products" value="1" checked>
							<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable the /products endpoint', 'ai-layer' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Publishes your WooCommerce catalogue at /wp-json/ai-layer/v1/products. Read-only, no extra database writes. You can disable this any time in Settings.', 'ai-layer' ); ?>
						</p>
					</div>

					<div class="wpail-wizard__nav">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save and continue', 'ai-layer' ); ?> &rarr;
						</button>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_PROFILE ], admin_url( 'admin.php' ) ) ); ?>"
						   class="button button-secondary">
							&larr; <?php esc_html_e( 'Back', 'ai-layer' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DISCOVERY ], admin_url( 'admin.php' ) ) ); ?>"
						   class="wpail-wizard__text-link">
							<?php esc_html_e( 'Skip this step', 'ai-layer' ); ?>
						</a>
					</div>
				</form>
			<?php endif; ?>

		</div>
		<?php
	}

	private static function render_discovery( bool $has_woo ): void {
		$discovery_mode    = SettingsPage::get( SettingsPage::SETTING_AI_DISCOVERY_MODE, SettingsPage::AI_DISCOVERY_WELL_KNOWN );
		$head_links        = SettingsPage::get( SettingsPage::SETTING_HEAD_LINKS_ENABLED, true );
		$robots_injection  = SettingsPage::get( SettingsPage::SETTING_ROBOTS_INJECTION_ENABLED, true );
		$http_headers      = SettingsPage::get( SettingsPage::SETTING_HTTP_HEADERS_ENABLED, true );
		$ai_layer_page     = SettingsPage::get( SettingsPage::SETTING_AI_LAYER_PAGE_ENABLED, true );
		$sitemap_enabled   = SettingsPage::get( SettingsPage::SETTING_SITEMAP_ENABLED, true );
		$llmstxt_enabled   = (bool) LLMsTxtSettings::get( 'enabled', true );
		$aitxt_enabled     = (bool) AiTxtSettings::get( 'enabled', false );
		$llms_url          = home_url( '/llms.txt' );
		$aitxt_url         = home_url( '/ai.txt' );
		$conflicts         = ( new ConflictDetector() )->get_conflicts();
		$llms_conflicts    = array_filter(
			$conflicts,
			static fn( array $c ): bool => in_array( $c['severity'] ?? '', [ 'error', 'warning' ], true )
		);
		$back_step         = $has_woo ? self::STEP_WOOCOMMERCE : self::STEP_PROFILE;
		?>
		<div class="wpail-wizard__body">

			<h2><?php esc_html_e( 'AI Discovery', 'ai-layer' ); ?></h2>
			<p><?php esc_html_e( 'Configure how AI agents and crawlers find your structured data — endpoint routing, discovery signals, and standard AI files.', 'ai-layer' ); ?></p>

			<?php foreach ( $llms_conflicts as $conflict ) : ?>
				<div class="notice notice-<?php echo 'error' === ( $conflict['severity'] ?? '' ) ? 'error' : 'warning'; ?> inline" style="margin:0 0 16px;">
					<p><?php echo wp_kses_post( $conflict['message'] ); ?></p>
				</div>
			<?php endforeach; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_DISCOVERY, 'wpail_wizard_discovery_nonce' ); ?>
				<input type="hidden" name="wpail_wizard_action" value="save_discovery">

				<h3 style="margin-top: 20px;"><?php esc_html_e( 'Endpoint discovery mode', 'ai-layer' ); ?></h3>

				<div class="wpail-wizard__field" style="align-items: flex-start;">
					<label class="wpail-wizard__field-check" style="align-items: flex-start; padding-top: 2px;">
						<input type="radio" name="ai_discovery_mode" value="<?php echo esc_attr( SettingsPage::AI_DISCOVERY_WELL_KNOWN ); ?>"
							<?php checked( $discovery_mode, SettingsPage::AI_DISCOVERY_WELL_KNOWN ); ?>>
						<span class="wpail-wizard__field-label">
							<?php esc_html_e( '/.well-known/ai-layer', 'ai-layer' ); ?>
							<span class="wpail-badge wpail-badge--new" style="background:#edfaef;color:#00a32a;border:1px solid #b8e6bf;"><?php esc_html_e( 'Recommended', 'ai-layer' ); ?></span>
						</span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Serves a canonical JSON document at a standard well-known URL. llms.txt links to it as a pointer rather than duplicating all endpoints.', 'ai-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__field" style="align-items: flex-start;">
					<label class="wpail-wizard__field-check" style="align-items: flex-start; padding-top: 2px;">
						<input type="radio" name="ai_discovery_mode" value="<?php echo esc_attr( SettingsPage::AI_DISCOVERY_LLMSTXT ); ?>"
							<?php checked( $discovery_mode, SettingsPage::AI_DISCOVERY_LLMSTXT ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'llms.txt only', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Lists all endpoints directly inside llms.txt. The /.well-known/ai-layer URL will not respond.', 'ai-layer' ); ?></p>
					</div>
				</div>

				<hr style="margin: 4px 0 8px; border: none; border-top: 1px solid #f0f0f1;">

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="head_links_enabled" value="1"
							<?php checked( $head_links ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Add discovery link tags to every page', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description">
							<?php
							printf(
								/* translators: 1: rel="ai-layer" 2: rel="llms-txt" 3: <head> */
								esc_html__( 'Injects %1$s and %2$s tags into the page %3$s. Helps crawlers find your data without needing to know the URLs in advance.', 'ai-layer' ),
								'<code>rel="ai-layer"</code>',
								'<code>rel="llms-txt"</code>',
								'<code>&lt;head&gt;</code>'
							);
							?>
						</p>
					</div>
				</div>

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="robots_injection_enabled" value="1"
							<?php checked( $robots_injection ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Inject discovery directives into robots.txt', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Appends AI-Layer: directives to your dynamically generated robots.txt so crawlers can find the manifest without parsing HTML.', 'ai-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="http_headers_enabled" value="1"
							<?php checked( $http_headers ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Send HTTP discovery headers', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Outputs Link: rel="service" and X-AI-Layer headers on every frontend response so agents can find AI Layer without reading the page.', 'ai-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="ai_layer_page_enabled" value="1"
							<?php checked( $ai_layer_page ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable /ai-layer discovery page', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Serves a human and agent-readable endpoint listing at /ai-layer (HTML) and /ai-layer.md (Markdown).', 'ai-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="sitemap_enabled" value="1"
							<?php checked( $sitemap_enabled ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable AI Layer sitemap', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Serves a dedicated XML sitemap at /ai-layer-sitemap.xml and adds it to the Yoast sitemap index automatically when Yoast is active.', 'ai-layer' ); ?></p>
					</div>
				</div>

				<h3 style="margin-top: 28px;"><?php esc_html_e( 'Standard AI files', 'ai-layer' ); ?></h3>
				<p class="description" style="margin-bottom: 16px;">
					<?php esc_html_e( 'Served dynamically at your site root — no files are written to disk. Fine-tune content on the full settings pages later.', 'ai-layer' ); ?>
				</p>

				<div class="wpail-wizard__file-cards">
					<div class="wpail-wizard__file-card wpail-wizard__file-card--featured">
						<div class="wpail-wizard__file-card-head">
							<label class="wpail-wizard__field-check">
								<input type="checkbox" name="llmstxt_enabled" value="1" <?php checked( $llmstxt_enabled ); ?>>
								<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable llms.txt', 'ai-layer' ); ?></span>
							</label>
							<span class="wpail-badge wpail-badge--new"><?php esc_html_e( 'Recommended', 'ai-layer' ); ?></span>
						</div>
						<p class="description">
							<?php esc_html_e( 'A machine-readable index at /llms.txt that points AI crawlers to your business data endpoints and key pages.', 'ai-layer' ); ?>
						</p>
						<p class="wpail-wizard__file-url">
							<code><?php echo esc_html( $llms_url ); ?></code>
						</p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_llmstxt' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Configure llms.txt', 'ai-layer' ); ?>
						</a>
					</div>

					<div class="wpail-wizard__file-card">
						<div class="wpail-wizard__file-card-head">
							<label class="wpail-wizard__field-check">
								<input type="checkbox" name="aitxt_enabled" value="1" <?php checked( $aitxt_enabled ); ?>>
								<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable AI.txt', 'ai-layer' ); ?></span>
							</label>
							<span class="wpail-badge wpail-badge--beta"><?php esc_html_e( 'Beta', 'ai-layer' ); ?></span>
						</div>
						<p class="description">
							<?php esc_html_e( 'Declares crawling, training, and attribution preferences for AI agents at /ai.txt.', 'ai-layer' ); ?>
						</p>
						<p class="wpail-wizard__file-url">
							<code><?php echo esc_html( $aitxt_url ); ?></code>
						</p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_aitxt' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Configure AI.txt', 'ai-layer' ); ?>
						</a>
					</div>
				</div>

				<div class="wpail-wizard__nav">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save and continue', 'ai-layer' ); ?> &rarr;
					</button>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => $back_step ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-layer' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_AI ], admin_url( 'admin.php' ) ) ); ?>"
					   class="wpail-wizard__text-link">
						<?php esc_html_e( 'Skip this step', 'ai-layer' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	private static function render_ai(): void {
		$model           = AiSettings::get_selected_model();
		$model_info      = AiSettings::get_model_info( $model );
		$provider        = $model_info['provider'] ?? 'openai';
		$has_key         = AiSettings::is_selected_provider_configured();
		$use_connectors  = ConnectorBridge::is_available();
		$connectors_url  = ConnectorBridge::get_admin_url();
		$provider_status = ConnectorBridge::get_provider_status();
		?>
		<div class="wpail-wizard__body">

			<h2><?php esc_html_e( 'AI Setup', 'ai-layer' ); ?></h2>
			<p>
				<?php esc_html_e( 'Optional but recommended if you want to use AI Import. Connect a provider and choose a model — you can skip and add entities manually instead.', 'ai-layer' ); ?>
			</p>

			<?php if ( $use_connectors && '' !== $connectors_url ) : ?>
				<div class="notice notice-info inline" style="margin:0 0 20px;">
					<p>
						<?php
						printf(
							/* translators: %s: link to Settings → Connectors */
							esc_html__( 'WordPress can store your AI provider keys centrally. Configure them on %s — any compatible plugin on this site can use the same connection.', 'ai-layer' ),
							'<a href="' . esc_url( $connectors_url ) . '"><strong>' . esc_html__( 'Settings → Connectors', 'ai-layer' ) . '</strong></a>'
						);
						?>
					</p>
				</div>

				<h3><?php esc_html_e( 'Provider status', 'ai-layer' ); ?></h3>
				<ul class="wpail-wizard__found-list" style="margin-bottom:20px;">
					<?php foreach ( AiSettings::PROVIDER_LABELS as $prov => $label ) : ?>
						<li>
							<strong><?php echo esc_html( $label ); ?>:</strong>
							<?php if ( ! empty( $provider_status[ $prov ] ) ) : ?>
								<span style="color:#00a32a;"><?php esc_html_e( 'Connected', 'ai-layer' ); ?></span>
							<?php else : ?>
								<span style="color:#646970;"><?php esc_html_e( 'Not configured', 'ai-layer' ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_AI, 'wpail_wizard_ai_nonce' ); ?>
				<input type="hidden" name="wpail_wizard_action" value="save_ai">

				<div class="wpail-wizard__field" style="align-items:flex-start;">
					<label class="wpail-wizard__field-check" for="wpail_wizard_ai_model" style="padding-top:6px;">
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Model', 'ai-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<select name="wpail_ai_model" id="wpail_wizard_ai_model" style="min-width:280px;">
							<?php AiSettings::render_model_options( $model ); ?>
						</select>
						<p class="description"><?php esc_html_e( 'GPT-4o Mini works well for extraction. Use a stronger model for the relationship-linking step if results are imprecise.', 'ai-layer' ); ?></p>
					</div>
				</div>

				<?php if ( ! $use_connectors ) : ?>
					<h3 style="margin-top:20px;"><?php esc_html_e( 'API keys', 'ai-layer' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Enter the key for the provider your chosen model uses. Keys are stored in this site\'s database.', 'ai-layer' ); ?></p>

					<?php foreach ( AiSettings::PROVIDER_LABELS as $prov => $label ) : ?>
						<?php $stored_key = AiSettings::get_local_api_key( $prov ); ?>
						<div class="wpail-wizard__field" style="align-items:flex-start;">
							<label class="wpail-wizard__field-check" for="wpail_wizard_ai_key_<?php echo esc_attr( $prov ); ?>" style="padding-top:6px;">
								<span class="wpail-wizard__field-label">
									<?php
									/* translators: %s: provider name */
									printf( esc_html__( '%s API Key', 'ai-layer' ), esc_html( $label ) );
									?>
								</span>
							</label>
							<div class="wpail-wizard__field-body">
								<input type="password"
								       id="wpail_wizard_ai_key_<?php echo esc_attr( $prov ); ?>"
								       name="wpail_ai_key_<?php echo esc_attr( $prov ); ?>"
								       value=""
								       placeholder="<?php echo $stored_key ? esc_attr__( '(saved — leave blank to keep)', 'ai-layer' ) : esc_attr__( 'Paste your API key', 'ai-layer' ); ?>"
								       style="width:340px;font-family:monospace;" />
								<?php if ( $stored_key ) : ?>
									<span style="color:#00a32a;margin-left:8px;">&#10003; <?php esc_html_e( 'Key saved', 'ai-layer' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<?php if ( ! $has_key ) : ?>
					<div class="notice notice-warning inline" style="margin:16px 0 0;">
						<p>
							<?php
							if ( $use_connectors ) {
								printf(
									/* translators: 1: provider name, 2: Settings → Connectors link */
									esc_html__( 'Add an API key for %1$s on %2$s before running AI Import.', 'ai-layer' ),
									esc_html( AiSettings::PROVIDER_LABELS[ $provider ] ?? $provider ),
									'<a href="' . esc_url( $connectors_url ) . '">' . esc_html__( 'Settings → Connectors', 'ai-layer' ) . '</a>'
								);
							} else {
								printf(
									/* translators: %s: provider name */
									esc_html__( 'Add an API key for %s above before running AI Import.', 'ai-layer' ),
									esc_html( AiSettings::PROVIDER_LABELS[ $provider ] ?? $provider )
								);
							}
							?>
						</p>
					</div>
				<?php endif; ?>

				<div class="wpail-wizard__nav">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save and continue', 'ai-layer' ); ?> &rarr;
					</button>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DISCOVERY ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-layer' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="wpail-wizard__text-link">
						<?php esc_html_e( 'Skip for now', 'ai-layer' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	private static function render_done(): void {
		$products_enabled = isset( $_GET['products_enabled'] ) && '1' === $_GET['products_enabled'];
		$has_ai_key       = AiSettings::is_selected_provider_configured();
		$llmstxt_on       = (bool) LLMsTxtSettings::get( 'enabled', false );
		$aitxt_on         = (bool) AiTxtSettings::get( 'enabled', false );
		$import_url       = add_query_arg( 'from', 'wizard', admin_url( 'admin.php?page=wpail_ai_import' ) );
		$manual_path      = isset( $_GET['path'] ) && 'manual' === sanitize_key( wp_unslash( $_GET['path'] ) );
		$setup            = SetupProgress::assess();
		?>
		<div class="wpail-wizard__body">

			<div class="wpail-wizard__milestone">
				<span class="dashicons dashicons-flag wpail-wizard__milestone-icon"></span>
				<h2><?php esc_html_e( 'Configuration saved', 'ai-layer' ); ?></h2>
				<p>
					<?php esc_html_e( 'Your profile, discovery, and AI connection settings are in place. Onboarding is not finished yet — you still need to add business entities before the answer engine can respond.', 'ai-layer' ); ?>
				</p>
				<p class="wpail-wizard__milestone-stat">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: completed count, 2: total count, 3: percentage */
							__( 'Overall setup progress: %1$d of %2$d (%3$d%%)', 'ai-layer' ),
							(int) $setup['complete_count'],
							(int) $setup['total_count'],
							(int) $setup['percent']
						)
					);
					?>
				</p>
				<?php if ( $products_enabled ) : ?>
					<p>
						<?php esc_html_e( 'The /products endpoint has been enabled and your WooCommerce catalogue is now accessible to AI agents.', 'ai-layer' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'Fine-tune AI files (recommended)', 'ai-layer' ); ?></h2>
			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'You chose defaults during discovery — adjust key pages, endpoint sections, or crawling policy anytime.', 'ai-layer' ); ?>
			</p>
			<div class="wpail-wizard__file-status">
				<div class="wpail-wizard__file-status-item">
					<strong>llms.txt</strong>
					<span class="wpail-wizard__file-status-badge <?php echo $llmstxt_on ? 'is-on' : 'is-off'; ?>">
						<?php echo esc_html( $llmstxt_on ? __( 'Enabled', 'ai-layer' ) : __( 'Disabled', 'ai-layer' ) ); ?>
					</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_llmstxt' ) ); ?>"><?php esc_html_e( 'Configure', 'ai-layer' ); ?></a>
					<?php if ( $llmstxt_on ) : ?>
						<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View live', 'ai-layer' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="wpail-wizard__file-status-item">
					<strong>AI.txt</strong>
					<span class="wpail-wizard__file-status-badge <?php echo $aitxt_on ? 'is-on' : 'is-off'; ?>">
						<?php echo esc_html( $aitxt_on ? __( 'Enabled', 'ai-layer' ) : __( 'Disabled', 'ai-layer' ) ); ?>
					</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_aitxt' ) ); ?>"><?php esc_html_e( 'Configure', 'ai-layer' ); ?></a>
					<?php if ( $aitxt_on ) : ?>
						<a href="<?php echo esc_url( home_url( '/ai.txt' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View live', 'ai-layer' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<h2 style="margin-top:28px;"><?php esc_html_e( 'How do you want to add your entities?', 'ai-layer' ); ?></h2>

			<div class="wpail-wizard__choices">
				<div class="wpail-wizard__choice<?php echo ! $manual_path ? ' is-recommended' : ''; ?>">
					<span class="dashicons dashicons-cloud-upload wpail-wizard__choice-icon"></span>
					<h3><?php esc_html_e( 'Import with AI', 'ai-layer' ); ?></h3>
					<p><?php esc_html_e( 'Scan your existing pages and create draft Services, FAQs, Locations, Proof, and Actions automatically. Review everything before publishing.', 'ai-layer' ); ?></p>
					<?php if ( ! $has_ai_key ) : ?>
						<p class="wpail-wizard__choice-note">
							<?php
							if ( ConnectorBridge::is_available() ) {
								printf(
									/* translators: %s: link to AI setup step */
									esc_html__( 'Connect a provider on %s first.', 'ai-layer' ),
									'<a href="' . esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_AI ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'the AI setup step', 'ai-layer' ) . '</a>'
								);
							} else {
								printf(
									/* translators: %s: link to AI setup step */
									esc_html__( 'Add an API key on %s first.', 'ai-layer' ),
									'<a href="' . esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_AI ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'the AI setup step', 'ai-layer' ) . '</a>'
								);
							}
							?>
						</p>
					<?php endif; ?>
					<a href="<?php echo esc_url( $import_url ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Start AI Import', 'ai-layer' ); ?>
					</a>
				</div>

				<div class="wpail-wizard__choice<?php echo $manual_path ? ' is-recommended' : ''; ?>">
					<span class="dashicons dashicons-edit wpail-wizard__choice-icon"></span>
					<h3><?php esc_html_e( 'Add manually', 'ai-layer' ); ?></h3>
					<p><?php esc_html_e( 'Create Services, FAQs, Proof, and Actions yourself in the admin. Best if you already have structured data or prefer full control from the start.', 'ai-layer' ); ?></p>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE, 'path' => 'manual' ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button<?php echo $manual_path ? ' button-primary' : ' button-secondary'; ?>">
						<?php esc_html_e( 'Show manual checklist', 'ai-layer' ); ?>
					</a>
				</div>
			</div>

			<?php if ( $manual_path ) : ?>
			<h2 style="margin-top:32px;"><?php esc_html_e( 'Manual setup checklist', 'ai-layer' ); ?></h2>
			<p><?php esc_html_e( 'Work through these in any order. You can switch to AI Import later from the menu.', 'ai-layer' ); ?></p>

			<div class="wpail-wizard__next-steps">
				<?php
				$next = [
					[
						'icon'  => 'dashicons-store',
						'label' => __( 'Review Business Profile', 'ai-layer' ),
						'desc'  => __( 'Check the auto-populated values and fill in anything that was missed.', 'ai-layer' ),
						'url'   => admin_url( 'admin.php?page=wpail_business_profile' ),
						'cta'   => __( 'Open Business Profile', 'ai-layer' ),
					],
					[
						'icon'  => 'dashicons-clipboard',
						'label' => __( 'Enrich your Services', 'ai-layer' ),
						'desc'  => __( 'Add keywords, synonyms, pricing, and related FAQs to each service so the answer engine can match queries accurately.', 'ai-layer' ),
						'url'   => admin_url( 'edit.php?post_type=wpail_service' ),
						'cta'   => __( 'Manage Services', 'ai-layer' ),
					],
					[
						'icon'  => 'dashicons-editor-help',
						'label' => __( 'Add FAQs', 'ai-layer' ),
						'desc'  => __( 'FAQs are the main input for the answer engine. Aim for at least 5–10 covering your most common questions.', 'ai-layer' ),
						'url'   => admin_url( 'post-new.php?post_type=wpail_faq' ),
						'cta'   => __( 'Add first FAQ', 'ai-layer' ),
					],
					[
						'icon'  => 'dashicons-awards',
						'label' => __( 'Add Proof & Trust', 'ai-layer' ),
						'desc'  => __( 'Testimonials, case studies, and accreditations are attached to answers as supporting evidence.', 'ai-layer' ),
						'url'   => admin_url( 'post-new.php?post_type=wpail_proof' ),
						'cta'   => __( 'Add first Proof item', 'ai-layer' ),
					],
					[
						'icon'  => 'dashicons-arrow-right-alt',
						'label' => __( 'Add Actions', 'ai-layer' ),
						'desc'  => __( 'Calls-to-action are returned alongside every answer. Add at least one — a booking link, phone number, or contact form.', 'ai-layer' ),
						'url'   => admin_url( 'post-new.php?post_type=wpail_action' ),
						'cta'   => __( 'Add first Action', 'ai-layer' ),
					],
					[
						'icon'  => 'dashicons-format-chat',
						'label' => __( 'Add Answers', 'ai-layer' ),
						'desc'  => Features::answers_enabled()
							? __( 'Pre-written answers are returned when an agent queries your data. Pair each one with Services, Locations, FAQs, and call-to-actions.', 'ai-layer' )
							: __( 'Pre-written answers power the /answers endpoint. Upgrade to AI Layer Pro to unlock this feature.', 'ai-layer' ),
						'url'   => Features::answers_enabled()
							? admin_url( 'post-new.php?post_type=wpail_answer' )
							: admin_url( 'admin.php?page=wpail_answers' ),
						'cta'   => Features::answers_enabled()
							? __( 'Add first Answer', 'ai-layer' )
							: __( 'Learn about Answers', 'ai-layer' ),
						'pro'   => ! Features::answers_enabled(),
					]
				];

				foreach ( $next as $item ) : ?>
					<div class="wpail-wizard__next-step">
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?> wpail-wizard__next-icon"></span>
						<div>
							<strong>
								<?php echo esc_html( $item['label'] ); ?>
								<?php if ( ! empty( $item['pro'] ) ) : ?>
									<span class="wpail-pro-badge">Pro</span>
								<?php endif; ?>
							</strong>
							<p><?php echo esc_html( $item['desc'] ); ?></p>
							<a href="<?php echo esc_url( $item['url'] ); ?>" class="button">
								<?php echo esc_html( $item['cta'] ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<div class="wpail-wizard__nav" style="margin-top: 24px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_dashboard' ) ); ?>"
				   class="button button-primary">
					<?php esc_html_e( 'Go to Overview', 'ai-layer' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_SCAN ], admin_url( 'admin.php' ) ) ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Run wizard again', 'ai-layer' ); ?>
				</a>
			</div>

		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Helpers.
	// ------------------------------------------------------------------

	/**
	 * @return array<string, string>
	 */
	private static function build_steps( bool $has_woo ): array {
		$n     = 1;
		$steps = [
			self::STEP_SCAN    => $n++ . '. ' . __( 'Detect', 'ai-layer' ),
			self::STEP_PROFILE => $n++ . '. ' . __( 'Business Profile', 'ai-layer' ),
		];

		if ( $has_woo ) {
			$steps[ self::STEP_WOOCOMMERCE ] = $n++ . '. ' . __( 'WooCommerce', 'ai-layer' );
		}

		$steps[ self::STEP_DISCOVERY ] = $n++ . '. ' . __( 'Discovery', 'ai-layer' );
		$steps[ self::STEP_AI ]        = $n++ . '. ' . __( 'AI Setup', 'ai-layer' );
		$steps[ self::STEP_DONE ]      = $n . '. ' . __( 'Add content', 'ai-layer' );

		return $steps;
	}
}

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
 *   discovery   — configure endpoint discovery mode, link tags, llms.txt, and AI.txt
 *   done        — completion summary with next-step links
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Setup\Extractor;
use WPAIL\Support\FieldDefinitions;
use WPAIL\Support\Sanitizer;
use WPAIL\Licensing\Features;
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
	const STEP_DONE        = 'done';

	const NONCE_PROFILE     = 'wpail_wizard_profile';
	const NONCE_WOOCOMMERCE = 'wpail_wizard_woo';
	const NONCE_DISCOVERY   = 'wpail_wizard_discovery';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	// ------------------------------------------------------------------
	// Form handlers.
	// ------------------------------------------------------------------

	public function handle_save(): void {
		$action = sanitize_key( $_POST['wpail_wizard_action'] ?? '' );

		if ( 'apply_profile' === $action ) {
			$this->handle_profile_save();
		} elseif ( 'enable_products' === $action ) {
			$this->handle_enable_products();
		} elseif ( 'save_discovery' === $action ) {
			$this->handle_discovery_save();
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
		$to_apply   = array_keys( (array) ( $_POST['wpail_apply'] ?? [] ) );
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
		$settings[ SettingsPage::SETTING_HEAD_LINKS_ENABLED ] = ! empty( $_POST['head_links_enabled'] );
		update_option( WPAIL_OPT_SETTINGS, $settings );

		// llms.txt — merge enabled flag into existing settings.
		$llms            = LLMsTxtSettings::get_all();
		$llms['enabled'] = ! empty( $_POST['llmstxt_enabled'] );
		LLMsTxtSettings::save( $llms );

		// AI.txt — merge enabled flag into existing settings.
		$aitxt            = AiTxtSettings::get_all();
		$aitxt['enabled'] = ! empty( $_POST['aitxt_enabled'] );
		AiTxtSettings::save( $aitxt );

		LLMsTxtController::flush_cache();
		AiLayerController::flush_cache();
		AiTxtController::flush_cache();
		flush_rewrite_rules();

		wp_safe_redirect(
			add_query_arg(
				[ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE ],
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

		$step     = sanitize_key( $_GET['step'] ?? self::STEP_SCAN );
		$has_woo  = $extractor->has_woocommerce_products();
		$all_steps = self::build_steps( $has_woo );

		if ( ! array_key_exists( $step, $all_steps ) ) {
			$step = self::STEP_SCAN;
		}

		?>
		<div class="wrap wpail-admin wpail-wizard">

			<div class="wpail-wizard__header">
				<span class="dashicons dashicons-database-import wpail-wizard__header-icon"></span>
				<div>
					<h1><?php esc_html_e( 'Setup Wizard', 'ai-ready-layer' ); ?></h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'Auto-populate your AI Layer data from existing plugins and settings. Every suggestion needs your approval before anything is saved.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			</div>

			<nav class="wpail-wizard__steps" aria-label="<?php esc_attr_e( 'Wizard steps', 'ai-ready-layer' ); ?>">
				<?php foreach ( $all_steps as $step_key => $step_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => $step_key ], admin_url( 'admin.php' ) ) ); ?>"
					   class="wpail-wizard__step<?php echo $step === $step_key ? ' is-active' : ''; ?>"
					   aria-current="<?php echo $step === $step_key ? 'step' : 'false'; ?>">
						<?php echo esc_html( $step_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( isset( $_GET['updated'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				esc_html_e( 'Business Profile updated.', 'ai-ready-layer' );
				echo '</p></div>';
			}

			match ( $step ) {
				self::STEP_PROFILE     => self::render_profile( $suggestions, $current ),
				self::STEP_WOOCOMMERCE => self::render_woocommerce(),
				self::STEP_DISCOVERY   => self::render_discovery( $has_woo ),
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

			<h2><?php esc_html_e( 'Detected sources', 'ai-ready-layer' ); ?></h2>
			<p><?php esc_html_e( 'The wizard checks your installed plugins and WordPress settings for data it can use.', 'ai-ready-layer' ); ?></p>

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

			<h2><?php esc_html_e( 'What we found', 'ai-ready-layer' ); ?></h2>

			<?php if ( $total_found > 0 ) : ?>
				<ul class="wpail-wizard__found-list">
					<?php if ( $profile_count > 0 ) : ?>
						<li>
							<strong><?php echo esc_html( (string) $profile_count ); ?></strong>
							<?php echo esc_html(
								/* translators: %d: number of fields */
								_n( 'Business Profile field with a suggested value', 'Business Profile fields with suggested values', $profile_count, 'ai-ready-layer' )
							); ?>
						</li>
					<?php endif; ?>
					<?php if ( $has_woo ) : ?>
						<li>
							<?php esc_html_e( 'WooCommerce is active — you can enable the AI Layer /products endpoint.', 'ai-ready-layer' ); ?>
						</li>
					<?php endif; ?>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No data could be detected automatically. You can still fill in your Business Profile and add entities manually.', 'ai-ready-layer' ); ?></p>
			<?php endif; ?>

			<div class="wpail-wizard__nav">
				<?php if ( $profile_count > 0 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_PROFILE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Review Business Profile suggestions', 'ai-ready-layer' ); ?> &rarr;
					</a>
				<?php elseif ( $has_woo ) : ?>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_WOOCOMMERCE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Configure WooCommerce endpoint', 'ai-ready-layer' ); ?> &rarr;
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Set up Business Profile manually', 'ai-ready-layer' ); ?>
					</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_dashboard' ) ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Back to Overview', 'ai-ready-layer' ); ?>
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
			echo '<p>' . esc_html__( 'No Business Profile suggestions were found from your installed plugins.', 'ai-ready-layer' ) . '</p>';
			echo '<div class="wpail-wizard__nav">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ) . '" class="button button-primary">' . esc_html__( 'Edit Business Profile manually', 'ai-ready-layer' ) . '</a>';
			echo '</div></div>';
			return;
		}
		?>
		<div class="wpail-wizard__body">

			<p>
				<?php esc_html_e( 'The values below were found in your existing settings. Tick the ones you want to apply and click Save. Fields that already have a value are unticked by default — tick them to overwrite.', 'ai-ready-layer' ); ?>
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
									<span class="wpail-wizard__current-label"><?php esc_html_e( 'Current:', 'ai-ready-layer' ); ?></span>
									<span class="wpail-wizard__current-value"><?php echo esc_html( $current_val ); ?></span>
								</div>
							<?php endif; ?>
						</div>
					</div>

				<?php endforeach; ?>

				<div class="wpail-wizard__nav">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save selected to Business Profile', 'ai-ready-layer' ); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_SCAN ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-ready-layer' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ); ?>"
					   class="wpail-wizard__text-link">
						<?php esc_html_e( 'Edit full Business Profile instead', 'ai-ready-layer' ); ?>
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

			<h2><?php esc_html_e( 'WooCommerce Products Endpoint', 'ai-ready-layer' ); ?></h2>
			<p>
				<?php esc_html_e( 'AI Layer can expose your WooCommerce product catalogue through a dedicated read-only endpoint. This lets AI agents and search tools browse your products without any data duplication — it reads live from WooCommerce on every request.', 'ai-ready-layer' ); ?>
			</p>

			<?php if ( $already_enabled ) : ?>
				<div class="notice notice-success inline">
					<p><?php esc_html_e( 'The /products endpoint is already enabled. You can manage it in Settings.', 'ai-ready-layer' ); ?></p>
				</div>
				<div class="wpail-wizard__nav">
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-primary">
						<?php esc_html_e( 'Continue', 'ai-ready-layer' ); ?> &rarr;
					</a>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_PROFILE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-ready-layer' ); ?>
					</a>
				</div>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE_WOOCOMMERCE, 'wpail_wizard_woo_nonce' ); ?>
					<input type="hidden" name="wpail_wizard_action" value="enable_products">

					<div class="wpail-wizard__field">
						<label class="wpail-wizard__field-check">
							<input type="checkbox" name="wpail_enable_products" value="1" checked>
							<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable the /products endpoint', 'ai-ready-layer' ); ?></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Publishes your WooCommerce catalogue at /wp-json/ai-layer/v1/products. Read-only, no extra database writes. You can disable this any time in Settings.', 'ai-ready-layer' ); ?>
						</p>
					</div>

					<div class="wpail-wizard__nav">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save and continue', 'ai-ready-layer' ); ?> &rarr;
						</button>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_PROFILE ], admin_url( 'admin.php' ) ) ); ?>"
						   class="button button-secondary">
							&larr; <?php esc_html_e( 'Back', 'ai-ready-layer' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE ], admin_url( 'admin.php' ) ) ); ?>"
						   class="wpail-wizard__text-link">
							<?php esc_html_e( 'Skip this step', 'ai-ready-layer' ); ?>
						</a>
					</div>
				</form>
			<?php endif; ?>

		</div>
		<?php
	}

	private static function render_discovery( bool $has_woo ): void {
		$discovery_mode  = SettingsPage::get( SettingsPage::SETTING_AI_DISCOVERY_MODE, SettingsPage::AI_DISCOVERY_WELL_KNOWN );
		$head_links      = SettingsPage::get( SettingsPage::SETTING_HEAD_LINKS_ENABLED, true );
		$llmstxt_enabled = LLMsTxtSettings::get( 'enabled', false );
		$aitxt_enabled   = AiTxtSettings::get( 'enabled', false );
		$back_step       = $has_woo ? self::STEP_WOOCOMMERCE : self::STEP_PROFILE;
		?>
		<div class="wpail-wizard__body">

			<h2><?php esc_html_e( 'Discovery &amp; AI Files', 'ai-ready-layer' ); ?></h2>
			<p><?php esc_html_e( 'Choose how AI agents and crawlers find your structured data, and which standard files your site serves.', 'ai-ready-layer' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_DISCOVERY, 'wpail_wizard_discovery_nonce' ); ?>
				<input type="hidden" name="wpail_wizard_action" value="save_discovery">

				<h3 style="margin-top: 20px;"><?php esc_html_e( 'Endpoint discovery mode', 'ai-ready-layer' ); ?></h3>

				<div class="wpail-wizard__field" style="align-items: flex-start;">
					<label class="wpail-wizard__field-check" style="align-items: flex-start; padding-top: 2px;">
						<input type="radio" name="ai_discovery_mode" value="<?php echo esc_attr( SettingsPage::AI_DISCOVERY_WELL_KNOWN ); ?>"
							<?php checked( $discovery_mode, SettingsPage::AI_DISCOVERY_WELL_KNOWN ); ?>>
						<span class="wpail-wizard__field-label">
							<?php esc_html_e( '/.well-known/ai-layer', 'ai-ready-layer' ); ?>
							<span class="wpail-badge wpail-badge--new" style="background:#edfaef;color:#00a32a;border:1px solid #b8e6bf;"><?php esc_html_e( 'Recommended', 'ai-ready-layer' ); ?></span>
						</span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Serves a canonical JSON document at a standard well-known URL. llms.txt links to it as a pointer rather than duplicating all endpoints.', 'ai-ready-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__field" style="align-items: flex-start;">
					<label class="wpail-wizard__field-check" style="align-items: flex-start; padding-top: 2px;">
						<input type="radio" name="ai_discovery_mode" value="<?php echo esc_attr( SettingsPage::AI_DISCOVERY_LLMSTXT ); ?>"
							<?php checked( $discovery_mode, SettingsPage::AI_DISCOVERY_LLMSTXT ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'llms.txt only', 'ai-ready-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Lists all endpoints directly inside llms.txt. The /.well-known/ai-layer URL will not respond.', 'ai-ready-layer' ); ?></p>
					</div>
				</div>

				<hr style="margin: 4px 0 8px; border: none; border-top: 1px solid #f0f0f1;">

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="head_links_enabled" value="1"
							<?php checked( $head_links ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Add discovery link tags to every page', 'ai-ready-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description">
							<?php
							printf(
								/* translators: 1: rel="ai-layer" 2: rel="llms-txt" 3: <head> */
								esc_html__( 'Injects %1$s and %2$s tags into the page %3$s. Helps crawlers find your data without needing to know the URLs in advance.', 'ai-ready-layer' ),
								'<code>rel="ai-layer"</code>',
								'<code>rel="llms-txt"</code>',
								'<code>&lt;head&gt;</code>'
							);
							?>
						</p>
					</div>
				</div>

				<hr style="margin: 4px 0 8px; border: none; border-top: 1px solid #f0f0f1;">
				<h3 style="margin: 12px 0 4px;"><?php esc_html_e( 'AI files', 'ai-ready-layer' ); ?></h3>

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="llmstxt_enabled" value="1"
							<?php checked( $llmstxt_enabled ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable llms.txt', 'ai-ready-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Serves a generated /llms.txt file pointing AI systems to your structured data endpoints. Follows the emerging llms.txt standard.', 'ai-ready-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__field">
					<label class="wpail-wizard__field-check">
						<input type="checkbox" name="aitxt_enabled" value="1"
							<?php checked( $aitxt_enabled ); ?>>
						<span class="wpail-wizard__field-label"><?php esc_html_e( 'Enable AI.txt', 'ai-ready-layer' ); ?></span>
					</label>
					<div class="wpail-wizard__field-body">
						<p class="description"><?php esc_html_e( 'Serves an /ai.txt file declaring your crawling and training permissions for AI agents. Fine-tune per-agent rules on the AI.txt settings page.', 'ai-ready-layer' ); ?></p>
					</div>
				</div>

				<div class="wpail-wizard__nav">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save and continue', 'ai-ready-layer' ); ?> &rarr;
					</button>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => $back_step ], admin_url( 'admin.php' ) ) ); ?>"
					   class="button button-secondary">
						&larr; <?php esc_html_e( 'Back', 'ai-ready-layer' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_DONE ], admin_url( 'admin.php' ) ) ); ?>"
					   class="wpail-wizard__text-link">
						<?php esc_html_e( 'Skip this step', 'ai-ready-layer' ); ?>
					</a>
				</div>

			</form>
		</div>
		<?php
	}

	private static function render_done(): void {
		$products_enabled = isset( $_GET['products_enabled'] ) && '1' === $_GET['products_enabled'];
		?>
		<div class="wpail-wizard__body">

			<div class="wpail-wizard__done">
				<span class="dashicons dashicons-yes-alt wpail-wizard__done-icon"></span>
				<h2><?php esc_html_e( 'Setup complete', 'ai-ready-layer' ); ?></h2>
				<p>
					<?php esc_html_e( 'Your auto-populated data has been applied. You can re-run the wizard any time from the menu.', 'ai-ready-layer' ); ?>
				</p>
				<?php if ( $products_enabled ) : ?>
					<p>
						<?php esc_html_e( 'The /products endpoint has been enabled and your WooCommerce catalogue is now accessible to AI agents.', 'ai-ready-layer' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'What to do next', 'ai-ready-layer' ); ?></h2>
			<p><?php esc_html_e( 'The wizard covers Business Profile and discovery settings. These sections still need your attention:', 'ai-ready-layer' ); ?></p>

			<div class="wpail-wizard__next-steps">
				<?php
				$next = [
					[
						'icon'  => 'dashicons-store',
						'label' => __( 'Review Business Profile', 'ai-ready-layer' ),
						'desc'  => __( 'Check the auto-populated values and fill in anything that was missed.', 'ai-ready-layer' ),
						'url'   => admin_url( 'admin.php?page=wpail_business_profile' ),
						'cta'   => __( 'Open Business Profile', 'ai-ready-layer' ),
					],
					[
						'icon'  => 'dashicons-clipboard',
						'label' => __( 'Enrich your Services', 'ai-ready-layer' ),
						'desc'  => __( 'Add keywords, synonyms, pricing, and related FAQs to each service so the answer engine can match queries accurately.', 'ai-ready-layer' ),
						'url'   => admin_url( 'edit.php?post_type=wpail_service' ),
						'cta'   => __( 'Manage Services', 'ai-ready-layer' ),
					],
					[
						'icon'  => 'dashicons-editor-help',
						'label' => __( 'Add FAQs', 'ai-ready-layer' ),
						'desc'  => __( 'FAQs are the main input for the answer engine. Aim for at least 5–10 covering your most common questions.', 'ai-ready-layer' ),
						'url'   => admin_url( 'post-new.php?post_type=wpail_faq' ),
						'cta'   => __( 'Add first FAQ', 'ai-ready-layer' ),
					],
					[
						'icon'  => 'dashicons-awards',
						'label' => __( 'Add Proof & Trust', 'ai-ready-layer' ),
						'desc'  => __( 'Testimonials, case studies, and accreditations are attached to answers as supporting evidence.', 'ai-ready-layer' ),
						'url'   => admin_url( 'post-new.php?post_type=wpail_proof' ),
						'cta'   => __( 'Add first Proof item', 'ai-ready-layer' ),
					],
					[
						'icon'  => 'dashicons-arrow-right-alt',
						'label' => __( 'Add Actions', 'ai-ready-layer' ),
						'desc'  => __( 'Calls-to-action are returned alongside every answer. Add at least one — a booking link, phone number, or contact form.', 'ai-ready-layer' ),
						'url'   => admin_url( 'post-new.php?post_type=wpail_action' ),
						'cta'   => __( 'Add first Action', 'ai-ready-layer' ),
					],
					[
						'icon'  => 'dashicons-format-chat',
						'label' => __( 'Add Answers', 'ai-ready-layer' ),
						'desc'  => Features::answers_enabled()
							? __( 'Pre-written answers are returned when an agent queries your data. Pair each one with Services, Locations, FAQs, and call-to-actions.', 'ai-ready-layer' )
							: __( 'Pre-written answers power the /answers endpoint. Upgrade to AI Layer Pro to unlock this feature.', 'ai-ready-layer' ),
						'url'   => Features::answers_enabled()
							? admin_url( 'post-new.php?post_type=wpail_answer' )
							: admin_url( 'admin.php?page=wpail_answers' ),
						'cta'   => Features::answers_enabled()
							? __( 'Add first Answer', 'ai-ready-layer' )
							: __( 'Learn about Answers', 'ai-ready-layer' ),
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

			<div class="wpail-wizard__nav" style="margin-top: 24px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_dashboard' ) ); ?>"
				   class="button button-primary">
					<?php esc_html_e( 'Go to Overview', 'ai-ready-layer' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'wpail_setup_wizard', 'step' => self::STEP_SCAN ], admin_url( 'admin.php' ) ) ); ?>"
				   class="button button-secondary">
					<?php esc_html_e( 'Run wizard again', 'ai-ready-layer' ); ?>
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
			self::STEP_SCAN    => $n++ . '. ' . __( 'Detect', 'ai-ready-layer' ),
			self::STEP_PROFILE => $n++ . '. ' . __( 'Business Profile', 'ai-ready-layer' ),
		];

		if ( $has_woo ) {
			$steps[ self::STEP_WOOCOMMERCE ] = $n++ . '. ' . __( 'WooCommerce', 'ai-ready-layer' );
		}

		$steps[ self::STEP_DISCOVERY ] = $n++ . '. ' . __( 'Discovery', 'ai-ready-layer' );
		$steps[ self::STEP_DONE ]      = $n . '. ' . __( 'Done', 'ai-ready-layer' );

		return $steps;
	}
}

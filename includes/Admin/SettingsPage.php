<?php
/**
 * Plugin Settings page.
 *
 * Controls schema output, endpoint visibility, and future options.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\WellKnown\AiLayerController;
use WPAIL\LLMsTxt\LLMsTxtController;

class SettingsPage {

	const NONCE_ACTION = 'wpail_save_settings';
	const NONCE_NAME   = 'wpail_settings_nonce';

	// Option keys.
	const SETTING_SCHEMA_ENABLED          = 'schema_enabled';
	const SETTING_SCHEMA_ORG_TYPE         = 'schema_org_type';
	const SETTING_SCHEMA_FAQ_ENABLED      = 'schema_faq_enabled';
	const SETTING_SCHEMA_FAQ_PAGES_MODE   = 'schema_faq_pages_mode'; // 'all' | 'specific'
	const SETTING_SCHEMA_FAQ_PAGE_IDS     = 'schema_faq_page_ids';   // int[]
	const SETTING_ENDPOINT_CACHE_TTL      = 'endpoint_cache_ttl';
	const SETTING_PRODUCTS_ENABLED        = 'products_enabled';

	// AI discovery mode.
	const SETTING_AI_DISCOVERY_MODE  = 'ai_discovery_mode';
	const AI_DISCOVERY_WELL_KNOWN    = 'well_known'; // /.well-known/ai-layer is canonical; llms.txt links to it
	const AI_DISCOVERY_LLMSTXT       = 'llmstxt';    // Endpoints listed in /llms.txt; /.well-known/ai-layer disabled
	const SETTING_HEAD_LINKS_ENABLED = 'head_links_enabled'; // Output <link> tags in <head>

	// Data management.
	const SETTING_DELETE_ON_UNINSTALL         = 'delete_data_on_uninstall';
	const SETTING_ANALYTICS_RETENTION_DAYS    = 'analytics_retention_days';

	// Post type visibility.
	const SETTING_SERVICE_PUBLIC          = 'service_public';
	const SETTING_SERVICE_SLUG            = 'service_slug';
	const SETTING_LOCATION_PUBLIC         = 'location_public';
	const SETTING_LOCATION_SLUG           = 'location_slug';
	const SETTING_FAQ_PUBLIC              = 'faq_public';
	const SETTING_FAQ_SLUG                = 'faq_slug';
	const SETTING_PROOF_PUBLIC            = 'proof_public';
	const SETTING_PROOF_SLUG              = 'proof_slug';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
	}

	public static function get( string $key, mixed $default = null ): mixed {
		$settings = get_option( WPAIL_OPT_SETTINGS, [] );
		return $settings[ $key ] ?? $default;
	}

	public function handle_save(): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ai-layer' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ai-layer' ) );
		}

		$raw_page_ids = isset( $_POST[ self::SETTING_SCHEMA_FAQ_PAGE_IDS ] )
			? (array) wp_unslash( $_POST[ self::SETTING_SCHEMA_FAQ_PAGE_IDS ] )
			: [];

		$faq_pages_mode_raw = sanitize_text_field( wp_unslash( $_POST[ self::SETTING_SCHEMA_FAQ_PAGES_MODE ] ?? '' ) );
		$ai_discovery_raw   = sanitize_text_field( wp_unslash( $_POST[ self::SETTING_AI_DISCOVERY_MODE ] ?? '' ) );

		$settings = [
			self::SETTING_SCHEMA_ENABLED        => isset( $_POST[ self::SETTING_SCHEMA_ENABLED ] ),
			self::SETTING_SCHEMA_ORG_TYPE       => sanitize_text_field( wp_unslash( $_POST[ self::SETTING_SCHEMA_ORG_TYPE ] ?? '' ) ),
			self::SETTING_SCHEMA_FAQ_ENABLED    => isset( $_POST[ self::SETTING_SCHEMA_FAQ_ENABLED ] ),
			self::SETTING_SCHEMA_FAQ_PAGES_MODE => in_array( $faq_pages_mode_raw, [ 'all', 'specific' ], true )
				? $faq_pages_mode_raw
				: 'all',
			self::SETTING_SCHEMA_FAQ_PAGE_IDS   => array_values( array_filter( array_map( 'absint', $raw_page_ids ) ) ),
			self::SETTING_ENDPOINT_CACHE_TTL    => absint( wp_unslash( $_POST[ self::SETTING_ENDPOINT_CACHE_TTL ] ?? 0 ) ),
			self::SETTING_PRODUCTS_ENABLED      => isset( $_POST[ self::SETTING_PRODUCTS_ENABLED ] ),
			self::SETTING_SERVICE_PUBLIC        => isset( $_POST[ self::SETTING_SERVICE_PUBLIC ] ),
			self::SETTING_SERVICE_SLUG          => self::sanitize_rewrite_slug( $_POST[ self::SETTING_SERVICE_SLUG ] ?? '', 'services' ),
			self::SETTING_LOCATION_PUBLIC       => isset( $_POST[ self::SETTING_LOCATION_PUBLIC ] ),
			self::SETTING_LOCATION_SLUG         => self::sanitize_rewrite_slug( $_POST[ self::SETTING_LOCATION_SLUG ] ?? '', 'locations' ),
			self::SETTING_FAQ_PUBLIC            => isset( $_POST[ self::SETTING_FAQ_PUBLIC ] ),
			self::SETTING_FAQ_SLUG              => self::sanitize_rewrite_slug( $_POST[ self::SETTING_FAQ_SLUG ] ?? '', 'faqs' ),
			self::SETTING_PROOF_PUBLIC          => isset( $_POST[ self::SETTING_PROOF_PUBLIC ] ),
			self::SETTING_PROOF_SLUG            => self::sanitize_rewrite_slug( $_POST[ self::SETTING_PROOF_SLUG ] ?? '', 'proof' ),
			self::SETTING_AI_DISCOVERY_MODE     => in_array( $ai_discovery_raw, [ self::AI_DISCOVERY_WELL_KNOWN, self::AI_DISCOVERY_LLMSTXT ], true )
				? $ai_discovery_raw
				: self::AI_DISCOVERY_WELL_KNOWN,
			self::SETTING_HEAD_LINKS_ENABLED         => isset( $_POST[ self::SETTING_HEAD_LINKS_ENABLED ] ),
			self::SETTING_DELETE_ON_UNINSTALL        => isset( $_POST[ self::SETTING_DELETE_ON_UNINSTALL ] ),
			self::SETTING_ANALYTICS_RETENTION_DAYS  => self::sanitize_retention_days( $_POST[ self::SETTING_ANALYTICS_RETENTION_DAYS ] ?? '' ),
		];

		update_option( WPAIL_OPT_SETTINGS, $settings );

		// Schedule a rewrite flush for the next request — CPTs will register with
		// the new visibility settings and the rules will be regenerated cleanly.
		set_transient( 'wpail_flush_rewrite', true, MINUTE_IN_SECONDS );

		// Settings that affect discovery output — invalidate both caches.
		AiLayerController::flush_cache();
		LLMsTxtController::flush_cache();

		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Settings saved.', 'ai-layer' );
			echo '</p></div>';
		} );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$schema_enabled      = (bool) self::get( self::SETTING_SCHEMA_ENABLED, false );
		$schema_org_type     = (string) self::get( self::SETTING_SCHEMA_ORG_TYPE, 'LocalBusiness' );
		$schema_faq_enabled  = (bool) self::get( self::SETTING_SCHEMA_FAQ_ENABLED, false );
		$faq_pages_mode      = (string) self::get( self::SETTING_SCHEMA_FAQ_PAGES_MODE, 'all' );
		$faq_page_ids        = (array) self::get( self::SETTING_SCHEMA_FAQ_PAGE_IDS, [] );
		$cache_ttl           = (int) self::get( self::SETTING_ENDPOINT_CACHE_TTL, 0 );
		$products_enabled    = (bool) self::get( self::SETTING_PRODUCTS_ENABLED, false );
		$has_woocommerce     = class_exists( 'WooCommerce' );
		$ai_discovery_mode   = (string) self::get( self::SETTING_AI_DISCOVERY_MODE, self::AI_DISCOVERY_WELL_KNOWN );
		$head_links_enabled  = (bool) self::get( self::SETTING_HEAD_LINKS_ENABLED, true );

		$service_public      = (bool) self::get( self::SETTING_SERVICE_PUBLIC, false );
		$service_slug        = (string) self::get( self::SETTING_SERVICE_SLUG, 'services' );
		$location_public     = (bool) self::get( self::SETTING_LOCATION_PUBLIC, false );
		$location_slug       = (string) self::get( self::SETTING_LOCATION_SLUG, 'locations' );
		$faq_public          = (bool) self::get( self::SETTING_FAQ_PUBLIC, false );
		$faq_slug            = (string) self::get( self::SETTING_FAQ_SLUG, 'faqs' );
		$proof_public        = (bool) self::get( self::SETTING_PROOF_PUBLIC, false );
		$proof_slug          = (string) self::get( self::SETTING_PROOF_SLUG, 'proof' );
		$delete_on_uninstall = (bool) self::get( self::SETTING_DELETE_ON_UNINSTALL, false );
		$analytics_retention = (int) self::get( self::SETTING_ANALYTICS_RETENTION_DAYS, 365 );

		// Detect conflicting SEO plugins.
		$has_yoast     = defined( 'WPSEO_VERSION' );
		$has_rank_math = defined( 'RANK_MATH_VERSION' );
		?>
		<div class="wrap wpail-admin">
			<h1><?php esc_html_e( 'AI Layer Settings', 'ai-layer' ); ?></h1>

			<?php if ( $has_yoast || $has_rank_math ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php if ( $has_yoast ) : ?>
							<strong><?php esc_html_e( 'Yoast SEO detected.', 'ai-layer' ); ?></strong>
						<?php endif; ?>
						<?php if ( $has_rank_math ) : ?>
							<strong><?php esc_html_e( 'Rank Math detected.', 'ai-layer' ); ?></strong>
						<?php endif; ?>
						<?php esc_html_e( 'Schema output is disabled by default to avoid duplication. Enable carefully and review your pages.', 'ai-layer' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<h2><?php esc_html_e( 'Schema.org Output', 'ai-layer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Schema Output', 'ai-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::SETTING_SCHEMA_ENABLED ); ?>" value="1"
								       <?php checked( $schema_enabled ); ?>>
								<?php esc_html_e( 'Output JSON-LD schema on site pages', 'ai-layer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Outputs a JSON-LD block in &lt;head&gt; using the Schema.org type selected below, populated from your Business Profile.', 'ai-layer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Schema.org Type', 'ai-layer' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::SETTING_SCHEMA_ORG_TYPE ); ?>">
								<?php
								$types = [
									'Organization'               => 'Organization',
									'LocalBusiness'              => 'LocalBusiness',
									'ProfessionalService'        => 'ProfessionalService',
									'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness',
									'LegalService'               => 'LegalService',
									'HealthAndBeautyBusiness'    => 'HealthAndBeautyBusiness',
								];
								foreach ( $types as $v => $l ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $v ),
										selected( $schema_org_type, $v, false ),
										esc_html( $l )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable FAQPage Schema', 'ai-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_ENABLED ); ?>" value="1"
								       <?php checked( $schema_faq_enabled ); ?>>
								<?php esc_html_e( 'Output FAQPage JSON-LD using all published public FAQs', 'ai-layer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'FAQPage target pages', 'ai-layer' ); ?></th>
						<td>
							<label style="display:block; margin-bottom:6px;">
								<input type="radio" name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_PAGES_MODE ); ?>"
								       value="all" <?php checked( $faq_pages_mode, 'all' ); ?>>
								<?php esc_html_e( 'All pages', 'ai-layer' ); ?>
							</label>
							<label style="display:block; margin-bottom:8px;">
								<input type="radio" name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_PAGES_MODE ); ?>"
								       id="wpail_faq_mode_specific"
								       value="specific" <?php checked( $faq_pages_mode, 'specific' ); ?>>
								<?php esc_html_e( 'Specific pages', 'ai-layer' ); ?>
							</label>
							<div id="wpail_faq_page_list" <?php echo 'specific' === $faq_pages_mode ? '' : 'style="display:none"'; ?>>
								<div class="wpail-post-checklist">
									<?php foreach ( get_pages( [ 'post_status' => 'publish' ] ) as $wp_page ): ?>
										<label class="wpail-post-check">
											<input type="checkbox"
											       name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_PAGE_IDS ); ?>[]"
											       value="<?php echo esc_attr( (string) $wp_page->ID ); ?>"
											       <?php checked( in_array( $wp_page->ID, $faq_page_ids, true ) ); ?>>
											<?php echo esc_html( $wp_page->post_title ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description" style="margin-top:6px;">
									<?php esc_html_e( 'FAQPage schema will only be output on the pages checked above.', 'ai-layer' ); ?>
								</p>
							</div>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'API Endpoints', 'ai-layer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Endpoint Base URL', 'ai-layer' ); ?></th>
						<td>
							<code><?php echo esc_html( rest_url( WPAIL_REST_NS ) ); ?></code>
							<p class="description"><?php esc_html_e( 'Versioned namespace for all AI Layer endpoints.', 'ai-layer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Products Endpoint', 'ai-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::SETTING_PRODUCTS_ENABLED ); ?>"
								       value="1"
								       <?php checked( $products_enabled ); ?>
								       <?php disabled( ! $has_woocommerce ); ?>>
								<?php esc_html_e( 'Enable /products endpoint', 'ai-layer' ); ?>
							</label>
							<?php if ( $has_woocommerce ) : ?>
								<p class="description">
									<?php esc_html_e( 'Exposes your WooCommerce product catalogue at /products. Reads live from WooCommerce — no data duplication or extra database usage.', 'ai-layer' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php esc_html_e( 'WooCommerce is not active. Install and activate WooCommerce to use this endpoint.', 'ai-layer' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'AI Discovery', 'ai-layer' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Controls how AI systems and agents discover your AI Layer endpoints. This affects both /.well-known/ai-layer and the endpoints section of /llms.txt.', 'ai-layer' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Endpoint discovery mode', 'ai-layer' ); ?></th>
						<td>
							<fieldset>
								<label style="display:block; margin-bottom:12px;">
									<input type="radio"
									       name="<?php echo esc_attr( self::SETTING_AI_DISCOVERY_MODE ); ?>"
									       value="<?php echo esc_attr( self::AI_DISCOVERY_WELL_KNOWN ); ?>"
									       <?php checked( $ai_discovery_mode, self::AI_DISCOVERY_WELL_KNOWN ); ?>>
									<strong><?php esc_html_e( '/.well-known/ai-layer — recommended', 'ai-layer' ); ?></strong>
									<span class="description" style="display:block; margin-left:22px; margin-top:3px;">
										<?php
										printf(
											/* translators: %s: well-known URL */
											esc_html__( 'Endpoints defined as machine-readable JSON at %s — the single source of truth. /llms.txt links to it. Best for agents and tools that query the discovery document directly.', 'ai-layer' ),
											'<code>' . esc_html( home_url( '/.well-known/ai-layer' ) ) . '</code>'
										);
										?>
									</span>
								</label>
								<label style="display:block;">
									<input type="radio"
									       name="<?php echo esc_attr( self::SETTING_AI_DISCOVERY_MODE ); ?>"
									       value="<?php echo esc_attr( self::AI_DISCOVERY_LLMSTXT ); ?>"
									       <?php checked( $ai_discovery_mode, self::AI_DISCOVERY_LLMSTXT ); ?>>
									<strong><?php esc_html_e( '/llms.txt only', 'ai-layer' ); ?></strong>
									<span class="description" style="display:block; margin-left:22px; margin-top:3px;">
										<?php esc_html_e( 'Endpoints listed directly in /llms.txt. /.well-known/ai-layer returns 404. Use this if you prefer a single plain-text discovery file.', 'ai-layer' ); ?>
									</span>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Discovery link tags', 'ai-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::SETTING_HEAD_LINKS_ENABLED ); ?>"
								       value="1"
								       <?php checked( $head_links_enabled ); ?>>
								<?php esc_html_e( 'Output AI discovery', 'ai-layer' ); ?>
								<code>&lt;link&gt;</code>
								<?php esc_html_e( 'tags in every page', 'ai-layer' ); ?>
								<code>&lt;head&gt;</code>
							</label>
							<p class="description">
								<?php esc_html_e( 'Signals to AI crawlers and agents where to find machine-readable business data. Outputs a rel="ai-layer" link for /.well-known/ai-layer (when active) and a rel="llms-txt" link for /llms.txt (when enabled). Enabled by default.', 'ai-layer' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Post Type Visibility', 'ai-layer' ); ?></h2>
				<p>
					<?php esc_html_e( 'By default, all AI Layer post types are private — they serve the REST API only and have no front-end URLs. Enable public access to make a post type available in your theme so your content and API layer share a single source of data.', 'ai-layer' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'When enabled, WordPress creates a front-end archive and single-post URL for that post type. Add a template file in your theme (e.g. archive-wpail_service.php, single-wpail_service.php) to control how the content displays. Permalink rules are refreshed automatically after saving.', 'ai-layer' ); ?>
				</p>

				<?php
				$cpt_rows = [
					[
						'label'      => __( 'Services', 'ai-layer' ),
						'public_key' => self::SETTING_SERVICE_PUBLIC,
						'slug_key'   => self::SETTING_SERVICE_SLUG,
						'is_public'  => $service_public,
						'slug'       => $service_slug ?: 'services',
						'default'    => 'services',
					],
					[
						'label'      => __( 'Locations', 'ai-layer' ),
						'public_key' => self::SETTING_LOCATION_PUBLIC,
						'slug_key'   => self::SETTING_LOCATION_SLUG,
						'is_public'  => $location_public,
						'slug'       => $location_slug ?: 'locations',
						'default'    => 'locations',
					],
					[
						'label'      => __( 'FAQs', 'ai-layer' ),
						'public_key' => self::SETTING_FAQ_PUBLIC,
						'slug_key'   => self::SETTING_FAQ_SLUG,
						'is_public'  => $faq_public,
						'slug'       => $faq_slug ?: 'faqs',
						'default'    => 'faqs',
					],
					[
						'label'      => __( 'Proof & Trust', 'ai-layer' ),
						'public_key' => self::SETTING_PROOF_PUBLIC,
						'slug_key'   => self::SETTING_PROOF_SLUG,
						'is_public'  => $proof_public,
						'slug'       => $proof_slug ?: 'proof',
						'default'    => 'proof',
					],
				];
				?>
				<table class="form-table" role="presentation">
					<?php foreach ( $cpt_rows as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox"
									       name="<?php echo esc_attr( $row['public_key'] ); ?>"
									       value="1"
									       <?php checked( $row['is_public'] ); ?>>
									<?php esc_html_e( 'Enable public front-end access', 'ai-layer' ); ?>
								</label>
								<div style="margin-top: 8px;">
									<label>
										<?php esc_html_e( 'Rewrite slug:', 'ai-layer' ); ?>
										<input type="text"
										       name="<?php echo esc_attr( $row['slug_key'] ); ?>"
										       value="<?php echo esc_attr( $row['slug'] ); ?>"
										       placeholder="<?php echo esc_attr( $row['default'] ); ?>"
										       class="regular-text"
										       style="width: 180px; margin-left: 6px;">
									</label>
									<p class="description">
										<?php
										printf(
											/* translators: 1: archive URL, 2: single post URL example */
											esc_html__( 'Archive: %1$s — Single: %2$s', 'ai-layer' ),
											'<code>' . esc_html( home_url( '/' . $row['slug'] . '/' ) ) . '</code>',
											'<code>' . esc_html( home_url( '/' . $row['slug'] . '/example-post/' ) ) . '</code>'
										);
										?>
									</p>
								</div>
							</fieldset>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Data Management', 'ai-layer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr id="wpail-analytics-retention">
						<th scope="row"><?php esc_html_e( 'Analytics retention', 'ai-layer' ); ?></th>
						<td>
							<input type="number"
							       name="<?php echo esc_attr( self::SETTING_ANALYTICS_RETENTION_DAYS ); ?>"
							       value="<?php echo $analytics_retention > 0 ? esc_attr( (string) $analytics_retention ) : ''; ?>"
							       min="1"
							       placeholder="365"
							       class="small-text">
							<span style="margin-left:6px;"><?php esc_html_e( 'days', 'ai-layer' ); ?></span>
							<p class="description">
								<?php esc_html_e( 'How long to keep analytics data. Leave blank for unlimited. Data older than this threshold is automatically deleted each day. Default: 365 days.', 'ai-layer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Remove data on deletion', 'ai-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::SETTING_DELETE_ON_UNINSTALL ); ?>"
								       value="1"
								       <?php checked( $delete_on_uninstall ); ?>>
								<?php esc_html_e( 'Delete all AI Layer data when the plugin is removed', 'ai-layer' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, deleting this plugin will permanently erase all posts (services, locations, FAQs, proof, actions, answers), the business profile, all settings, and all cached data. This cannot be undone. Leave disabled if you may reinstall the plugin later.', 'ai-layer' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="submit" class="button button-primary"
					       value="<?php esc_attr_e( 'Save Settings', 'ai-layer' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	private static function sanitize_rewrite_slug( string $raw, string $default ): string {
		$slug = sanitize_title( wp_unslash( $raw ) );
		return $slug !== '' ? $slug : $default;
	}

	private static function sanitize_retention_days( mixed $raw ): int {
		$str = trim( (string) wp_unslash( $raw ) );
		if ( '' === $str ) {
			return 0; // 0 = unlimited.
		}
		return max( 1, absint( $str ) );
	}
}

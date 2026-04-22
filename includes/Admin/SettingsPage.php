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
			wp_die( esc_html__( 'Security check failed.', 'ai-ready-layer' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ai-ready-layer' ) );
		}

		$raw_page_ids = isset( $_POST[ self::SETTING_SCHEMA_FAQ_PAGE_IDS ] )
			? (array) $_POST[ self::SETTING_SCHEMA_FAQ_PAGE_IDS ]
			: [];

		$settings = [
			self::SETTING_SCHEMA_ENABLED        => isset( $_POST[ self::SETTING_SCHEMA_ENABLED ] ),
			self::SETTING_SCHEMA_ORG_TYPE       => sanitize_text_field( wp_unslash( $_POST[ self::SETTING_SCHEMA_ORG_TYPE ] ?? '' ) ),
			self::SETTING_SCHEMA_FAQ_ENABLED    => isset( $_POST[ self::SETTING_SCHEMA_FAQ_ENABLED ] ),
			self::SETTING_SCHEMA_FAQ_PAGES_MODE => in_array( $_POST[ self::SETTING_SCHEMA_FAQ_PAGES_MODE ] ?? '', [ 'all', 'specific' ], true )
				? $_POST[ self::SETTING_SCHEMA_FAQ_PAGES_MODE ]
				: 'all',
			self::SETTING_SCHEMA_FAQ_PAGE_IDS   => array_values( array_filter( array_map( 'absint', $raw_page_ids ) ) ),
			self::SETTING_ENDPOINT_CACHE_TTL    => absint( $_POST[ self::SETTING_ENDPOINT_CACHE_TTL ] ?? 0 ),
			self::SETTING_PRODUCTS_ENABLED      => isset( $_POST[ self::SETTING_PRODUCTS_ENABLED ] ),
			self::SETTING_SERVICE_PUBLIC        => isset( $_POST[ self::SETTING_SERVICE_PUBLIC ] ),
			self::SETTING_SERVICE_SLUG          => self::sanitize_rewrite_slug( $_POST[ self::SETTING_SERVICE_SLUG ] ?? '', 'services' ),
			self::SETTING_LOCATION_PUBLIC       => isset( $_POST[ self::SETTING_LOCATION_PUBLIC ] ),
			self::SETTING_LOCATION_SLUG         => self::sanitize_rewrite_slug( $_POST[ self::SETTING_LOCATION_SLUG ] ?? '', 'locations' ),
			self::SETTING_FAQ_PUBLIC            => isset( $_POST[ self::SETTING_FAQ_PUBLIC ] ),
			self::SETTING_FAQ_SLUG              => self::sanitize_rewrite_slug( $_POST[ self::SETTING_FAQ_SLUG ] ?? '', 'faqs' ),
			self::SETTING_PROOF_PUBLIC          => isset( $_POST[ self::SETTING_PROOF_PUBLIC ] ),
			self::SETTING_PROOF_SLUG            => self::sanitize_rewrite_slug( $_POST[ self::SETTING_PROOF_SLUG ] ?? '', 'proof' ),
		];

		update_option( WPAIL_OPT_SETTINGS, $settings );

		// Schedule a rewrite flush for the next request — CPTs will register with
		// the new visibility settings and the rules will be regenerated cleanly.
		set_transient( 'wpail_flush_rewrite', true, MINUTE_IN_SECONDS );

		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Settings saved.', 'ai-ready-layer' );
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

		$service_public      = (bool) self::get( self::SETTING_SERVICE_PUBLIC, false );
		$service_slug        = (string) self::get( self::SETTING_SERVICE_SLUG, 'services' );
		$location_public     = (bool) self::get( self::SETTING_LOCATION_PUBLIC, false );
		$location_slug       = (string) self::get( self::SETTING_LOCATION_SLUG, 'locations' );
		$faq_public          = (bool) self::get( self::SETTING_FAQ_PUBLIC, false );
		$faq_slug            = (string) self::get( self::SETTING_FAQ_SLUG, 'faqs' );
		$proof_public        = (bool) self::get( self::SETTING_PROOF_PUBLIC, false );
		$proof_slug          = (string) self::get( self::SETTING_PROOF_SLUG, 'proof' );

		// Detect conflicting SEO plugins.
		$has_yoast     = defined( 'WPSEO_VERSION' );
		$has_rank_math = defined( 'RANK_MATH_VERSION' );
		?>
		<div class="wrap wpail-admin">
			<h1><?php esc_html_e( 'AI Layer Settings', 'ai-ready-layer' ); ?></h1>

			<?php if ( $has_yoast || $has_rank_math ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php if ( $has_yoast ) : ?>
							<strong><?php esc_html_e( 'Yoast SEO detected.', 'ai-ready-layer' ); ?></strong>
						<?php endif; ?>
						<?php if ( $has_rank_math ) : ?>
							<strong><?php esc_html_e( 'Rank Math detected.', 'ai-ready-layer' ); ?></strong>
						<?php endif; ?>
						<?php esc_html_e( 'Schema output is disabled by default to avoid duplication. Enable carefully and review your pages.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<h2><?php esc_html_e( 'Schema.org Output', 'ai-ready-layer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Schema Output', 'ai-ready-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::SETTING_SCHEMA_ENABLED ); ?>" value="1"
								       <?php checked( $schema_enabled ); ?>>
								<?php esc_html_e( 'Output JSON-LD schema on site pages', 'ai-ready-layer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Outputs a JSON-LD block in &lt;head&gt; using the Schema.org type selected below, populated from your Business Profile.', 'ai-ready-layer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Schema.org Type', 'ai-ready-layer' ); ?></th>
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
						<th scope="row"><?php esc_html_e( 'Enable FAQPage Schema', 'ai-ready-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_ENABLED ); ?>" value="1"
								       <?php checked( $schema_faq_enabled ); ?>>
								<?php esc_html_e( 'Output FAQPage JSON-LD using all published public FAQs', 'ai-ready-layer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'FAQPage target pages', 'ai-ready-layer' ); ?></th>
						<td>
							<label style="display:block; margin-bottom:6px;">
								<input type="radio" name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_PAGES_MODE ); ?>"
								       value="all" <?php checked( $faq_pages_mode, 'all' ); ?>>
								<?php esc_html_e( 'All pages', 'ai-ready-layer' ); ?>
							</label>
							<label style="display:block; margin-bottom:8px;">
								<input type="radio" name="<?php echo esc_attr( self::SETTING_SCHEMA_FAQ_PAGES_MODE ); ?>"
								       id="wpail_faq_mode_specific"
								       value="specific" <?php checked( $faq_pages_mode, 'specific' ); ?>>
								<?php esc_html_e( 'Specific pages', 'ai-ready-layer' ); ?>
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
									<?php esc_html_e( 'FAQPage schema will only be output on the pages checked above.', 'ai-ready-layer' ); ?>
								</p>
							</div>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'API Endpoints', 'ai-ready-layer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Endpoint Base URL', 'ai-ready-layer' ); ?></th>
						<td>
							<code><?php echo esc_html( rest_url( WPAIL_REST_NS ) ); ?></code>
							<p class="description"><?php esc_html_e( 'Versioned namespace for all AI Layer endpoints.', 'ai-ready-layer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Products Endpoint', 'ai-ready-layer' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
								       name="<?php echo esc_attr( self::SETTING_PRODUCTS_ENABLED ); ?>"
								       value="1"
								       <?php checked( $products_enabled ); ?>
								       <?php disabled( ! $has_woocommerce ); ?>>
								<?php esc_html_e( 'Enable /products endpoint', 'ai-ready-layer' ); ?>
							</label>
							<?php if ( $has_woocommerce ) : ?>
								<p class="description">
									<?php esc_html_e( 'Exposes your WooCommerce product catalogue at /products. Reads live from WooCommerce — no data duplication or extra database usage.', 'ai-ready-layer' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php esc_html_e( 'WooCommerce is not active. Install and activate WooCommerce to use this endpoint.', 'ai-ready-layer' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Post Type Visibility', 'ai-ready-layer' ); ?></h2>
				<p>
					<?php esc_html_e( 'By default, all AI Layer post types are private — they serve the REST API only and have no front-end URLs. Enable public access to make a post type available in your theme so your content and API layer share a single source of data.', 'ai-ready-layer' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'When enabled, WordPress creates a front-end archive and single-post URL for that post type. Add a template file in your theme (e.g. archive-wpail_service.php, single-wpail_service.php) to control how the content displays. Permalink rules are refreshed automatically after saving.', 'ai-ready-layer' ); ?>
				</p>

				<?php
				$cpt_rows = [
					[
						'label'      => __( 'Services', 'ai-ready-layer' ),
						'public_key' => self::SETTING_SERVICE_PUBLIC,
						'slug_key'   => self::SETTING_SERVICE_SLUG,
						'is_public'  => $service_public,
						'slug'       => $service_slug ?: 'services',
						'default'    => 'services',
					],
					[
						'label'      => __( 'Locations', 'ai-ready-layer' ),
						'public_key' => self::SETTING_LOCATION_PUBLIC,
						'slug_key'   => self::SETTING_LOCATION_SLUG,
						'is_public'  => $location_public,
						'slug'       => $location_slug ?: 'locations',
						'default'    => 'locations',
					],
					[
						'label'      => __( 'FAQs', 'ai-ready-layer' ),
						'public_key' => self::SETTING_FAQ_PUBLIC,
						'slug_key'   => self::SETTING_FAQ_SLUG,
						'is_public'  => $faq_public,
						'slug'       => $faq_slug ?: 'faqs',
						'default'    => 'faqs',
					],
					[
						'label'      => __( 'Proof & Trust', 'ai-ready-layer' ),
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
									<?php esc_html_e( 'Enable public front-end access', 'ai-ready-layer' ); ?>
								</label>
								<div style="margin-top: 8px;">
									<label>
										<?php esc_html_e( 'Rewrite slug:', 'ai-ready-layer' ); ?>
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
											esc_html__( 'Archive: %1$s — Single: %2$s', 'ai-ready-layer' ),
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

				<p class="submit">
					<input type="submit" name="submit" class="button button-primary"
					       value="<?php esc_attr_e( 'Save Settings', 'ai-ready-layer' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	private static function sanitize_rewrite_slug( string $raw, string $default ): string {
		$slug = sanitize_title( wp_unslash( $raw ) );
		return $slug !== '' ? $slug : $default;
	}
}

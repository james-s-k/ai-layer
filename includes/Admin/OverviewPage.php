<?php
/**
 * Overview / dashboard page — shown when the user clicks "AI Layer" in the sidebar.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Licensing\Features;
use WPAIL\Licensing\License;

class OverviewPage {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$profile_complete  = self::is_profile_complete();
		$counts            = self::get_counts();
		$rest_base         = rest_url( WPAIL_REST_NS );
		$schema_enabled    = (bool) SettingsPage::get( SettingsPage::SETTING_SCHEMA_ENABLED, false );
		$products_enabled  = (bool) SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED, false );
		$has_woocommerce   = class_exists( 'WooCommerce' );
		?>
		<div class="wrap wpail-admin wpail-overview">

			<div class="wpail-overview__header">
				<span class="dashicons dashicons-networking wpail-overview__icon"></span>
				<div>
					<h1><?php esc_html_e( 'AI Layer', 'ai-ready-layer' ); ?></h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'A structured knowledge layer that exposes your business data to AI systems, agents, and search tools via a clean REST API.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			</div>

			<?php if ( ! $profile_complete ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Get started:', 'ai-ready-layer' ); ?></strong>
						<?php
						printf(
							/* translators: 1: link to Setup Wizard, 2: link to Business Profile */
							esc_html__( 'Run the %1$s to auto-populate your Business Profile from existing plugins, or fill it in %2$s.', 'ai-ready-layer' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=wpail_setup_wizard' ) ) . '">' . esc_html__( 'Setup Wizard', 'ai-ready-layer' ) . '</a>',
							'<a href="' . esc_url( admin_url( 'admin.php?page=wpail_business_profile' ) ) . '">' . esc_html__( 'manually', 'ai-ready-layer' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php /* ── How it works ── */ ?>
			<h2><?php esc_html_e( 'How It Works', 'ai-ready-layer' ); ?></h2>
			<p class="wpail-overview__intro">
				<?php esc_html_e( 'AI Layer stores your business data in structured, typed entities inside WordPress. Everything you enter here is served through a public REST API — no scraping, no guesswork. AI chatbots, voice assistants, and search integrations can query a single authoritative source for accurate information about your services, locations, FAQs, and more.', 'ai-ready-layer' ); ?>
			</p>

			<?php /* ── Content types: cards are the single hub (no duplicate step list) ── */ ?>
			<h2><?php esc_html_e( 'Your content', 'ai-ready-layer' ); ?></h2>
			<p class="wpail-overview__content-intro">
				<?php esc_html_e( 'Typical order: Business Profile → Services & Locations → FAQs & Proof → Actions. Each card links to the editor; counts show published items. Connect integrations from the REST API section below.', 'ai-ready-layer' ); ?>
			</p>
			<div class="wpail-cards">

				<?php
				$cards = [
					[
						'icon'     => 'dashicons-store',
						'label'    => __( 'Business Profile', 'ai-ready-layer' ),
						'desc'     => __( 'Identity, contact, address, opening hours, and social links.', 'ai-ready-layer' ),
						'count'    => null,
						'status'   => $profile_complete ? __( 'Complete', 'ai-ready-layer' ) : __( 'Incomplete', 'ai-ready-layer' ),
						'status_ok'=> $profile_complete,
						'url'      => admin_url( 'admin.php?page=wpail_business_profile' ),
						'cta'      => __( 'Edit Profile', 'ai-ready-layer' ),
					],
					[
						'icon'     => 'dashicons-clipboard',
						'label'    => __( 'Services', 'ai-ready-layer' ),
						'desc'     => __( 'What you offer — keywords, pricing, benefits, and customer types (used for intent matching).', 'ai-ready-layer' ),
						'count'    => $counts['wpail_service'],
						'url'      => admin_url( 'edit.php?post_type=wpail_service' ),
						'new_url'  => admin_url( 'post-new.php?post_type=wpail_service' ),
						'cta'      => __( 'Manage Services', 'ai-ready-layer' ),
					],
					[
						'icon'     => 'dashicons-location',
						'label'    => __( 'Locations', 'ai-ready-layer' ),
						'desc'     => __( 'Physical or virtual locations with postcodes, regions, and service radius.', 'ai-ready-layer' ),
						'count'    => $counts['wpail_location'],
						'url'      => admin_url( 'edit.php?post_type=wpail_location' ),
						'new_url'  => admin_url( 'post-new.php?post_type=wpail_location' ),
						'cta'      => __( 'Manage Locations', 'ai-ready-layer' ),
					],
					[
						'icon'     => 'dashicons-editor-help',
						'label'    => __( 'FAQs', 'ai-ready-layer' ),
						'desc'     => __( 'Questions and answers — main source for dynamic replies in /answers.', 'ai-ready-layer' ),
						'count'    => $counts['wpail_faq'],
						'url'      => admin_url( 'edit.php?post_type=wpail_faq' ),
						'new_url'  => admin_url( 'post-new.php?post_type=wpail_faq' ),
						'cta'      => __( 'Manage FAQs', 'ai-ready-layer' ),
					],
					[
						'icon'     => 'dashicons-awards',
						'label'    => __( 'Proof & Trust', 'ai-ready-layer' ),
						'desc'     => __( 'Testimonials, accreditations, stats, awards, and case studies returned alongside answers.', 'ai-ready-layer' ),
						'count'    => $counts['wpail_proof'],
						'url'      => admin_url( 'edit.php?post_type=wpail_proof' ),
						'new_url'  => admin_url( 'post-new.php?post_type=wpail_proof' ),
						'cta'      => __( 'Manage Proof', 'ai-ready-layer' ),
					],
					[
						'icon'     => 'dashicons-arrow-right-alt',
						'label'    => __( 'Actions', 'ai-ready-layer' ),
						'desc'     => __( 'Calls-to-action returned with every answer — book, call, email, quote, and more.', 'ai-ready-layer' ),
						'count'    => $counts['wpail_action'],
						'url'      => admin_url( 'edit.php?post_type=wpail_action' ),
						'new_url'  => admin_url( 'post-new.php?post_type=wpail_action' ),
						'cta'      => __( 'Manage Actions', 'ai-ready-layer' ),
					],
					Features::answers_enabled()
					? [
						'icon'    => 'dashicons-lightbulb',
						'label'   => __( 'Answers', 'ai-ready-layer' ),
						'desc'    => __( 'Manually authored answers that override the dynamic engine for predictable queries.', 'ai-ready-layer' ),
						'count'   => $counts['wpail_answer'],
						'url'     => admin_url( 'edit.php?post_type=wpail_answer' ),
						'new_url' => admin_url( 'post-new.php?post_type=wpail_answer' ),
						'cta'     => __( 'Manage Answers', 'ai-ready-layer' ),
					]
					: [
						'icon'    => 'dashicons-lightbulb',
						'label'   => __( 'Answers', 'ai-ready-layer' ),
						'desc'    => __( 'Author guaranteed responses and let the answer engine handle natural language queries — with service detection, location detection, and confidence scoring.', 'ai-ready-layer' ),
						'count'   => null,
						'locked'  => true,
						'url'     => admin_url( 'admin.php?page=wpail_answers_upgrade' ),
						'cta'     => __( 'Learn More', 'ai-ready-layer' ),
					],
				];

				foreach ( $cards as $card ) :
					$is_locked  = ! empty( $card['locked'] );
					$has_status = isset( $card['status'] );
					?>
					<div class="wpail-card <?php echo $is_locked ? 'wpail-card--locked' : ''; ?>">
						<div class="wpail-card__header">
							<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?> wpail-card__icon"></span>
							<strong class="wpail-card__title"><?php echo esc_html( $card['label'] ); ?></strong>
							<?php if ( $is_locked ) : ?>
								<span class="wpail-pro-badge"><?php esc_html_e( 'Pro', 'ai-ready-layer' ); ?></span>
							<?php elseif ( null !== $card['count'] ) : ?>
								<span class="wpail-card__count"><?php echo esc_html( (string) $card['count'] ); ?></span>
							<?php endif; ?>
							<?php if ( $has_status ) : ?>
								<span class="wpail-card__status <?php echo $card['status_ok'] ? 'wpail-card__status--ok' : 'wpail-card__status--warn'; ?>">
									<?php echo esc_html( $card['status'] ); ?>
								</span>
							<?php endif; ?>
						</div>
						<p class="wpail-card__desc"><?php echo esc_html( $card['desc'] ); ?></p>
						<div class="wpail-card__actions">
							<?php if ( $is_locked ) : ?>
								<a href="<?php echo esc_url( License::upgrade_url() ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'Upgrade to Pro', 'ai-ready-layer' ); ?>
								</a>
								<a href="<?php echo esc_url( $card['url'] ); ?>" class="button button-secondary">
									<?php echo esc_html( $card['cta'] ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $card['url'] ); ?>" class="button button-secondary">
									<?php echo esc_html( $card['cta'] ); ?>
								</a>
								<?php if ( ! empty( $card['new_url'] ) ) : ?>
									<a href="<?php echo esc_url( $card['new_url'] ); ?>" class="button">
										+ <?php esc_html_e( 'Add New', 'ai-ready-layer' ); ?>
									</a>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

			</div>

			<?php /* ── API reference (matches RestRegistrar + controllers) ── */ ?>
			<h2><?php esc_html_e( 'REST API Endpoints', 'ai-ready-layer' ); ?></h2>
			<p>
				<?php esc_html_e( 'All routes are GET, public, and read-only. Paths are relative to the base URL below.', 'ai-ready-layer' ); ?>
			</p>
			<p class="wpail-overview__endpoint-legend">
				<?php esc_html_e( 'Path: part of the address after the base URL (some routes end with a slug you replace). Query string: text after ? in the URL — key=value pairs separated by &.', 'ai-ready-layer' ); ?>
			</p>

			<table class="widefat striped wpail-endpoint-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Endpoint', 'ai-ready-layer' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What it returns', 'ai-ready-layer' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Path', 'ai-ready-layer' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Query string', 'ai-ready-layer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$dash = '—';
					$endpoints = [
						[
							'path' => '/profile',
							'desc' => __( 'Canonical business name, contact details, address, and hours.', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => $dash,
							'pro' => false,
						],
						[
							'path' => '/services',
							'desc' => __( 'All published services (summary list).', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => $dash,
							'pro' => false,
						],
						[
							'path' => '/services/{slug}',
							'desc' => __( 'Full service detail with related FAQs, proof, actions, and locations.', 'ai-ready-layer' ),
							'path_note' => __( 'Swap {slug} for the service\'s URL slug (lowercase, hyphens). Example: …/services/acme-plumbing', 'ai-ready-layer' ),
							'query_note' => $dash,
							'pro' => false,
						],
						[
							'path' => '/locations',
							'desc' => __( 'All published locations (summary list).', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => $dash,
							'pro' => false,
						],
						[
							'path' => '/locations/{slug}',
							'desc' => __( 'Full location detail with related services and local proof.', 'ai-ready-layer' ),
							'path_note' => __( 'Swap {slug} for the location\'s URL slug (lowercase, hyphens).', 'ai-ready-layer' ),
							'query_note' => $dash,
							'pro' => false,
						],
						[
							'path' => '/faqs',
							'desc' => __( 'Published FAQs with short and long answers.', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => __( 'Optional filters: service=N and/or location=N (N = WordPress post ID from the editor URL).', 'ai-ready-layer' ),
							'pro' => false,
						],
						[
							'path' => '/proof',
							'desc' => __( 'Trust signals — testimonials, accreditations, stats, awards.', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => __( 'Optional: service=N (post ID) to limit proof linked to that service.', 'ai-ready-layer' ),
							'pro' => false,
						],
						[
							'path' => '/actions',
							'desc' => __( 'Calls-to-action — book, call, email, quote, etc.', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => __( 'Optional: service=N (post ID) to limit actions for that service.', 'ai-ready-layer' ),
							'pro' => false,
						],
					];

					if ( $has_woocommerce && $products_enabled ) {
						$endpoints[] = [
							'path'       => '/products',
							'desc'       => __( 'WooCommerce product catalogue (summary list).', 'ai-ready-layer' ),
							'path_note'  => $dash,
							'query_note' => __( 'Optional: per_page (default 20, max 100), page, category=slug.', 'ai-ready-layer' ),
							'pro'        => false,
						];
						$endpoints[] = [
							'path'       => '/products/{slug}',
							'desc'       => __( 'Full product detail with description, pricing, categories, and tags.', 'ai-ready-layer' ),
							'path_note'  => __( 'Swap {slug} for the product\'s URL slug (lowercase, hyphens).', 'ai-ready-layer' ),
							'query_note' => $dash,
							'pro'        => false,
						];
					}

					$endpoints = array_merge( $endpoints, [
						[
							'path' => '/answers',
							'desc' => __( 'Assembled answer to a natural language query, with actions and supporting proof.', 'ai-ready-layer' ),
							'path_note' => $dash,
							'query_note' => __( 'Required: query=your question (URL-encoded). Optional hints: service=N, location=N (post IDs).', 'ai-ready-layer' ),
							'pro' => true,
						],
					] );

					foreach ( $endpoints as $row ) :
						$pro_only = $row['pro'];
						$row_class = ( $pro_only && ! Features::answers_enabled() ) ? 'wpail-endpoint--pro' : '';
						?>
						<tr class="<?php echo esc_attr( $row_class ); ?>">
							<td>
								<code><?php echo esc_html( $row['path'] ); ?></code>
								<?php if ( $pro_only && ! Features::answers_enabled() ) : ?>
									<span class="wpail-pro-badge"><?php esc_html_e( 'Pro', 'ai-ready-layer' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['desc'] ); ?></td>
							<td class="wpail-endpoint-table__param"><small><?php echo esc_html( $row['path_note'] ); ?></small></td>
							<td class="wpail-endpoint-table__param"><small><?php echo esc_html( $row['query_note'] ); ?></small></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="wpail-overview__api-url">
				<?php esc_html_e( 'Base URL:', 'ai-ready-layer' ); ?>
				<code><?php echo esc_html( $rest_base ); ?></code>
			</p>

			<?php /* ── Schema status ── */ ?>
			<h2><?php esc_html_e( 'Structured data (Schema.org)', 'ai-ready-layer' ); ?></h2>
			<p class="wpail-overview__schema-desc">
				<?php esc_html_e( 'Optional JSON-LD in your site\'s <head> so search engines can understand your business data.', 'ai-ready-layer' ); ?>
			</p>
			<?php if ( $schema_enabled ) : ?>
				<p class="wpail-overview__schema-status">
					<span class="wpail-status wpail-status--on"><?php esc_html_e( 'On', 'ai-ready-layer' ); ?></span>
					<?php esc_html_e( 'JSON-LD is active. Change the schema type in', 'ai-ready-layer' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_settings' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-ready-layer' ); ?></a>.
				</p>
			<?php else : ?>
				<p class="wpail-overview__schema-status">
					<span class="wpail-status wpail-status--off"><?php esc_html_e( 'Off', 'ai-ready-layer' ); ?></span>
					<?php esc_html_e( 'Turn it on in', 'ai-ready-layer' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_settings' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-ready-layer' ); ?></a>
					<?php esc_html_e( 'when you want rich-result markup in your HTML.', 'ai-ready-layer' ); ?>
				</p>
			<?php endif; ?>

		</div>
		<?php
	}

	private static function is_profile_complete(): bool {
		$profile = get_option( WPAIL_OPT_BUSINESS, [] );
		return ! empty( $profile['name'] ) && ! empty( $profile['phone'] );
	}

	/**
	 * @return array<string, int>
	 */
	private static function get_counts(): array {
		$types = [ 'wpail_service', 'wpail_location', 'wpail_faq', 'wpail_proof', 'wpail_action', 'wpail_answer' ];
		$out   = [];
		foreach ( $types as $type ) {
			$result     = wp_count_posts( $type );
			$out[$type] = isset( $result->publish ) ? (int) $result->publish : 0;
		}
		return $out;
	}
}

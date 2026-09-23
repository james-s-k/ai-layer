<?php
/**
 * Onboarding / setup completion assessment.
 *
 * @package WPAIL\Setup
 */

declare(strict_types=1);

namespace WPAIL\Setup;

use WPAIL\AI\AiSettings;
use WPAIL\Admin\SettingsPage;
use WPAIL\AiTxt\AiTxtSettings;
use WPAIL\Licensing\Features;
use WPAIL\LLMsTxt\LLMsTxtSettings;

class SetupProgress {

	public const TIER_ESSENTIAL    = 'essential';
	public const TIER_RECOMMENDED  = 'recommended';
	public const TIER_OPTIONAL     = 'optional';

	/**
	 * @return array{
	 *   percent: int,
	 *   complete_count: int,
	 *   total_count: int,
	 *   essential: list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}>,
	 *   recommended: list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}>,
	 *   optional: list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}>,
	 *   essential_complete: int,
	 *   essential_total: int,
	 *   recommended_complete: int,
	 *   recommended_total: int
	 * }
	 */
	public static function assess(): array {
		$profile     = self::get_profile();
		$llms        = LLMsTxtSettings::get_all();
		$llms_on     = (bool) ( $llms['enabled'] ?? false );
		$has_woo     = class_exists( 'WooCommerce' );
		$schema_on   = (bool) SettingsPage::get( SettingsPage::SETTING_SCHEMA_ENABLED, false );

		$items = [];

		// ── Essential: minimum viable layer ──────────────────────────────
		$items[] = self::item(
			self::TIER_ESSENTIAL,
			'profile_core',
			__( 'Business profile', 'ai-layer' ),
			__( 'Business name and at least one contact method (phone or email).', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			self::is_profile_complete()
		);

		foreach (
			[
				'wpail_service'  => [
					__( 'Services', 'ai-layer' ),
					__( 'At least one service — the core of what you offer.', 'ai-layer' ),
					'edit.php?post_type=wpail_service',
				],
				'wpail_faq'      => [
					__( 'FAQs', 'ai-layer' ),
					__( 'At least one FAQ for the answer engine to draw from.', 'ai-layer' ),
					'edit.php?post_type=wpail_faq',
				],
				'wpail_action'   => [
					__( 'Actions', 'ai-layer' ),
					__( 'At least one call-to-action returned alongside answers.', 'ai-layer' ),
					'edit.php?post_type=wpail_action',
				],
			] as $type => [ $label, $empty_desc, $url_path ]
		) {
			$counts = self::get_entity_counts( $type );
			$items[] = self::item(
				self::TIER_ESSENTIAL,
				'entity_' . $type,
				$label,
				self::entity_status_description( $counts, $empty_desc ),
				admin_url( $url_path ),
				$counts['total'] > 0
			);
		}

		// ── Recommended: profile depth ───────────────────────────────────
		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'profile_summary',
			__( 'Short summary', 'ai-layer' ),
			__( 'A 1–2 sentence description used in API responses, schema, and llms.txt.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			'' !== self::profile_field( 'short_summary' )
		);

		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'profile_business_type',
			__( 'Business type', 'ai-layer' ),
			__( 'Schema.org category — helps search engines classify your business.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			'' !== self::profile_field( 'business_type' )
		);

		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'profile_address',
			__( 'Business address', 'ai-layer' ),
			__( 'Street or city — improves local and location-aware answers.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			'' !== self::profile_field( 'address_line1' ) || '' !== self::profile_field( 'city' )
		);

		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'profile_opening_hours',
			__( 'Opening hours', 'ai-layer' ),
			__( 'When you are open — returned in profile and answer responses.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			'' !== self::profile_field( 'opening_hours' )
		);

		// ── Recommended: entity coverage ─────────────────────────────────
		foreach (
			[
				'wpail_location' => [
					__( 'Locations', 'ai-layer' ),
					__( 'Service areas or offices for location-aware answers.', 'ai-layer' ),
					'edit.php?post_type=wpail_location',
				],
				'wpail_proof'    => [
					__( 'Proof & Trust', 'ai-layer' ),
					__( 'Testimonials or accreditations attached to answers.', 'ai-layer' ),
					'edit.php?post_type=wpail_proof',
				],
			] as $type => [ $label, $empty_desc, $url_path ]
		) {
			$counts = self::get_entity_counts( $type );
			$items[] = self::item(
				self::TIER_RECOMMENDED,
				'entity_' . $type,
				$label,
				self::entity_status_description( $counts, $empty_desc ),
				admin_url( $url_path ),
				$counts['total'] > 0
			);
		}

		// ── Recommended: AI discovery settings ───────────────────────────
		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'llms_txt',
			'llms.txt',
			__( 'Serve /llms.txt so AI crawlers can discover your endpoints and content.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_llmstxt' ),
			$llms_on
		);

		if ( $llms_on ) {
			$discovery_mode = (string) SettingsPage::get(
				SettingsPage::SETTING_AI_DISCOVERY_MODE,
				SettingsPage::AI_DISCOVERY_WELL_KNOWN
			);
			$is_llmstxt_mode = SettingsPage::AI_DISCOVERY_LLMSTXT === $discovery_mode;

			$items[] = self::item(
				self::TIER_RECOMMENDED,
				'llms_endpoints',
				__( 'llms.txt — endpoint discovery', 'ai-layer' ),
				$is_llmstxt_mode
					? __( 'Enable the endpoints section so llms.txt lists your REST API URLs (including /answers when checked below).', 'ai-layer' )
					: __( 'Enable the endpoints section — adds manifest, OpenAPI, and a link to /.well-known/ai-layer (which lists all endpoints including /answers).', 'ai-layer' ),
				admin_url( 'admin.php?page=wpail_llmstxt' ),
				self::is_llms_endpoints_configured( $llms, $discovery_mode )
			);
		}

		$discovery_settings = [
			[
				'discovery_head_links',
				__( 'Discovery link tags', 'ai-layer' ),
				__( 'Output <link> tags in <head> pointing to your AI Layer endpoints.', 'ai-layer' ),
				SettingsPage::SETTING_HEAD_LINKS_ENABLED,
				true,
			],
			[
				'discovery_robots',
				__( 'robots.txt injection', 'ai-layer' ),
				__( 'Add AI Layer directives to your robots.txt.', 'ai-layer' ),
				SettingsPage::SETTING_ROBOTS_INJECTION_ENABLED,
				true,
			],
			[
				'discovery_headers',
				__( 'HTTP discovery headers', 'ai-layer' ),
				__( 'Send Link and X-AI-Layer headers on frontend responses.', 'ai-layer' ),
				SettingsPage::SETTING_HTTP_HEADERS_ENABLED,
				true,
			],
			[
				'discovery_ai_page',
				__( '/ai-layer discovery page', 'ai-layer' ),
				__( 'Human-readable endpoint listing at /ai-layer.', 'ai-layer' ),
				SettingsPage::SETTING_AI_LAYER_PAGE_ENABLED,
				true,
			],
			[
				'discovery_sitemap',
				__( 'AI Layer sitemap', 'ai-layer' ),
				__( 'XML sitemap listing manifest, OpenAPI, and key endpoints.', 'ai-layer' ),
				SettingsPage::SETTING_SITEMAP_ENABLED,
				true,
			],
		];

		foreach ( $discovery_settings as [ $id, $label, $desc, $setting_key, $default ] ) {
			$items[] = self::item(
				self::TIER_RECOMMENDED,
				$id,
				$label,
				$desc,
				admin_url( 'admin.php?page=wpail_settings' ),
				(bool) SettingsPage::get( $setting_key, $default )
			);
		}

		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'ai_provider',
			__( 'AI provider', 'ai-layer' ),
			__( 'Connect OpenAI, Anthropic, or Google for AI Import and relationship tools.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_setup_wizard&step=ai' ),
			self::is_any_provider_configured()
		);

		$items[] = self::item(
			self::TIER_RECOMMENDED,
			'schema_output',
			__( 'Schema.org output', 'ai-layer' ),
			__( 'JSON-LD in your site head — disable if an SEO plugin already handles schema.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_settings' ),
			$schema_on
		);

		if ( $schema_on ) {
			$items[] = self::item(
				self::TIER_RECOMMENDED,
				'schema_faq',
				__( 'FAQPage schema', 'ai-layer' ),
				__( 'Output FAQPage JSON-LD from your published FAQs.', 'ai-layer' ),
				admin_url( 'admin.php?page=wpail_settings' ),
				(bool) SettingsPage::get( SettingsPage::SETTING_SCHEMA_FAQ_ENABLED, false )
			);
		}

		if ( $has_woo ) {
			$items[] = self::item(
				self::TIER_RECOMMENDED,
				'woocommerce_products',
				__( 'WooCommerce products endpoint', 'ai-layer' ),
				__( 'Expose your product catalogue at /products for AI agents.', 'ai-layer' ),
				admin_url( 'admin.php?page=wpail_settings' ),
				(bool) SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED, false )
			);
		}

		// ── Optional enhancements ────────────────────────────────────────
		$answer_counts = self::get_entity_counts( 'wpail_answer' );
		$items[] = self::item(
			self::TIER_OPTIONAL,
			'entity_wpail_answer',
			__( 'Authored Answers', 'ai-layer' ),
			self::entity_status_description(
				$answer_counts,
				__( 'Guaranteed word-for-word responses for specific questions.', 'ai-layer' )
			),
			admin_url( 'edit.php?post_type=wpail_answer' ),
			$answer_counts['total'] > 0
		);

		$items[] = self::item(
			self::TIER_OPTIONAL,
			'ai_txt',
			'AI.txt',
			__( 'Declare crawling and training preferences at /ai.txt.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_aitxt' ),
			(bool) AiTxtSettings::get( 'enabled', false )
		);

		if ( $llms_on && ! empty( $llms['include_pages'] ) ) {
			$items[] = self::item(
				self::TIER_OPTIONAL,
				'llms_pages',
				__( 'llms.txt — key pages linked', 'ai-layer' ),
				__( 'Map About, Contact, and other key pages in llms.txt.', 'ai-layer' ),
				admin_url( 'admin.php?page=wpail_llmstxt' ),
				self::has_llms_pages_configured( $llms )
			);
		}

		$items[] = self::item(
			self::TIER_OPTIONAL,
			'profile_trust_summary',
			__( 'Trust summary', 'ai-layer' ),
			__( 'A brief statement of why customers trust you.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			'' !== self::profile_field( 'trust_summary' )
		);

		$items[] = self::item(
			self::TIER_OPTIONAL,
			'profile_long_summary',
			__( 'Long summary', 'ai-layer' ),
			__( 'Extended business description for richer API responses.', 'ai-layer' ),
			admin_url( 'admin.php?page=wpail_business_profile' ),
			'' !== self::profile_field( 'long_summary' )
		);

		$essential   = array_values( array_filter( $items, static fn( array $i ): bool => self::TIER_ESSENTIAL === $i['tier'] ) );
		$recommended = array_values( array_filter( $items, static fn( array $i ): bool => self::TIER_RECOMMENDED === $i['tier'] ) );
		$optional    = array_values( array_filter( $items, static fn( array $i ): bool => self::TIER_OPTIONAL === $i['tier'] ) );

		$scored          = array_merge( $essential, $recommended );
		$complete_count  = count( array_filter( $scored, static fn( array $i ): bool => $i['complete'] ) );
		$total_count     = count( $scored );
		$percent         = $total_count > 0 ? (int) round( ( $complete_count / $total_count ) * 100 ) : 0;

		$essential_complete   = count( array_filter( $essential, static fn( array $i ): bool => $i['complete'] ) );
		$recommended_complete = count( array_filter( $recommended, static fn( array $i ): bool => $i['complete'] ) );

		return [
			'percent'              => $percent,
			'complete_count'       => $complete_count,
			'total_count'          => $total_count,
			'essential'            => $essential,
			'recommended'          => $recommended,
			'optional'             => $optional,
			'essential_complete'   => $essential_complete,
			'essential_total'      => count( $essential ),
			'recommended_complete' => $recommended_complete,
			'recommended_total'    => count( $recommended ),
		];
	}

	public static function is_profile_complete(): bool {
		$profile = self::get_profile();
		if ( empty( $profile ) ) {
			return false;
		}

		$has_name    = ! empty( $profile['name'] );
		$has_contact = ! empty( $profile['phone'] ) || ! empty( $profile['email'] );

		return $has_name && $has_contact;
	}

	public static function is_any_provider_configured(): bool {
		foreach ( array_keys( AiSettings::PROVIDER_LABELS ) as $provider ) {
			if ( AiSettings::is_provider_configured( $provider ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array{
	 *   percent: int,
	 *   complete_count: int,
	 *   total_count: int,
	 *   essential: list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}>,
	 *   recommended: list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}>,
	 *   optional: list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}>,
	 *   essential_complete: int,
	 *   essential_total: int,
	 *   recommended_complete: int,
	 *   recommended_total: int
	 * } $assessment
	 */
	public static function render_widget( array $assessment, bool $expand_incomplete = true ): void {
		$percent                = $assessment['percent'];
		$complete_count         = $assessment['complete_count'];
		$total_count            = $assessment['total_count'];
		$essential              = $assessment['essential'];
		$recommended            = $assessment['recommended'];
		$optional               = $assessment['optional'];
		$essential_incomplete   = array_filter( $essential, static fn( array $i ): bool => ! $i['complete'] );
		$recommended_incomplete = array_filter( $recommended, static fn( array $i ): bool => ! $i['complete'] );
		$optional_incomplete    = array_filter( $optional, static fn( array $i ): bool => ! $i['complete'] );
		?>
		<div class="wpail-setup-progress" role="region" aria-label="<?php esc_attr_e( 'Setup progress', 'ai-layer' ); ?>">
			<div class="wpail-setup-progress__head">
				<strong class="wpail-setup-progress__title"><?php esc_html_e( 'Setup progress', 'ai-layer' ); ?></strong>
				<span class="wpail-setup-progress__stat">
					<?php
					printf(
						/* translators: 1: completed count, 2: total count, 3: percentage */
						esc_html__( '%1$d of %2$d complete (%3$d%%)', 'ai-layer' ),
						$complete_count,
						$total_count,
						$percent
					);
					?>
				</span>
			</div>
			<p class="wpail-setup-progress__breakdown">
				<?php
				printf(
					/* translators: 1: essential complete, 2: essential total, 3: recommended complete, 4: recommended total */
					esc_html__( 'Essential: %1$d/%2$d · Recommended: %3$d/%4$d', 'ai-layer' ),
					$assessment['essential_complete'],
					$assessment['essential_total'],
					$assessment['recommended_complete'],
					$assessment['recommended_total']
				);
				?>
			</p>
			<div class="wpail-setup-progress__bar" aria-hidden="true">
				<div class="wpail-setup-progress__fill" style="width:<?php echo esc_attr( (string) max( 0, min( 100, $percent ) ) ); ?>%;"></div>
			</div>

			<?php if ( $expand_incomplete && ! empty( $essential_incomplete ) ) : ?>
				<div class="wpail-setup-progress__alert">
					<strong><?php esc_html_e( 'Essential — still required', 'ai-layer' ); ?></strong>
					<?php self::render_item_list( $essential_incomplete ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $expand_incomplete && ! empty( $recommended_incomplete ) ) : ?>
				<details class="wpail-setup-progress__details wpail-setup-progress__details--recommended" <?php echo $percent < 100 ? 'open' : ''; ?>>
					<summary>
						<?php
						printf(
							/* translators: %d: number of incomplete recommended items */
							esc_html( _n(
								'%d recommended setting not yet configured',
								'%d recommended settings not yet configured',
								count( $recommended_incomplete ),
								'ai-layer'
							) ),
							count( $recommended_incomplete )
						);
						?>
					</summary>
					<?php self::render_item_list( $recommended_incomplete ); ?>
				</details>
			<?php elseif ( 100 === $percent && empty( $essential_incomplete ) ) : ?>
				<p class="wpail-setup-progress__done"><?php esc_html_e( 'Essential and recommended setup is complete.', 'ai-layer' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $optional_incomplete ) ) : ?>
				<details class="wpail-setup-progress__details wpail-setup-progress__details--optional">
					<summary>
						<?php
						printf(
							/* translators: %d: number of incomplete optional items */
							esc_html( _n(
								'%d optional enhancement available',
								'%d optional enhancements available',
								count( $optional_incomplete ),
								'ai-layer'
							) ),
							count( $optional_incomplete )
						);
						?>
					</summary>
					<?php self::render_item_list( $optional_incomplete ); ?>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return array<string, mixed> */
	private static function get_profile(): array {
		$profile = get_option( WPAIL_OPT_BUSINESS, [] );
		return is_array( $profile ) ? $profile : [];
	}

	private static function profile_field( string $key ): string {
		$profile = self::get_profile();
		return trim( (string) ( $profile[ $key ] ?? '' ) );
	}

	/**
	 * @param list<array{id: string, label: string, description: string, url: string, complete: bool, tier: string}> $items
	 */
	private static function render_item_list( array $items ): void {
		?>
		<ul class="wpail-setup-progress__list">
			<?php foreach ( $items as $item ) : ?>
				<li>
					<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					<span class="wpail-setup-progress__desc"><?php echo esc_html( $item['description'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/** @return array{publish: int, draft: int, total: int} */
	private static function get_entity_counts( string $post_type ): array {
		$result   = wp_count_posts( $post_type );
		$publish  = isset( $result->publish ) ? (int) $result->publish : 0;
		$draft    = isset( $result->draft ) ? (int) $result->draft : 0;
		$pending  = isset( $result->pending ) ? (int) $result->pending : 0;
		$future   = isset( $result->future ) ? (int) $result->future : 0;
		$private  = isset( $result->private ) ? (int) $result->private : 0;

		return [
			'publish' => $publish,
			'draft'   => $draft + $pending + $future + $private,
			'total'   => $publish + $draft + $pending + $future + $private,
		];
	}

	/**
	 * @param array{publish: int, draft: int, total: int} $counts
	 */
	private static function entity_status_description( array $counts, string $empty_message ): string {
		if ( $counts['total'] <= 0 ) {
			return $empty_message;
		}

		$parts = [];
		if ( $counts['publish'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of published items */
				_n( '%d published', '%d published', $counts['publish'], 'ai-layer' ),
				$counts['publish']
			);
		}
		if ( $counts['draft'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of draft items */
				_n( '%d draft', '%d drafts', $counts['draft'], 'ai-layer' ),
				$counts['draft']
			);
		}

		return implode( ', ', $parts );
	}

	/** @param array<string, mixed> $llms */
	private static function is_llms_endpoints_configured( array $llms, string $discovery_mode ): bool {
		if ( empty( $llms['include_endpoints'] ) ) {
			return false;
		}

		if (
			SettingsPage::AI_DISCOVERY_LLMSTXT === $discovery_mode
			&& Features::answers_enabled()
			&& empty( $llms['include_answers'] )
		) {
			return false;
		}

		return true;
	}

	/** @param array<string, mixed> $llms */
	private static function has_llms_pages_configured( array $llms ): bool {
		$pages = $llms['pages'] ?? [];
		if ( ! is_array( $pages ) ) {
			return false;
		}

		foreach ( $pages['common'] ?? [] as $page_id ) {
			if ( (int) $page_id > 0 ) {
				return true;
			}
		}

		foreach ( $pages['custom'] ?? [] as $page_id ) {
			if ( (int) $page_id > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array{id: string, label: string, description: string, url: string, complete: bool, tier: string}
	 */
	private static function item( string $tier, string $id, string $label, string $description, string $url, bool $complete ): array {
		return [
			'id'          => $id,
			'label'       => $label,
			'description' => $description,
			'url'         => $url,
			'complete'    => $complete,
			'tier'        => $tier,
		];
	}
}

<?php
/**
 * Upgrade / locked-feature page.
 *
 * Rendered when a free user clicks a Pro-gated menu item.
 * Explains the feature, lists benefits, and provides a single upgrade CTA.
 * Intentionally low-pressure — no countdown timers, no persistent notices.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Licensing\License;

class UpgradePage {

	/**
	 * Renders the upgrade page for the Answers (Pro) feature.
	 */
	public static function render_answers(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$upgrade_url = License::upgrade_url();
		?>
		<div class="wrap wpail-admin wpail-upgrade">

			<div class="wpail-upgrade__hero">
				<span class="dashicons dashicons-lightbulb wpail-upgrade__icon"></span>
				<div>
					<h1>
						<?php esc_html_e( 'Answers', 'ai-ready-layer' ); ?>
						<span class="wpail-pro-badge"><?php esc_html_e( 'Pro', 'ai-ready-layer' ); ?></span>
					</h1>
					<p class="wpail-upgrade__tagline">
						<?php esc_html_e( 'Give your site the ability to answer natural language questions — directly, accurately, and instantly.', 'ai-ready-layer' ); ?>
					</p>
				</div>
			</div>

			<div class="wpail-upgrade__body">

				<div class="wpail-upgrade__explainer">
					<h2><?php esc_html_e( 'What is the Answers feature?', 'ai-ready-layer' ); ?></h2>
					<p>
						<?php esc_html_e( 'Answers is a rules-based answer engine built into AI Layer. It lets any AI system, chatbot, or voice assistant send a natural language question to your site and receive a structured, accurate response — assembled from your own data.', 'ai-ready-layer' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'No external AI API needed. No hallucinations. Just clean answers drawn from the services, FAQs, locations, and trust signals you\'ve already entered.', 'ai-ready-layer' ); ?>
					</p>

					<h2><?php esc_html_e( 'How it works', 'ai-ready-layer' ); ?></h2>
					<ol class="wpail-upgrade__steps">
						<li><?php esc_html_e( 'You author Answers — structured question/answer pairs with matching patterns — for predictable queries.', 'ai-ready-layer' ); ?></li>
						<li><?php esc_html_e( 'For everything else, the engine detects service and location intent from the query, finds the best matching FAQ, and assembles a response.', 'ai-ready-layer' ); ?></li>
						<li><?php esc_html_e( 'Every response includes a confidence level, supporting proof, and next actions — ready for any AI consumer to use.', 'ai-ready-layer' ); ?></li>
					</ol>

					<h2><?php esc_html_e( 'Example', 'ai-ready-layer' ); ?></h2>
					<div class="wpail-upgrade__code-example">
						<p class="wpail-upgrade__code-label"><?php esc_html_e( 'Request', 'ai-ready-layer' ); ?></p>
						<code>GET /wp-json/ai-layer/v1/answers?query=Do+you+offer+SEO+in+London</code>
						<p class="wpail-upgrade__code-label"><?php esc_html_e( 'Response', 'ai-ready-layer' ); ?></p>
						<pre class="wpail-upgrade__pre">{
  "data": {
    "answer_short": "Yes, we offer SEO Consultancy across London.",
    "confidence": "high",
    "service": { "name": "SEO Consultancy" },
    "location": { "name": "London" },
    "actions": [{ "type": "book", "label": "Book a free call" }],
    "supporting_data": [{ "type": "testimonial", "headline": "..." }]
  }
}</pre>
					</div>
				</div>

				<div class="wpail-upgrade__sidebar">
					<div class="wpail-upgrade__cta-box">
						<h3><?php esc_html_e( 'Unlock Answers with Pro', 'ai-ready-layer' ); ?></h3>

						<ul class="wpail-upgrade__benefits">
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Answers CPT — author guaranteed responses', 'ai-ready-layer' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( '/answers REST endpoint', 'ai-ready-layer' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Rules-based answer engine with service + location detection', 'ai-ready-layer' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Confidence scoring and source attribution', 'ai-ready-layer' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Proof and next actions attached to every response', 'ai-ready-layer' ); ?>
							</li>
							<li>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Future: analytics, AI-assisted drafting, OpenAPI export', 'ai-ready-layer' ); ?>
							</li>
						</ul>

						<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary wpail-upgrade__btn" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Upgrade to Pro', 'ai-ready-layer' ); ?>
						</a>

						<p class="wpail-upgrade__note">
							<?php esc_html_e( '14-day free trial. No commitment required.', 'ai-ready-layer' ); ?>
						</p>
					</div>

					<div class="wpail-upgrade__free-reminder">
						<h4><?php esc_html_e( 'Already included in Free', 'ai-ready-layer' ); ?></h4>
						<ul>
							<li><?php esc_html_e( 'Business Profile', 'ai-ready-layer' ); ?></li>
							<li><?php esc_html_e( 'Services, Locations, FAQs, Proof & Trust, Actions', 'ai-ready-layer' ); ?></li>
							<li><?php esc_html_e( 'Full REST API (/profile, /services, /locations, /faqs, /proof, /actions)', 'ai-ready-layer' ); ?></li>
							<li><?php esc_html_e( 'Schema.org JSON-LD output', 'ai-ready-layer' ); ?></li>
						</ul>
					</div>
				</div>

			</div>

		</div>
		<?php
	}
}

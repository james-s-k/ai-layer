<?php
/**
 * Admin page: Help & documentation.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Licensing\Features;

class HelpPage {

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$mcp_url      = home_url( '/wp-json/mcp/mcp-adapter-default-server' );
		$rest_base    = rest_url( WPAIL_REST_NS );
		$answers_url  = admin_url( 'admin.php?page=wpail_answer_test' );
		$settings_url = admin_url( 'admin.php?page=wpail_settings' );
		?>
		<div class="wrap wpail-admin">

			<div class="wpail-admin__header">
				<span class="dashicons dashicons-sos wpail-admin__header-icon"></span>
				<div>
					<h1><?php esc_html_e( 'Help & Documentation', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline">
						<?php esc_html_e( 'How AI Layer works, how to connect AI agents via MCP, and how to get the most from your data.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<?php /* ── Answer engine ── */ ?>
			<div class="wpail-card wpail-help__section">
				<h2 class="wpail-help__heading">
					<span class="dashicons dashicons-controls-play wpail-help__heading-icon"></span>
					<?php esc_html_e( 'How the Answer Engine Works', 'ai-layer' ); ?>
				</h2>
				<p>
					<?php esc_html_e( 'The /answers endpoint processes natural language queries through a rules-based pipeline — no external AI service required. Everything runs on your server.', 'ai-layer' ); ?>
				</p>
				<ol class="wpail-engine-steps">
					<li>
						<strong><?php esc_html_e( 'Manual answer check', 'ai-layer' ); ?></strong> —
						<?php esc_html_e( 'If a manually-authored Answer matches the query pattern, it is returned immediately at the highest confidence level. This step always wins.', 'ai-layer' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Service detection', 'ai-layer' ); ?></strong> —
						<?php esc_html_e( 'Each Service is scored by how many of its keywords and synonyms appear in the query. The highest-scoring Service is selected.', 'ai-layer' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Location detection', 'ai-layer' ); ?></strong> —
						<?php esc_html_e( 'Query terms are matched against Location names, regions, and postcode prefixes.', 'ai-layer' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'FAQ scoring', 'ai-layer' ); ?></strong> —
						<?php esc_html_e( 'FAQs are scored by how closely the question and answer text match the query. Results are then filtered to FAQs linked to the detected Service.', 'ai-layer' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Response assembly', 'ai-layer' ); ?></strong> —
						<?php esc_html_e( 'The best-matching FAQ (or Service summary if no FAQ matches) is assembled into a response with up to 3 Actions and up to 3 Proof items attached.', 'ai-layer' ); ?>
					</li>
				</ol>
				<p>
					<?php esc_html_e( 'The response includes a confidence level (high / medium / low) and a source field (manual / faq / dynamic) so the consuming application can decide how to display it.', 'ai-layer' ); ?>
				</p>
				<h3 class="wpail-help__subheading"><?php esc_html_e( 'Getting the best results', 'ai-layer' ); ?></h3>
				<ul class="wpail-help__tips">
					<li><?php esc_html_e( 'Add specific keywords and synonyms to each Service — the engine matches exact words, so "web design" and "website development" should both be listed if customers use both phrases.', 'ai-layer' ); ?></li>
					<li><?php esc_html_e( 'Link FAQs to the Services they are relevant to. The engine narrows results to linked FAQs once a Service is detected, so an unlinked FAQ may not surface for service-specific queries.', 'ai-layer' ); ?></li>
					<li><?php esc_html_e( 'Use manually-authored Answers for high-value, predictable queries ("Do you cover Manchester?", "What are your prices?") to guarantee the exact response.', 'ai-layer' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: link to test console */
							esc_html__( 'Use the %s to verify results as you add content — it shows exactly what the engine returns for any question.', 'ai-layer' ),
							'<a href="' . esc_url( $answers_url ) . '">' . esc_html__( 'Test Answer Engine', 'ai-layer' ) . '</a>'
						);
						?>
					</li>
				</ul>
			</div>

			<?php /* ── MCP ── */ ?>
			<div class="wpail-card wpail-help__section">
				<h2 class="wpail-help__heading">
					<span class="dashicons dashicons-networking wpail-help__heading-icon"></span>
					<?php esc_html_e( 'Connecting AI Agents via MCP', 'ai-layer' ); ?>
				</h2>
				<p>
					<?php esc_html_e( 'MCP (Model Context Protocol) is an open standard that lets AI assistants — Claude, Cursor, and others — call tools directly on your server. AI Layer registers all its read and write operations as MCP tools, which means an AI agent can query your business data, answer questions, and update content without you writing any code.', 'ai-layer' ); ?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: link to WordPress MCP Adapter GitHub repo */
						esc_html__( 'MCP support requires the %s to be installed and active alongside AI Layer.', 'ai-layer' ),
						'<a href="https://github.com/wordpress/mcp-adapter" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WordPress MCP Adapter plugin', 'ai-layer' ) . '</a>'
					);
					?>
				</p>

				<p>
					<?php
					printf(
						/* translators: %s: link to WordPress MCP Adapter GitHub repo */
						esc_html__( 'For installation and connection instructions, see the %s.', 'ai-layer' ),
						'<a href="https://github.com/wordpress/mcp-adapter" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WordPress MCP Adapter documentation', 'ai-layer' ) . '</a>'
					);
					?>
				</p>

				<h3 class="wpail-help__subheading"><?php esc_html_e( 'Available MCP tools', 'ai-layer' ); ?></h3>
				<p><?php esc_html_e( 'AI Layer registers the following tool groups. All tool names are prefixed with ai-layer/.', 'ai-layer' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tool', 'ai-layer' ); ?></th>
							<th><?php esc_html_e( 'What it does', 'ai-layer' ); ?></th>
							<th><?php esc_html_e( 'Requires', 'ai-layer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$tools = [
							[ 'get-business-profile',    __( 'Read the full Business Profile', 'ai-layer' ),                          __( 'read', 'ai-layer' ) ],
							[ 'update-business-profile', __( 'Update Business Profile fields', 'ai-layer' ),                          __( 'edit_posts', 'ai-layer' ) ],
							[ 'list-services',           __( 'List all published Services', 'ai-layer' ),                             __( 'read', 'ai-layer' ) ],
							[ 'get-service',             __( 'Get a single Service by ID or slug', 'ai-layer' ),                      __( 'read', 'ai-layer' ) ],
							[ 'create-service',          __( 'Create a new Service', 'ai-layer' ),                                    __( 'edit_posts', 'ai-layer' ) ],
							[ 'update-service',          __( 'Update an existing Service', 'ai-layer' ),                              __( 'edit_posts', 'ai-layer' ) ],
							[ 'delete-service',          __( 'Delete a Service', 'ai-layer' ),                                        __( 'delete_posts', 'ai-layer' ) ],
							[ 'list-locations',          __( 'List all published Locations', 'ai-layer' ),                            __( 'read', 'ai-layer' ) ],
							[ 'get-location',            __( 'Get a single Location by ID or slug', 'ai-layer' ),                     __( 'read', 'ai-layer' ) ],
							[ 'create-location',         __( 'Create a new Location', 'ai-layer' ),                                   __( 'edit_posts', 'ai-layer' ) ],
							[ 'update-location',         __( 'Update an existing Location', 'ai-layer' ),                             __( 'edit_posts', 'ai-layer' ) ],
							[ 'delete-location',         __( 'Delete a Location', 'ai-layer' ),                                       __( 'delete_posts', 'ai-layer' ) ],
							[ 'list-faqs',               __( 'List all published FAQs', 'ai-layer' ),                                 __( 'read', 'ai-layer' ) ],
							[ 'get-faq',                 __( 'Get a single FAQ by ID', 'ai-layer' ),                                  __( 'read', 'ai-layer' ) ],
							[ 'create-faq',              __( 'Create a new FAQ', 'ai-layer' ),                                        __( 'edit_posts', 'ai-layer' ) ],
							[ 'update-faq',              __( 'Update an existing FAQ', 'ai-layer' ),                                  __( 'edit_posts', 'ai-layer' ) ],
							[ 'delete-faq',              __( 'Delete a FAQ', 'ai-layer' ),                                            __( 'delete_posts', 'ai-layer' ) ],
							[ 'list-proof',              __( 'List all published Proof & Trust items', 'ai-layer' ),                  __( 'read', 'ai-layer' ) ],
							[ 'get-proof',               __( 'Get a single Proof item by ID', 'ai-layer' ),                           __( 'read', 'ai-layer' ) ],
							[ 'create-proof',            __( 'Create a new Proof item', 'ai-layer' ),                                 __( 'edit_posts', 'ai-layer' ) ],
							[ 'update-proof',            __( 'Update an existing Proof item', 'ai-layer' ),                           __( 'edit_posts', 'ai-layer' ) ],
							[ 'delete-proof',            __( 'Delete a Proof item', 'ai-layer' ),                                     __( 'delete_posts', 'ai-layer' ) ],
							[ 'list-actions',            __( 'List all published Actions', 'ai-layer' ),                              __( 'read', 'ai-layer' ) ],
							[ 'get-action',              __( 'Get a single Action by ID', 'ai-layer' ),                               __( 'read', 'ai-layer' ) ],
							[ 'create-action',           __( 'Create a new Action', 'ai-layer' ),                                     __( 'edit_posts', 'ai-layer' ) ],
							[ 'update-action',           __( 'Update an existing Action', 'ai-layer' ),                               __( 'edit_posts', 'ai-layer' ) ],
							[ 'delete-action',           __( 'Delete an Action', 'ai-layer' ),                                        __( 'delete_posts', 'ai-layer' ) ],
							[ 'query-answer-engine',     __( 'Ask the answer engine a natural language question and return the structured response', 'ai-layer' ), __( 'read', 'ai-layer' ) ],
						];

						if ( Features::answers_enabled() ) {
							$tools = array_merge( $tools, [
								[ 'list-answers',   __( 'List all manually-authored Answers', 'ai-layer' ),  __( 'read', 'ai-layer' ) ],
								[ 'get-answer',     __( 'Get a single Answer by ID', 'ai-layer' ),           __( 'read', 'ai-layer' ) ],
								[ 'create-answer',  __( 'Create a new manually-authored Answer', 'ai-layer' ), __( 'edit_posts', 'ai-layer' ) ],
								[ 'update-answer',  __( 'Update an existing Answer', 'ai-layer' ),            __( 'edit_posts', 'ai-layer' ) ],
								[ 'delete-answer',  __( 'Delete an Answer', 'ai-layer' ),                     __( 'delete_posts', 'ai-layer' ) ],
							] );
						}

						foreach ( $tools as [ $name, $desc, $cap ] ) :
							?>
							<tr>
								<td><code>ai-layer/<?php echo esc_html( $name ); ?></code></td>
								<td><?php echo esc_html( $desc ); ?></td>
								<td><small><?php echo esc_html( $cap ); ?></small></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php /* ── Troubleshooting ── */ ?>
			<div class="wpail-card wpail-help__section">
				<h2 class="wpail-help__heading">
					<span class="dashicons dashicons-search wpail-help__heading-icon"></span>
					<?php esc_html_e( 'Troubleshooting', 'ai-layer' ); ?>
				</h2>
				<dl class="wpail-help__faq">

					<dt><?php esc_html_e( 'The wrong service is being detected', 'ai-layer' ); ?></dt>
					<dd><?php esc_html_e( 'Check the Keywords and Synonyms fields on each Service. Multi-word phrases must match as a complete phrase — "web design" scores only if both words are in the query. Remove broad keywords that could match unrelated queries.', 'ai-layer' ); ?></dd>

					<dt><?php esc_html_e( 'Location is not being detected', 'ai-layer' ); ?></dt>
					<dd><?php esc_html_e( 'Make sure the Location name, region, or postcode prefix exactly matches what a customer would type. Common queries end with punctuation (e.g. "Manchester?") — the engine strips punctuation before matching, so this should work automatically.', 'ai-layer' ); ?></dd>

					<dt><?php esc_html_e( 'No answer is returned for a query', 'ai-layer' ); ?></dt>
					<dd><?php esc_html_e( 'The engine requires either a matching FAQ or a detected Service to assemble a response. If neither matches, it returns a 404. Add more FAQs, broaden Service keywords, or create a manual Answer for that query pattern.', 'ai-layer' ); ?></dd>

					<dt><?php esc_html_e( 'A manual Answer is not being matched', 'ai-layer' ); ?></dt>
					<dd><?php esc_html_e( 'Check the Query Patterns field on the Answer post. Each pattern is matched as a case-insensitive substring of the incoming query. If the pattern is too specific it may not match — try a shorter, more general phrase.', 'ai-layer' ); ?></dd>

					<dt><?php esc_html_e( 'Service or location is missing from the answer response', 'ai-layer' ); ?></dt>
					<dd><?php esc_html_e( 'For manually-authored Answers, set the Related Services and Related Locations fields on the Answer post — the engine uses those to populate the response rather than detecting them from the query.', 'ai-layer' ); ?></dd>

				</dl>
			</div>

		</div>
		<?php
	}
}

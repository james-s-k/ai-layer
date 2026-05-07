<?php
/**
 * AI Import admin page.
 *
 * Handles provider/model settings, source page selection, step-by-step
 * extraction (via admin-ajax), and results display.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\AI\AiSettings;
use WPAIL\AI\ExtractionJob;
use WPAIL\AI\ProviderFactory;

class AiImportPage {

	public function register(): void {
		( new AiSettings() )->register();
		add_action( 'wp_ajax_wpail_ai_start',    [ $this, 'ajax_start' ] );
		add_action( 'wp_ajax_wpail_ai_run_step', [ $this, 'ajax_run_step' ] );
		add_action( 'wp_ajax_wpail_ai_resync',               [ $this, 'ajax_resync' ] );
		add_action( 'wp_ajax_wpail_ai_find_relationships',  [ $this, 'ajax_find_relationships' ] );
		add_action( 'wp_ajax_wpail_ai_rebuild_relationships', [ $this, 'ajax_rebuild_relationships' ] );
	}

	// ------------------------------------------------------------------
	// AJAX handlers.
	// ------------------------------------------------------------------

	public function ajax_start(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$post_ids = array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) );
		$post_ids = array_filter( $post_ids );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( [ 'message' => 'Select at least one page or post.' ] );
		}

		$types = array_map( 'sanitize_key', (array) ( $_POST['types'] ?? [] ) );
		$types = array_filter( $types );

		if ( empty( $types ) ) {
			wp_send_json_error( [ 'message' => 'Select at least one entity type to extract.' ] );
		}

		$job = ExtractionJob::create( $post_ids, $types );
		wp_send_json_success( $job ); // Includes job_id + full types list (with automatic 'link' step).
	}

	public function ajax_resync(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$processed = ExtractionJob::resync_consistency();
		wp_send_json_success( [ 'processed' => $processed ] );
	}

	public function ajax_find_relationships(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$provider = ProviderFactory::make();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ] );
		}

		$result = ExtractionJob::ai_find_relationships( $provider );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'updated' => $result ] );
	}

	public function ajax_rebuild_relationships(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$provider = ProviderFactory::make();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ] );
		}

		$result = ExtractionJob::ai_rebuild_relationships( $provider );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'updated' => $result ] );
	}

	public function ajax_run_step(): void {
		check_ajax_referer( 'wpail_ai_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		if ( '' === $job_id ) {
			wp_send_json_error( [ 'message' => 'Missing job ID.' ] );
		}

		$provider = ProviderFactory::make();
		if ( is_wp_error( $provider ) ) {
			wp_send_json_error( [ 'message' => $provider->get_error_message() ] );
		}

		$result = ExtractionJob::run_step( $job_id, $provider );

		if ( isset( $result['error'] ) ) {
			wp_send_json_error( [ 'message' => $result['error'] ] );
		}

		wp_send_json_success( $result );
	}

	// ------------------------------------------------------------------
	// Page render.
	// ------------------------------------------------------------------

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$model      = AiSettings::get_selected_model();
		$model_info = AiSettings::get_model_info( $model );
		$provider   = $model_info['provider'] ?? 'openai';
		$has_key    = '' !== AiSettings::get_api_key( $provider );

		?>
		<div class="wrap wpail-admin">

			<div class="wpail-admin__header">
				<div>
					<h1><?php esc_html_e( 'AI Import', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline" style="margin-bottom:0;">
						<?php esc_html_e( 'Use AI to extract services, FAQs, locations, proof, and actions from your existing pages.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<div class="notice notice-warning" style="margin:20px 0 0;">
				<p>
					<strong><?php esc_html_e( 'Back up your database before importing.', 'ai-layer' ); ?></strong>
					<?php esc_html_e( 'AI Import creates new posts and modifies relationship data. Use a backup plugin or your host\'s backup tool to take a full database backup before running an import or any relationship operation.', 'ai-layer' ); ?>
				</p>
			</div>

			<?php // ── Provider & Model ──────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Provider & Model', 'ai-layer' ); ?></h2>

				<form method="post" action="">
					<?php wp_nonce_field( AiSettings::NONCE_ACTION, AiSettings::NONCE_NAME ); ?>

					<table class="form-table" style="max-width:700px;">
						<tr>
							<th scope="row"><label for="wpail_ai_model"><?php esc_html_e( 'Model', 'ai-layer' ); ?></label></th>
							<td>
								<select name="wpail_ai_model" id="wpail_ai_model" style="min-width:280px;">
									<?php
									$current_provider = '';
									foreach ( AiSettings::MODELS as $model_id => $info ) :
										if ( $info['provider'] !== $current_provider ) :
											if ( '' !== $current_provider ) echo '</optgroup>';
											$current_provider = $info['provider'];
											$group_label = AiSettings::PROVIDER_LABELS[ $current_provider ] ?? $current_provider;
											echo '<optgroup label="' . esc_attr( $group_label ) . '">';
										endif;
										$selected = selected( $model, $model_id, false );
										printf(
											'<option value="%s" %s>%s (%s)</option>',
											esc_attr( $model_id ),
											$selected,
											esc_html( $info['name'] ),
											esc_html( $info['speed'] )
										);
									endforeach;
									if ( '' !== $current_provider ) echo '</optgroup>';
									?>
								</select>
								<p class="description"><?php esc_html_e( 'GPT-4o Mini is the recommended default — fast, cheap, and accurate for most sites. For the relationship step, a stronger model such as GPT-4.1 or Claude Sonnet 4.6 gives more accurate results.', 'ai-layer' ); ?></p>
							</td>
						</tr>

						<?php foreach ( AiSettings::PROVIDER_LABELS as $prov => $label ) : ?>
							<tr>
								<th scope="row">
									<label for="wpail_ai_key_<?php echo esc_attr( $prov ); ?>">
										<?php
										/* translators: %s: provider name (e.g. OpenAI) */
										printf( esc_html__( '%s API Key', 'ai-layer' ), esc_html( $label ) );
										?>
									</label>
								</th>
								<td>
									<?php $stored_key = AiSettings::get_api_key( $prov ); ?>
									<input type="password"
									       id="wpail_ai_key_<?php echo esc_attr( $prov ); ?>"
									       name="wpail_ai_key_<?php echo esc_attr( $prov ); ?>"
									       value=""
									       placeholder="<?php echo $stored_key ? esc_attr__( '(saved — leave blank to keep)', 'ai-layer' ) : esc_attr__( 'Paste your API key', 'ai-layer' ); ?>"
									       style="width:340px;font-family:monospace;" />
									<?php if ( $stored_key ) : ?>
										<span style="color:#00a32a;margin-left:8px;">&#10003; <?php esc_html_e( 'Key saved', 'ai-layer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<p style="margin-top:16px;"><button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Settings', 'ai-layer' ); ?></button></p>
				</form>
			</div>

			<?php // ── Import from Pages ─────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:24px;" id="wpail-ai-import-card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import from Pages', 'ai-layer' ); ?></h2>
				<p class="description" style="margin:0 0 20px;max-width:680px;">
					<?php esc_html_e( 'Select the pages that describe your business (e.g. Services, About, Areas). The AI will read them and create draft items for each entity type. You can review and publish the drafts afterwards.', 'ai-layer' ); ?>
				</p>

				<?php if ( ! $has_key ) : ?>
					<div class="notice notice-warning inline" style="margin:0 0 20px;">
						<p>
							<?php
							printf(
								/* translators: %s: provider name */
								esc_html__( 'Add an API key for %s above and save before running an import.', 'ai-layer' ),
								esc_html( AiSettings::PROVIDER_LABELS[ $provider ] ?? $provider )
							);
							?>
						</p>
					</div>
				<?php endif; ?>

				<?php // ── Page search picker ────────────────────────────── ?>
				<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:16px 20px;margin-bottom:20px;max-width:680px;">
					<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
						<strong style="font-size:13px;"><?php esc_html_e( 'Pages to scan', 'ai-layer' ); ?></strong>
						<a href="#" id="wpail-clear-pages" style="font-size:13px;display:none;"><?php esc_html_e( 'Deselect all', 'ai-layer' ); ?></a>
					</div>
					<div class="wpail-page-picker" id="wpail-import-picker" style="margin-bottom:12px;">
						<div class="wpail-page-picker__search-wrap">
							<input type="text"
							       class="wpail-page-picker__search regular-text"
							       placeholder="<?php esc_attr_e( 'Search for a page…', 'ai-layer' ); ?>"
							       style="width:100%;"
							       autocomplete="off" />
							<div class="wpail-page-picker__dropdown"></div>
						</div>
					</div>
					<div id="wpail-selected-pages" style="display:flex;flex-wrap:wrap;gap:6px;min-height:0;"></div>
					<p id="wpail-pages-hint" class="description" style="margin:10px 0 0;font-size:12px;">
						<?php esc_html_e( 'Type at least 2 characters to search. Click a result to add it to the list.', 'ai-layer' ); ?>
					</p>
				</div>

				<?php // ── Entity type selector ──────────────────────────── ?>
				<div style="margin-bottom:20px;">
					<strong style="display:block;font-size:13px;margin-bottom:10px;"><?php esc_html_e( 'What to extract', 'ai-layer' ); ?></strong>
					<div style="display:flex;flex-wrap:wrap;gap:10px 24px;">
						<?php
						$entity_labels = [
							'services'  => __( 'Services', 'ai-layer' ),
							'faqs'      => __( 'FAQs', 'ai-layer' ),
							'locations' => __( 'Locations', 'ai-layer' ),
							'proof'     => __( 'Proof & Trust', 'ai-layer' ),
							'actions'   => __( 'Actions', 'ai-layer' ),
						];
						foreach ( $entity_labels as $type => $label ) :
						?>
							<label style="font-size:13px;">
								<input type="checkbox"
								       class="wpail-ai-type-cb"
								       value="<?php echo esc_attr( $type ); ?>"
								       checked />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<p style="margin:0 0 4px;">
					<button id="wpail-ai-start-btn"
					        class="button button-primary"
					        <?php disabled( ! $has_key ); ?>>
						<?php esc_html_e( 'Start AI Extraction', 'ai-layer' ); ?>
					</button>
				</p>
				<p class="description" style="margin:8px 0 0;">
					<?php esc_html_e( 'All extracted items are saved as drafts. AI can make mistakes — review titles, content, and relationships before publishing.', 'ai-layer' ); ?>
				</p>

				<?php // ── Progress ──────────────────────────────────────── ?>
				<div id="wpail-ai-progress" style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid #dcdcde;">
					<p style="margin:0 0 8px;font-weight:600;"><?php esc_html_e( 'Extracting…', 'ai-layer' ); ?></p>
					<div style="background:#dcdcde;border-radius:4px;height:10px;overflow:hidden;max-width:500px;">
						<div id="wpail-ai-bar" style="background:#2271b1;height:10px;width:0;transition:width .4s;"></div>
					</div>
					<p id="wpail-ai-status-text" style="margin:8px 0 0;color:#646970;font-size:13px;"></p>
				</div>

				<?php // ── Results ───────────────────────────────────────── ?>
				<div id="wpail-ai-results" style="display:none;margin-top:24px;padding-top:20px;border-top:1px solid #dcdcde;">
					<p style="margin:0 0 12px;font-weight:600;color:#00a32a;">&#10003; <?php esc_html_e( 'Extraction complete', 'ai-layer' ); ?></p>
					<table class="widefat striped" style="max-width:460px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Entity', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Drafts created', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Review', 'ai-layer' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpail-ai-results-body"></tbody>
					</table>
					<p class="description" style="margin-top:12px;">
						<?php esc_html_e( 'All items were saved as drafts. Review and publish the ones you want to keep.', 'ai-layer' ); ?>
					</p>
				</div>

				<?php // ── Error ─────────────────────────────────────────── ?>
				<div id="wpail-ai-error" style="display:none;margin-top:20px;" class="notice notice-error inline">
					<p id="wpail-ai-error-text"></p>
				</div>
			</div>

			<?php // ── Relationships ─────────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:24px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Relationships', 'ai-layer' ); ?></h2>
				<p class="description" style="margin:0 0 20px;max-width:680px;">
					<?php esc_html_e( 'Manage how entities are linked to one another. All relationship operations affect published and draft posts.', 'ai-layer' ); ?>
				</p>

				<?php // ── Reference table ───────────────────────────────── ?>
				<table style="border-collapse:collapse;max-width:680px;width:100%;margin-bottom:28px;font-size:13px;">
					<thead>
						<tr style="border-bottom:2px solid #dcdcde;">
							<th style="text-align:left;padding:0 16px 8px 0;color:#3c434a;font-weight:600;width:160px;"><?php esc_html_e( 'Relationship', 'ai-layer' ); ?></th>
							<th style="text-align:left;padding:0 0 8px;color:#3c434a;font-weight:600;"><?php esc_html_e( 'Set when…', 'ai-layer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rel_rows = [
							[ __( 'FAQ → Service', 'ai-layer' ),      __( 'The FAQ is clearly about that specific service.', 'ai-layer' ) ],
							[ __( 'Proof → Service', 'ai-layer' ),    __( 'The testimonial, stat, or award is about a specific service — not general company-wide proof.', 'ai-layer' ) ],
							[ __( 'Proof → Location', 'ai-layer' ),   __( 'A specific city or area is explicitly named in the proof text. Never inferred — left empty if no place name appears.', 'ai-layer' ) ],
							[ __( 'Action → Service', 'ai-layer' ),   __( 'The action is the primary way to enquire about or book that service.', 'ai-layer' ) ],
							[ __( 'Location → Service', 'ai-layer' ), __( 'The service is offered at or from that location.', 'ai-layer' ) ],
						];
						foreach ( $rel_rows as $row ) :
						?>
							<tr style="border-bottom:1px solid #f0f0f1;">
								<td style="padding:10px 16px 10px 0;font-weight:600;color:#1d2327;white-space:nowrap;"><?php echo esc_html( $row[0] ); ?></td>
								<td style="padding:10px 0;color:#646970;"><?php echo esc_html( $row[1] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php // ── Three action cards ────────────────────────────── ?>
				<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">

					<?php // Card 1 — Resync (safe / green) ?>
					<div style="border:1px solid #dcdcde;border-top:3px solid #00a32a;border-radius:4px;padding:20px;display:flex;flex-direction:column;">
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<strong style="font-size:13px;"><?php esc_html_e( 'Resync All Relationships', 'ai-layer' ); ?></strong>
							<span style="font-size:11px;font-weight:600;background:#e7f6ea;color:#00a32a;padding:2px 7px;border-radius:3px;white-space:nowrap;"><?php esc_html_e( 'Safe', 'ai-layer' ); ?></span>
						</div>
						<p class="description" style="margin:0 0 12px;flex:1;font-size:13px;">
							<?php esc_html_e( 'Repairs missing inverse links using relationship data already saved on your entities. Additive only — never removes existing links.', 'ai-layer' ); ?>
						</p>
						<p style="margin:0 0 14px;font-size:12px;color:#646970;"><?php esc_html_e( 'No API key required.', 'ai-layer' ); ?></p>
						<button id="wpail-resync-btn" class="button button-secondary" style="width:100%;">
							<?php esc_html_e( 'Resync All Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-resync-status" style="margin:8px 0 0;font-size:13px;color:#646970;min-height:20px;"></p>
					</div>

					<?php // Card 2 — Find New (AI / amber) ?>
					<div style="border:1px solid #dcdcde;border-top:3px solid #dba617;border-radius:4px;padding:20px;display:flex;flex-direction:column;">
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<strong style="font-size:13px;"><?php esc_html_e( 'Find New Relationships', 'ai-layer' ); ?></strong>
							<span style="font-size:11px;font-weight:600;background:#fef8ee;color:#996800;padding:2px 7px;border-radius:3px;white-space:nowrap;"><?php esc_html_e( 'AI', 'ai-layer' ); ?></span>
						</div>
						<p class="description" style="margin:0 0 12px;flex:1;font-size:13px;">
							<?php esc_html_e( 'Uses AI to discover relationships not yet set and adds them. Does not remove existing links.', 'ai-layer' ); ?>
						</p>
						<p style="margin:0 0 14px;font-size:12px;color:#646970;"><?php esc_html_e( 'Requires an API key. Review results manually — AI can make mistakes.', 'ai-layer' ); ?></p>
						<button id="wpail-find-rel-btn" class="button button-secondary" style="width:100%;">
							<?php esc_html_e( 'Find New Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-find-rel-status" style="margin:8px 0 0;font-size:13px;color:#646970;min-height:20px;"></p>
					</div>

					<?php // Card 3 — Rebuild (destructive / red) ?>
					<div style="border:1px solid #dcdcde;border-top:3px solid #d63638;border-radius:4px;padding:20px;display:flex;flex-direction:column;">
						<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
							<strong style="font-size:13px;"><?php esc_html_e( 'Rebuild All Relationships', 'ai-layer' ); ?></strong>
							<span style="font-size:11px;font-weight:600;background:#fef0f0;color:#d63638;padding:2px 7px;border-radius:3px;white-space:nowrap;"><?php esc_html_e( 'Destructive', 'ai-layer' ); ?></span>
						</div>
						<p class="description" style="margin:0 0 12px;flex:1;font-size:13px;">
							<?php esc_html_e( 'AI sets the complete, authoritative set of relationships for every entity. Existing relationship data is replaced — links not confirmed by the AI are removed.', 'ai-layer' ); ?>
						</p>
						<p style="margin:0 0 14px;font-size:12px;color:#d63638;"><?php esc_html_e( 'Requires an API key. Overwrites all existing relationship data. Review manually afterwards.', 'ai-layer' ); ?></p>
						<button id="wpail-rebuild-rel-btn" class="button" style="background:#d63638;border-color:#b32d2e;color:#fff;width:100%;">
							<?php esc_html_e( 'Rebuild All Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-rebuild-rel-status" style="margin:8px 0 0;font-size:13px;color:#646970;min-height:20px;"></p>
					</div>

				</div>
			</div>

		</div>

		<script>
		(function () {
			const ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wpail_ai_import' ) ); ?>;
			const restUrl  = <?php echo wp_json_encode( rest_url() ); ?>;
			const restNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
			const labels  = {
				services:  <?php echo wp_json_encode( __( 'Services', 'ai-layer' ) ); ?>,
				faqs:      <?php echo wp_json_encode( __( 'FAQs', 'ai-layer' ) ); ?>,
				locations: <?php echo wp_json_encode( __( 'Locations', 'ai-layer' ) ); ?>,
				proof:     <?php echo wp_json_encode( __( 'Proof & Trust', 'ai-layer' ) ); ?>,
				actions:   <?php echo wp_json_encode( __( 'Actions', 'ai-layer' ) ); ?>,
				link:      <?php echo wp_json_encode( __( 'Relationships', 'ai-layer' ) ); ?>,
			};
			const reviewUrls = {
				services:  <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_service&post_status=draft' ) ); ?>,
				faqs:      <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_faq&post_status=draft' ) ); ?>,
				locations: <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_location&post_status=draft' ) ); ?>,
				proof:     <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_proof&post_status=draft' ) ); ?>,
				actions:   <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpail_action&post_status=draft' ) ); ?>,
			};

			// ── Multi-page picker ─────────────────────────────────────────
			let selectedPages = []; // [{ id, title }, ...]
			const picker        = document.getElementById('wpail-import-picker');
			const searchInput   = picker?.querySelector('.wpail-page-picker__search');
			const dropdown      = picker?.querySelector('.wpail-page-picker__dropdown');
			const selectedWrap  = document.getElementById('wpail-selected-pages');
			const clearAllLink  = document.getElementById('wpail-clear-pages');
			const hintEl        = document.getElementById('wpail-pages-hint');
			let searchCache     = {};
			let debounce;

			function searchPages(term, callback) {
				if (searchCache[term]) { callback(searchCache[term]); return; }
				const url = restUrl + 'wp/v2/search?search=' + encodeURIComponent(term) +
					'&subtype=page&type=post&_fields=id,title&per_page=10';
				fetch(url, { headers: { 'X-WP-Nonce': restNonce } })
					.then(r => r.json())
					.then(data => {
						const results = Array.isArray(data)
							? data.map(item => ({ id: item.id, title: item.title }))
							: [];
						searchCache[term] = results;
						callback(results);
					})
					.catch(() => callback([]));
			}

			function renderDropdown(results) {
				dropdown.innerHTML = '';
				if (!results.length) {
					const empty = document.createElement('div');
					empty.className   = 'wpail-page-picker__option wpail-page-picker__option--empty';
					empty.textContent = <?php echo wp_json_encode( __( 'No pages found.', 'ai-layer' ) ); ?>;
					dropdown.appendChild(empty);
					dropdown.style.display = 'block';
					return;
				}
				results.forEach(page => {
					const opt = document.createElement('div');
					opt.className   = 'wpail-page-picker__option';
					opt.textContent = page.title;
					if (selectedPages.some(p => p.id === page.id)) {
						opt.style.opacity = '0.45';
						opt.style.cursor  = 'default';
					}
					opt.addEventListener('mousedown', function (e) {
						e.preventDefault();
						if (selectedPages.some(p => p.id === page.id)) return;
						selectedPages.push({ id: page.id, title: page.title });
						renderChips();
						searchInput.value      = '';
						dropdown.style.display = 'none';
					});
					dropdown.appendChild(opt);
				});
				dropdown.style.display = 'block';
			}

			function renderChips() {
				selectedWrap.innerHTML = '';
				selectedPages.forEach(page => {
					const chip = document.createElement('span');
					chip.style.cssText = 'display:inline-flex;align-items:center;gap:5px;background:#fff;' +
						'border:1px solid #c3c4c7;border-radius:3px;padding:4px 8px;font-size:12px;line-height:1.4;';
					chip.textContent = page.title;
					const rm = document.createElement('button');
					rm.type            = 'button';
					rm.textContent     = '×';
					rm.title           = <?php echo wp_json_encode( __( 'Remove', 'ai-layer' ) ); ?>;
					rm.style.cssText   = 'background:none;border:none;cursor:pointer;padding:0 0 0 2px;' +
						'font-size:14px;line-height:1;color:#646970;';
					rm.addEventListener('click', () => {
						selectedPages = selectedPages.filter(p => p.id !== page.id);
						renderChips();
					});
					chip.appendChild(rm);
					selectedWrap.appendChild(chip);
				});
				const hasPages = selectedPages.length > 0;
				clearAllLink.style.display = hasPages ? '' : 'none';
				hintEl.style.display       = hasPages ? 'none' : '';
			}

			if (searchInput && dropdown) {
				searchInput.addEventListener('input', function () {
					clearTimeout(debounce);
					const term = searchInput.value.trim();
					if (term.length < 2) { dropdown.innerHTML = ''; dropdown.style.display = 'none'; return; }
					debounce = setTimeout(() => searchPages(term, renderDropdown), 300);
				});

				searchInput.addEventListener('focus', function () {
					const term = searchInput.value.trim();
					if (term.length >= 2) searchPages(term, renderDropdown);
				});

				document.addEventListener('click', function (e) {
					if (!picker.contains(e.target)) dropdown.style.display = 'none';
				});
			}

			clearAllLink?.addEventListener('click', function (e) {
				e.preventDefault();
				selectedPages = [];
				renderChips();
			});

			// ── Extraction ────────────────────────────────────────────────
			const btn         = document.getElementById('wpail-ai-start-btn');
			const progress    = document.getElementById('wpail-ai-progress');
			const bar         = document.getElementById('wpail-ai-bar');
			const statusTxt   = document.getElementById('wpail-ai-status-text');
			const results     = document.getElementById('wpail-ai-results');
			const resultsBody = document.getElementById('wpail-ai-results-body');
			const errorBox    = document.getElementById('wpail-ai-error');
			const errorTxt    = document.getElementById('wpail-ai-error-text');

			btn?.addEventListener('click', async function () {
				const checkedTypes = [...document.querySelectorAll('.wpail-ai-type-cb:checked')].map(cb => cb.value);

				if (!selectedPages.length) {
					alert(<?php echo wp_json_encode( __( 'Search for and select at least one page.', 'ai-layer' ) ); ?>);
					return;
				}
				if (!checkedTypes.length) {
					alert(<?php echo wp_json_encode( __( 'Select at least one entity type to extract.', 'ai-layer' ) ); ?>);
					return;
				}

				btn.disabled = true;
				errorBox.style.display = 'none';
				results.style.display  = 'none';
				resultsBody.innerHTML  = '';
				progress.style.display = 'block';
				bar.style.width        = '0';
				statusTxt.textContent  = <?php echo wp_json_encode( __( 'Preparing…', 'ai-layer' ) ); ?>;

				const startData = new FormData();
				startData.append('action', 'wpail_ai_start');
				startData.append('nonce', nonce);
				selectedPages.forEach(p => startData.append('post_ids[]', p.id));
				checkedTypes.forEach(t => startData.append('types[]', t));

				let jobId, activeTypes;
				try {
					const startRes  = await fetch(ajaxUrl, { method: 'POST', body: startData });
					const startJson = await startRes.json();
					if (!startJson.success) throw new Error(startJson.data?.message || 'Failed to start job.');
					jobId       = startJson.data.job_id;
					activeTypes = startJson.data.types;
				} catch (err) {
					showError(err.message);
					return;
				}

				for (let i = 0; i < activeTypes.length; i++) {
					bar.style.width       = Math.round((i / activeTypes.length) * 100) + '%';
					statusTxt.textContent = activeTypes[i] === 'link'
						? <?php echo wp_json_encode( __( 'Linking relationships…', 'ai-layer' ) ); ?>
						: <?php echo wp_json_encode( __( 'Extracting', 'ai-layer' ) ); ?> + ' ' + (labels[activeTypes[i]] || activeTypes[i]) + '…';

					const stepData = new FormData();
					stepData.append('action', 'wpail_ai_run_step');
					stepData.append('nonce', nonce);
					stepData.append('job_id', jobId);

					try {
						const stepRes  = await fetch(ajaxUrl, { method: 'POST', body: stepData });
						const stepJson = await stepRes.json();
						if (!stepJson.success) throw new Error(stepJson.data?.message || 'Step failed.');

						const d = stepJson.data;
						if (d.step_name) {
							const tr = document.createElement('tr');
							if (d.step_name === 'link') {
								tr.innerHTML = `<td>${labels.link}</td><td><strong>${d.created}</strong></td><td>—</td>`;
							} else {
								tr.innerHTML = `<td>${labels[d.step_name] || d.step_name}</td>` +
									`<td><strong>${d.created}</strong></td>` +
									`<td>${d.created > 0 ? '<a href="' + reviewUrls[d.step_name] + '"><?php echo esc_js( __( 'Review drafts', 'ai-layer' ) ); ?></a>' : '—'}</td>`;
							}
							resultsBody.appendChild(tr);
						}
					} catch (err) {
						showError(err.message);
						return;
					}
				}

				bar.style.width = '100%';
				progress.style.display = 'none';
				results.style.display  = 'block';
				btn.disabled = false;
			});

			function showError(msg) {
				progress.style.display = 'none';
				errorTxt.textContent   = msg;
				errorBox.style.display = 'block';
				btn.disabled           = false;
			}
		})();

		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wpail_ai_import' ) ); ?>;

			async function runAction( action, btn, statusEl, pendingMsg, successFn ) {
				btn.disabled         = true;
				statusEl.style.color = '#646970';
				statusEl.textContent = pendingMsg;
				const data = new FormData();
				data.append('action', action);
				data.append('nonce', nonce);
				try {
					const res  = await fetch(ajaxUrl, { method: 'POST', body: data });
					const json = await res.json();
					if (json.success) {
						statusEl.style.color = '#00a32a';
						statusEl.textContent = successFn(json.data);
					} else {
						statusEl.style.color = '#d63638';
						statusEl.textContent = json.data?.message || <?php echo wp_json_encode( __( 'Something went wrong.', 'ai-layer' ) ); ?>;
					}
				} catch (err) {
					statusEl.style.color = '#d63638';
					statusEl.textContent = err.message;
				} finally {
					btn.disabled = false;
				}
			}

			document.getElementById('wpail-resync-btn')?.addEventListener('click', function () {
				if ( ! confirm( <?php echo wp_json_encode( __( 'This will repair any missing inverse links using existing relationship data. Continue?', 'ai-layer' ) ); ?> ) ) {
					return;
				}
				runAction(
					'wpail_ai_resync',
					this,
					document.getElementById('wpail-resync-status'),
					<?php echo wp_json_encode( __( 'Resyncing…', 'ai-layer' ) ); ?>,
					data => <?php echo wp_json_encode( __( 'Done —', 'ai-layer' ) ); ?> + ' ' + data.processed + ' ' + <?php echo wp_json_encode( __( 'posts processed.', 'ai-layer' ) ); ?>
				);
			});

			document.getElementById('wpail-find-rel-btn')?.addEventListener('click', function () {
				if ( ! confirm( <?php echo wp_json_encode( __( 'This will use AI to discover and add new relationships. Existing links will not be removed. Continue?', 'ai-layer' ) ); ?> ) ) {
					return;
				}
				runAction(
					'wpail_ai_find_relationships',
					this,
					document.getElementById('wpail-find-rel-status'),
					<?php echo wp_json_encode( __( 'Asking AI to find relationships…', 'ai-layer' ) ); ?>,
					data => <?php echo wp_json_encode( __( 'Done —', 'ai-layer' ) ); ?> + ' ' + data.updated + ' ' + <?php echo wp_json_encode( __( 'entities updated.', 'ai-layer' ) ); ?>
				);
			});

			document.getElementById('wpail-rebuild-rel-btn')?.addEventListener('click', function () {
				if ( ! confirm( <?php echo wp_json_encode( __( 'This will replace all existing relationship data using AI. Any links not confirmed by the AI will be removed. Continue?', 'ai-layer' ) ); ?> ) ) {
					return;
				}
				runAction(
					'wpail_ai_rebuild_relationships',
					this,
					document.getElementById('wpail-rebuild-rel-status'),
					<?php echo wp_json_encode( __( 'Rebuilding relationships via AI — this may take a moment…', 'ai-layer' ) ); ?>,
					data => <?php echo wp_json_encode( __( 'Done —', 'ai-layer' ) ); ?> + ' ' + data.updated + ' ' + <?php echo wp_json_encode( __( 'entities updated.', 'ai-layer' ) ); ?>
				);
			});
		})();
		</script>
		<?php
	}
}

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

		$pages = get_posts( [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
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

			<?php // ── Settings ─────────────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:20px;">
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
								<p class="description"><?php esc_html_e( 'GPT-4o Mini is the recommended default — fast, cheap, and accurate for most sites.', 'ai-layer' ); ?></p>
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

					<p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Settings', 'ai-layer' ); ?></button></p>
				</form>
			</div>

			<?php // ── Import ────────────────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:20px;" id="wpail-ai-import-card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Import from Pages', 'ai-layer' ); ?></h2>

				<?php if ( ! $has_key ) : ?>
					<div class="notice notice-warning inline" style="margin:0 0 16px;">
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

				<p class="description" style="margin:0 0 16px;">
					<?php esc_html_e( 'Select the pages that describe your business (e.g. Services, About, Areas). The AI will read them and create draft items for each entity type. You can review and publish the drafts afterwards.', 'ai-layer' ); ?>
				</p>
				<div class="notice notice-warning inline" style="margin:0 0 16px;">
					<p><?php esc_html_e( 'AI can make mistakes. All extracted items should be reviewed manually before publishing — check titles, content, and relationships for accuracy.', 'ai-layer' ); ?></p>
				</div>

				<?php if ( empty( $pages ) ) : ?>
					<p><?php esc_html_e( 'No published pages found.', 'ai-layer' ); ?></p>
				<?php else : ?>
					<div id="wpail-page-list" style="columns:2;max-width:680px;margin-bottom:16px;">
						<?php foreach ( $pages as $page ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox"
								       class="wpail-ai-page-cb"
								       value="<?php echo esc_attr( (string) $page->ID ); ?>" />
								<?php echo esc_html( $page->post_title ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<p style="margin:16px 0 8px;"><strong><?php esc_html_e( 'What to extract', 'ai-layer' ); ?></strong></p>
					<div style="display:flex;flex-wrap:wrap;gap:12px 24px;margin-bottom:16px;">
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
							<label>
								<input type="checkbox"
								       class="wpail-ai-type-cb"
								       value="<?php echo esc_attr( $type ); ?>"
								       checked />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<p>
						<button id="wpail-ai-start-btn"
						        class="button button-primary"
						        <?php disabled( ! $has_key ); ?>>
							<?php esc_html_e( 'Start AI Extraction', 'ai-layer' ); ?>
						</button>
						<a href="#" id="wpail-ai-select-all" style="margin-left:12px;"><?php esc_html_e( 'Select all pages', 'ai-layer' ); ?></a>
					</p>
				<?php endif; ?>

				<?php // ── Progress ──────────────────────────────────────── ?>
				<div id="wpail-ai-progress" style="display:none;margin-top:20px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Extracting…', 'ai-layer' ); ?></h3>
					<div style="background:#e0e0e0;border-radius:4px;height:12px;overflow:hidden;max-width:500px;">
						<div id="wpail-ai-bar" style="background:#2271b1;height:12px;width:0;transition:width .4s;"></div>
					</div>
					<p id="wpail-ai-status-text" style="margin:8px 0 0;color:#646970;"></p>
				</div>

				<?php // ── Results ───────────────────────────────────────── ?>
				<div id="wpail-ai-results" style="display:none;margin-top:20px;">
					<h3 style="margin-top:0;color:#00a32a;">&#10003; <?php esc_html_e( 'Extraction complete', 'ai-layer' ); ?></h3>
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
					<p style="margin-top:12px;">
						<?php esc_html_e( 'All items were saved as drafts. Review and publish the ones you want to keep.', 'ai-layer' ); ?>
					</p>
				</div>

				<?php // ── Error ─────────────────────────────────────────── ?>
				<div id="wpail-ai-error" style="display:none;margin-top:16px;" class="notice notice-error inline">
					<p id="wpail-ai-error-text"></p>
				</div>
			</div>

			<?php // ── Relationships ─────────────────────────────────── ?>
			<div class="wpail-card" style="margin-top:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Relationships', 'ai-layer' ); ?></h2>

				<table class="widefat striped" style="max-width:680px;margin-bottom:20px;font-size:13px;">
					<thead>
						<tr>
							<th style="width:160px;"><?php esc_html_e( 'Relationship', 'ai-layer' ); ?></th>
							<th><?php esc_html_e( 'Set when…', 'ai-layer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'FAQ → Service', 'ai-layer' ); ?></strong></td>
							<td><?php esc_html_e( 'The FAQ is clearly about that service.', 'ai-layer' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Proof → Service', 'ai-layer' ); ?></strong></td>
							<td><?php esc_html_e( 'The testimonial, stat, or award is clearly about a specific service (not set for general company-wide proof).', 'ai-layer' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Proof → Location', 'ai-layer' ); ?></strong></td>
							<td><?php esc_html_e( 'A specific city or area is explicitly named in the proof text. Never inferred — left empty if no place name appears.', 'ai-layer' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Action → Service', 'ai-layer' ); ?></strong></td>
							<td><?php esc_html_e( 'The action is the primary way to enquire about or book that service.', 'ai-layer' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Location → Service', 'ai-layer' ); ?></strong></td>
							<td><?php esc_html_e( 'The service is offered at or from that location.', 'ai-layer' ); ?></td>
						</tr>
					</tbody>
				</table>

				<div style="display:flex;gap:32px;flex-wrap:wrap;align-items:flex-start;">

					<div style="flex:1;min-width:260px;max-width:340px;">
						<h3 style="margin-top:0;font-size:14px;"><?php esc_html_e( 'Resync All Relationships', 'ai-layer' ); ?></h3>
						<p class="description" style="margin:0 0 12px;">
							<?php esc_html_e( 'Repairs any missing inverse links using the relationship data that is already saved. Safe to run at any time — additive only, never removes existing relationships. No API key required.', 'ai-layer' ); ?>
						</p>
						<button id="wpail-resync-btn" class="button button-secondary">
							<?php esc_html_e( 'Resync All Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-resync-status" style="margin:8px 0 0;color:#646970;min-height:20px;"></p>
					</div>

					<div style="flex:1;min-width:260px;max-width:340px;">
						<h3 style="margin-top:0;font-size:14px;"><?php esc_html_e( 'Find New Relationships', 'ai-layer' ); ?></h3>
						<p class="description" style="margin:0 0 12px;">
							<?php esc_html_e( 'Uses AI to discover relationships that are not yet set. Adds new links — does not remove existing ones. Requires an API key.', 'ai-layer' ); ?>
						</p>
						<div class="notice notice-warning inline" style="margin:0 0 12px;padding:6px 12px;">
							<p style="margin:0;font-size:12px;">
								<?php esc_html_e( 'AI can make mistakes. Review all relationships manually after running this.', 'ai-layer' ); ?>
							</p>
						</div>
						<button id="wpail-find-rel-btn" class="button button-secondary">
							<?php esc_html_e( 'Find New Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-find-rel-status" style="margin:8px 0 0;color:#646970;min-height:20px;"></p>
					</div>

					<div style="flex:1;min-width:260px;max-width:340px;">
						<h3 style="margin-top:0;font-size:14px;"><?php esc_html_e( 'Rebuild All Relationships', 'ai-layer' ); ?></h3>
						<p class="description" style="margin:0 0 12px;">
							<?php esc_html_e( 'Uses AI to set the complete, authoritative set of relationships for every entity. Existing relationship data is replaced — any links not confirmed by the AI will be removed. Requires an API key.', 'ai-layer' ); ?>
						</p>
						<div class="notice notice-error inline" style="margin:0 0 12px;padding:6px 12px;">
							<p style="margin:0;font-size:12px;">
								<strong><?php esc_html_e( 'Destructive:', 'ai-layer' ); ?></strong>
								<?php esc_html_e( 'This will overwrite all existing relationship data. Review manually afterwards — AI can remove valid links.', 'ai-layer' ); ?>
							</p>
						</div>
						<button id="wpail-rebuild-rel-btn" class="button" style="background:#d63638;border-color:#d63638;color:#fff;">
							<?php esc_html_e( 'Rebuild All Relationships', 'ai-layer' ); ?>
						</button>
						<p id="wpail-rebuild-rel-status" style="margin:8px 0 0;color:#646970;min-height:20px;"></p>
					</div>

				</div>
			</div>

		</div>

		<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wpail_ai_import' ) ); ?>;
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

			const btn       = document.getElementById('wpail-ai-start-btn');
			const progress  = document.getElementById('wpail-ai-progress');
			const bar       = document.getElementById('wpail-ai-bar');
			const statusTxt = document.getElementById('wpail-ai-status-text');
			const results   = document.getElementById('wpail-ai-results');
			const resultsBody = document.getElementById('wpail-ai-results-body');
			const errorBox  = document.getElementById('wpail-ai-error');
			const errorTxt  = document.getElementById('wpail-ai-error-text');

			document.getElementById('wpail-ai-select-all')?.addEventListener('click', function (e) {
				e.preventDefault();
				document.querySelectorAll('.wpail-ai-page-cb').forEach(cb => cb.checked = true);
			});

			btn?.addEventListener('click', async function () {
				const checked      = [...document.querySelectorAll('.wpail-ai-page-cb:checked')].map(cb => cb.value);
				const checkedTypes = [...document.querySelectorAll('.wpail-ai-type-cb:checked')].map(cb => cb.value);

				if (!checked.length) {
					alert(<?php echo wp_json_encode( __( 'Select at least one page.', 'ai-layer' ) ); ?>);
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

				// Start job.
				const startData = new FormData();
				startData.append('action', 'wpail_ai_start');
				startData.append('nonce', nonce);
				checked.forEach(id => startData.append('post_ids[]', id));
				checkedTypes.forEach(t => startData.append('types[]', t));

				let jobId, activeTypes;
				try {
					const startRes = await fetch(ajaxUrl, { method: 'POST', body: startData });
					const startJson = await startRes.json();
					if (!startJson.success) throw new Error(startJson.data?.message || 'Failed to start job.');
					jobId       = startJson.data.job_id;
					activeTypes = startJson.data.types;
				} catch (err) {
					showError(err.message);
					return;
				}

				// Step through all types (content types + automatic 'link' step).
				for (let i = 0; i < activeTypes.length; i++) {
					const pct = Math.round((i / activeTypes.length) * 100);
					bar.style.width = pct + '%';
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
								tr.innerHTML = `<td>${labels.link}</td>` +
									`<td><strong>${d.created}</strong></td>` +
									`<td>—</td>`;
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

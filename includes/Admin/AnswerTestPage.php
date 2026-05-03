<?php
/**
 * Admin page: Answer Engine test console.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Repositories\ServiceRepository;
use WPAIL\Repositories\LocationRepository;

class AnswerTestPage {

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$services  = ( new ServiceRepository() )->get_all();
		$locations = ( new LocationRepository() )->get_all();
		$rest_url  = rest_url( 'ai-layer/v1/answers' );
		$nonce     = wp_create_nonce( 'wp_rest' );
		?>
		<div class="wrap wpail-admin">

			<div class="wpail-admin__header">
				<div>
					<h1><?php esc_html_e( 'Answer Engine Test', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline" style="margin-bottom: 20px;">
						<?php esc_html_e( 'Ask a natural language question and see exactly what the answer engine returns.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<div class="wpail-card" style="max-width: 860px;">

				<div class="wpail-test__form-row">
					<input type="text" id="wpail-test-query"
						class="large-text wpail-test__input"
						placeholder="<?php esc_attr_e( 'e.g. Do you offer SEO audits in Manchester?', 'ai-layer' ); ?>"
						autocomplete="off">
					<button id="wpail-test-submit" class="button button-primary wpail-test__submit">
						<?php esc_html_e( 'Ask', 'ai-layer' ); ?>
					</button>
				</div>

				<?php if ( ! empty( $services ) || ! empty( $locations ) ) : ?>
				<div class="wpail-test__hints">
					<span class="wpail-test__hints-label"><?php esc_html_e( 'Hints (optional):', 'ai-layer' ); ?></span>
					<?php if ( ! empty( $services ) ) : ?>
					<select id="wpail-test-service" class="wpail-test__select">
						<option value=""><?php esc_html_e( 'No service hint', 'ai-layer' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
						<option value="<?php echo esc_attr( (string) $service->id ); ?>">
							<?php echo esc_html( $service->name ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
					<?php if ( ! empty( $locations ) ) : ?>
					<select id="wpail-test-location" class="wpail-test__select">
						<option value=""><?php esc_html_e( 'No location hint', 'ai-layer' ); ?></option>
						<?php foreach ( $locations as $location ) : ?>
						<option value="<?php echo esc_attr( (string) $location->id ); ?>">
							<?php echo esc_html( $location->name ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div id="wpail-test-result" style="display:none;">

					<div id="wpail-test-error" class="notice notice-error" style="display:none; margin: 20px 0 0;">
						<p id="wpail-test-error-msg"></p>
					</div>

					<div id="wpail-test-output" class="wpail-test__result-inner">

						<div class="wpail-test__meta-row">
							<span id="wpail-test-confidence" class="wpail-badge"></span>
							<span id="wpail-test-source" class="wpail-badge wpail-badge--private"></span>
							<span id="wpail-test-service-tag" class="wpail-badge" style="background:#eef6fb;color:#0073aa;border:1px solid #bad9eb;display:none;"></span>
							<span id="wpail-test-location-tag" class="wpail-badge" style="background:#f0f6e8;color:#4a7c1f;border:1px solid #c2dea0;display:none;"></span>
						</div>

						<div class="wpail-test__answer">
							<p id="wpail-test-short" class="wpail-test__short"></p>
							<p id="wpail-test-long" class="wpail-test__long" style="display:none;"></p>
							<button id="wpail-test-toggle-long" class="button button-link" style="display:none; padding: 0; margin-top: 6px; font-size: 12px;">
								<?php esc_html_e( '+ Show full answer', 'ai-layer' ); ?>
							</button>
						</div>

						<div id="wpail-test-faqs-wrap" style="display:none;">
							<h4 class="wpail-test__section-title"><?php esc_html_e( 'Matched FAQ', 'ai-layer' ); ?></h4>
							<div id="wpail-test-faqs"></div>
						</div>

						<div id="wpail-test-actions-wrap" style="display:none;">
							<h4 class="wpail-test__section-title"><?php esc_html_e( 'Suggested Actions', 'ai-layer' ); ?></h4>
							<ul id="wpail-test-actions" class="wpail-test__list"></ul>
						</div>

						<div id="wpail-test-proof-wrap" style="display:none;">
							<h4 class="wpail-test__section-title"><?php esc_html_e( 'Supporting Proof', 'ai-layer' ); ?></h4>
							<ul id="wpail-test-proof" class="wpail-test__list"></ul>
						</div>

						<details class="wpail-test__raw-wrap">
							<summary class="wpail-test__raw-toggle"><?php esc_html_e( 'Raw JSON response', 'ai-layer' ); ?></summary>
							<pre id="wpail-test-raw" class="wpail-test__raw"></pre>
						</details>

					</div><!-- /#wpail-test-output -->

				</div><!-- /#wpail-test-result -->

			</div><!-- /.wpail-card -->

		</div><!-- /.wrap -->

		<script>
		(function () {
			var form     = document.getElementById( 'wpail-test-submit' );
			var queryEl  = document.getElementById( 'wpail-test-query' );
			var result   = document.getElementById( 'wpail-test-result' );
			var output   = document.getElementById( 'wpail-test-output' );
			var errorBox = document.getElementById( 'wpail-test-error' );
			var errorMsg = document.getElementById( 'wpail-test-error-msg' );
			var restUrl  = <?php echo wp_json_encode( $rest_url ); ?>;
			var nonce    = <?php echo wp_json_encode( $nonce ); ?>;

			function ask() {
				var query = queryEl.value.trim();
				if ( ! query ) {
					queryEl.focus();
					return;
				}

				var url = new URL( restUrl );
				url.searchParams.set( 'query', query );

				var service  = document.getElementById( 'wpail-test-service' );
				var location = document.getElementById( 'wpail-test-location' );
				if ( service && service.value )   { url.searchParams.set( 'service',  service.value ); }
				if ( location && location.value ) { url.searchParams.set( 'location', location.value ); }

				form.textContent = '<?php echo esc_js( __( 'Asking…', 'ai-layer' ) ); ?>';
				form.disabled = true;
				result.style.display = 'none';

				fetch( url.toString(), {
					headers: { 'X-WP-Nonce': nonce }
				} )
				.then( function ( res ) { return res.json().then( function ( body ) { return { ok: res.ok, status: res.status, body: body }; } ); } )
				.then( function ( res ) {
					result.style.display = 'block';
					if ( ! res.ok || res.body.code ) {
						showError( res.body.message || 'No matching answer found.' );
					} else {
						showResult( res.body.data, res.body );
					}
				} )
				.catch( function ( err ) {
					result.style.display = 'block';
					showError( err.message || 'Request failed.' );
				} )
				.finally( function () {
					form.textContent = '<?php echo esc_js( __( 'Ask', 'ai-layer' ) ); ?>';
					form.disabled = false;
				} );
			}

			function showError( msg ) {
				output.style.display = 'none';
				errorBox.style.display = 'block';
				errorMsg.textContent = msg;
			}

			function showResult( data, raw ) {
				errorBox.style.display = 'none';
				output.style.display = 'block';

				// Confidence badge.
				var conf = document.getElementById( 'wpail-test-confidence' );
				conf.textContent = 'Confidence: ' + ( data.confidence || '—' );
				conf.style.background = data.confidence === 'high' ? '#edfaef' : data.confidence === 'medium' ? '#fff8e5' : '#fef0f0';
				conf.style.color      = data.confidence === 'high' ? '#00a32a' : data.confidence === 'medium' ? '#996800' : '#c02b0a';
				conf.style.border     = '1px solid ' + ( data.confidence === 'high' ? '#b8e6bf' : data.confidence === 'medium' ? '#f5d983' : '#facac4' );

				// Source badge.
				var src = document.getElementById( 'wpail-test-source' );
				src.textContent = 'Source: ' + ( data.source || '—' );

				// Service tags.
				var svcTag = document.getElementById( 'wpail-test-service-tag' );
				if ( data.services && data.services.length ) {
					svcTag.textContent = '⚙ ' + data.services.map( function ( s ) { return s.name; } ).join( ', ' );
					svcTag.style.display = 'inline';
				} else {
					svcTag.style.display = 'none';
				}

				// Location tags.
				var locTag = document.getElementById( 'wpail-test-location-tag' );
				if ( data.locations && data.locations.length ) {
					locTag.textContent = '📍 ' + data.locations.map( function ( l ) { return l.name; } ).join( ', ' );
					locTag.style.display = 'inline';
				} else {
					locTag.style.display = 'none';
				}

				// Short answer.
				document.getElementById( 'wpail-test-short' ).textContent = data.answer_short || '';

				// Long answer toggle.
				var longEl   = document.getElementById( 'wpail-test-long' );
				var toggleEl = document.getElementById( 'wpail-test-toggle-long' );
				if ( data.answer_long && data.answer_long !== data.answer_short ) {
					longEl.textContent = data.answer_long;
					toggleEl.style.display = 'inline';
					toggleEl.textContent = '+ Show full answer';
					longEl.style.display = 'none';
				} else {
					longEl.style.display = 'none';
					toggleEl.style.display = 'none';
				}

				// FAQs.
				var faqsWrap = document.getElementById( 'wpail-test-faqs-wrap' );
				var faqsEl   = document.getElementById( 'wpail-test-faqs' );
				faqsEl.innerHTML = '';
				var faqs = data.source_faqs || [];
				if ( faqs.length ) {
					faqsWrap.style.display = 'block';
					faqs.forEach( function ( faq ) {
						var div = document.createElement( 'div' );
						div.className = 'wpail-test__faq';
						var q = document.createElement( 'p' );
						q.className = 'wpail-test__faq-question';
						q.textContent = faq.question || '';
						div.appendChild( q );
						if ( faq.short_answer ) {
							var a = document.createElement( 'p' );
							a.className = 'wpail-test__faq-answer';
							a.textContent = faq.short_answer;
							div.appendChild( a );
						}
						faqsEl.appendChild( div );
					} );
				} else {
					faqsWrap.style.display = 'none';
				}

				// Actions.
				var actionsWrap = document.getElementById( 'wpail-test-actions-wrap' );
				var actionsList = document.getElementById( 'wpail-test-actions' );
				actionsList.innerHTML = '';
				var actions = data.actions || [];
				if ( actions.length ) {
					actionsWrap.style.display = 'block';
					actions.forEach( function ( a ) {
						var li      = document.createElement( 'li' );
						li.className = 'wpail-test__action';
						var badge  = document.createElement( 'span' );
						badge.className = 'wpail-test__action-type wpail-test__action-type--' + ( a.type || '' );
						badge.textContent = a.type || 'action';
						var label  = document.createElement( 'span' );
						label.className = 'wpail-test__action-label';
						if ( a.url ) {
							var link = document.createElement( 'a' );
							try {
								var parsed = new URL( a.url, window.location.href );
								var okProto = parsed.protocol === 'http:' || parsed.protocol === 'https:' || parsed.protocol === 'mailto:' || parsed.protocol === 'tel:';
								link.href = okProto ? parsed.href : '#';
								if ( ! okProto ) {
									link.addEventListener( 'click', function ( e ) { e.preventDefault(); } );
								}
							} catch ( err ) {
								link.href = '#';
								link.addEventListener( 'click', function ( e ) { e.preventDefault(); } );
							}
							link.target = '_blank';
							link.rel = 'noopener';
							link.textContent = a.label || a.url;
							label.appendChild( link );
						} else {
							label.textContent = a.label || '';
						}
						li.appendChild( badge );
						li.appendChild( label );
						if ( a.phone ) {
							var ph = document.createElement( 'span' );
							ph.className = 'wpail-test__action-url';
							ph.textContent = a.phone;
							li.appendChild( ph );
						} else if ( a.url ) {
							var urlSpan = document.createElement( 'span' );
							urlSpan.className = 'wpail-test__action-url';
							urlSpan.textContent = a.url;
							li.appendChild( urlSpan );
						}
						actionsList.appendChild( li );
					} );
				} else {
					actionsWrap.style.display = 'none';
				}

				// Proof.
				var proofWrap = document.getElementById( 'wpail-test-proof-wrap' );
				var proofList = document.getElementById( 'wpail-test-proof' );
				proofList.innerHTML = '';
				var proof = data.supporting_data || [];
				if ( proof.length ) {
					proofWrap.style.display = 'block';
					proof.forEach( function ( p ) {
						var li = document.createElement( 'li' );
						li.className = 'wpail-test__proof';
						if ( p.type ) {
							var badge = document.createElement( 'span' );
							badge.className = 'wpail-test__proof-type wpail-test__proof-type--' + p.type;
							badge.textContent = p.type.replace( /_/g, ' ' );
							li.appendChild( badge );
						}
						var hl = document.createElement( 'span' );
						hl.className = 'wpail-test__proof-headline';
						hl.textContent = p.headline || '';
						li.appendChild( hl );
						proofList.appendChild( li );
					} );
				} else {
					proofWrap.style.display = 'none';
				}

				// Raw JSON.
				document.getElementById( 'wpail-test-raw' ).textContent = JSON.stringify( raw, null, 2 );
			}

			// Long answer toggle.
			document.getElementById( 'wpail-test-toggle-long' ).addEventListener( 'click', function () {
				var longEl = document.getElementById( 'wpail-test-long' );
				var visible = longEl.style.display !== 'none';
				longEl.style.display = visible ? 'none' : 'block';
				this.textContent = visible ? '+ Show full answer' : '− Hide full answer';
			} );

			form.addEventListener( 'click', ask );
			queryEl.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) { ask(); }
			} );
		}());
		</script>
		<?php
	}
}

<?php
/**
 * Admin page: Analytics dashboard.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\Analytics\AnalyticsRepository;
use WPAIL\Analytics\AuditRepository;
use WPAIL\Admin\SettingsPage;

class AnalyticsPage {

	/** @var array<int,string> */
	private static array $period_labels = [
		7  => 'Last 7 days',
		30 => 'Last 30 days',
		90 => 'Last 90 days',
		0  => 'All time',
	];

	/** @var array<string,string> */
	private static array $endpoint_labels = [
		'answers'   => 'Answer Engine',
		'services'  => 'Services',
		'locations' => 'Locations',
		'faqs'      => 'FAQs',
		'proof'     => 'Proof & Trust',
		'actions'   => 'Actions',
		'profile'   => 'Business Profile',
		'products'  => 'Products',
	];

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$period = self::current_period();
		$repo   = new AnalyticsRepository();
		$stats  = $repo->get_summary( $period );
		$top    = $repo->get_top_queries( 20, $period );
		$missed = $repo->get_unanswered_queries( 20, $period );
		$hits   = $repo->get_endpoint_hits( $period );

		$audit_repo    = new AuditRepository();
		$audit_entries = $audit_repo->get_recent( 20 );

		$retention_days = (int) SettingsPage::get( SettingsPage::SETTING_ANALYTICS_RETENTION_DAYS, 365 );
		?>
		<div class="wrap wpail-admin">

			<div class="wpail-admin__header">
				<div>
					<h1><?php esc_html_e( 'Analytics', 'ai-layer' ); ?></h1>
					<p class="wpail-overview__tagline" style="margin-bottom:0;">
						<?php esc_html_e( 'Endpoint hit tracking and query intelligence — see what AI systems are asking about your business.', 'ai-layer' ); ?>
					</p>
				</div>
			</div>

			<?php // Period selector. ?>
			<div class="wpail-analytics__period-nav">
				<?php foreach ( self::$period_labels as $days => $label ) : ?>
					<?php
					$url     = add_query_arg( [ 'page' => 'wpail_analytics', 'period' => $days ], admin_url( 'admin.php' ) );
					$is_active = ( $period === $days );
					?>
					<a href="<?php echo esc_url( $url ); ?>"
					   class="button <?php echo $is_active ? 'button-primary' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
				<?php if ( $retention_days > 0 ) : ?>
					<span class="wpail-analytics__retention-note">
						<?php
						printf(
							/* translators: %d: number of days */
							esc_html__( 'Data retained for %d days.', 'ai-layer' ),
							$retention_days
						);
						?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_settings#wpail-analytics-retention' ) ); ?>"><?php esc_html_e( 'Change', 'ai-layer' ); ?></a>
					</span>
				<?php else : ?>
					<span class="wpail-analytics__retention-note">
						<?php esc_html_e( 'Unlimited data retention.', 'ai-layer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpail_settings#wpail-analytics-retention' ) ); ?>"><?php esc_html_e( 'Change', 'ai-layer' ); ?></a>
					</span>
				<?php endif; ?>
			</div>

			<?php // Summary stat cards. ?>
			<div class="wpail-analytics__stats">
				<?php
				self::stat_card(
					(string) number_format( $stats['total_hits'] ),
					__( 'Endpoint hits', 'ai-layer' ),
					'#2271b1'
				);
				self::stat_card(
					(string) number_format( $stats['total_queries'] ),
					__( 'Answer engine queries', 'ai-layer' ),
					'#8c8f94'
				);
				self::stat_card(
					(string) number_format( $stats['answered'] ),
					__( 'Queries answered', 'ai-layer' ),
					'#00a32a'
				);
				self::stat_card(
					$stats['total_queries'] > 0 ? $stats['answer_rate'] . '%' : '—',
					__( 'Answer rate', 'ai-layer' ),
					$stats['answer_rate'] >= 80 ? '#00a32a' : ( $stats['answer_rate'] >= 50 ? '#996800' : '#c02b0a' )
				);
				?>
			</div>

			<?php // Two-column section: top questions + unanswered. ?>
			<div class="wpail-analytics__grid">

				<div class="wpail-card">
					<h3 class="wpail-analytics__card-title">
						<?php esc_html_e( 'Top questions AI is asking about your business', 'ai-layer' ); ?>
					</h3>
					<?php if ( empty( $top ) ) : ?>
						<p class="wpail-analytics__empty"><?php esc_html_e( 'No queries recorded yet. Questions sent to the /answers endpoint will appear here.', 'ai-layer' ); ?></p>
					<?php else : ?>
						<table class="widefat striped wpail-analytics__table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Question', 'ai-layer' ); ?></th>
									<th class="wpail-analytics__col-num"><?php esc_html_e( 'Asked', 'ai-layer' ); ?></th>
									<th class="wpail-analytics__col-num"><?php esc_html_e( 'Answered', 'ai-layer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['query_text'] ); ?></td>
										<td class="wpail-analytics__col-num"><?php echo esc_html( number_format( (int) $row['count'] ) ); ?></td>
										<td class="wpail-analytics__col-num">
											<?php
											$answered_pct = (int) $row['count'] > 0
												? (int) round( (int) $row['matched_count'] / (int) $row['count'] * 100 )
												: 0;
											$color = $answered_pct >= 80 ? '#00a32a' : ( $answered_pct >= 50 ? '#996800' : '#c02b0a' );
											printf(
												'<span style="color:%s;font-weight:600;">%d%%</span>',
												esc_attr( $color ),
												$answered_pct
											);
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div class="wpail-card">
					<h3 class="wpail-analytics__card-title">
						<?php esc_html_e( 'Missing intents — unanswered queries', 'ai-layer' ); ?>
					</h3>
					<?php if ( empty( $missed ) ) : ?>
						<p class="wpail-analytics__empty">
							<?php echo $stats['total_queries'] > 0
								? esc_html__( 'All queries have been answered.', 'ai-layer' )
								: esc_html__( 'No query data yet.', 'ai-layer' ); ?>
						</p>
					<?php else : ?>
						<p class="description" style="margin:0 0 14px;">
							<?php esc_html_e( 'These questions could not be answered. Add FAQs or Authored Answers to fill these gaps.', 'ai-layer' ); ?>
						</p>
						<table class="widefat striped wpail-analytics__table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Unanswered question', 'ai-layer' ); ?></th>
									<th class="wpail-analytics__col-num"><?php esc_html_e( 'Times asked', 'ai-layer' ); ?></th>
									<th><?php esc_html_e( 'Fix', 'ai-layer' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $missed as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['query_text'] ); ?></td>
										<td class="wpail-analytics__col-num"><?php echo esc_html( number_format( (int) $row['count'] ) ); ?></td>
										<td>
											<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpail_faq' ) ); ?>" class="button button-small">
												<?php esc_html_e( '+ Add FAQ', 'ai-layer' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

			</div>

			<?php // Endpoint hits breakdown. ?>
			<div class="wpail-card" style="margin-top:20px;">
				<h3 class="wpail-analytics__card-title">
					<?php esc_html_e( 'Endpoint hit breakdown', 'ai-layer' ); ?>
				</h3>
				<?php if ( empty( $hits ) ) : ?>
					<p class="wpail-analytics__empty">
						<?php esc_html_e( 'No endpoint hits recorded yet. Hits will appear here automatically as your API is called.', 'ai-layer' ); ?>
					</p>
				<?php else : ?>
					<table class="widefat striped wpail-analytics__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Endpoint', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Route', 'ai-layer' ); ?></th>
								<th class="wpail-analytics__col-num"><?php esc_html_e( 'Hits', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Share', 'ai-layer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$total_hits = array_sum( array_column( $hits, 'hits' ) );
							foreach ( $hits as $row ) :
								$label = self::$endpoint_labels[ $row['endpoint'] ] ?? ucfirst( $row['endpoint'] );
								$pct   = $total_hits > 0 ? round( (int) $row['hits'] / $total_hits * 100 ) : 0;
							?>
								<tr>
									<td><strong><?php echo esc_html( $label ); ?></strong></td>
									<td><code>/<?php echo esc_html( $row['endpoint'] ); ?></code></td>
									<td class="wpail-analytics__col-num"><?php echo esc_html( number_format( (int) $row['hits'] ) ); ?></td>
									<td>
										<div class="wpail-analytics__bar-wrap">
											<div class="wpail-analytics__bar" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></div>
											<span class="wpail-analytics__bar-label"><?php echo esc_html( (string) $pct ); ?>%</span>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php // Recent write operations (audit log). ?>
			<div class="wpail-card" style="margin-top:20px;">
				<h3 class="wpail-analytics__card-title">
					<?php esc_html_e( 'Recent write operations', 'ai-layer' ); ?>
				</h3>
				<?php if ( empty( $audit_entries ) ) : ?>
					<p class="wpail-analytics__empty">
						<?php esc_html_e( 'No write operations logged yet. Create, update, or delete actions will appear here.', 'ai-layer' ); ?>
					</p>
				<?php else : ?>
					<table class="widefat striped wpail-analytics__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date / Time (UTC)', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Action', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'Entity type', 'ai-layer' ); ?></th>
								<th class="wpail-analytics__col-num"><?php esc_html_e( 'ID', 'ai-layer' ); ?></th>
								<th><?php esc_html_e( 'User', 'ai-layer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $audit_entries as $entry ) : ?>
								<tr>
									<td><code><?php echo esc_html( $entry['created_at'] ); ?></code></td>
									<td>
										<?php
										$action_color = 'delete' === $entry['action'] ? '#c02b0a' : ( 'create' === $entry['action'] ? '#00a32a' : '#996800' );
										printf(
											'<span style="color:%s;font-weight:600;">%s</span>',
											esc_attr( $action_color ),
											esc_html( $entry['action'] )
										);
										?>
									</td>
									<td><code><?php echo esc_html( $entry['entity_type'] ); ?></code></td>
									<td class="wpail-analytics__col-num"><?php echo esc_html( (string) $entry['entity_id'] ); ?></td>
									<td><?php echo esc_html( $entry['user_login'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	private static function stat_card( string $value, string $label, string $color ): void {
		?>
		<div class="wpail-analytics__stat">
			<span class="wpail-analytics__stat-number" style="color:<?php echo esc_attr( $color ); ?>;">
				<?php echo esc_html( $value ); ?>
			</span>
			<span class="wpail-analytics__stat-label"><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	private static function current_period(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['period'] ) ? (int) $_GET['period'] : 30;
		return in_array( $raw, [ 7, 30, 90, 0 ], true ) ? $raw : 30;
	}
}

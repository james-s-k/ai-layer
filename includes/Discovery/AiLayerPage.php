<?php
/**
 * Serves the /ai-layer and /ai-layer.md discovery pages.
 *
 * @package WPAIL\Discovery
 */

declare(strict_types=1);

namespace WPAIL\Discovery;

use WPAIL\LLMsTxt\LLMsTxtSettings;

class AiLayerPage {

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_action( 'init',              [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars',        [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
	}

	/**
	 * Add rewrite rules for HTML and Markdown discovery pages.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^ai-layer$',     'index.php?wpail_ai_page=html', 'top' );
		add_rewrite_rule( '^ai-layer\.md$', 'index.php?wpail_ai_page=md',   'top' );
	}

	/**
	 * Register wpail_ai_page as a recognised query variable.
	 *
	 * @param array<string> $vars
	 * @return array<string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'wpail_ai_page';
		return $vars;
	}

	/**
	 * Serve the appropriate page format when the query var is set.
	 */
	public function maybe_serve(): void {
		$format = get_query_var( 'wpail_ai_page' );

		if ( ! $format ) {
			return;
		}

		if ( ! \WPAIL\Admin\SettingsPage::get( \WPAIL\Admin\SettingsPage::SETTING_AI_LAYER_PAGE_ENABLED, true ) ) {
			status_header( 404 );
			exit;
		}

		if ( 'md' === $format ) {
			$this->serve_markdown();
		} else {
			$this->serve_html();
		}
	}

	/**
	 * Serve the HTML discovery page.
	 */
	private function serve_html(): void {
		$base     = rtrim( rest_url( WPAIL_REST_NS ), '/' );
		$manifest = $base . '/manifest';
		$openapi  = $base . '/openapi';
		$sitename = get_bloginfo( 'name' );

		header( 'Content-Type: text/html; charset=utf-8' );

		$llms_txt_enabled = LLMsTxtSettings::get( 'enabled', false );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Layer &mdash; ' . esc_html( $sitename ) . ' | Structured Business Data</title>
<style>
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0d14;color:#e2e8f0;line-height:1.6}
a{color:#4f8ef7;text-decoration:none}
a:hover{text-decoration:underline}
.wrap{max-width:860px;margin:0 auto;padding:48px 24px}
h1{font-size:2rem;font-weight:700;margin:0 0 8px;color:#fff}
.subtitle{font-size:1.05rem;color:#94a3b8;margin:0 0 48px}
h2{font-size:1.1rem;font-weight:600;color:#cbd5e1;margin:40px 0 12px;text-transform:uppercase;letter-spacing:.06em;font-size:.8rem}
.card{background:#111827;border:1px solid #1e2a3a;border-radius:8px;padding:20px 24px;margin-bottom:12px}
.card a{display:block;font-size:.95rem;margin-bottom:4px}
.card .desc{color:#64748b;font-size:.85rem}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th{text-align:left;padding:8px 12px;color:#64748b;font-weight:500;border-bottom:1px solid #1e2a3a}
td{padding:8px 12px;border-bottom:1px solid #111827;vertical-align:top}
td:first-child{color:#4f8ef7;font-family:monospace;white-space:nowrap}
td:nth-child(2){color:#94a3b8;font-size:.8rem;white-space:nowrap}
code{background:#1e2a3a;padding:2px 6px;border-radius:4px;font-size:.85rem;color:#7dd3fc;font-family:monospace}
.footer{margin-top:56px;padding-top:24px;border-top:1px solid #1e2a3a;color:#475569;font-size:.8rem}
</style>
</head>
<body>
<div class="wrap">
<h1>AI Layer: Structured Business Data</h1>
<p class="subtitle">This site exposes structured business data via AI Layer for direct access by AI systems, agents, and search tools.</p>

<h2>Discovery</h2>
<div class="card">
<a href="' . esc_url( $manifest ) . '">Manifest (JSON) &rarr;</a>
<span class="desc">Primary discovery document — start here.</span>
</div>
<div class="card">
<a href="' . esc_url( $openapi ) . '">OpenAPI Spec (JSON) &rarr;</a>
<span class="desc">Full OpenAPI 3.1.0 specification for all endpoints.</span>
</div>
<div class="card">
<a href="' . esc_url( home_url( '/.well-known/ai-layer' ) ) . '">.well-known/ai-layer &rarr;</a>
<span class="desc">Machine-readable well-known discovery document.</span>
</div>';

		if ( $llms_txt_enabled ) {
			echo '
<div class="card">
<a href="' . esc_url( home_url( '/llms.txt' ) ) . '">/llms.txt &rarr;</a>
<span class="desc">LLMs.txt discovery file for large language models.</span>
</div>';
		}

		echo '
<h2>Available Endpoints</h2>
<table>
<thead><tr><th>Path</th><th>Method</th><th>Description</th></tr></thead>
<tbody>
<tr><td>/profile</td><td>GET</td><td>Business name, contact, address, and description</td></tr>
<tr><td>/services</td><td>GET</td><td>All published services</td></tr>
<tr><td>/services/{slug}</td><td>GET</td><td>Full service detail with related FAQs, locations, and proof</td></tr>
<tr><td>/locations</td><td>GET</td><td>All published locations and service areas</td></tr>
<tr><td>/locations/{slug}</td><td>GET</td><td>Full location detail with related services</td></tr>
<tr><td>/faqs</td><td>GET</td><td>All published FAQs (filter by service or location)</td></tr>
<tr><td>/faqs/{id}</td><td>GET</td><td>Single FAQ by post ID</td></tr>
<tr><td>/proof</td><td>GET</td><td>Testimonials, case studies, and accreditations</td></tr>
<tr><td>/proof/{id}</td><td>GET</td><td>Single proof or trust signal</td></tr>
<tr><td>/actions</td><td>GET</td><td>All published calls-to-action</td></tr>
<tr><td>/actions/{id}</td><td>GET</td><td>Single call-to-action</td></tr>
<tr><td>/answers</td><td>GET</td><td>Natural language answer engine or list of authored answers</td></tr>
</tbody>
</table>

<h2>Querying</h2>
<div class="card">
<code>GET ' . esc_html( $base ) . '/answers?query=Do+you+offer+SEO+in+London</code>
<p class="desc" style="margin-top:8px;">The answer engine accepts natural language questions and returns a structured response with confidence scoring, matched services, supporting proof, and suggested next actions.</p>
</div>

<div class="footer">Powered by <a href="https://wordpress.org/plugins/ai-layer/">AI Layer for WordPress</a></div>
</div>
</body>
</html>';
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		exit;
	}

	/**
	 * Serve the Markdown discovery page.
	 */
	private function serve_markdown(): void {
		$base         = rtrim( rest_url( WPAIL_REST_NS ), '/' );
		$manifest_url = $base . '/manifest';
		$openapi_url  = $base . '/openapi';
		$well_known   = home_url( '/.well-known/ai-layer' );

		header( 'Content-Type: text/markdown; charset=utf-8' );

		$output  = "# AI Layer \xe2\x80\x94 Structured Business Data\n\n";
		$output .= "This site exposes structured business data via AI Layer for direct access by AI systems, agents, and search tools.\n\n";
		$output .= "## Discovery\n\n";
		$output .= '- Manifest (JSON): ' . $manifest_url . "\n";
		$output .= '- OpenAPI Spec: ' . $openapi_url . "\n";
		$output .= '- Well-Known: ' . $well_known . "\n\n";
		$output .= "## Endpoints\n\n";
		$output .= "| Path | Description |\n";
		$output .= "|------|-------------|\n";
		$output .= "| /profile | Business name, contact, address, and description |\n";
		$output .= "| /services | Services offered |\n";
		$output .= "| /services/{slug} | Full service detail with related FAQs, locations, and proof |\n";
		$output .= "| /locations | Locations and service areas |\n";
		$output .= "| /locations/{slug} | Full location detail |\n";
		$output .= "| /faqs | Frequently asked questions |\n";
		$output .= "| /faqs/{id} | Single FAQ |\n";
		$output .= "| /proof | Testimonials, case studies, and accreditations |\n";
		$output .= "| /proof/{id} | Single proof item |\n";
		$output .= "| /actions | Calls-to-action |\n";
		$output .= "| /actions/{id} | Single action |\n";
		$output .= "| /answers?query= | Natural language answer engine |\n\n";
		$output .= "## Base URL\n\n";
		$output .= $base . "\n\n";
		$output .= "## Authentication\n\n";
		$output .= "Read endpoints are public. Write endpoints require WordPress Application Passwords (HTTP Basic Auth).\n";

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;

		exit;
	}
}

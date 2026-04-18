===  AI Layer ===
Contributors:      ailayer
Tags:              ai, structured data, rest api, business data, knowledge layer
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Structured business knowledge layer for WordPress. Exposes canonical business data via versioned REST endpoints for AI systems, agents, and search tools.

== Description ==

AI Layer turns your WordPress site from a loose collection of pages into a structured, queryable business knowledge system.

It creates a canonical internal business model — covering services, locations, FAQs, trust signals, and actions — and exposes it through versioned REST API endpoints.

Built for:
* Local service businesses
* Agencies
* Brochure websites
* Any business with services, locations, FAQs, and clear calls to action

**Endpoints (base: /wp-json/ai-layer/v1/)**

* `/profile` — Business profile
* `/services` — All services
* `/services/{slug}` — Single service with relationships
* `/locations` — Service areas and locations
* `/faqs` — FAQs (filterable by service or location)
* `/proof` — Trust signals and testimonials
* `/actions` — Contact/conversion actions
* `/answers?query=...` — Rules-based answer engine

**What this plugin is not:**
* Not a chatbot
* Not a page builder
* Not a replacement for Yoast SEO
* Not a generic schema plugin
* Not an AI content generator

== Installation ==

1. Upload the `ai-layer` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **AI Layer → Business Profile** and fill in your business details
4. Add Services, Locations, FAQs, Proof, and Actions via the sub-menus
5. Visit `/wp-json/ai-layer/v1/profile` to verify your endpoint

== Frequently Asked Questions ==

= Does this replace Yoast SEO? =
No. AI Layer is a structured data layer for API consumers and AI systems. Yoast manages on-page SEO. They serve different purposes and can coexist.

= Will it conflict with Yoast or Rank Math? =
Schema output in AI Layer is disabled by default when Yoast or Rank Math is detected. You can enable it in Settings if you need it.

= Is the data public? =
All REST endpoints are public read-only in v1. Future versions will support authenticated endpoints for private data.

= Can I use this without adding any content? =
Yes — empty endpoints return empty arrays. The plugin is fully functional with partial data.

== Changelog ==

= 1.0.0 =
* Initial release
* Business profile (settings-based)
* Services, Locations, FAQs, Proof, Actions, Answers CPTs
* Versioned REST API endpoints
* Rules-based /answers engine
* Optional Schema.org output (Organisation, FAQPage)
* Yoast SEO and Rank Math conflict detection
* Clean canonical model layer with full transformer/repository architecture

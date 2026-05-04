=== AI Layer ===
Contributors:      JamesKoussertari, strivewp
Tags:              ai, structured data, rest api, llms.txt, ai discovery
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.1
Stable tag:        1.4.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Structured business knowledge layer for WordPress. Give AI systems, agents, and search tools direct, accurate access to your business data.

== Description ==

AI Layer turns your WordPress site from a loose collection of pages into a structured, queryable business knowledge system.

Websites are built for humans to browse. AI can read them — but it has to interpret unstructured content, guess at relationships, and piece together answers across multiple pages. That is slow, unreliable, and wrong more often than it should be.

AI Layer fixes this by giving your site a parallel data layer: clean, typed, connected, and queryable. Humans browse your site. AI queries it.

**What it does**

AI Layer creates a single source of truth for your business inside WordPress. You enter your services, locations, FAQs, trust signals, and calls-to-action once — with proper structure, relationships, and metadata. Everything connects: a service knows its related FAQs, which locations it applies to, what proof supports it, and what a visitor should do next.

That structured data is exposed through simple REST endpoints at `/wp-json/ai-layer/v1/`. Any AI system, agent, or integration can query it directly and get back exactly what it needs — no scraping, no parsing, no guesswork.

AI Layer does not modify your front-end. It operates as a pure data and API layer alongside your existing theme, content, and SEO setup.

**REST API endpoints (base: /wp-json/ai-layer/v1/)**

Read endpoints are public. Write endpoints (POST, PATCH, DELETE) require authentication via WordPress Application Passwords.

* `/profile` — Canonical business profile: name, contact, address, opening hours, social links
* `/services` — All services; `/services/{slug}` for full detail with relationships
* `/locations` — Service areas and locations; `/locations/{slug}` for full detail
* `/faqs` — FAQs, filterable by service or location; `/faqs/{id}` for single item
* `/proof` — Testimonials, case studies, accreditations, and other trust signals; `/proof/{id}` for single item
* `/actions` — Calls-to-action; booking links, phone numbers, contact forms; `/actions/{id}` for single item
* `/answers` — List all manually-authored Answers; `/answers?query=...` runs the rules-based engine (Pro); `/answers/{id}` for single item; POST/PATCH/DELETE for full CRUD management
* `/products` — Live WooCommerce product catalogue (requires WooCommerce + setting enabled)

**MCP integration (WordPress 6.9+ with WordPress MCP Adapter plugin)**

AI Layer registers 33 WordPress Abilities that the MCP Adapter plugin automatically exposes as MCP tools. Connect any MCP-compatible AI client to your site and manage all AI Layer content without touching the admin UI.

Tools cover: read and update for the business profile; full CRUD (list, get, create, update, delete) for Services, Locations, FAQs, Proof & Trust, Actions, and Answers; and a natural-language answer engine query tool. Read tools require a logged-in user. Write tools require `edit_posts`. Delete tools require `delete_posts`.

See the [WordPress MCP Adapter](https://github.com/wordpress/mcp-adapter) documentation for connection instructions.

**The answer engine**

The standout feature is the built-in answer engine. Send it a natural language question — "Do you offer SEO in London?", "How much does web design cost?", "What happens after I place an order?" — and it returns a structured response with an answer, supporting proof, and next actions. All from your content. No external AI call required.

The engine checks manually-authored Answers first (so you can guarantee specific responses to predictable questions), then scores your FAQs, services, and locations to assemble the best possible answer. Every response includes a confidence level and source field.

**Example: one request, one structured response**

`GET /wp-json/ai-layer/v1/answers?query=Do+you+offer+SEO+in+London`

Returns the matched service, location, a direct answer, supporting testimonial, and a booking link — in one hop, from your own data.

**AI discovery features**

* `/.well-known/ai-layer` — Machine-readable JSON discovery document listing all active endpoints. The recommended canonical source of truth for agents.
* `llms.txt` — Dynamically generated at `/llms.txt` following the emerging llms.txt standard. In well-known mode it links to the JSON document; in llms.txt-only mode it lists endpoints directly.
* `AI.txt` *(Beta)* — `/ai.txt` file declaring your crawling, training, and attribution preferences to AI systems.
* Discovery `<link>` tags — `rel="ai-layer"` and `rel="llms-txt"` injected into every page `<head>` by default, so crawlers can find your data from any page.

**Entity types (custom post types)**

* **Services** — Structured service records with pricing, keywords, synonyms, service modes, and relationships to FAQs, proof, actions, and locations
* **Locations** — Physical or virtual locations with region, postcode prefixes, and service radius
* **FAQs** — Questions and answers with intent tags for the answer engine
* **Proof & Trust** — Testimonials, accreditations, statistics, awards, case studies, and media mentions
* **Actions** — Calls-to-action: call, email, book, quote, visit, download, chat
* **Answers** — Manually authored answers that take guaranteed priority in the answer engine

**Bidirectional relationships**

When you link a Service to a Location, the relationship is automatically written back — the Location also knows about the Service. No manual double-entry.

**Setup Wizard**

A revisitable wizard at AI Layer → Setup Wizard auto-populates your Business Profile from WordPress settings, Yoast SEO, Rank Math, and WooCommerce. Every suggestion requires your explicit approval before anything is saved.

**Test Answer Engine**

A built-in test console at AI Layer → Test Answer Engine. Enter any natural language question and see exactly what the engine returns — confidence level, matched source, detected service and location, matched FAQ, suggested actions, supporting proof, and the raw JSON response. Useful for verifying keyword matching, FAQ linking, and manual Answer priority without making external API calls.

**Answer Console shortcode**

Use `[wpail_answer_console]` to embed the query console on any page or post. Visitors can type a natural language question and see the full structured response — including confidence, source, matched FAQ, suggested actions, supporting proof, and the raw JSON — without accessing the admin.

**Analytics dashboard**

AI Layer → Analytics tracks every request to your `ai-layer/v1` endpoints automatically. The dashboard shows total endpoint hits, answer engine query volume, answered vs unanswered counts, and answer rate. Two tables highlight the most frequent questions AI systems are asking and — crucially — the questions that could not be answered, so you know exactly which FAQs or Authored Answers to add next. Configurable data retention (default 365 days) with automatic daily cleanup.

**Schema.org JSON-LD**

Optional Organization, LocalBusiness, and FAQPage structured data output in `<head>`. Works alongside Yoast SEO and Rank Math with conflict detection.

**What this plugin is not**

* Not a chatbot or AI content generator
* Not a page builder or front-end plugin
* Not a replacement for Yoast SEO or Rank Math
* Not a generic schema plugin
* Not an external AI service — the answer engine runs entirely on your server

**Built for**

* Local service businesses
* Agencies and consultancies
* Brochure and lead-generation websites
* Any business with services, locations, FAQs, and clear next steps

== Installation ==

1. Upload the `ai-layer` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Run the **Setup Wizard** at **AI Layer → Setup Wizard** to auto-populate your Business Profile from existing plugins — or skip it and fill in **AI Layer → Business Profile** manually
4. Add Services, Locations, FAQs, Proof & Trust signals, and Actions via the admin menu
5. Optionally enable schema.org JSON-LD output at **AI Layer → Settings**
6. Optionally enable llms.txt at **AI Layer → llms.txt**
7. If WooCommerce is active, optionally enable the `/products` endpoint at **AI Layer → Settings**
8. Visit `/.well-known/ai-layer` to verify your discovery document is live

== Frequently Asked Questions ==

= Does this replace Yoast SEO or Rank Math? =

No. AI Layer is a structured data layer for API consumers and AI systems. Yoast and Rank Math manage on-page SEO signals. They serve different purposes and can coexist. Schema.org output in AI Layer is disabled by default when Yoast or Rank Math is detected, and the settings page warns you if there is a conflict.

= Isn't this just the WordPress REST API? =

WordPress already gives you REST endpoints for posts, users, and blocks. Those are general-purpose data access. AI Layer adds purpose-built surfaces: a single business profile, typed entities with explicit relationships, and a natural-language answer engine — not whatever JSON happened to land in a page.

= Does it work without adding any content? =

Yes. Empty endpoints return empty arrays. The plugin is fully functional with partial data. Start with your Business Profile and add entities over time.

= Is the data public? =

All REST endpoints are public and read-only. Private fields (marked as internal in the field definitions) are automatically excluded from API responses.

= What is the difference between /.well-known/ai-layer and llms.txt? =

`/.well-known/ai-layer` is a machine-readable JSON document listing all active endpoints — designed for agents and tools to query programmatically. `llms.txt` is a human-readable text file following the emerging llms.txt standard — designed as a signpost for AI systems reading your site. In the recommended setup, llms.txt links to the well-known document as a pointer; agents use the JSON as their source of truth.

= Will this conflict with Yoast SEO's own llms.txt? =

The AI Layer llms.txt settings page detects active SEO plugins and warns you. You can disable AI Layer's llms.txt if you prefer to manage it through another plugin, or disable the other plugin's llms.txt if it has that option.

= What is AI.txt and does it actually do anything? =

AI.txt is an emerging standard (similar in concept to robots.txt) that signals to AI systems how they may interact with your content — crawling, training, and attribution. It is experimental. Major AI providers are not yet required to honour it, but publishing it is low-risk and may influence compliant systems. The AI Layer settings page flags this clearly.

= Can I use this as a headless CMS? =

It is not designed as a headless CMS, but if you enable public post type visibility in Settings, your Services, Locations, FAQs, and Proof & Trust items get front-end archive and single-post URLs that your theme can template. The same data powering the API is available in templates via the repository classes or raw post meta.

= Does the WooCommerce endpoint duplicate product data? =

No. The `/products` endpoint reads live from WooCommerce on every request via `wc_get_products()`. No data is copied, stored separately, or written to extra database tables. It is always in sync with your catalogue.

= What are the server requirements? =

WordPress 6.0 or later, PHP 8.1 or later. No additional server software or external services required. The answer engine runs entirely on your server.

= Is it compatible with multisite? =

Single-site only in the current version. Multisite support is not explicitly blocked but has not been tested.

== Screenshots ==

1. Overview dashboard showing entity counts, REST endpoint table, and getting-started checklist
2. Business Profile admin page
3. Setup Wizard — Detect step showing available data sources
4. Setup Wizard — Discovery step for endpoint mode, llms.txt, and AI.txt
5. llms.txt settings page with live preview and conflict detection
6. AI.txt settings page with global rules, agent-specific repeater, and live preview
7. Service CPT edit screen with meta box showing all field groups
8. Settings page — AI Discovery section

== Changelog ==

= 1.4.0 =
* **Analytics dashboard** — new AI Layer → Analytics admin page; tracks every GET request to `ai-layer/v1/*` endpoints automatically; no configuration required
* **Top questions** — ranked table of the most frequent query strings sent to the answer engine; shows ask count and per-query answer rate so you can see what AI systems are most interested in
* **Missing intents** — unanswered queries ranked by frequency with direct "Add FAQ" shortcut links; shows exactly where the answer engine is failing and what content to add next
* **Endpoint hit breakdown** — per-endpoint hit counts and relative share across all active endpoints (`answers`, `services`, `locations`, `faqs`, `proof`, `actions`, `profile`, `products`)
* **Period filtering** — all stats and tables switch between Last 7 days, Last 30 days, Last 90 days, and All time
* **Configurable data retention** — default 365 days; configurable in Settings → Data Management → Analytics retention; leave blank for unlimited; old records pruned automatically by WP-Cron each day; no IP addresses or personal data stored

= 1.3.0 =
* **Answer Console shortcode** — `[wpail_answer_console]` embeds the full answer engine query console on any page or post; visitors can ask natural language questions and see the full structured response (confidence, source, matched FAQ, actions, proof, raw JSON); no login required when Pro is active
* **Shared rendering layer** — admin test page and frontend shortcode share a single rendering class, eliminating duplicated markup and JS

= 1.2.0 =
* **MCP integration** — 33 WordPress Abilities registered under the `ai-layer/` namespace; the WordPress MCP Adapter plugin exposes them as MCP tools automatically; any MCP-compatible AI client can connect and fully manage AI Layer content; requires WordPress 6.9+ (Abilities API is in core) and the WordPress MCP Adapter plugin
* **MCP tools** — full CRUD for Services, Locations, FAQs, Proof & Trust, Actions, and Answers; read and partial-update for Business Profile; natural-language query via `ai-layer-query-answers`
* **Answers CRUD** — `POST /answers`, `GET /answers/{id}`, `PATCH /answers/{id}`, `DELETE /answers/{id}` added; `GET /answers` (no `?query`) now lists all authored Answers for management; five new MCP tools (`ai-layer-list-answers`, `ai-layer-get-answer`, `ai-layer-create-answer`, `ai-layer-update-answer`, `ai-layer-delete-answer`)
* **Write endpoints** — POST, PATCH, and DELETE added for all six entity CPTs via the REST API; authenticated with WordPress Application Passwords
* **Single-item GET** — `GET /faqs/{id}`, `GET /proof/{id}`, `GET /actions/{id}`, and `GET /answers/{id}` added
* **Authentication** — write endpoints use WordPress Application Passwords (HTTP Basic Auth); `edit_posts` required; 401 for missing credentials, 403 for insufficient permissions
* **Relationship sync on write** — POST and PATCH maintain bidirectional relationships; DELETE cleans up all inverse references before removing the post
* **Partial updates** — PATCH and MCP update tools only change fields present in the request; omitted fields are untouched
* **Answer engine extracted** — `AnswerEngine` class shared by the REST endpoint and MCP ability
* **Application Password fix** — filters ensure Application Passwords work on non-SSL localhost without `WP_ENVIRONMENT_TYPE=local`

= 1.1.0 =
* **Setup Wizard** — revisitable wizard at AI Layer → Setup Wizard; auto-populates Business Profile from WordPress core, Yoast SEO, Rank Math, and WooCommerce; source priority system ensures the most authoritative source wins; explicit approval required before any data is written; new Discovery step to configure endpoint mode, link tags, llms.txt, and AI.txt in one place
* **Discovery & AI files** — new Endpoint discovery mode setting: `/.well-known/ai-layer` (recommended, default) or `llms.txt only`; in well-known mode llms.txt outputs a single pointer line; in llms.txt-only mode the well-known URL returns 404; both caches invalidated automatically when settings change
* **`/.well-known/ai-layer`** — machine-readable JSON discovery document listing all active endpoints and accepted parameters; always registered, no rewrite flush required
* **Discovery link tags** — `rel="ai-layer"` and `rel="llms-txt"` injected into every page `<head>` by default; toggle in Settings → AI Discovery; each tag only appears when its corresponding feature is active
* **AI.txt (Beta)** — new admin page at AI Layer → AI.txt; dynamic `/ai.txt` file with global crawling, training, and attribution controls; agent-specific rules repeater; live preview; conflict detection for physical files and plain permalinks
* **Products endpoint** — `GET /products` and `GET /products/{slug}`: live read-only proxy over WooCommerce product data with pagination and category filtering; no data duplication; gated behind a Settings toggle and WooCommerce active check; conditionally included in llms.txt and well-known document
* **Post Type Visibility** — Settings controls to make Services, Locations, FAQs, and Proof & Trust publicly accessible on the front-end with a configurable rewrite slug; permalink rules flushed automatically after save
* **Bidirectional relationships** — saving a relationship on one CPT (e.g. Service → Location) automatically writes the reverse relationship; no manual double-entry
* **Key Pages picker (llms.txt)** — replaced textarea with a Yoast-style page picker: predefined slots for About, Contact, Privacy, Terms, Blog, each with a searchable dropdown; custom pages repeater with Add/Remove
* **Field UX** — placeholders and contextual help text added to all admin fields across Business Profile and all six CPTs
* **Answers moved to free** — Answers CPT and `/answers` endpoint are now free features; Pro gating preserved in `Features::answers_enabled()` for future tiers

= 1.0.0 =
* Initial release
* Business Profile settings page
* Overview dashboard with live entity counts, endpoint table, and getting-started checklist
* Six CPTs: Services, Locations, FAQs, Proof & Trust, Actions, Answers
* REST API: `/profile`, `/services`, `/locations`, `/faqs`, `/proof`, `/actions`
* `/answers` endpoint — rules-based answer engine with service and location detection, confidence scoring, and proof attachment
* Organization and FAQPage schema.org JSON-LD output
* llms.txt support — dynamic virtual route at `/llms.txt` with conflict detection for physical files, plain permalinks, Yoast SEO, and Rank Math
* Yoast SEO and Rank Math schema conflict detection
* Freemius licensing infrastructure

== Upgrade Notice ==

= 1.4.0 =
A new `wpail_analytics` database table is created automatically on first load. No data migration required. Visit AI Layer → Analytics to see endpoint and query data. Set a data retention period in Settings → Data Management if needed.

= 1.3.0 =
No data migration required. Use `[wpail_answer_console]` on any page to embed the answer engine console for site visitors.

= 1.1.0 =
New features require no data migration. The Setup Wizard Discovery step will help you configure endpoint discovery mode, llms.txt, and AI.txt if you have not already done so in Settings.

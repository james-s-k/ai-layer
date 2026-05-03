# AI Layer — WordPress Plugin

AI Layer transforms your WordPress site from a collection of pages into a structured system that can answer questions, expose clean data, and drive actions. Instead of AI scraping messy content and guessing at relationships, it queries your site directly and gets accurate, structured responses.

---

## In Simple Terms

Websites are built for humans to browse. AI can read them — but it has to interpret unstructured content, guess at relationships, and piece together answers across multiple pages. That's slow, unreliable, and wrong more often than it should be.

AI Layer fixes this by giving your site a parallel data layer: clean, typed, connected, and queryable.

> Humans browse your site. AI queries it.

---

## Why This Exists

Every business website contains the same core information — what you offer, where you operate, what you charge, what customers say about you, how to get in touch. But that information is buried in page copy, scattered across templates, and formatted for a human eye.

AI chatbots, voice assistants, and AI-powered search tools need that same information in a form they can use: structured, authoritative, and consistent. Right now they have to guess. AI Layer removes the guessing.

---

## What It Does

AI Layer creates a single source of truth for your business inside WordPress.

You enter your services, locations, FAQs, trust signals, and calls-to-action once — with proper structure, relationships, and metadata. Everything connects: a service knows its related FAQs, which locations it applies to, what proof supports it, and what someone should do next.

That structured data is exposed through simple REST endpoints. Any AI system, agent, or integration can query it directly and get back exactly what it needs — no scraping, no parsing, no guesswork.

AI Layer does not modify your front-end. It operates as a pure data and API layer, working alongside your existing theme, content, and SEO setup.

---

## The Answer Engine

The standout feature is the built-in answer engine.

Send it a natural language question — `"Do you offer SEO in London?"`, `"How much does web design cost?"`, `"What happens after I place an order?"` — and it detects what the question is about, finds the most relevant data in your site, and returns a structured response with an answer, supporting proof, and next actions. All from your content. No external AI call required.

The engine works through a rules-based pipeline: it checks for manually-authored answers first (so you can guarantee specific responses to predictable questions), then falls back to scoring your FAQs, services, and locations to assemble the best possible answer. Every response includes a confidence level and a source field so the consuming application can decide how to present it.

This is what turns your site from something that can be browsed into something that can be queried.

---

## Example

**Without AI Layer** — an AI has to read multiple pages and guess:

> "Let me check the services page... and the contact page... and the pricing page..."

**With AI Layer** — one request, one structured response:

```
GET /wp-json/ai-layer/v1/answers?query=Do+you+offer+SEO+audits+in+Manchester
```

```json
{
  "data": {
    "answer_short": "Our SEO audits cover 100+ points including technical health, crawlability, page speed, on-page SEO, content quality, and backlink profile — delivered as a prioritised action plan.",
    "answer_long": "The audit covers six core areas: (1) Technical — crawl errors, site speed, Core Web Vitals, HTTPS, structured data; (2) On-page — title tags, meta descriptions, header structure; (3) Content — thin content, duplication, E-E-A-T signals; (4) Backlinks — profile health, toxic links; (5) Competitor analysis; (6) Local — GBP, citations, local schema. You receive a PDF report and optionally a walkthrough call.",
    "confidence": "high",
    "source": "faq",
    "service": { "id": 12, "slug": "seo-audit", "name": "SEO Audit" },
    "location": { "id": 5, "slug": "manchester", "name": "Manchester" },
    "actions": [
      { "id": 30, "type": "book", "label": "Book a Free Consultation", "phone": null, "url": "https://strivewp.com/contact", "method": "form" },
      { "id": 31, "type": "call", "label": "Call Us Now", "phone": "0207 946 0312", "url": null, "method": "phone" },
      { "id": 32, "type": "download", "label": "Download Free SEO Checklist", "phone": null, "url": "https://strivewp.com/seo-checklist", "method": "link" }
    ],
    "source_faqs": [
      { "id": 20, "question": "What does an SEO audit include?", "short_answer": "Our SEO audits cover 100+ points including technical health, crawlability, page speed..." }
    ],
    "supporting_data": [
      { "id": 40, "type": "statistic", "headline": "Our clients see an average 214% increase in organic traffic within 12 months." },
      { "id": 41, "type": "testimonial", "headline": "The audit identified issues we'd missed for years — rankings improved within 8 weeks." },
      { "id": 42, "type": "accreditation", "headline": "Certified Google Partner since 2019." }
    ]
  }
}
```

Same question. One hop. The right service and location detected automatically, the matching FAQ used as the answer, three CTAs attached, and supporting proof included.

---

## Common questions & assumptions

You will hear AI Layer compared to things you already know — REST, GraphQL, schema markup, MCP. That usually means you are in the right conversation. Below is a straight comparison so you can decide whether this plugin fits what you are building.

**The short version:** AI Layer does not replace WordPress’s REST API, Schema.org JSON-LD, or MCP. It adds a **business layer** between your raw site data and anything that needs **meaning**: what you offer, how it fits together, and what someone should do next — without inferring that from pages and templates.

### “Isn’t this just the REST API?”

WordPress already gives you REST endpoints for posts, users, blocks, and more. Those are general-purpose **data access**. AI Layer adds **purpose-built surfaces**: a single business profile, typed entities (services, locations, FAQs, proof, actions), explicit relationships between them, and (with Pro) a **natural-language answer** built from that model — not whatever JSON happened to land in a page.

So: ordinary REST answers “what records exist?” AI Layer helps answer “what should an agent **do** with this business, in one structured step, when someone asks a question.”

### “This is like GraphQL / a headless CMS”

GraphQL and headless stacks excel at **flexible queries** — you ask for the shape you need as a developer. AI Layer focuses on **predictable shapes for AI** — responses tuned for questions, next steps, and trust signals from **your** business model. You can use both; they address different layers of the stack.

### “Schema.org already does this”

Schema markup is mainly **page-level metadata for search** — great for rich results, not a full operational model of your business. It does not replace a graph of services, locations, FAQs, and actions, and it does not drive dynamic, query-shaped responses. When you enable JSON-LD Schema markup in AI Layer, it works **alongside** this plugin’s model; it does not duplicate it.

### “MCP already solves this”

MCP describes **how** an agent talks to tools. It does not define **what** your business objects are or how they relate. You can think of AI Layer as the **data and behaviour model** behind those tools; MCP (or similar) is how clients reach it — the two fit together.

### “Why not just extend the default REST API?”

Transport is not the hard part — **intent** is. Patching generic REST without a domain model still leaves every integration to reverse-engineer your content. AI Layer centralises **what matters**, how it is structured, and how responses are assembled (including the Pro answer engine), so consumers get a consistent story.

### “This feels over-engineered — can’t agents just crawl the site?”

Crawling works until layout changes, JavaScript gets in the way, or answers depend on information scattered across pages — and ambiguity makes bad answers more likely. AI Layer offers **stable, explicit inputs** instead of best-effort scraping; that is the same reason you prefer a database over parsing HTML when reliability counts.

### “Isn’t this a lot to maintain?”

Fair question. The plugin is designed around data you would manage for a serious site anyway (profile, offerings, FAQs, proof, next steps), with relationships wired in WordPress. The more AI Layer saves you from one-off integrations and brittle crawlers, the more that upfront structure pays off. If all you need is slightly tidier JSON with no real model behind it, plain REST may be enough — AI Layer is aimed at cases where **accuracy and structure** matter.

### “What is it actually for?”

Typical fits: AI chat or voice that must answer from **your** services and policies; assistants that need live offerings and locations; workflows where you want **grounded** answers and suggested actions; anything where guessing from page copy is not good enough.

### Do these concerns mean you should skip AI Layer?

**No single objection here should disqualify the plugin by itself** — but they point at what to validate for **your** project:

- **Worth weighing:** how much structure you are willing to maintain, and whether agent or integration outputs need to be **noticeably better** than reading the site (for complex or accuracy-sensitive cases, they usually do).
- **Often misunderstandings, not blockers:** comparisons to schema-only or “just use REST” usually clear up once you separate **transport** from **business shape**.
- **The practical question** is simply: does a structured business layer plus our endpoints solve a problem **you** have?

---

## Requirements

- WordPress 6.0+
- PHP 8.1+

---

## Free vs. Pro

AI Layer is a free plugin with a Pro upgrade available via Freemius.

**Free** includes the full structured data and intelligence layer:

- Business Profile
- All six entity types: Services, Locations, FAQs, Proof & Trust, Actions, Answers CPT
- REST API: `/profile`, `/services`, `/locations`, `/faqs`, `/proof`, `/actions`
- `/answers` endpoint — rules-based answer engine; author guaranteed responses to predictable queries with confidence scoring, source attribution, and proof attachment
- `/products` endpoint — live WooCommerce product catalogue proxy (WooCommerce required; enabled in Settings)
- Schema.org JSON-LD output (Organization, LocalBusiness, FAQPage)
- llms.txt support
- `/.well-known/ai-layer` machine-readable discovery document
- AI discovery `<link>` tags — `rel="ai-layer"` and `rel="llms-txt"` injected in every page `<head>` (enabled by default; toggle in Settings)
- AI.txt support *(beta)* — signal crawling, training, and attribution preferences to AI systems
- Setup Wizard — auto-populate Business Profile from WordPress, Yoast SEO, Rank Math, and WooCommerce

**Pro** — pro features are being revised. The licensing infrastructure is in place for future paid tiers.

---

## Installation

1. Upload the `ai-layer` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. Run the **Setup Wizard** at **AI Layer → Setup Wizard** to auto-populate your Business Profile from existing plugins — or skip it and fill in **AI Layer → Business Profile** manually
4. Add Services, Locations, FAQs, Proof & Trust signals, and Actions via the admin menu
5. Optionally enable schema.org JSON-LD output at **AI Layer → Settings**
6. Optionally enable llms.txt at **AI Layer → llms.txt**
7. If WooCommerce is active, optionally enable the `/products` endpoint at **AI Layer → Settings**

---

## Admin Interface

The plugin adds a top-level **AI Layer** menu in the WordPress admin at position 25.

### Business Profile

**AI Layer → Business Profile**

A single settings page that stores your canonical business information. Organised into sections:

| Section | Fields |
|---------|--------|
| Identity | Business name, legal name, type, subtype, short summary, long summary, brand tone (internal), founded year |
| Contact | Phone, email, website |
| Address | Address lines, county, postcode, country |
| Operations | Opening hours, service modes (in-person / remote / on-site) |
| Social | Facebook, Instagram, LinkedIn, X, YouTube URLs |
| Trust | Trust summary text |

Data is stored as a single JSON object in `wp_options` under the key `wpail_business_profile`.

---

### Setup Wizard

**AI Layer → Setup Wizard**

A revisitable, step-by-step wizard that pre-populates your AI Layer data from existing WordPress settings and active plugins. Every suggestion requires explicit approval — nothing is written without your confirmation. The wizard can be re-run at any time from the menu.

**Steps:**

| Step | What it does |
|------|--------------|
| 1. Detect | Scans installed plugins and WordPress settings; shows a summary of available data sources and what was found |
| 2. Business Profile | Lists suggested field values with source badges; tick what to apply, leave unticked to skip |
| 3. WooCommerce *(shown only when WooCommerce is active)* | Prompts to enable the `/products` endpoint; shows current status if already enabled |
| 4. Done | Completion summary with direct links to remaining setup tasks |

**Data sources:**

| Source | What it extracts |
|--------|------------------|
| WordPress | Site title → Business name; tagline → Short summary; admin email → Email; site URL → Website |
| Yoast SEO | Company name; Facebook, Twitter, LinkedIn, Instagram, YouTube URLs; address fields (requires Yoast Local SEO) |
| Rank Math | Knowledge Graph name; social profile URLs |
| WooCommerce | Detects WooCommerce is active; offers to enable the `/products` endpoint |

**Source priority for conflicting fields:** WordPress (lowest) → Rank Math → Yoast SEO (highest). When multiple sources suggest the same field, the more authoritative source wins.

**Profile step behaviour:**
- Fields with no current saved value are pre-ticked — applying is recommended
- Fields that already have a saved value are unticked by default — tick to overwrite

---

### Settings

**AI Layer → Settings**

| Setting | Description |
|---------|-------------|
| Enable schema.org output | Toggle JSON-LD output in `<head>` |
| Schema type | Organization, LocalBusiness, ProfessionalService, HomeAndConstructionBusiness, LegalService, HealthAndBeautyBusiness |
| Enable FAQPage schema | Output FAQPage JSON-LD from published FAQs |
| FAQPage target pages | Output FAQPage schema on all pages, or restrict to specific pages |
| Enable Products endpoint | Enable the `/products` endpoint (requires WooCommerce to be active) |
| Endpoint Cache TTL | Cache lifetime in seconds for all REST endpoint responses; `0` disables caching |
| **AI Discovery** | |
| Endpoint discovery mode | **/.well-known/ai-layer (recommended)** — machine-readable JSON is the source of truth; /llms.txt links to it. **llms.txt only** — endpoints listed in /llms.txt; `/.well-known/ai-layer` returns 404 |
| Discovery link tags | Output `<link rel="ai-layer">` and `<link rel="llms-txt">` in every page `<head>`. Enabled by default — uncheck to suppress |
| **Post Type Visibility** | |
| Services — Enable public | Make the Services CPT publicly accessible on the front-end |
| Services — Rewrite slug | URL base for the Services archive and single posts (default: `services`) |
| Locations — Enable public | Make the Locations CPT publicly accessible on the front-end |
| Locations — Rewrite slug | URL base for the Locations archive and single posts (default: `locations`) |
| FAQs — Enable public | Make the FAQs CPT publicly accessible on the front-end |
| FAQs — Rewrite slug | URL base for the FAQs archive and single posts (default: `faqs`) |
| Proof & Trust — Enable public | Make the Proof & Trust CPT publicly accessible on the front-end |
| Proof & Trust — Rewrite slug | URL base for the Proof & Trust archive and single posts (default: `proof`) |

The settings page detects Yoast SEO and Rank Math and warns you if their schema output will conflict.

Permalink rewrite rules are flushed automatically after saving — no need to visit the Permalinks screen.

Settings stored in `wp_options` under `wpail_settings`.

---

### Post Type Visibility

By default, all AI Layer post types are private — they have no front-end URLs and are only accessible through the REST API. The Post Type Visibility section in Settings lets you make Services, Locations, FAQs, and Proof & Trust publicly available on the front-end, so your theme can use the same data the API exposes rather than managing it twice.

Actions and Answers are excluded because they are inherently operational (CTAs, AI engine inputs) rather than browsable content.

**What enabling public access does:**

- Sets the CPT `public` and `publicly_queryable` flags to `true`
- Creates a front-end archive at `/{slug}/` and single posts at `/{slug}/{post-slug}/`
- Makes the posts appear in WordPress search and queries

**Rewrite slug:**

Each post type has an editable URL base, defaulting to the plural name. Changing it renames both the archive URL and the single-post URL prefix. Rewrite rules are regenerated automatically on the next page load after saving.

| Post type | Default archive | Default single |
|-----------|----------------|----------------|
| Services | `/services/` | `/services/my-service/` |
| Locations | `/locations/` | `/locations/my-location/` |
| FAQs | `/faqs/` | `/faqs/my-faq/` |
| Proof & Trust | `/proof/` | `/proof/my-testimonial/` |

**Theme templates:**

WordPress uses its standard template hierarchy. Create these files in your theme to control the output:

| Template file | Used for |
|--------------|---------|
| `archive-wpail_service.php` | Services archive |
| `single-wpail_service.php` | Single service |
| `archive-wpail_location.php` | Locations archive |
| `single-wpail_location.php` | Single location |
| `archive-wpail_faq.php` | FAQs archive |
| `single-wpail_faq.php` | Single FAQ |
| `archive-wpail_proof.php` | Proof & Trust archive |
| `single-wpail_proof.php` | Single proof item |

If no template exists, WordPress falls back to `archive.php` / `single.php`, or `index.php`.

**Accessing field data in templates:**

All custom field data is stored as a JSON blob in `wp_postmeta` under `_wpail_data`. Use the repositories to retrieve typed model objects, or retrieve the raw meta for simple cases:

```php
// Via repository (recommended — returns a typed model).
$repo    = new \WPAIL\Repositories\ServiceRepository();
$service = $repo->find( get_the_ID() );
echo esc_html( $service->name );
echo esc_html( $service->short_summary );

// Raw meta (for simple template access).
$data = json_decode( get_post_meta( get_the_ID(), '_wpail_data', true ), true );
echo esc_html( $data['short_summary'] ?? '' );
```

---

### Test Answer Engine

**AI Layer → Test Answer Engine**

A built-in test console for verifying that your answer engine is configured correctly. Enter any natural language question and see exactly what the engine returns — confidence level, matched source, detected service and location, the short and long answer, any matched FAQ, suggested actions, and supporting proof items.

Optional **service** and **location** hints let you simulate a query that arrives with context already attached (for example, from a chatbot widget that knows which service page the user is on).

The console is useful for:

- Checking that keyword and synonym matching works as expected for your services
- Verifying that FAQs are linked to the right services and surface for the right queries
- Confirming that manual Answers take priority over engine-assembled responses
- Debugging unexpected results without making API calls from outside WordPress

The raw JSON response is shown in a collapsible section at the bottom of each result — this is exactly what the `/answers` REST endpoint returns.

Requires `edit_posts` capability.

---

### llms.txt

**AI Layer → llms.txt**

Optionally expose a standardised `llms.txt` file at your site root (`https://strivewp.com/llms.txt`). The file guides AI systems and agents toward your structured data endpoints.

This feature is fully free. It is implemented as a dynamic WordPress rewrite route — no filesystem writes required.

**Settings:**

| Setting | Description |
|---------|-------------|
| Enable llms.txt | Toggle dynamic serving on/off |
| Custom introduction | Optional paragraph inserted after the auto-generated header |
| Include AI Layer endpoints | Toggle the endpoints section. What this produces depends on the **Endpoint discovery mode** set in **AI Layer → Settings** (see below) |
| Include /answers endpoint | Shown only in llms.txt-only discovery mode and only when Pro is active |
| Include key pages | Include a Key Pages section |
| Page URLs | One entry per line in markdown link format: `[Page Title](https://strivewp.com/page)` |

The Products endpoint is not a separate llms.txt toggle — it appears automatically in the endpoint listing when the Products endpoint is enabled in **AI Layer → Settings** and WooCommerce is active.

**Endpoint discovery mode affects llms.txt output:**

- **`/.well-known/ai-layer` mode (recommended):** The endpoints section contains a single line pointing to the JSON discovery document. llms.txt is a human-readable pointer; the machine-readable source of truth lives at `/.well-known/ai-layer`.
- **`llms.txt only` mode:** The endpoints section lists all active endpoints directly. `/.well-known/ai-layer` is disabled.

**Generated file format — `/.well-known/ai-layer` mode (recommended):**

```
# Business Name

> Short business summary

## AI Layer Structured Endpoints

Machine-readable endpoint index (JSON): https://strivewp.com/.well-known/ai-layer

## Notes

This site exposes structured business data via AI Layer for machine-readable access by AI systems, agents, and search tools.
```

**Generated file format — `llms.txt only` mode:**

```
# Business Name

> Short business summary

## AI Layer Structured Endpoints

Structured, machine-readable business data is available at the following endpoints:

- [Business Profile](https://strivewp.com/wp-json/ai-layer/v1/profile): Business name, contact details, and description.
- [Services](https://strivewp.com/wp-json/ai-layer/v1/services): Services and products offered.
- [Locations](https://strivewp.com/wp-json/ai-layer/v1/locations): Locations and service areas.
- [FAQs](https://strivewp.com/wp-json/ai-layer/v1/faqs): Frequently asked questions and answers.
- [Proof & Trust](https://strivewp.com/wp-json/ai-layer/v1/proof): Testimonials, case studies, and accreditations.
- [Actions](https://strivewp.com/wp-json/ai-layer/v1/actions): Recommended next steps and calls to action.
- [Products](https://strivewp.com/wp-json/ai-layer/v1/products): Product catalogue with pricing, availability, and categories.

## Notes

This site exposes structured business data via AI Layer for machine-readable access by AI systems, agents, and search tools.
```

The business name and summary are pulled from your Business Profile.

**Conflict detection:**

The settings page automatically detects and warns about four conditions:

| Condition | Severity | Effect |
|-----------|----------|--------|
| Physical `llms.txt` file at site root | Error | Web server serves that file directly; AI Layer route is bypassed |
| Plain permalink structure | Error | WordPress rewrites cannot intercept the request |
| Yoast SEO active | Warning | Verify only one source manages llms.txt |
| Rank Math SEO active | Warning | Verify only one source manages llms.txt |

If a physical file conflict is detected, the settings page remains fully functional as a preview and copy tool — you can copy the generated content and paste it into the existing file manually.

**Caching:** Generated output is cached in a WordPress transient for one hour. The cache is flushed automatically on every settings save.

**Settings stored in `wp_options` under `wpail_llmstxt`.**

---

### AI.txt *(Beta)*

**AI Layer → AI.txt (Beta)**

> ⚠ This feature is experimental. The AI.txt standard is still evolving and has not been formally adopted by major AI providers. Settings may have no effect on some systems. Use with caution.

Optionally expose an `ai.txt` file at your site root (`https://strivewp.com/ai.txt`). The file signals to AI systems how they are permitted to interact with your content — crawling, training, and attribution.

Implemented as a dynamic WordPress rewrite route. No filesystem writes required.

**Settings:**

| Setting | Description |
|---------|-------------|
| Enable AI.txt | Toggle dynamic serving on/off. When disabled, `/ai.txt` returns 404 |
| **Global Rules** | |
| Allow AI crawling | On (default): outputs `Allow: /` — Off: outputs `Disallow: /` |
| Allow AI training | On: outputs `Training: allow` — Off (default): outputs `Training: disallow` |
| Require attribution | When on: outputs `Attribution: required` |
| **Agent-Specific Rules** | |
| Agent name | Name of the AI crawler (e.g. `GPTBot`, `ClaudeBot`, `Google-Extended`) |
| Allow crawling | Per-agent Allow: / or Disallow: / — defaults to allow for new rows |
| Allow training | Per-agent Training: allow or Training: disallow |
| Require attribution | Per-agent Attribution: required |

**Preview:** The preview pane updates in real time as you change settings without requiring a save.

**Generated file example:**

```
User-agent: *
Allow: /

Training: disallow
Attribution: required

User-agent: GPTBot
Disallow: /
Training: disallow
```

Each agent block fully overrides the global `*` block for that agent, so all applicable directives are repeated explicitly.

**Conflict detection:** If a physical `ai.txt` file exists at the site root, the dynamic route is bypassed and a notice is shown. Plain permalink structures prevent WordPress from intercepting the request.

**Known AI agent names:** `GPTBot` (OpenAI), `ClaudeBot` (Anthropic), `Google-Extended` (Google), `CCBot` (Common Crawl), `FacebookBot` (Meta).

**Settings stored in `wp_options` under `wpail_aitxt`.**

---

### AI Discovery Link Tags

**AI Layer → Settings → AI Discovery → Discovery link tags**

When enabled (the default), AI Layer injects two `<link>` tags into the `<head>` of every front-end page:

```html
<link rel="ai-layer" href="https://strivewp.com/.well-known/ai-layer" type="application/json">
<link rel="llms-txt" href="https://strivewp.com/llms.txt" type="text/plain">
```

Each tag is only output when its corresponding feature is active:

| Tag | Output when |
|-----|-------------|
| `rel="ai-layer"` | Discovery mode is set to `/.well-known/ai-layer` (the default) |
| `rel="llms-txt"` | llms.txt is enabled in **AI Layer → llms.txt** |

**Why this matters:** AI crawlers and agents that index page source — including search-grounded tools like Perplexity and Bing Copilot — can read `<link>` tags to discover where structured data lives without needing prior knowledge of the URL. This follows the same convention as `rel="canonical"` and `rel="alternate"` used for search engines today.

**To disable:** uncheck **Discovery link tags** in **AI Layer → Settings → AI Discovery** and save.

---

### Entity Types (Custom Post Types)

All six CPTs are non-public — no front-end archives or single templates.

#### Services (`wpail_service`)

Represents a service your business offers.

| Field Group | Fields |
|-------------|--------|
| Overview | Category, status, short summary, long summary, customer types, service modes |
| Matching | Keywords, synonyms (internal), common problems |
| Pricing | Price type, from price, currency, price notes, availability flag |
| Content | Benefits, linked page URL |
| Relationships | Related FAQs, proof items, actions, locations |
| Schema | schema.org service type |

#### Locations (`wpail_location`)

A physical or virtual business location. Implemented as a CPT (not a taxonomy) because locations carry rich metadata.

| Field Group | Fields |
|-------------|--------|
| Location Info | Type, region, country, postcode prefixes, primary location flag, service radius (km) |
| Content | Summary, linked page URL |
| Relationships | Related services, local proof items |

#### FAQs (`wpail_faq`)

Frequently asked questions and their answers. FAQs are the engine's **source material** — when a query arrives and no Authored Answer matches, the engine scores your FAQs by keyword overlap, intent tags, and service/location relationships, then builds a structured response from the best match. Write FAQs for the questions your customers actually ask. For questions where you need to guarantee a specific response word-for-word, use an Authored Answer instead (see below).

| Field Group | Fields |
|-------------|--------|
| Content | Question, short answer, long answer, status, public flag |
| Matching | Intent tags, priority |
| Relationships | Related services, locations |

#### Proof & Trust (`wpail_proof`)

Trust signals: testimonials, accreditations, statistics, awards, case studies, and media mentions.

| Field Group | Fields |
|-------------|--------|
| Proof Item | Type, headline, content, source name, source context, rating, public flag |
| Relationships | Related services, locations |

**Proof types:** `testimonial`, `accreditation`, `statistic`, `award`, `case_study`, `media_mention`

#### Actions (`wpail_action`)

Calls-to-action — the next steps you want a visitor or AI system to offer.

| Field Group | Fields |
|-------------|--------|
| Action | Action type, label, description, phone, URL, method, public flag |
| Advanced | Availability rule |
| Relationships | Related services, locations |

**Action types:** `call`, `email`, `book`, `quote`, `visit`, `download`, `chat`  
**Methods:** `link`, `phone`, `form`, `email`

#### Answers (`wpail_answer`)

Manually authored structured answers that take **guaranteed priority** in the answer engine — they are checked before FAQs. When an incoming query contains any of the `query_patterns`, this answer is returned immediately at highest confidence, bypassing the auto-assembly engine entirely. Use these for your most predictable, high-stakes questions (pricing, location coverage, specific service queries) where you cannot afford the engine to guess. For general question coverage, use FAQs and let the engine assemble answers automatically.

Managed via the WordPress admin UI or via the REST API and MCP tools (full CRUD: POST, PATCH, DELETE, plus `ai-layer-create-answer` and friends).

| Field Group | Fields |
|-------------|--------|
| Matching | Query patterns (line-separated text patterns) |
| Answer | Short answer, long answer, confidence level |
| Relationships | Related services, locations, next actions, source FAQs |

---

## REST API

**Base URL:** `/wp-json/ai-layer/v1`

Read endpoints are public — no authentication required.

Write endpoints (POST, PATCH, DELETE) are available across all entity types and require authentication via **WordPress Application Passwords**.

**Standard response envelope:**

```json
{
  "data": { ... },
  "meta": { "count": 5 }
}
```

---

### Authentication

Write endpoints (POST, PATCH, DELETE) use **WordPress Application Passwords** via HTTP Basic Auth. Application Passwords are built into WordPress 6.0+ — no extra plugin required. The authenticated user must have the `edit_posts` capability (Editor role or above).

**Create an Application Password:**

1. Go to **Users → Profile** in the WordPress admin
2. Scroll to **Application Passwords**
3. Enter a name (e.g. `AI Agent`) and click **Add New Application Password**
4. Copy the generated password — it will not be shown again

**Making authenticated requests:**

Encode `username:application-password` as Base64 and pass it in the `Authorization` header.

```shell
# Encode your credentials
echo -n "admin:xxxx xxxx xxxx xxxx xxxx xxxx" | base64

# Authenticated POST example
curl -X POST https://strivewp.com/wp-json/ai-layer/v1/services \
  -H "Authorization: Basic <base64-credential>" \
  -H "Content-Type: application/json" \
  -d '{"title": "SEO Consultancy", "short_summary": "Improve your organic rankings."}'
```

**Write endpoint error codes:**

| HTTP | Code | When |
|------|------|------|
| 400 | `wpail_bad_request` | Missing required field or invalid value |
| 401 | `wpail_unauthorized` | No credentials supplied |
| 403 | `wpail_forbidden` | Credentials valid but insufficient capability |
| 404 | `wpail_not_found` | Item not found |

---

### GET `/.well-known/ai-layer`

The machine-readable discovery document for this plugin. Returns a JSON object listing all active endpoints, their full URLs, descriptions, and accepted parameters. Designed for agents and tools that need to discover available capabilities without prior knowledge of the site.

This is the **single source of truth** for what AI Layer exposes. `/llms.txt` links here; agents should query this document directly.

**Example response:**
```json
{
  "schema_version": "1.0",
  "name": "Acme Co",
  "description": "We make the best widgets.",
  "api": {
    "base": "https://strivewp.com/wp-json/ai-layer/v1",
    "endpoints": [
      {
        "path": "/profile",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/profile",
        "description": "Business name, contact details, and description.",
        "methods": ["GET"]
      },
      {
        "path": "/services",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/services",
        "description": "Services and products offered.",
        "methods": ["GET"]
      },
      {
        "path": "/locations",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/locations",
        "description": "Locations and service areas.",
        "methods": ["GET"]
      },
      {
        "path": "/faqs",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/faqs",
        "description": "Frequently asked questions and answers.",
        "methods": ["GET"],
        "params": {
          "service": "integer — filter by service ID",
          "location": "integer — filter by location ID"
        }
      },
      {
        "path": "/proof",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/proof",
        "description": "Testimonials, case studies, and accreditations.",
        "methods": ["GET"],
        "params": {
          "service": "integer — filter by service ID"
        }
      },
      {
        "path": "/actions",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/actions",
        "description": "Recommended next steps and calls to action.",
        "methods": ["GET"],
        "params": {
          "service": "integer — filter by service ID"
        }
      },
      {
        "path": "/answers",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/answers",
        "description": "Natural language question answering.",
        "methods": ["GET"],
        "params": {
          "query": "string — the natural language question to answer",
          "service": "integer — optional service ID hint",
          "location": "integer — optional location ID hint"
        }
      },
      {
        "path": "/products",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/products",
        "description": "Product catalogue with pricing and availability.",
        "methods": ["GET"],
        "params": {
          "per_page": "integer — products per page (max 100, default 20)",
          "page": "integer — page number",
          "category": "string — filter by category slug"
        }
      },
      {
        "path": "/products/{slug}",
        "url": "https://strivewp.com/wp-json/ai-layer/v1/products/{slug}",
        "description": "Full detail for a single product.",
        "methods": ["GET"]
      }
    ]
  },
  "llms_txt": "https://strivewp.com/llms.txt"
}
```

The `endpoints` array only includes entries that are currently active — the `/products` entries appear only when WooCommerce is active and the Products endpoint is enabled; `/answers` only appears when the answers engine is enabled.

No WordPress rewrite flush is required after enabling this — the route is always registered.

---

### GET `/profile`

Returns the canonical business profile.

**Example response:**
```json
{
  "data": {
    "name": "Acme Ltd",
    "business_type": "professional_services",
    "short_summary": "We help businesses grow.",
    "phone": "01234 567890",
    "email": "hello@strivewp.com",
    "address_line_1": "10 High Street",
    "postcode": "AB1 2CD",
    "country": "UK",
    "opening_hours": "Mon–Fri 9am–5pm",
    "service_modes": ["in-person", "remote"]
  }
}
```

---

### GET `/services`

Returns all published services as summaries.

**Example response:**
```json
{
  "data": [
    { "id": 42, "slug": "seo-consultancy", "name": "SEO Consultancy" }
  ],
  "meta": { "count": 1 }
}
```

---

### GET `/services/{slug}`

Returns full detail for a single service including related entities.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | The service post slug |

**Example response:**
```json
{
  "data": {
    "id": 42,
    "slug": "seo-consultancy",
    "name": "SEO Consultancy",
    "category": "marketing",
    "short_summary": "Improve your organic search rankings.",
    "long_summary": "...",
    "keywords": ["SEO", "search engine optimisation"],
    "price_type": "monthly_retainer",
    "from_price": 500,
    "currency": "GBP",
    "benefits": ["More traffic", "Better visibility"],
    "service_modes": ["remote"],
    "faqs": [ { "id": 10, "question": "How long does SEO take?" } ],
    "proof": [ { "id": 20, "type": "testimonial", "headline": "Great results" } ],
    "actions": [ { "id": 30, "type": "book", "label": "Book a call" } ],
    "locations": [ { "id": 5, "name": "London", "slug": "london" } ]
  }
}
```

---

### POST `/services` *(auth required)*

Creates a new service.

**Required fields:**

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Service name (sets the post title) |

**Optional fields:** any field from the Services field definition — `category`, `short_summary`, `long_summary`, `keywords`, `pricing_type`, `from_price`, `currency`, `benefits`, `related_faqs` (array of IDs), `related_proof`, `related_actions`, `related_locations`, etc.

**Response:** `201 Created` with the full service object.

```shell
curl -X POST https://strivewp.com/wp-json/ai-layer/v1/services \
  -H "Authorization: Basic <base64>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Technical SEO Audit",
    "short_summary": "In-depth audit of your site'\''s technical foundations.",
    "pricing_type": "fixed",
    "from_price": 750,
    "currency": "GBP"
  }'
```

---

### PATCH `/services/{slug}` *(auth required)*

Partially updates an existing service. Only fields present in the request body are changed; omitted fields are left unchanged.

**Response:** `200 OK` with the updated service object.

```shell
curl -X PATCH https://strivewp.com/wp-json/ai-layer/v1/services/technical-seo-audit \
  -H "Authorization: Basic <base64>" \
  -H "Content-Type: application/json" \
  -d '{"from_price": 850, "related_locations": [5, 9]}'
```

---

### DELETE `/services/{slug}` *(auth required)*

Permanently deletes a service and cleans up all bidirectional relationship references.

**Response:** `200 OK`
```json
{ "data": { "deleted": true, "id": 42 } }
```

---

### GET `/locations`

Returns all published locations as summaries.

---

### GET `/locations/{slug}`

Returns full detail for a single location.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | The location post slug |

**Example response:**
```json
{
  "data": {
    "id": 5,
    "slug": "london",
    "name": "London",
    "type": "primary",
    "region": "Greater London",
    "country": "UK",
    "postcode_prefixes": ["EC1", "EC2", "WC1"],
    "is_primary": true,
    "service_radius_km": 25,
    "summary": "Our London office serves central and greater London.",
    "services": [ { "id": 42, "name": "SEO Consultancy", "slug": "seo-consultancy" } ],
    "proof": []
  }
}
```

---

### POST `/locations` *(auth required)*

Creates a new location.

**Required fields:**

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Location name (sets the post title) |

**Optional fields:** `location_type`, `region`, `country`, `postcode_prefixes`, `is_primary`, `service_radius_km`, `summary`, `related_services` (array of IDs), `local_proof` (array of IDs), `linked_page_url`.

**Response:** `201 Created` with the full location object.

---

### PATCH `/locations/{slug}` *(auth required)*

Partially updates a location. Only supplied fields are changed.

**Response:** `200 OK` with the updated location object.

---

### DELETE `/locations/{slug}` *(auth required)*

Permanently deletes a location and removes all bidirectional relationship references.

**Response:** `200 OK`
```json
{ "data": { "deleted": true, "id": 5 } }
```

---

### GET `/faqs`

Returns published FAQs, optionally filtered.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service` | integer | No | Filter by service post ID |
| `location` | integer | No | Filter by location post ID |

**Example response:**
```json
{
  "data": [
    {
      "id": 10,
      "question": "How long does SEO take?",
      "answer_short": "Results typically appear within 3–6 months.",
      "answer_long": "...",
      "services": [],
      "locations": []
    }
  ],
  "meta": { "count": 1 }
}
```

---

### GET `/faqs/{id}`

Returns full detail for a single FAQ.

---

### POST `/faqs` *(auth required)*

Creates a new FAQ.

**Required fields:**

| Field | Type | Description |
|-------|------|-------------|
| `question` | string | The question text (also sets the post title) |
| `short_answer` | string | Concise answer — returned directly in `/answers` responses |

**Optional fields:** `long_answer`, `status`, `related_services` (array of IDs), `related_locations` (array of IDs), `intent_tags`, `priority`, `is_public`.

**Response:** `201 Created` with the full FAQ object.

```shell
curl -X POST https://strivewp.com/wp-json/ai-layer/v1/faqs \
  -H "Authorization: Basic <base64>" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "How long does SEO take to show results?",
    "short_answer": "Most clients see meaningful improvements within 3–6 months.",
    "related_services": [42]
  }'
```

---

### PATCH `/faqs/{id}` *(auth required)*

Partially updates a FAQ. Updating `question` also updates the underlying post title.

**Response:** `200 OK` with the updated FAQ object.

---

### DELETE `/faqs/{id}` *(auth required)*

Permanently deletes a FAQ and removes all bidirectional relationship references.

**Response:** `200 OK`
```json
{ "data": { "deleted": true, "id": 10 } }
```

---

### GET `/proof`

Returns published proof and trust signals, optionally filtered.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service` | integer | No | Filter by service post ID |

**Example response:**
```json
{
  "data": [
    {
      "id": 20,
      "type": "testimonial",
      "headline": "Doubled our traffic in 6 months",
      "content": "Working with Acme transformed our search presence.",
      "source_name": "Jane Smith",
      "source_context": "CEO, Example Co",
      "rating": 5
    }
  ],
  "meta": { "count": 1 }
}
```

---

### GET `/proof/{id}`

Returns full detail for a single proof item.

---

### POST `/proof` *(auth required)*

Creates a new proof or trust signal.

**Required fields:**

| Field | Type | Description |
|-------|------|-------------|
| `title` or `headline` | string | Display title; `headline` is accepted as an alias for `title` |

**Optional fields:** `proof_type` (`testimonial`, `accreditation`, `statistic`, `award`, `case_study`, `media_mention`), `headline`, `content`, `source_name`, `source_context`, `rating`, `related_services` (array of IDs), `related_locations` (array of IDs), `is_public`.

**Response:** `201 Created` with the full proof object.

---

### PATCH `/proof/{id}` *(auth required)*

Partially updates a proof item. Only supplied fields are changed.

**Response:** `200 OK` with the updated proof object.

---

### DELETE `/proof/{id}` *(auth required)*

Permanently deletes a proof item and removes all bidirectional relationship references.

**Response:** `200 OK`
```json
{ "data": { "deleted": true, "id": 20 } }
```

---

### GET `/actions`

Returns published calls-to-action, optionally filtered. When no `service` filter is provided, global (service-agnostic) actions are returned alongside all others.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service` | integer | No | Filter by service post ID |

**Example response:**
```json
{
  "data": [
    {
      "id": 30,
      "type": "book",
      "label": "Book a free call",
      "description": "30-minute strategy session",
      "url": "https://calendly.com/example",
      "method": "link"
    }
  ],
  "meta": { "count": 1 }
}
```

---

### GET `/actions/{id}`

Returns full detail for a single action.

---

### POST `/actions` *(auth required)*

Creates a new call-to-action.

**Required fields:**

| Field | Type | Description |
|-------|------|-------------|
| `title` or `label` | string | Display name; `label` is accepted as an alias for `title` |

**Optional fields:** `action_type` (`call`, `email`, `book`, `quote`, `visit`, `download`, `chat`), `label`, `description`, `phone`, `url`, `method` (`link`, `phone`, `form`, `email`), `related_services` (array of IDs), `related_locations` (array of IDs), `is_public`.

**Response:** `201 Created` with the full action object.

```shell
curl -X POST https://strivewp.com/wp-json/ai-layer/v1/actions \
  -H "Authorization: Basic <base64>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Book a free strategy call",
    "label": "Book a free strategy call",
    "action_type": "book",
    "method": "link",
    "url": "https://calendly.com/example/30min",
    "related_services": [42]
  }'
```

---

### PATCH `/actions/{id}` *(auth required)*

Partially updates an action. Only supplied fields are changed. Updating `title` also updates the underlying post title.

**Response:** `200 OK` with the updated action object.

---

### DELETE `/actions/{id}` *(auth required)*

Permanently deletes an action and removes all bidirectional relationship references.

**Response:** `200 OK`
```json
{ "data": { "deleted": true, "id": 30 } }
```

---

### GET `/products` *(requires WooCommerce + Products endpoint enabled in Settings)*

A read-only proxy over WooCommerce's native product data. No data is duplicated or stored separately — every request reads live from WooCommerce. Only registered when WooCommerce is active and the Products endpoint setting is enabled in **AI Layer → Settings**.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `per_page` | integer | No | Products per page (default 20, max 100) |
| `page` | integer | No | Page number (default 1) |
| `category` | string | No | Filter by product category slug |

**Example response:**
```json
{
  "data": [
    {
      "id": 123,
      "slug": "widget-pro",
      "name": "Widget Pro",
      "type": "simple",
      "price": "39.99",
      "currency": "GBP",
      "on_sale": true,
      "in_stock": true,
      "categories": ["widgets", "pro"],
      "image": "https://strivewp.com/wp-content/uploads/widget-pro.jpg",
      "url": "https://strivewp.com/product/widget-pro"
    }
  ],
  "meta": {
    "count": 1,
    "total": 47,
    "page": 1,
    "per_page": 20,
    "total_pages": 3
  }
}
```

The list shape is intentionally lean. For pricing detail, descriptions, gallery, and physical attributes, fetch the individual product via `/products/{slug}`.

---

### GET `/products/{slug}` *(requires WooCommerce + Products endpoint enabled in Settings)*

Returns full detail for a single product.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `slug` | string | Yes | Product URL slug |

**Example response:**
```json
{
  "data": {
    "id": 123,
    "slug": "widget-pro",
    "name": "Widget Pro",
    "type": "simple",
    "price": "39.99",
    "currency": "GBP",
    "on_sale": true,
    "in_stock": true,
    "categories": [{ "id": 5, "name": "Widgets", "slug": "widgets" }],
    "image": "https://strivewp.com/wp-content/uploads/widget-pro.jpg",
    "url": "https://strivewp.com/product/widget-pro",
    "sku": "WGT-PRO-001",
    "regular_price": "49.99",
    "sale_price": "39.99",
    "short_description": "The professional-grade widget.",
    "description": "Widget Pro is built for demanding workflows...",
    "is_virtual": false,
    "is_downloadable": false,
    "tags": ["featured", "bestseller"],
    "gallery": [
      "https://strivewp.com/wp-content/uploads/widget-pro-alt.jpg"
    ],
    "weight": "0.5",
    "weight_unit": "kg",
    "dimensions": {
      "length": "10",
      "width": "5",
      "height": "3",
      "unit": "cm"
    },
    "stock_quantity": 42
  }
}
```

For variable products, `price_range` and `attributes` are also returned:

```json
{
  "price_range": { "min": "29.99", "max": "79.99" },
  "attributes": [
    { "name": "Size", "options": ["small", "medium", "large"] }
  ]
}
```

---

### GET `/answers`

Without a `?query` parameter, returns all manually-authored Answers as a management list (free, no authentication required).

**Example response:**
```json
{
  "data": [
    {
      "id": 55,
      "short_answer": "Yes, we offer SEO Consultancy in London.",
      "long_answer": "...",
      "confidence": "high",
      "query_patterns": ["seo london", "do you do seo in london"],
      "services": [{ "id": 42, "name": "SEO Consultancy", "slug": "seo-consultancy" }],
      "locations": [{ "id": 5, "name": "London", "slug": "london" }],
      "next_actions": [],
      "source_faqs": []
    }
  ],
  "meta": { "count": 1 }
}
```

### GET `/answers?query=...` *(Pro)*

When `?query` is present, runs the intelligent answer engine and returns an assembled, structured response.

Requires AI Layer Pro. Free users receive HTTP 402 with an upgrade URL (see [Free vs. Pro](#free-vs-pro)).

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | Yes | Natural language question |
| `service` | integer | No | Service ID hint to bias results |
| `location` | integer | No | Location ID hint to bias results |

**Assembly pipeline (rules-based, in priority order):**

1. Check manually-authored Answers for matching query patterns — if matched, return immediately at highest confidence
2. Detect service intent via keyword and synonym scoring across published services
3. Detect location intent via name, region, and postcode prefix matching
4. Score FAQs by tokenised query term overlap with question text, answer text, and intent tags
5. Assemble answer from the best-matching FAQ plus service and location context
6. Attach up to 3 relevant Actions as next steps
7. Attach up to 3 Proof items as supporting evidence

**Example request:**
```
GET /wp-json/ai-layer/v1/answers?query=Do+you+offer+SEO+audits+in+Manchester
```

**Example response:**
```json
{
  "data": {
    "answer_short": "Our SEO audits cover 100+ points including technical health, crawlability, page speed, on-page SEO, content quality, and backlink profile — delivered as a prioritised action plan.",
    "answer_long": "The audit covers six core areas: (1) Technical — crawl errors, site speed, Core Web Vitals, HTTPS, structured data; (2) On-page — title tags, meta descriptions, header structure; (3) Content — thin content, duplication, E-E-A-T signals; (4) Backlinks — profile health, toxic links; (5) Competitor analysis; (6) Local — GBP, citations, local schema. You receive a PDF report and optionally a walkthrough call.",
    "confidence": "high",
    "source": "faq",
    "service": { "id": 12, "slug": "seo-audit", "name": "SEO Audit" },
    "location": { "id": 5, "slug": "manchester", "name": "Manchester" },
    "actions": [
      { "id": 30, "type": "book", "label": "Book a Free Consultation", "phone": null, "url": "https://strivewp.com/contact", "method": "form" },
      { "id": 31, "type": "call", "label": "Call Us Now", "phone": "0207 946 0312", "url": null, "method": "phone" },
      { "id": 32, "type": "download", "label": "Download Free SEO Checklist", "phone": null, "url": "https://strivewp.com/seo-checklist", "method": "link" }
    ],
    "source_faqs": [
      { "id": 20, "question": "What does an SEO audit include?", "short_answer": "Our SEO audits cover 100+ points including technical health, crawlability, page speed..." }
    ],
    "supporting_data": [
      { "id": 40, "type": "statistic", "headline": "Our clients see an average 214% increase in organic traffic within 12 months." },
      { "id": 41, "type": "testimonial", "headline": "The audit identified issues we'd missed for years — rankings improved within 8 weeks." },
      { "id": 42, "type": "accreditation", "headline": "Certified Google Partner since 2019." }
    ]
  }
}
```

**Confidence values:** `high`, `medium`, `low`  
**Source values:** `manual` (authored Answer matched), `faq` (assembled from FAQ), `dynamic` (assembled without FAQ match)

---

### GET `/answers/{id}`

Returns full detail for a single authored Answer by ID.

---

### POST `/answers` *(auth required)*

Creates a new manually-authored Answer. When an incoming query matches any of the `query_patterns`, this answer is returned immediately at highest priority — bypassing the auto-assembly engine.

**Required fields:**

| Field | Type | Description |
|-------|------|-------------|
| `short_answer` | string | 1–2 sentence answer returned as the primary response |

**Optional fields:**

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Internal label (defaults to first query pattern or truncated `short_answer`) |
| `long_answer` | string | Extended answer |
| `confidence` | string | `high`, `medium`, or `low` |
| `query_patterns` | array\|string | Trigger phrases — array of strings or newline-separated string |
| `related_services` | integer[] | Service post IDs to attach as context |
| `related_locations` | integer[] | Location post IDs to attach as context |
| `next_actions` | integer[] | Action post IDs to suggest as next steps |
| `source_faq_ids` | integer[] | FAQ post IDs this answer was derived from |

**Response:** `201 Created` with the full authored Answer object.

```shell
curl -X POST https://strivewp.com/wp-json/ai-layer/v1/answers \
  -H "Authorization: Basic <base64>" \
  -H "Content-Type: application/json" \
  -d '{
    "short_answer": "Yes, we offer SEO Consultancy across London and the South East.",
    "confidence": "high",
    "query_patterns": ["seo london", "do you offer seo in london", "london seo"],
    "related_services": [42],
    "related_locations": [5]
  }'
```

---

### PATCH `/answers/{id}` *(auth required)*

Partially updates an authored Answer. Only fields present in the request body are changed.

**Response:** `200 OK` with the updated Answer object.

---

### DELETE `/answers/{id}` *(auth required)*

Permanently deletes an authored Answer and removes all bidirectional relationship references.

**Response:** `200 OK`
```json
{ "data": { "deleted": true, "id": 55 } }
```

---

## MCP Integration

AI Layer registers all its data and management operations as **WordPress Abilities** (available in WordPress 6.9+ core). The [WordPress MCP Adapter](https://github.com/wordpress/mcp-adapter) plugin picks them up automatically and exposes them as MCP tools — no extra configuration required on the AI Layer side.

With the MCP Adapter active, any MCP-compatible AI client can connect to your site and fully manage AI Layer content: read structured business data, create or update entities, and run the answer engine.

---

### Requirements

- WordPress 6.9+ (the Abilities API is built into core)
- [WordPress MCP Adapter](https://github.com/wordpress/mcp-adapter) plugin — installed and active

---

### Connecting

Install and activate the [WordPress MCP Adapter](https://github.com/wordpress/mcp-adapter) plugin, then follow its documentation to connect your MCP client. Once connected, the AI client has access to all 33 AI Layer tools immediately.

---

### Available Tools

Abilities are registered under the `ai-layer/` namespace. The MCP Adapter converts the forward slash to a hyphen, so `ai-layer/create-service` becomes the MCP tool name `ai-layer-create-service`.

**Business Profile**

| Tool | Description |
|------|-------------|
| `ai-layer-get-profile` | Read the full business profile |
| `ai-layer-update-profile` | Partially update the business profile |

**Services**

| Tool | Description |
|------|-------------|
| `ai-layer-list-services` | List all services (id, slug, name) |
| `ai-layer-get-service` | Get a service by slug with full detail and relationships |
| `ai-layer-create-service` | Create a new service |
| `ai-layer-update-service` | Partially update a service by slug |
| `ai-layer-delete-service` | Permanently delete a service by slug |

**Locations**

| Tool | Description |
|------|-------------|
| `ai-layer-list-locations` | List all locations |
| `ai-layer-get-location` | Get a location by slug with full detail and relationships |
| `ai-layer-create-location` | Create a new location |
| `ai-layer-update-location` | Partially update a location by slug |
| `ai-layer-delete-location` | Permanently delete a location by slug |

**FAQs**

| Tool | Description |
|------|-------------|
| `ai-layer-list-faqs` | List all FAQs (filterable by `service` or `location` ID) |
| `ai-layer-get-faq` | Get a FAQ by ID |
| `ai-layer-create-faq` | Create a new FAQ (`question` and `short_answer` required) |
| `ai-layer-update-faq` | Partially update a FAQ by ID |
| `ai-layer-delete-faq` | Permanently delete a FAQ by ID |

**Proof & Trust**

| Tool | Description |
|------|-------------|
| `ai-layer-list-proof` | List all proof items (filterable by `service` ID) |
| `ai-layer-get-proof-item` | Get a proof item by ID |
| `ai-layer-create-proof-item` | Create a new proof item |
| `ai-layer-update-proof-item` | Partially update a proof item by ID |
| `ai-layer-delete-proof-item` | Permanently delete a proof item by ID |

**Actions**

| Tool | Description |
|------|-------------|
| `ai-layer-list-actions` | List all actions (filterable by `service` ID) |
| `ai-layer-get-action` | Get an action by ID |
| `ai-layer-create-action` | Create a new call-to-action |
| `ai-layer-update-action` | Partially update an action by ID |
| `ai-layer-delete-action` | Permanently delete an action by ID |

**Authored Answers**

| Tool | Description |
|------|-------------|
| `ai-layer-list-answers` | List all manually-authored Answers (id, short_answer, query_patterns, confidence) |
| `ai-layer-get-answer` | Get a single authored Answer by ID with full detail and relationships |
| `ai-layer-create-answer` | Create a new authored Answer with trigger patterns (`short_answer` required) |
| `ai-layer-update-answer` | Partially update an authored Answer by ID |
| `ai-layer-delete-answer` | Permanently delete an authored Answer by ID |

**Answer Engine**

| Tool | Description |
|------|-------------|
| `ai-layer-query-answers` | Run a natural-language query through the answer engine; returns a structured answer assembled from your authored Answers, FAQs, services, locations, proof, and actions |

---

### Permissions

| Tool category | Capability required |
|---------------|---------------------|
| Read tools (list, get, query) | Logged-in user (enforced at the MCP Adapter transport level) |
| Write tools (create, update) | `edit_posts` (Editor role or above) |
| Delete tools | `delete_posts` |
| `ai-layer-query-answers` | AI Layer Pro (`Features::answers_enabled()`) |

---

### How it works

AI Layer hooks into `wp_abilities_api_init` and calls `wp_register_ability()` for each tool. Each ability carries `meta.mcp.public = true`, which signals the MCP Adapter to include it in the default server's tool list. The MCP Adapter handles the protocol, JSON-RPC transport, session management, and tool name sanitisation — AI Layer only provides the business logic.

If `wp_register_ability()` is not available (WordPress < 6.9 without the MCP Adapter plugin), the abilities are silently skipped and no errors are thrown.

**Write tools** use the same repository, sanitiser, and relationship sync logic as the REST API write endpoints, so data created via MCP is identical to data created via the REST API or admin UI.

---

## Schema.org Output

When enabled in Settings, the plugin outputs JSON-LD structured data in `<head>` on the front-end.

- **Organization / LocalBusiness schema** — output on the front page, populated from the Business Profile. Schema type is configurable.
- **FAQPage schema** — output site-wide or page-specific, populated from all published public FAQs.

The settings page detects Yoast SEO and Rank Math and shows a warning — schema output is not automatically suppressed, so review your pages to avoid duplication.

---

## Data Architecture

### Storage

| Entity | WordPress storage | Key |
|--------|------------------|-----|
| Business Profile | `wp_options` | `wpail_business_profile` |
| All CPT metadata | `wp_postmeta` | `_wpail_data` (JSON) |
| Plugin settings | `wp_options` | `wpail_settings` |
| llms.txt settings | `wp_options` | `wpail_llmstxt` |

All CPT metadata is stored as a single JSON blob per post. Schema versioning is embedded in each blob for future migrations.

### Patterns

- **Model–Repository–Transformer** — `WP_Post` → Transformer → immutable readonly Model → Repository provides query access
- **Centralised field definitions** — `FieldDefinitions` is the single source of truth for field types, labels, placeholders, help text, defaults, validation rules, and visibility across admin forms, REST, and sanitization
- **Visibility control** — Fields are tagged `public`, `private` (admin-only), or `ai_only` (reserved). Private fields are automatically excluded from REST responses
- **Relationship resolution** — Post IDs are stored as arrays and resolved to lightweight summaries on demand, preventing bloated responses
- **Sanitization by type** — All POST data is validated and sanitized against field definitions before storage
- **Live proxy (WooCommerce)** — The `/products` endpoint reads directly from WooCommerce on every request via `wc_get_products()`; no AI Layer CPT or extra database writes are involved, so the endpoint is always in sync and scales to any catalogue size
- **Configurable CPT visibility** — Services, Locations, FAQs, and Proof & Trust are private by default; a settings toggle promotes them to public with a custom rewrite slug, enabling theme templates to consume the same data the API exposes

### Constants

```php
WPAIL_VERSION          // '1.0.0'
WPAIL_REST_NS          // 'ai-layer/v1'
WPAIL_META_KEY         // '_wpail_data'
WPAIL_OPT_BUSINESS     // 'wpail_business_profile'
WPAIL_OPT_SETTINGS     // 'wpail_settings'
```

### Namespace

All PHP classes use the `WPAIL\` namespace, with subnamespaces mirroring the `includes/` directory structure:

```
WPAIL\Core\
WPAIL\Admin\
WPAIL\Admin\MetaBoxes\
WPAIL\Abilities\
WPAIL\Models\
WPAIL\Transformers\
WPAIL\Repositories\
WPAIL\Rest\
WPAIL\Schema\
WPAIL\Setup\
WPAIL\Setup\Sources\
WPAIL\Integrations\
WPAIL\LLMsTxt\
WPAIL\Licensing\
WPAIL\Support\
WPAIL\PostTypes\
```

---

## Changelog

### 1.2.0

- **MCP integration** — 33 WordPress Abilities registered under the `ai-layer/` namespace; the [WordPress MCP Adapter](https://github.com/wordpress/mcp-adapter) plugin automatically exposes them as MCP tools; any MCP-compatible AI client (Claude, Cursor, etc.) can connect and fully manage AI Layer content without touching the admin UI; requires WordPress 6.9+ (Abilities API is in core)
- **MCP tools** — full CRUD for Services, Locations, FAQs, Proof & Trust, Actions, and Answers; read and partial-update for Business Profile; natural-language answer engine query via `ai-layer-query-answers`
- **Answers CRUD** — `POST /answers`, `GET /answers/{id}`, `PATCH /answers/{id}`, `DELETE /answers/{id}` added; `GET /answers` (no `?query`) now lists all authored Answers; use the REST API or MCP tools to manage guaranteed-response patterns without touching the admin UI
- **Write endpoints** — POST, PATCH, and DELETE added to the REST API for all six entity CPTs; create, update, and delete content via authenticated HTTP calls
- **Single-item GET for ID-based entities** — `GET /faqs/{id}`, `GET /proof/{id}`, `GET /actions/{id}`, and `GET /answers/{id}` added
- **Authentication** — write endpoints use WordPress Application Passwords (HTTP Basic Auth); `edit_posts` capability required; `401` for unauthenticated, `403` for insufficient permissions
- **Relationship sync on write** — POST and PATCH maintain bidirectional references automatically; DELETE cleans up all inverse references before removing the post
- **Partial updates** — PATCH (REST) and update tools (MCP) only change fields present in the request; omitted fields are untouched
- **Answer engine extracted** — `AnswerEngine` class in `WPAIL\Support` replaces inline logic in `AnswersController`; shared by both the REST endpoint and the MCP ability
- **Application Password availability fix** — `wp_is_application_passwords_available` and `application_password_is_api_request` filters ensure Application Passwords work on non-SSL localhost without `WP_ENVIRONMENT_TYPE=local`

### 1.1.0

- **Setup Wizard** — revisitable wizard at AI Layer → Setup Wizard; auto-populates Business Profile from WordPress core settings, Yoast SEO, Rank Math, and WooCommerce; source priority system ensures the most authoritative source wins; every suggestion requires explicit approval before anything is saved
- **Field UX** — placeholders and contextual help text added to all admin fields across the Business Profile and all six CPTs, sourced from the centralised `FieldDefinitions` class
- **Products endpoint** — `GET /products` and `GET /products/{slug}`: a live, read-only proxy over WooCommerce product data with pagination and category filtering; no data duplication or extra database writes; gated behind a Settings toggle and a WooCommerce active check
- **Settings** — added Products endpoint toggle (disabled and greyed out when WooCommerce is not active); added Endpoint Cache TTL field; added FAQPage target pages control (all pages or specific pages)
- **llms.txt** — Products endpoint conditionally included in generated output when WooCommerce is active and Products endpoint is enabled
- **Overview** — Products endpoint rows appear conditionally in the REST API endpoint table; Setup Wizard linked from the incomplete profile notice
- **Post Type Visibility** — Settings controls to make Services, Locations, FAQs, and Proof & Trust publicly accessible on the front-end with a configurable rewrite slug; permalink rules flushed automatically after save
- **`/.well-known/ai-layer`** — machine-readable JSON discovery document listing all active endpoints and capabilities; new **Endpoint discovery mode** setting in Settings chooses between `/.well-known/ai-layer` (recommended, default) and `llms.txt only`; in well-known mode llms.txt outputs a single pointer line; in llms.txt-only mode well-known returns 404; both caches invalidated automatically when settings change
- **Products endpoint shape** — list (`/products`) returns a lean summary (id, slug, name, type, price, currency, on_sale, in_stock, categories, image, url); detail (`/products/{slug}`) adds sku, regular_price, sale_price, short_description, description, gallery, weight, dimensions, stock_quantity
- **AI.txt (Beta)** — new admin page at AI Layer → AI.txt; generates a dynamic `/ai.txt` file with global crawling, training, and attribution controls; agent-specific rules repeater; live preview; conflict detection for physical files and plain permalinks
- **Answers moved to free** — `/answers` endpoint and Answers CPT admin UI are now free features; Pro gating preserved in `Features::answers_enabled()` and can be re-enabled at any time

### 1.0.0
- Initial release
- Business Profile, Settings admin pages
- Overview dashboard with live entity counts, endpoint table, and getting-started checklist
- Six CPTs: Services, Locations, FAQs, Proof & Trust, Actions, Answers
- REST API: `/profile`, `/services`, `/locations`, `/faqs`, `/proof`, `/actions`
- `/answers` endpoint (Pro) — rules-based answer engine with service and location detection
- Organization and FAQPage schema.org output
- Yoast SEO and Rank Math conflict detection
- Freemius free/pro architecture — Answers CPT and `/answers` endpoint gated behind Pro license
- Upgrade page with feature explainer and CTA for free users
- llms.txt support — dynamic virtual route at `/llms.txt`, generated from Business Profile and plugin settings, with conflict detection for physical files, plain permalinks, Yoast, and Rank Math

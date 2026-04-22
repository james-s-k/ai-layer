# AI Layer — WordPress Plugin

Turn your website into something you can ask questions to — not just click through.

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
GET /wp-json/ai-layer/v1/answers?query=Do+you+offer+SEO+in+London
```

```json
{
  "data": {
    "answer_short": "Yes, we offer SEO Consultancy in London and surrounding areas.",
    "confidence": "high",
    "service": { "name": "SEO Consultancy", "from_price": 500, "currency": "GBP" },
    "location": { "name": "London", "region": "Greater London" },
    "actions": [{ "type": "book", "label": "Book a free call", "url": "https://..." }],
    "supporting_data": [{ "type": "testimonial", "headline": "Doubled our traffic in 6 months" }]
  }
}
```

Same question. One hop. Structured answer with context and a next step attached.

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

**Free** includes the full structured data layer:

- Business Profile
- All six entity types: Services, Locations, FAQs, Proof & Trust, Actions, Answers CPT
- REST API: `/profile`, `/services`, `/locations`, `/faqs`, `/proof`, `/actions`
- `/products` endpoint — live WooCommerce product catalogue proxy (WooCommerce required; enabled in Settings)
- Schema.org JSON-LD output (Organization, LocalBusiness, FAQPage)
- llms.txt support
- Setup Wizard — auto-populate Business Profile from WordPress, Yoast SEO, Rank Math, and WooCommerce

**Pro** adds the intelligence layer:

- `/answers` REST endpoint — the rules-based answer engine
- Answers CPT admin UI — author guaranteed responses to predictable queries
- Confidence scoring, source attribution, and proof attachment on every response
- 14-day free trial, no commitment required

Free users who call `/answers` receive an HTTP 402 with an upgrade URL rather than a generic 403, so API consumers get a meaningful, actionable response:

```json
{
  "code": "upgrade_required",
  "message": "The /answers endpoint requires AI Layer Pro.",
  "data": { "status": 402, "upgrade_url": "https://..." }
}
```

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

### llms.txt

**AI Layer → llms.txt**

Optionally expose a standardised `llms.txt` file at your site root (`https://example.com/llms.txt`). The file guides AI systems and agents toward your structured data endpoints.

This feature is fully free. It is implemented as a dynamic WordPress rewrite route — no filesystem writes required.

**Settings:**

| Setting | Description |
|---------|-------------|
| Enable llms.txt | Toggle dynamic serving on/off |
| Custom introduction | Optional paragraph inserted after the auto-generated header |
| Include AI Layer endpoints | Include the full REST endpoints section |
| Include /answers endpoint | Shown only when Pro is active; includes the `/answers` endpoint |
| Include /products endpoint | Shown only when WooCommerce is active and the Products endpoint is enabled in Settings; includes the `/products` entry |
| Include key pages | Include a Key Pages section |
| Page URLs | One entry per line in markdown link format: `[Page Title](https://example.com/page)` |

**Generated file format** (llms.txt specification):

```
# Business Name

> Short business summary

## AI Layer Structured Endpoints

Structured, machine-readable business data is available at the following endpoints:

- [Business Profile](https://example.com/wp-json/ai-layer/v1/profile): Business name, contact details, and description.
- [Services](https://example.com/wp-json/ai-layer/v1/services): Services and products offered.
- [Locations](https://example.com/wp-json/ai-layer/v1/locations): Locations and service areas.
- [FAQs](https://example.com/wp-json/ai-layer/v1/faqs): Frequently asked questions and answers.
- [Proof & Trust](https://example.com/wp-json/ai-layer/v1/proof): Testimonials, case studies, and accreditations.
- [Actions](https://example.com/wp-json/ai-layer/v1/actions): Recommended next steps and calls to action.
- [Products](https://example.com/wp-json/ai-layer/v1/products): Product catalogue with pricing, availability, and categories. *(included when WooCommerce is active and Products endpoint is enabled)*

## Notes

This site exposes structured business data via AI Layer for machine-readable access by AI systems, agents, and search tools.
```

The business name and summary are pulled from your Business Profile. The `/answers` endpoint is only included when Pro is active.

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

Frequently asked questions and their answers.

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

Manually authored structured answers that take priority in the answer engine. Use these to guarantee specific responses to predictable queries.

| Field Group | Fields |
|-------------|--------|
| Matching | Query patterns (line-separated text patterns) |
| Answer | Short answer, long answer, confidence level |
| Relationships | Related services, locations, next actions, source FAQs |

---

## REST API

**Base URL:** `/wp-json/ai-layer/v1`

All endpoints are public and read-only. No authentication required in v1.

**Standard response envelope:**

```json
{
  "data": { ... },
  "meta": { "count": 5 }
}
```

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
    "email": "hello@example.com",
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
      "sku": "WGT-PRO-001",
      "price": "39.99",
      "regular_price": "49.99",
      "sale_price": "39.99",
      "currency": "GBP",
      "on_sale": true,
      "in_stock": true,
      "short_description": "The professional-grade widget.",
      "categories": ["widgets", "pro"],
      "image": "https://example.com/wp-content/uploads/widget-pro.jpg",
      "url": "https://example.com/product/widget-pro"
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
    "sku": "WGT-PRO-001",
    "price": "39.99",
    "regular_price": "49.99",
    "sale_price": "39.99",
    "currency": "GBP",
    "on_sale": true,
    "in_stock": true,
    "short_description": "The professional-grade widget.",
    "categories": [{ "id": 5, "name": "Widgets", "slug": "widgets" }],
    "image": "https://example.com/wp-content/uploads/widget-pro.jpg",
    "url": "https://example.com/product/widget-pro",
    "description": "Widget Pro is built for demanding workflows...",
    "is_virtual": false,
    "is_downloadable": false,
    "tags": ["featured", "bestseller"],
    "gallery": [
      "https://example.com/wp-content/uploads/widget-pro-alt.jpg"
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

### GET `/answers` *(Pro)*

The intelligent answer engine. Takes a natural language query and returns an assembled, structured response.

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
GET /wp-json/ai-layer/v1/answers?query=How+much+does+SEO+cost+in+London
```

**Example response:**
```json
{
  "data": {
    "answer_short": "Our SEO retainers start from £500/month.",
    "answer_long": "SEO pricing depends on your goals and competition...",
    "confidence": "high",
    "source": "faq",
    "service": { "id": 42, "name": "SEO Consultancy", "slug": "seo-consultancy" },
    "location": { "id": 5, "name": "London", "slug": "london" },
    "actions": [
      { "id": 30, "type": "book", "label": "Book a free call", "url": "https://..." }
    ],
    "source_faqs": [
      { "id": 10, "question": "How long does SEO take?" }
    ],
    "supporting_data": [
      { "id": 20, "type": "testimonial", "headline": "Doubled our traffic" }
    ]
  }
}
```

**Confidence values:** `high`, `medium`, `low`  
**Source values:** `manual` (authored Answer matched), `faq` (assembled from FAQ), `dynamic` (assembled without FAQ match)

**Error — missing query parameter:**
```json
{ "code": "missing_query", "message": "A query parameter is required." }
```
HTTP 400

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

### 1.1.0

- **Setup Wizard** — revisitable wizard at AI Layer → Setup Wizard; auto-populates Business Profile from WordPress core settings, Yoast SEO, Rank Math, and WooCommerce; source priority system ensures the most authoritative source wins; every suggestion requires explicit approval before anything is saved
- **Field UX** — placeholders and contextual help text added to all admin fields across the Business Profile and all six CPTs, sourced from the centralised `FieldDefinitions` class
- **Products endpoint** — `GET /products` and `GET /products/{slug}`: a live, read-only proxy over WooCommerce product data with pagination and category filtering; no data duplication or extra database writes; gated behind a Settings toggle and a WooCommerce active check
- **Settings** — added Products endpoint toggle (disabled and greyed out when WooCommerce is not active); added Endpoint Cache TTL field; added FAQPage target pages control (all pages or specific pages)
- **llms.txt** — Products endpoint conditionally included in generated output when WooCommerce is active and Products endpoint is enabled
- **Overview** — Products endpoint rows appear conditionally in the REST API endpoint table; Setup Wizard linked from the incomplete profile notice
- **Post Type Visibility** — Settings controls to make Services, Locations, FAQs, and Proof & Trust publicly accessible on the front-end with a configurable rewrite slug; permalink rules flushed automatically after save

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

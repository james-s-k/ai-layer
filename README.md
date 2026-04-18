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
- Schema.org JSON-LD output (Organization, LocalBusiness, FAQPage)
- llms.txt support

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
3. Fill in your **Business Profile** at **AI Layer → Business Profile**
4. Add Services, Locations, FAQs, Proof & Trust signals, and Actions via the admin menu
5. Optionally enable schema.org JSON-LD output at **AI Layer → Settings**
6. Optionally enable llms.txt at **AI Layer → llms.txt**

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

### Settings

**AI Layer → Settings**

| Setting | Description |
|---------|-------------|
| Enable schema.org output | Toggle JSON-LD output in `<head>` |
| Schema type | Organization, LocalBusiness, ProfessionalService, HomeAndConstructionBusiness, LegalService, HealthAndBeautyBusiness |
| Enable FAQPage schema | Output FAQPage JSON-LD from published FAQs |

The settings page detects Yoast SEO and Rank Math and warns you if their schema output will conflict.

Settings stored in `wp_options` under `wpail_settings`.

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
- **FAQPage schema** — output site-wide, populated from all published public FAQs.

The plugin detects Yoast SEO and Rank Math and disables its own schema output to prevent duplication.

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
- **Centralised field definitions** — `FieldDefinitions` is the single source of truth for field types, labels, defaults, validation rules, and visibility across admin forms, REST, and sanitization
- **Visibility control** — Fields are tagged `public`, `private` (admin-only), or `ai_only` (reserved). Private fields are automatically excluded from REST responses
- **Relationship resolution** — Post IDs are stored as arrays and resolved to lightweight summaries on demand, preventing bloated responses
- **Sanitization by type** — All POST data is validated and sanitized against field definitions before storage

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
WPAIL\Integrations\
WPAIL\LLMsTxt\
WPAIL\Licensing\
WPAIL\Support\
WPAIL\PostTypes\
```

---

## Changelog

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

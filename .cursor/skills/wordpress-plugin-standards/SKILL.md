---
name: wordpress-plugin-standards
description: Applies WordPress plugin architecture, security, database, REST API, Block Editor/React, readme/metadata, and third-party compatibility conventions. Use when authoring or reviewing WordPress plugin code, admin UI, custom tables, REST routes, Gutenberg blocks, readme.txt, plugin headers, or deployment-sensitive behavior.
---

# WordPress Plugin Standards

## Architecture

- Keep structure clean and modular; separate admin, frontend, REST/API, and shared logic when it reduces coupling.
- Load code only where needed: gate includes with `is_admin()`, `wp_doing_ajax()`, `REST_REQUEST`, shortcode/block registration hooks, etc.—avoid running heavy bootstrap on every front request.
- Prefer core APIs and patterns (Options API, transients, `wp_remote_*`, hooks, `WP_Error`) over ad-hoc globals or parallel systems.

## Security

- Sanitize input (`sanitize_*`), validate business rules, escape output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` as appropriate).
- Use nonces for mutating actions and forms; verify with `check_admin_referer` / `wp_verify_nonce` as fits the context.
- Check capabilities (`current_user_can`) before privileged operations; never rely on UI hiding alone.
- REST: `permission_callback` on every route; rate-limit or scope destructive operations; avoid exposing internal paths or raw stack traces in errors.

## Database

- Default to post meta, options, or taxonomies; add custom tables only with clear scale/query needs and a migration/versioning plan.
- Prefer `$wpdb->prepare()` for dynamic SQL; index columns you filter or sort on; avoid unbounded `SELECT *` in hot paths.
- Use dbDelta for schema changes where appropriate; version-stamp schema and upgrade on `admin_init` or a dedicated upgrader.

## REST API

- Namespace routes: e.g. `my-plugin/v1/...`; use consistent HTTP verbs and resource shapes.
- Return structured JSON (objects/arrays with stable keys); use appropriate status codes and `WP_Error` payloads consumers can branch on.
- Document required parameters, auth, and error shapes in code comments or developer docs when non-obvious.

## Gutenberg / React

- Small, reusable components; colocate editor vs save concerns per block conventions.
- Prefer `@wordpress/data` / block APIs / local state over global store sprawl unless cross-block coordination is required.
- Follow `@wordpress/scripts` build patterns and i18n (`@wordpress/i18n`, `wp_set_script_translations`) for user-facing strings.

## Plugin hygiene

- Align with [WordPress PHP/JavaScript coding standards](https://developer.wordpress.org/coding-standards/) as project tooling allows.
- Keep `readme.txt` (changelog, FAQs, Requires at least/Tested up to) in sync with releases.
- Keep plugin header in the main file accurate (Version, Text Domain, Domain Path).
- Minimize dependencies; prefer Composer dev tooling over shipping heavy runtime libs without need.

## Compatibility

- Respect object caching: avoid unbounded autoloaded options; use group-specific cache invalidation where applicable.
- Do not assume English-only: wrap strings, avoid concatenating translated fragments; respect `load_plugin_textdomain`.
- Avoid hard conflicts with common SEO/caching plugins: use standard hooks, defer non-critical work, document required constants or filter usage.
- Do not assume theme markup, `jQuery` in frontend, or specific hosting; feature-detect and degrade gracefully.

## Quick verification (when changing behavior)

- [ ] Capabilities and nonces for privileged paths
- [ ] Input sanitized; output escaped in the right context
- [ ] REST `permission_callback` and predictable error shapes
- [ ] No avoidable global load path regression
- [ ] readme.txt / headers if release-facing

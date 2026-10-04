# TechDoseDaily Core (plugin)

This plugin holds the publication data model, kept separate from the theme. A redesign or theme switch must never lose sections, story types, topics, editors, sources, corrections, placements, newsletter integration, forms or structured data.

Activate it **before** the theme. On activation it:
- seeds the nine sections and six story types;
- creates the `wp_tdd_placements` and `wp_tdd_view_buckets` tables;
- flushes rewrite rules.

Uninstalling keeps all data (see `uninstall.php`).

## Modules (`includes/`)

| Module | Owns |
|---|---|
| `sections.php` | Top-level section URLs (`/ai/`, `/big-tech/` …), `/category/…` → 301, reserved slugs, primary section, **stable story URLs** (section frozen at first publication in `_tdd_permalink_section`; other spellings 301) |
| `taxonomies.php` | `tdd_format` (story type: News, Analysis, Explainer, Guide, Review, Sponsored; exactly one per story, defaults to News) and `tdd_topic` (`/topic/<slug>/`) |
| `meta.php` | Every registered post, attachment and user field (table below) |
| `editorial.php` | Breaking as a time-limited state, substantive-update logic, corrections, sources, reading time |
| `placements.php` | Editorial placement table, API, REST (`/tdd/v1/placements`, `/placements/board`, `/placements/post/<id>`) and WP-CLI for scripting (`wp tdd placement …`) |
| `admin/*` | Newsroom admin: story + page sidebar panels, Media Library fields, placement editor, section settings, profile fields, Site settings with launch readiness. Field reference: `ADMIN-FIELDS.md` |
| `popularity.php` | Anonymous hourly view buckets (`/tdd/v1/view`) behind Most Read; configurable windows (home 24h, section 7d, author 30d); bots, previews and logged-in editorial users excluded; no IPs, cookies or user IDs stored |
| `security.php` | Signed time token, honeypot, hashed-IP throttling, `tdd_core_is_spam` filter; site-wide ceilings (`tdd_core_global_limits`) |
| `hardening.php` | Phase 8: XML-RPC and application passwords off, anonymous `/wp/v2/users` off, generic login errors, security headers, upload types (images + PDF), image-metadata stripping, raw HTML for administrators only, file editor off, Featured Reporting ownership. Filters listed in the file header; decisions in `SECURITY.md` |
| `performance.php` | Phase 7: page-cache headers, editorial-aware TTL, purges, WebP sub-sizes (`PERFORMANCE.md`) |
| `pages.php` | Static page fields (`tdd_kicker`, `tdd_page_headline`, `tdd_intro`, `tdd_statement`, `tdd_focus`, `tdd_last_reviewed`, `tdd_review_cadence`, `tdd_version_url/label`, `tdd_summary`, `tdd_numbered`, `tdd_related_docs`), real "Last updated" (`tdd_core_page_updated`), policy page list |
| `contact.php` | Contact routes and copy, inbox/notes/expectations/secure-tip settings (`tdd_contact_inboxes`, `tdd_contact_notes`, `tdd_contact_expectations`, `tdd_secure_tip`), `POST /tdd/v1/contact` and the no-JS `admin-post.php?action=tdd_contact` (PRG). Checks: nonce, signed token (min 3 s), honeypot, throttle 5 / 10 min, `tdd_core_is_spam`, server validation. Sends one email to the route inbox (Reply-To = sender, name sanitised); stores and logs nothing. Hook `tdd_core_contact_result` (state, route only). |
| `newsletter/*` | `Provider` interface, `Result`, `MailPoet_Provider`, `Null_Provider`, `/tdd/v1/subscribe` and the no-JS POST fallback |
| `schema/schema.php` | Ownership switch for structured data; graph implemented in Phase 6 |
| `seo.php` | Description fallback for Yoast (deck/intro), noindex rules (search, 404, empty authors, thin topics, fixtures), sitemap exclusions for core and Yoast sitemaps |
| `schema/schema.php`, `schema/graph.php` | Single JSON-LD owner (Core or Yoast; Core fallback when Yoast is missing) and the Core graph. See `SEO-SCHEMA.md` |
| `api.php` | Public functions the theme calls (`tdd_core_*`) |

Contact form processing arrives in Phase 4 under the same security rules: nonce (the contact page is excluded from cache), honeypot, minimum time, throttling, server-side validation, sanitising and escaping, Post/Redirect/Get, configurable inboxes, no raw submissions in logs, and a spam filter hook.

## Data model

### Post meta (`post`)

| Key | Type | Purpose |
|---|---|---|
| `tdd_deck` | string | Deck; also the meta description |
| `tdd_short_title` | string | Short headline for compact lists (Daily Tech Brief) |
| `tdd_primary_section` | int (term) | Exactly one section per story. Drives the label, breadcrumb and permalink |
| `tdd_editor` | int (user) | "Edited by" |
| `tdd_updated_at`, `tdd_update_note` | ISO 8601, string | Substantive update only (never cosmetic edits). Drives "Updated" and `dateModified` |
| `tdd_breaking_until` | ISO 8601 | Breaking state ends at this time (default window 6 h). Breaking is not a story type |
| `tdd_corrections` | [{time, text}] | Dated corrections, never deleted |
| `tdd_sources` | [{title, url, type, publisher, date, note}] | Source list. Type is one of: primary, interview, supporting, public-record, confidential |
| `tdd_vendor_reported` | bool | Figures not independently verified |
| `tdd_ai_disclosure` | string | Shown where AI-generated material appears |
| `tdd_sponsor` | string | Forces the Sponsored story type |
| `tdd_reading_time` | int | Computed on save |
| `tdd_severity` | string | Security severity line for alert treatments (from the vendor/CVE rating) |
| `_tdd_permalink_section` | slug (protected) | URL section frozen at first publication |

### Attachment meta

`tdd_credit`, `tdd_source_url`, `tdd_license_note`, `tdd_source_type` (official · photo · licensed · screenshot · graphic · ai-generated). Credit lives on the image, so it travels with it wherever the image is reused.

### User meta

`tdd_title`, `tdd_beats` (topic IDs), `tdd_location`, `tdd_covering_since`, `tdd_short_bio`, `tdd_note`, `tdd_photo`, `tdd_social` ([{label, url}], real accounts only), `tdd_public_email`, `tdd_show_on_about`, `tdd_about_order`, `tdd_featured_posts` (up to 3 story IDs, editors), `tdd_editor_user`.

### Section (category) meta

`tdd_desk_note`, `tdd_desk_members` (user IDs), `tdd_desk_url`, `tdd_topic_nav` (ordered topic IDs), `tdd_short_description` (one line for the About coverage list). Registered with `manage_categories` permission and sanitizers. Read with `tdd_core_section_settings()`; topic counts with `tdd_core_section_topics()`.

### Placements (`wp_tdd_placements`)

| Placement | Slots | Section? |
|---|---|---|
| `homepage_lead` | 1 | — |
| `homepage_secondary` | 4 | — |
| `editors_pick` | 4 | — |
| `ai_band_lead` / `ai_band_secondary` | 1 / 4 | — |
| `section_lead` / `section_secondary` | 1 / 3 | yes |
| `daily_brief` | 10 | — |

Each row holds `post_id`, `placement`, `position`, `section_id`, `start_at` and an optional `expires_at`. `tdd_core_placement_resolve()` fills each slot with: the latest-started valid entry → the next valid entry for that slot (a superseded pick returns when the newer one expires) → the newest eligible story; stories already on the page are skipped, and the result never has holes. Placing a story never ends the previous holder; it only ends other entries for the same story in that placement (a move).

## Ownership: who outputs what

| Concern | Owner |
|---|---|
| Titles, meta descriptions, canonical, robots, Open Graph / X cards, XML sitemaps | Yoast SEO |
| JSON-LD graph (NewsMediaOrganization, WebSite, NewsArticle, ProfilePage/Person, BreadcrumbList, CollectionPage, AboutPage, ContactPage, WebPage), news sitemap | TechDoseDaily Core (Phase 6). When enabled, Yoast's schema output is turned off so there is exactly one graph |
| Visible breadcrumbs, labels, cards, templates, CSS, JS | Theme |
| Newsletter provider | Core (adapter); the theme only posts to `/tdd/v1/subscribe` |

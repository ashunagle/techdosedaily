# SEO and structured data (Phase 6)

Verified against **Yoast SEO 28.6** (real plugin, local test site) on Oct 4, 2026.

## 1. Ownership boundary

| Concern | Owner | Notes |
|---|---|---|
| Document titles | **Yoast SEO** | Theme prints none. With Yoast active, Core removes the block-theme `<title>` WordPress adds during template loading, so there is exactly one. |
| Meta descriptions | **Yoast SEO** | Core supplies a *fallback* only when Yoast has no explicit value: `tdd_deck` (stories) / `tdd_intro` (pages), trimmed to 300 characters. An explicit Yoast description is never overwritten. Same fallback for `og:description` and `twitter:description`. |
| Canonical URLs | **Yoast SEO** (WordPress core prints singular canonicals when Yoast is absent) | Article URLs are stable because Core freezes the URL section at first publication. |
| Robots directives | **Yoast SEO** output | Core decides *which publication views* are noindex (one rule set, applied to both WordPress core robots and Yoast): search, 404, author pages with no published story, topics with fewer than 3 stories, local fixtures. |
| Open Graph / X cards | **Yoast SEO** | Core aligns Yoast's output with publication data only: description fallback, story dates (frozen publication time; modified only after a recorded update), story reading time in link previews, and the profile photo instead of a Gravatar on author pages. No generated images, descriptions or identities. |
| XML sitemaps | **Yoast SEO** (WordPress core sitemaps when Yoast is absent) | Core excludes fixtures, thin topics, attachments, tags and post formats from both. |
| JSON-LD graph | **TechDoseDaily Core** (`includes/schema/`) — or Yoast, by setting | Exactly one graph per page; none on 404, search, previews, attachments and empty author pages from either owner. When Yoast owns schema, Core still corrects publication data in it: story dates, no Gravatar person images, image `creditText`/licence URL. Theme prints nothing SEO-related. |
| Google News sitemap | **Not built** | Separate launch decision (eligibility, publication name, 48-hour window, operational review). |

## 2. Single schema owner and switching

Setting: **Tech Dose Daily → Site settings → Structured data** (option `tdd_core_schema_owner`, `yoast` default | `core`).

| Saved setting | Yoast loaded? | Printed |
|---|---|---|
| Yoast | yes | Yoast graph only (Core prints nothing) |
| Core | yes | Core graph only; Yoast's JSON-LD is switched off through its documented `wpseo_json_ld_output` filter |
| Yoast | **no** | Core graph (fallback) and a warning in Site settings — never "neither" |
| Core | no | Core graph |

The owner is evaluated on every request (`tdd_core_schema_owner()`), so a switch takes effect immediately and switching back restores Yoast's schema; nothing is deleted. An invalid saved value reads as `yoast`. Launch readiness shows whether Yoast is active and which graph is printed.

**Switching procedure**
1. Confirm Yoast is active and configured (section 7).
2. Site settings → Structured data → choose the owner → Save.
3. Check one story, one section and the About page: view source has exactly one `application/ld+json` block — `class="tdd-schema-graph"` (Core) or `class="yoast-schema-graph"` (Yoast).
4. Run Google's Rich Results Test on one story.
5. If a page cache is active (Phase 7), purge it.

## 3. Entities and stable `@id`s

| Entity | `@id` | Emitted on |
|---|---|---|
| `NewsMediaOrganization` (publisher) | `{home}/#organization` | every graph |
| `WebSite` + `SearchAction` | `{home}/#website` | every graph |
| `ImageObject` (logo) | `{home}/#logo` | when a Site Icon is set |
| `WebPage` · `AboutPage` · `ContactPage` · `CollectionPage` · `ProfilePage` | `{canonical}#webpage` | per view |
| `BreadcrumbList` | `{canonical}#breadcrumb` | every view except the homepage |
| `NewsArticle` (+ subtype) | `{canonical}#article` | published stories |
| `Person` | `{author archive URL}#person` | story author and editor; author pages |
| `ImageObject` | `{image file URL}#image` | real hero image / author photo |

Entities are referenced (`{"@id": …}`), never repeated; every reference resolves inside the same graph (tested). The same Person `@id` is used by `NewsArticle.author`, `NewsArticle.editor` and `ProfilePage.mainEntity`.

**Page types**

| View | Type |
|---|---|
| Story | `WebPage` + article |
| Homepage | `WebPage` (about → organization) |
| Latest, section, topic, story type, tag | `CollectionPage` (paginated pages use their own `/page/N/` URL) |
| Author with published stories | `ProfilePage` (mainEntity → Person) |
| About / Contact template | `AboutPage` / `ContactPage` (about → organization) |
| Other pages (policies, Newsletter) | `WebPage` |
| Search, 404, empty author, previews, drafts, password-protected, attachments, feeds, admin, date archives | **no schema** |

## 4. Mappings (stored data only)

**NewsMediaOrganization:** `name` (site title), `url`, `logo`/`image` (Site Icon, only if set), `publishingPrinciples` → Editorial Standards, `correctionsPolicy` → Corrections Policy, `actionableFeedbackPolicy` → Contact — each only when that page is published.

**WebSite:** `url`, `name`, `description` (tagline, if any), `publisher`, `inLanguage`, `potentialAction` SearchAction (`/?s={search_term_string}`).

**Article**

| Property | Source | Rule |
|---|---|---|
| `@type` | story type | News, Guide, Review → `NewsArticle`; Analysis → `["NewsArticle","AnalysisNewsArticle"]`; Explainer → `["NewsArticle","BackgroundNewsArticle"]`; Sponsored → `["Article","AdvertiserContentArticle"]` (sponsored content is not news). Review is **not** `ReviewNewsArticle`: we store no rating or reviewed item, and an incomplete Review would trigger review-snippet errors. |
| `genre` | story type name | always (the format as ordinary metadata) |
| `headline` | post title (raw, no display filters) | |
| `alternativeHeadline` | `tdd_short_title` | only when different |
| `description` | `tdd_deck` | omitted when empty |
| `url`, `mainEntityOfPage`, `isPartOf` | canonical URL / `#webpage` | |
| `datePublished` | `_tdd_first_published` (frozen at first publication; falls back to the post date) | never moves, even if the post date is edited |
| `dateModified` | `tdd_updated_at` (recorded substantive update) | otherwise equals `datePublished`; typo saves, placements and other saves never change it |
| `author` | post author → Person | |
| `editor` | `tdd_editor` → Person | only when assigned |
| `publisher` | organization | |
| `articleSection` | primary section name | |
| `keywords` | topic names | only when topics exist |
| `image` | featured image → ImageObject | only when a real image exists |
| `wordCount`, `timeRequired` | content words, `tdd_reading_time` (`PT9M`) | |
| `isAccessibleForFree` | `true` | site has no paywall |
| `sponsor` | `tdd_sponsor` → Organization `{name}` | Sponsored stories only |
| `correction` | `tdd_corrections` → `CorrectionComment {text, datePublished}` | published corrections only |
| `inLanguage` | site locale | |

**Person:** `name`, `url` (author page — only when the person has published stories), `jobTitle` (`tdd_title`), `description` (`tdd_short_bio`), `image` (`tdd_photo` attachment only; never Gravatar), `sameAs` (`tdd_social` URLs, http/https only), `worksFor` → organization. Never email, login, location or private notes.

**ImageObject:** `url`, `contentUrl`, `width`, `height`, `caption` (attachment caption, else alt text), `creditText` (`tdd_credit`), `license` (only when `tdd_license_note` is itself a valid URL), `inLanguage`.

**BreadcrumbList:** Home › Section › story; Home › About › policy page; Home › page; Home › section/topic/story type; Home › author. Last item = canonical URL.

## 5. Intentionally omitted

| Data / property | Reason |
|---|---|
| AI disclosure, vendor-reported flag, severity line | No valid Schema.org property; inventing one would mislead parsers. Shown to readers on the page instead. |
| Sources (`citation`, `isBasedOn`) | Source notes and confidential-source details are editorial data; not exposed. |
| Image `creator`, `copyrightNotice`, `acquireLicensePage`; free-text licence notes | Stored fields are free text (credit line, usage terms), not structured creator/licence data. Validator lists these as optional warnings. |
| Image source type (incl. AI-generated) | No valid property; the label is shown on the page. |
| `ReviewNewsArticle`, ratings | No rating or reviewed-item data. |
| Organization `sameAs` | No verified list of official accounts stored yet; add a Site setting when accounts exist. |
| Organization logo when no Site Icon | Never a default/placeholder logo. Launch readiness flags the missing icon. |
| `speakable`, `dateline`, `backstory` | No editorial data for them. |
| Google News sitemap | Separate launch decision. |

## 6. Canonical, robots, social and sitemap audit (real Yoast 28.6)

| Check | Result |
|---|---|
| Section canonical `/ai/`; `/category/ai/` → 301 `/ai/`; section sitemap uses `/ai/` | **Pass** |
| Story canonical stable after a section change; old section spelling 301s to the stored URL; `articleSection` follows the new section | **Pass** |
| Paginated archives: Yoast canonical self-referencing (`/ai/page/2/`), `rel=prev` → page 1, `rel=next` on page 1; same for topic and author archives | **Pass** |
| Search, 404, thin topic, empty author, fixture: `noindex, follow`, no canonical | **Pass** |
| Indexable story and paginated section: `index, follow` | **Pass** |
| 404: HTTP 404, title "Page not found · Tech Dose Daily", no schema from either owner | **Pass** (after fix 2 and the 404 title template, section 9) |
| Date archives disabled (redirect); attachment URLs redirect to the file | **Pass** |
| Previews/drafts: not public, no schema | **Pass** |
| Description: no explicit Yoast value → meta, `og:description` and `twitter:description` = deck; explicit meta description wins; explicit social description wins; story without deck → no meta description | **Pass** (after fix 3) |
| Page description fallback = intro | **Pass** |
| Story OG: `og:type article`, `og:title`, `og:url` = canonical, `og:image` = hero (none when no hero — no placeholder), `article:published_time` = frozen publication time, `article:modified_time` = last substantive update (absent without one), `twitter:card summary_large_image`, reading time = story reading time | **Pass** (after fix 4) |
| Static page / section / author / homepage OG: `og:type` article / article / profile / website, title, URL; author page has no Gravatar image | **Pass** (after fix 5) |
| Yoast sitemaps: `/wp-sitemap.xml` → `/sitemap_index.xml`; no attachment, tag or post-format sitemaps; fixtures excluded from post and page sitemaps; stories listed by canonical URL (audit mode); thin topics excluded; no author without stories | **Pass** |
| `og:image:alt` | **Not output by Yoast 28.6** (Yoast omits it). Alt text stays on the page image; not added by Core. |

## 7. Yoast configuration (applied on the local test site; apply the same on staging/production)

- Settings → Site representation: Organization, name "Tech Dose Daily", logo = final icon (used only when Yoast owns schema).
- Content types: Posts and Pages shown in search results; **Media: redirect attachment URLs**; Posts "schema" defaults irrelevant while Core owns schema.
- Categories (Sections), Topics, Story types: shown in search results. **Tags and Format: not shown.**
- Author archives: on; "Show author archives without posts" = **No**. Date archives: **off**.
- Breadcrumbs: **off** (theme prints its own).
- Title templates: sections, topics and story types `%%term_title%% %%page%% %%sep%% %%sitename%%` (Yoast's default adds "Archives"); authors `%%name%% %%page%% %%sep%% %%sitename%%`; 404 `Page not found · %%sitename%%` (approved 404 title).
- Yoast cannot build its indexables on a non-production environment (`WP_ENVIRONMENT_TYPE=local`); it computes values per request instead. Run Yoast's SEO data optimisation once on staging/production.
- Search pages and 404s are noindex by default (Core also enforces).
- Social: default Open Graph image left **empty** (no placeholder); X card = summary_large_image.
- Leave Yoast's "Remove category base" option off: Core already serves sections at `/ai/` and 301s `/category/…`.

## 8. Validation results (Yoast SEO 28.6 active)

| Check | Result |
|---|---|
| `tests/seo/seo_test.py` — the original site checks (Core graph for 6 story variants and 11 page/archive types, noindex views, redirects, section change, frozen dates, attachments, escaping, previews) plus real-Yoast owner modes, descriptions, titles, canonicals, robots, Open Graph/X and Yoast sitemaps | **300 / 300 pass** (was 243 without Yoast; core-sitemap checks replaced by Yoast-sitemap checks when Yoast is active) |
| `tests/seo/seo_hooks.php` — Yoast filters (description fallback, robots, sitemap exclusions, JSON-LD switch, owner logic) | **15 / 15 pass** |
| Owner modes on real Yoast (story, section, author, 404) — `tests/seo/out/owner-modes.txt`, screenshot `tests/review/wp06-phase6-owner-modes.png` | Setting Yoast → `yoast-schema-graph` only; setting Core → `tdd-schema-graph` only; Yoast deactivated → `tdd-schema-graph`; 404 → none in every mode. **Pass** |
| Schema.org vocabulary + Google rich-result rules (`validate.mjs`, schema.org v30.1), 15 pages — **Core owner** | **0 errors.** Warnings: optional image licensing fields on the 4 stories with hero images. |
| Same, **Yoast owner** | **0 errors** (after fix 6). Warnings: optional image licensing fields on 7 pages; Yoast's homepage BreadcrumbList has a single item (Yoast default). |
| Public regression (19 URLs × 8 widths) with Yoast active | **Pass** |
| Story editor panels with Yoast active (Phase 5 test) | **15 / 15 pass** |
| Google Rich Results Test (live) and validator.schema.org | **Pending** — both blocked from the build environment; the local site is not public. Run on staging. |

Expected Google warnings (not errors): stories without a hero image ("missing image"), Person without `url` when the person has no published story, organization without a logo until the Site Icon is uploaded.

Representative rendered graphs: `tests/seo/out/*.json`.

## 9. Failures found with real Yoast, and fixes (all now passing)

| # | Found with Yoast 28.6 | Fix (Core, `seo.php` / `schema.php`) |
|---|---|---|
| 1 | Two identical `<title>` elements: WordPress adds the block-theme title tag during template loading, after Yoast removed the core title hooks. | With Yoast active, Core removes `_block_template_render_title_tag` at `template_include`. |
| 2 | Yoast printed JSON-LD on the 404 page (and search, empty author pages). | `wpseo_json_ld_output` returns false on 404, search, previews, attachments and empty author pages, matching the Core graph's rules. |
| 3 | `og:description` used an auto-excerpt of the body ("[Sample] …") while the meta description used the deck. | OG/X descriptions fall back to the deck when the indexable has no explicit social or meta description; explicit values are untouched. |
| 4 | Yoast dated stories from WordPress's post date/last save: `article:modified_time` and schema `dateModified` moved on typo saves. | `wpseo_frontend_presentation`, `wpseo_schema_article` and `wpseo_schema_webpage` use the frozen publication time and the last recorded substantive update. Link-preview reading time uses the story's reading time (Yoast estimated 3 min vs 9). |
| 5 | Yoast used the Gravatar (derived from the private account email) as the author `og:image` and in schema `Person.image`. | Author pages use the profile photo or no image; Gravatar removed from Yoast person pieces. |
| 6 | Validator errors on Yoast's `ImageObject` (no `creditText`/`creator`/`copyrightNotice`/`license`). | `wpseo_schema_imageobject` adds the stored credit (and licence URL when it is one). |
| — | Title templates ("AI Archives", "Priya Raman, Author at …", 404 separator) | Yoast configuration, section 7 (not code). |

Remaining expected differences between the two owners (Yoast mode): Yoast uses its own `@id` scheme and types (`Article`, not `NewsArticle`/subtypes), no editor, sponsor, corrections, `isAccessibleForFree` or organization policy links. The Core graph is the richer, publication-specific one; Yoast mode stays available as the safe default and fallback.

# Implementation map — approved design → WordPress

Architecture: presentation in the theme, data model in the **TechDoseDaily Core** plugin (see `ARCHITECTURE.md`). Every row below is presentation; the data each block reads comes from `tdd_core_*()`.

Generated in Phase 1 from the 94 design-system entries (`01-Design-System/components.md`). Status: ✅ built · ◻ planned. Phase numbers follow the master prompt.

## Screens → templates

| Approved reference (03-Approved) | Template | Stylesheets | Phase |
|---|---|---|---|
| homepage-desktop-v1.1 / homepage-mobile-v1 | `front-page.html` | core, archive | 3 |
| article-desktop-v1 / article-mobile-v1 | `single.html` | core, article | 3 |
| category-desktop-v1 / category-mobile-v1 | `category.html`; `archive.html` (topic, story type, tag); `home.html` (Latest) | core, archive | 3 |
| search-desktop-v1 / search-mobile-v1 | `search.html` | core, search, archive | 3 |
| author-desktop-v1 / author-mobile-v1 | `author.html` | core, author, archive | 3 |
| static-page-desktop-v1 / static-page-mobile-v1 | `page.html` | core, article, static | 4 |
| static-page-short-v1 | `page-short.html` | core, article, static | 4 |
| about-desktop-v1 / about-mobile-v1 | `page-about.html` | + about-contact | 4 |
| contact-desktop-v1 / contact-mobile-v1 (+ form states) | `page-contact.html` | + about-contact | 4 |
| newsletter-desktop-v1 / newsletter-mobile-v1 (+ form states) | `page-newsletter.html` | + about-contact, newsletter-404 | 4 |
| 404-desktop-v1 / 404-mobile-v1 | `404.html` | core, search, about-contact, newsletter-404 | 4 |
| (all) | `parts/header.html, parts/footer.html` | core, layout | 1 |

## Components

### Templates

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| AboutDesktop | template | `templates/page-about.html` (tdd/static-page end=about) | 4 | ✅ |
| AboutMobile | template | `templates/page-about.html (≤767)` | 4 | ✅ |
| ArticleDesktop | template | `templates/single.html` (tdd/article-header, tdd/article-body, tdd/more-in-section, tdd/related-coverage, tdd/most-read, tdd/newsletter-cta) | 3 | ✅ |
| ArticleMobile | template | `templates/single.html` (same DOM; approved ≤767 values; mobile-only lists after the article) | 3 | ✅ |
| AuthorDesktop | template | `templates/author.html` (tdd/author-masthead, tdd/author-featured, tdd/author-archive, tdd/author-about) | 3 | ✅ |
| AuthorMobile | template | `templates/author.html` (≤767) | 3 | ✅ |
| CategoryDesktop | template | `templates/category.html` (tdd/section-masthead, section-top, section-feed, section-analysis, section-guides, topic-chips, section-desk, brief-link); topic / story type / tag / Latest → `archive.html`, `home.html` | 3 | ✅ |
| CategoryMobile | template | `templates/category.html` (≤767) | 3 | ✅ |
| ContactDesktop | template | `templates/page-contact.html` (tdd/contact-page) | 4 | ✅ |
| ContactMobile | template | `templates/page-contact.html (≤1199: approved mobile order)` | 4 | ✅ |
| HomepageDesktop | template | `templates/front-page.html` (tdd/home-hero, latest-feed, most-read, home-guides, home-ai-band, home-picks, home-sections, daily-brief, newsletter-cta) | 3 | ✅ |
| HomepageMobile | template | `templates/front-page.html` (≤767; mobile-only Most Read and guides swipe row) | 3 | ✅ |
| NewsletterDesktop | template | `templates/page-newsletter.html` (tdd/newsletter-hero + page content + tdd/newsletter-repeat) | 4 | ✅ |
| NewsletterMobile | template | `templates/page-newsletter.html (≤767)` | 4 | ✅ |
| NotFoundDesktop | template | `templates/404.html` (tdd/not-found) | 4 | ✅ |
| NotFoundMobile | template | `templates/404.html (≤767)` | 4 | ✅ |
| SearchDesktop | template | `templates/search.html` (tdd/search-results) | 3 | ✅ |
| SearchMobile | template | `templates/search.html` (≤767; filters bottom sheet) | 3 | ✅ |
| StaticPageDesktop | template | `templates/page.html` (tdd/static-page; rail ≥1200, inline contents <1200) | 4 | ✅ |
| StaticPageMobile | template | `templates/page.html (≤767)` | 4 | ✅ |
| StaticPageShort | template | `templates/page-short.html` (also automatic below 4 sections) | 4 | ✅ |

### Brand

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| ImageStrategy | doc | `EDITORIAL-WORKFLOW.md image rules` | 5 | ◻ |
| Logo | helper | `tdd_logo() + assets/images` | 1 | ✅ |

### Navigation

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| Footer | block (part) | `tdd/site-footer → parts/footer.html` | 1 | ✅ |
| Header | block (part) | `tdd/site-header → parts/header.html` | 1 | ✅ |
| TrendingBar | block | `tdd/trending` | 2 | ✅ |

### Actions

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| Button | core style | `core/button styles: primary, secondary, icon` | 2 | ◻ |
| FormInput | CSS + markup | `forms.php field helpers` | 2 | ◻ |

### Stories

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| LatestUpdateRow | block | `tdd/latest-feed` | 2 | ✅ |
| MostReadRow | block | `tdd/most-read` (real first-party view counts only; hidden without data) | 2 | ✅ |
| StoryCard | block | `tdd/story-card` (one) + `tdd/story-list` (several, de-duplicated per page); feature · large · medium · compact | 2 | ✅ |

### Modules

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| CategoryBlock | block | `tdd/home-sections` (alert / feature / list; desktop row + approved mobile order) | 3 | ✅ |
| DailyTechBrief | block | `tdd/daily-brief` (daily_brief placement) | 2 | ✅ |
| NewsletterCTA | block | `tdd/newsletter-cta` → `/tdd/v1/subscribe` (provider adapter) | 2 | ✅ |

### Editorial

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| Label | helper | `tdd_labels() (category + format)` | 2 | ✅ |
| SectionHeading | block | `tdd/section-heading` | 2 | ✅ |

### Article

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| ArticleHeader | block | `tdd/article-header` (+ hero) | 3 | ✅ |
| AuthorCard | block | part of `tdd/article-body` (`tdd_author_card()`) | 3 | ✅ |
| Breadcrumbs | block | `tdd/breadcrumbs` | 2 | ✅ |
| DataTable | block | `tdd/data-table` (table + mobile stacked rows; editor UI) | 3 | ✅ |
| KeyTakeaways | block | `tdd/key-takeaways` (InnerBlocks list; editor UI) | 3 | ✅ |
| Notices | block | part of `tdd/article-body` (update note + `tdd_corrections` meta) | 3 | ✅ |
| PullQuote | core style | `core/quote` mapped to `.tdd-quote` inside stories (render filter) | 3 | ✅ |
| RelatedCoverage | block | `tdd/related-coverage` | 3 | ✅ |
| ShareControls | block | `tdd_share()` in article header, rail and mobile rows | 3 | ✅ |
| SourceList | block | part of `tdd/article-body` (post meta `tdd_sources`) | 3 | ✅ |
| TableOfContents | block | rail + `details.m-toc` in `tdd/article-body` (auto from H2 ids, 7+ min) | 3 | ✅ |
| WhyThisMatters | block | `tdd/why-this-matters` (InnerBlocks; editor UI) | 3 | ✅ |

### Category

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| AnalysisItem | block | `tdd/section-analysis` | 3 | ✅ |
| CategoryMasthead | block | `tdd/section-masthead` | 3 | ✅ |
| FeedDateGroup | block | `tdd/section-feed` (main query, date groups) | 3 | ✅ |
| Pagination | block | `tdd/pagination` + `tdd_pagination()` (real `/page/N/` URLs; Load more appends, merges date groups, updates the URL, links after 3 loads) | 2–3 | ✅ |
| TopicNav | block | `tdd/section-masthead` (topic nav) | 3 | ✅ |
| TopicRail | block | `tdd/topic-chips` (+ mobile variant) | 3 | ✅ |

### Search

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| ResultRow | block | `tdd/search-results` (`<mark>` highlights) | 3 | ✅ |
| SearchEmpty | block | `tdd/search-results` (empty state) | 3 | ✅ |
| SearchField | block | `tdd/search-results` (field + clear) | 3 | ✅ |
| SearchFilters | block | `tdd/search-results` (filters + mobile sheet, `assets/js/search.js`) | 3 | ✅ |

### Author

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| AuthorArchive | block | `tdd/author-archive` | 3 | ✅ |
| AuthorArticleRow | block | `tdd/author-archive` (`tdd_author_row()`) | 3 | ✅ |
| AuthorBeatChip | block | `tdd/author-masthead` (beats) | 3 | ✅ |
| AuthorEmptyState | block | `tdd/author-archive` (empty state, noindex) | 3 | ✅ |
| AuthorMasthead | block | `tdd/author-masthead` | 3 | ✅ |
| AuthorMeta | block | `tdd/author-masthead` (meta row) + `tdd/author-about` | 3 | ✅ |
| AuthorSocialLinks | block | `tdd/author-masthead` (contact & follow) | 3 | ✅ |
| FeaturedAuthorStory | block | `tdd/author-featured` | 3 | ✅ |

### Static pages

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| ContactCTA | block | `tdd/contact-cta` (moved to the page end; `contact:<topic>` / `page:<path>` links) | 4 | ✅ |
| PolicyCallout | block | `tdd/policy-callout` (inner list/paragraphs; neutral option) | 4 | ✅ |
| PolicyNotice | block | `tdd/policy-notice` (“What changed” option) | 4 | ✅ |
| PolicyTable | block | `tdd/data-table` variant “policy” (+ labelled mobile rows) | 4 | ✅ |
| RelatedPolicyLinks | helper | `tdd_related_policies()` in tdd/static-page and tdd/contact-page (published pages only) | 4 | ✅ |
| StaticPageHeader | helper | `tdd_static_header()` (page fields in Core) | 4 | ✅ |
| StaticPageSection | core + builder | `core/heading` H2 → numbered `.tdd-spsec` with anchor link | 4 | ✅ |
| StaticPageTOC | helper | `tdd_static_rail()` / `tdd_static_inline_toc()` | 4 | ✅ |

### About & Contact

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| AboutMission | page fields | statement, intro, focus row (Core page meta) | 4 | ✅ |
| ContactForm | helper + JS | `tdd_contact_form()` + `contact.js` → REST `/tdd/v1/contact` (no-JS: admin-post PRG) | 4 | ✅ |
| ContactRoute | block | `tdd/contact-routes` (compact) + full list on Contact | 4 | ✅ |
| CoverageList | block | `tdd/coverage-list` (real sections) | 4 | ✅ |
| EditorialPrinciples | block | `tdd/principles` | 4 | ✅ |
| FundingDisclosure | block | `tdd/funding` (status per source) | 4 | ✅ |
| SecureTipNotice | helper | `tdd_secure_tip_html()` (Core setting; honest text when none) | 4 | ✅ |
| TeamCard | block | `tdd/team` (users with “Show on About”) | 4 | ✅ |

### Newsletter & 404

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| NewsletterBenefit | block | `tdd/nl-benefits` | 4 | ✅ |
| NewsletterFAQ | block | `tdd/faq` (native details) | 4 | ✅ |
| NewsletterFormStates | states | `newsletter.js` + Core REST responses | 4 | ✅ |
| NewsletterHero | block | `tdd/newsletter-hero` | 4 | ✅ |
| NewsletterPromise | block | `tdd/nl-promise` | 4 | ✅ |
| NewsletterSampleIssue | block | `tdd/nl-sample` (today’s real Daily Tech Brief; hidden below 3) | 4 | ✅ |
| NewsletterSignup | helper | `tdd_newsletter_signup()` → REST `/tdd/v1/subscribe` | 4 | ✅ |
| NotFoundLatest | block | `tdd/not-found` (latest 5) | 4 | ✅ |
| NotFoundMessage | template | `templates/404.html` | 4 | ✅ |
| NotFoundSearch | block | `tdd/not-found` | 4 | ✅ |
| NotFoundSections | block | `tdd/not-found` (existing sections only) | 4 | ✅ |

### Other

| Component | Implementation | WordPress artefact | Phase | Status |
|---|---|---|---|---|
| Cover | n/a | `design-system cover card (artifact only)` | — | ◻ |

## Notes

- **Block** = server-rendered `tdd/*` block (block.json + render.php) that prints the approved markup verbatim. Editor previews are server-side.
- **Core style** = a registered block style on a core block that adds the approved class — no new block.
- **Pattern** = locked pattern (content editable, layout fixed).
- Mobile/desktop share one DOM everywhere except the footer (two approved structures, toggled by CSS, see VISUAL-QA.md).
- **Responsive grid.** Phase 1 delivers the page gutters (40 / 32 / 20px via `--gutter`) and containers (1280 content in a 1360 max frame). The approved CSS defines column spans per component, so the 12 → 8 → 1 column behaviour of `.tdd-grid` is applied with each template in Phase 3, where every span can be checked against the references.

## Data model (TechDoseDaily Core)

| Capability | Module | Phase | Status |
|---|---|---|---|
| Sections at `/ai/` …, 301 from `/category/…`, reserved slugs, primary section in permalinks | `sections.php` | 1b | ✅ |
| Stable article URLs: section frozen at first publication (`_tdd_permalink_section`), other spellings 301 | `sections.php` | 3 | ✅ |
| Story type `tdd_format` (one per story, default News) · Topics `tdd_topic` | `taxonomies.php` | 1b | ✅ |
| Post / attachment / user meta (REST-exposed, sanitised) | `meta.php` | 1b | ✅ |
| Breaking state, updates, corrections, sources, reading time | `editorial.php` | 1b | ✅ |
| Editorial placements (table, API, REST, WP-CLI) | `placements.php` | 1b | ✅ |
| Placement resolution: active entry → next valid entry → newest eligible; no holes; page-level de-duplication | `placements.php`, theme `tdd_shown()` | 3 | ✅ |
| Most Read view counts (cookieless) | `popularity.php` | 2 | ✅ |
| Most Read v2: hourly buckets, windows home 24h / section 7d / author 30d (option `tdd_core_most_read_windows`), editors / previews / bots excluded | `popularity.php` | 3 | ✅ |
| Section settings (desk note, desk people, How-we-cover URL, topic nav) · topic counts | `meta.php`, `api.php` | 3 | ✅ |
| Author settings (featured posts, editor) · severity line `tdd_severity` | `meta.php` | 3 | ✅ |
| Newsletter `Provider` interface + MailPoet adapter + `/tdd/v1/subscribe` + no-JS fallback | `newsletter/` | 2 | ✅ (MailPoet itself untested until installed) |
| Form security (token, honeypot, throttle, spam hook) | `security.php` | 2 | ✅ |
| Editor UI: story panels (details, status & labels, sources, corrections, hero image credit, placement, pre-publish checklist), page details panel | `admin/admin.php`, `assets/admin/story.js`, `page.js` | 5 | ✅ |
| Placement editor (Homepage / Section pages / Daily Tech Brief; fallback shown per slot; replace, schedule, change times, remove) | `admin/placements-admin.php`, `assets/admin/placements.js`, REST `/tdd/v1/placements/board` | 5 | ✅ |
| Section settings screen (one-liner, topic nav, module switches, pinned stories, desk) | `admin/sections-admin.php` | 5 | ✅ |
| Author profile fields (self-edited + editors-only block) | `admin/users-admin.php` | 5 | ✅ |
| Site settings + launch readiness | `admin/settings.php` | 5 | ✅ |
| Media Library credit / source / licence fields | `admin/admin.php` | 5 | ✅ |
| Contact processing | `includes/contact.php` (REST + admin-post, nonce, token, honeypot, throttle, spam hook, configurable inboxes) | 4 | ✅ |
| Static page fields (kicker, headline, intro, statement, focus row, last reviewed, cadence, version link, summary, numbering, related documents) · section short description | `pages.php`, `meta.php` | 4 | ✅ |
| JSON-LD graph (NewsMediaOrganization, WebSite, WebPage types, BreadcrumbList, NewsArticle + subtypes, Person, ImageObject, ProfilePage, CollectionPage, AboutPage, ContactPage) + single-owner switch | `schema/schema.php`, `schema/graph.php` | 6 | ✅ |
| SEO rules: description fallback (Yoast filters), noindex rules, sitemap exclusions (core + Yoast) | `seo.php` | 6 | ✅ |
| Google News sitemap | — | — | ◻ separate launch decision |
| Page-cache headers: editorial-aware TTL (next Breaking / placement / scheduled-story boundary), LiteSpeed Cache API, purge on editorial changes, no-cache rules; WebP sub-sizes | `performance.php` | 7 | ✅ (production cache verification on staging) |
| Clock-correct placement cache, start/expiry indexes, batched post/meta/term/thumbnail priming | `placements.php`, `api.php` | 7 | ✅ |
| Most Read excludes lab/uptime tools (`X-TDD-Perf-Test`, webdriver, prerender) | `popularity.php`, theme `view-beacon.js` | 7 | ✅ |
| Theme loading: one high-priority hero, lazy content media + column `sizes`, minified + file-versioned assets, trimmed fonts + metric fallbacks, early search class | theme `inc/performance.php`, `tools/minify.mjs`, `tools/subset-fonts.sh`, `tools/build-tokens.mjs` | 7 | ✅ (see `PERFORMANCE.md`) |
| Security hardening: XML-RPC / application passwords off, anonymous REST users off, generic login errors, security headers, uploads (images + PDF), image-metadata stripping, raw HTML for administrators only, file editor off, featured-reporting ownership | `hardening.php` | 8 | ✅ (see `SECURITY.md`) |
| Append-only corrections on every write path; confidential source details internal; http(s)-only links; validated references; site-wide form ceilings; placement window validation | `editorial.php`, `meta.php`, `pages.php`, `security.php`, `placements.php` | 8 | ✅ |
| Theme: recursion guard for content-rendering blocks | theme `inc/performance.php` | 8 | ✅ |

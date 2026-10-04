# TechDoseDaily: Claude Code master implementation prompt (V1)

> Paste this whole file into Claude Code as the first message, from the root of the theme repository.
> Keep a copy at `06-WordPress/TDD-Claude-Code-Master-Prompt.md`.
> Prepared Oct 3, 2026. The design phase is complete and frozen.

---

## 0. Your role and the one rule that matters most

You are implementing **TechDoseDaily** (techdosedaily.com, "TDD"). It is a daily technology news publication covering AI, software, cybersecurity, cloud, developer tools, startups and Big Tech. You are building it as a **custom WordPress block theme plus a small companion plugin** on **Hostinger WordPress hosting**. The newsletter runs on **MailPoet**.

**You are an implementer, not a designer.** Every screen has an approved, frozen design. Your job is to reproduce those designs faithfully and make them work with real WordPress content.

- Do **not** redesign, "improve", modernise, re-space, recolour, add gradients, add shadows to cards, round corners further, swap fonts, add animations, or introduce any component that does not exist in the design system.
- If something in the designs looks wrong, inconsistent or impossible to build, **stop and ask**. Quote the file and the element. Don't decide on your own.
- If WordPress forces a compromise, such as core block markup that can't produce the exact structure, propose the smallest deviation. Show a before/after and wait for approval.
- Never invent editorial content. No fake news, statistics, readership numbers, testimonials, awards, staff, sponsor names or credentials. Sample content stays clearly marked as sample, and nothing sample-only ships to production.

**Source-of-truth order**, highest first:

1. `03-Approved/<screen>-v*/` holds the frozen HTML, PNG and PDF per screen. The self-contained HTML is the exact markup and CSS reference. The PNG is the visual reference for diffing.
2. `01-Design-System/bundle.css` contains every component class, already written and approved.
3. `01-Design-System/tokens.json` and `tokens.css` hold colour, type, spacing, radius, shadow and layout tokens, in light and dark themes.
4. `01-Design-System/components.md` gives the usage rules for all 94 components and templates.
5. `01-Design-System/design-system-README.md` is the brand book: voice, colour, typography, layout, imagery, motion and accessibility rules.
6. This prompt.
7. WordPress conventions. They apply only where items 1–6 are silent.

---

## 1. Project inputs (local folder `D:\TechDoseDaily`)

```
01-Design-System/   bundle.css (~99 KB, all component CSS), tokens.json, tokens.css,
                    components.md, design-system-README.md, brand-notes.md
03-Approved/        homepage-desktop-v1.1, homepage-mobile-v1,
                    article-desktop-v1, article-mobile-v1,
                    category-desktop-v1, category-mobile-v1,
                    search-desktop-v1, search-mobile-v1,
                    author-desktop-v1, author-mobile-v1,
                    static-page-desktop-v1, static-page-mobile-v1, static-page-short-v1,
                    about-desktop-v1, about-mobile-v1,
                    contact-desktop-v1 (+ ContactForm-States), contact-mobile-v1,
                    newsletter-desktop-v1 (+ NewsletterForm-States), newsletter-mobile-v1,
                    404-desktop-v1, 404-mobile-v1
04-Brand/           logo/*.svg (use tdd-logo-a-pulse(-white).svg), favicon/, fonts-notes/*.woff2
                    (Inter 400/500/600/700, Manrope 500/600/700/800)
05-Images/          sample editorial images (design-only; do not ship as content)
06-WordPress/       theme/, blocks/, patterns/, plugins-notes/, deployment/  ← your outputs
08-SEO/             schema/, sitemap/, taxonomy/, editorial-policies/  ← write your SEO docs here
```

The live design system (component previews) is a Claude Design System artifact. You don't need it; everything required is in the files above.

**Before writing any code**, read all of `01-Design-System/` and open every `03-Approved/*/…html` file. Then reply with what you found (section 14, Phase 0).

---

## 2. Fixed decisions

| Topic | Decision |
|---|---|
| Hosting | Hostinger WordPress hosting (LiteSpeed server). Confirm the plan's staging, SSH, Git and daily-backup features with the owner before Phase 12. |
| Theme type | **Custom block theme** `techdosedaily` (FSE): `theme.json`, HTML block templates, template parts, locked patterns. No parent theme, no page builder. |
| Functionality | Companion plugin **`tdd-core`**: custom blocks, post/user meta, taxonomies, JSON-LD schema, news sitemap, REST endpoints (newsletter, contact), search tweaks. The theme holds presentation only, so switching theme never loses data or schema. |
| Newsletter | **MailPoet** (self-hosted in WordPress). Our own form markup, with MailPoet used through its PHP API. Don't use MailPoet's form builder on the front end. |
| CSS | Port `bundle.css` **verbatim** (same class names), split per template. Don't rewrite it in Tailwind or SCSS, and don't rename classes. |
| JS | Vanilla JS, no jQuery on the front end, under 20 KB gzipped total. |
| Editor | Gutenberg. Editors build stories from core blocks plus TDD blocks, and layouts are locked where the design is fixed. |

---

## 3. Theme and plugin structure

```
wp-content/themes/techdosedaily/
  style.css                  (theme header only)
  theme.json                 (v3; tokens → presets; see §4)
  functions.php              (enqueue, supports, image sizes, block styles; no business logic)
  assets/css/
    tokens.css               (copied from 01-Design-System; light + dark custom properties)
    base.css                 (resets, .tdd root, typography, buttons, inputs, labels)  ┐
    layout-header-footer.css                                                       │ split from
    home.css  article.css  archive.css  author.css  search.css                     │ bundle.css,
    static.css  forms.css  newsletter.css  notfound.css                            ┘ unchanged rules
  assets/js/  toc.js (active section), search-clear.js, forms.js, menu.js (see §6)
  assets/fonts/  Inter-*.woff2, Manrope-*.woff2  (self-hosted, latin subset)
  assets/brand/  tdd-logo-a-pulse.svg, tdd-logo-a-pulse-white.svg, tdd-mark.svg, favicon set
  templates/   index.html front-page.html single.html category.html tag.html author.html
               search.html page.html page-short.html page-about.html page-contact.html
               page-newsletter.html 404.html archive.html
  parts/       header.html footer.html
  patterns/    (PHP pattern files; see §5)
  styles/      (none: there is one approved style only)

wp-content/plugins/tdd-core/
  tdd-core.php
  inc/ meta.php taxonomies.php schema.php news-sitemap.php search.php rest-newsletter.php
       rest-contact.php reading-time.php breaking.php roles.php security.php
  blocks/ (block.json + render.php per dynamic block; see §5)
  src/ (editor JS: sidebar panels for story meta; built with @wordpress/scripts)
```

The **mobile rules** in `bundle.css` are scoped under `.tdd--m …` because the designs were rendered as fixed 390px frames. A build script (`tools/split-css.mjs`) must rewrite every `.tdd--m X` selector to `X` inside `@media (max-width: 767px)` and drop the `.tdd--m` wrapper. Commit both the script and its output. Add no other mobile CSS.

---

## 4. Design tokens → `theme.json`

Generate `theme.json` from `tokens.json` with a script (`tools/tokens-to-theme-json.mjs`) so the two never drift.

- **Colour:** `settings.color.palette` holds every token (bg, surface, surface-warm, text, text-secondary, text-muted, text-subtle, primary, primary-hover, primary-soft, ai-wash, secondary, secondary-soft, border, border-subtle, border-strong, inverse*, breaking, analysis, explainer, guide, review, review-ink, correction). Slugs equal token names, and values are `var(--token)` so dark mode works via `tokens.css`. Disable custom colours, gradients, duotone and default palettes.
- **Dark mode:** `tokens.css` already defines the dark values. Support `prefers-color-scheme` plus a `data-theme` attribute, with no toggle UI in V1 unless asked.
- **Typography:** two families only, loaded with `fontFace` from local woff2. Manrope (`--font-display`, 500–800) is for headlines, nav and buttons. Inter (`--font-body`, 400–700) is for everything else. Font sizes come from the tokens: display 50, h1 48, h2 32, h3 24, section-heading 28, card-large 28, card-medium 20, card-small 17, article-body 18, summary 16, ui 15, metadata 13, label 11. Turn off custom font sizes and fluid typography; template-specific sizes such as article H1 46/32 are in the CSS.
- **Spacing:** `spacing.spacingSizes` uses the `space-4` … `space-128` scale (8px base). Turn off custom spacing.
- **Layout:** `contentSize` is 740px and `wideSize` is 1280px. The site max of 1360 and wide media of 960 are custom properties.
- **Radii and shadow:** `custom.radius.{sm 4, md 6, card 8, lg 10}` and `custom.shadow.overlay`. Cards never get shadows.
- `appearanceTools: false`. Lock down block supports so editors cannot add arbitrary colours, borders or typography.

---

## 5. Approved design → WordPress mapping

### 5.1 Templates

| Approved design | Template | Notes |
|---|---|---|
| homepage-desktop-v1.1 / homepage-mobile-v1 | `front-page.html` | Composed of locked patterns, with queries driven by blocks (§5.3). Order: header → featured story → Latest + Most Read rail → AI News Today band (`ai-wash`) → Editor's Picks → Cybersecurity · Developer · Startups row → Practical Guides → Daily Tech Brief → newsletter CTA → footer. |
| article-desktop-v1 / article-mobile-v1 | `single.html` | 168 rail (TOC + share) · 740 reading column · 292 sidebar. H1 46/32, body 18/1.7 and 17/1.68. Mobile: collapsible TOC, full-bleed figures, comparison tables become stacked rows. |
| category-desktop-v1 / category-mobile-v1 | `category.html` (all sections) | Masthead + topic nav → editor-pinned feature + 3 → date-grouped feed → Load more plus real paginated URLs → sidebar (Most Read, topics, desk) → Analysis & Explainers → Guides. `tag.html` reuses the feed layout without the pinned module. |
| search-desktop-v1 / search-mobile-v1 | `search.html` | SearchField, filters (section, format, date), ResultRow with `<mark>`, topic match, SearchEmpty. `noindex`. |
| author-desktop-v1 / author-mobile-v1 | `author.html` | AuthorMasthead, AuthorMeta, beats, social links, featured story, archive with pagination, AuthorEmptyState. URL `/author/{slug}/`. |
| static-page-desktop-v1 / mobile-v1 | `page.html` (default) | 168 sticky TOC rail + 740 column. The TOC is built from the H2s. Used for Editorial Standards, Corrections, Source Policy, AI Use, Privacy, Terms. |
| static-page-short-v1 | `page-short.html` | Single centred 740 column, no rail. Used for Advertise and short pages. |
| about-desktop-v1 / about-mobile-v1 | `page-about.html` | Static shell + AboutMission, CoverageList, EditorialPrinciples, TeamCard (from real users only), FundingDisclosure, ContactRoute (compact), RelatedPolicyLinks, newsletter. |
| contact-desktop-v1 / mobile-v1 (+ form states) | `page-contact.html` | 740 main column + 300 sticky aside: ContactRoute ×5, ContactForm, SecureTipNotice, response expectations. |
| newsletter-desktop-v1 / mobile-v1 (+ form states) | `page-newsletter.html` | NewsletterHero + Signup, Benefits, SampleIssue, Promise, recent issues, FAQ, repeat signup. Canonical `/newsletter/`. |
| 404-desktop-v1 / 404-mobile-v1 | `404.html` | NotFoundMessage, NotFoundSearch, actions, NotFoundLatest (5/4 rows), NotFoundSections. Real HTTP 404 + noindex. |
| — | `index.html`, `archive.html` | Fallbacks that reuse the category feed layout. Disable date archives (redirect to home) and attachment pages. |

### 5.2 Template parts

- `header.html` is the 68px desktop header (logo A "Pulse lockup", 9-section nav at 14px/600, search icon, Subscribe). At 767px and below it becomes the 56px mobile header (logo · search · menu). TrendingBar shows on the homepage only, and only if it has items.
- `footer.html` is the dark `inverse` footer with Categories, Company, Policies, Newsletter and Follow columns, and the bottom row. Social links are text, and only accounts that exist are shown.

### 5.3 Blocks (in `tdd-core`, dynamic, server-rendered, each with `block.json`)

Every block outputs the **exact markup** of its component in the approved HTML, using the same class names. Editor previews use `ServerSideRender` or a faithful static preview.

| Block | Component(s) | Data |
|---|---|---|
| `tdd/story-card` (variants: feature, large, medium, compact) | StoryCard | post, primary category label, format label, meta |
| `tdd/latest-feed` | LatestUpdateRow, FreshnessLine | latest posts with time |
| `tdd/most-read` | MostReadRow | top posts by views (see §11), or an editor-curated fallback |
| `tdd/category-block` (--alert, --feature, --list) | CategoryBlock | posts in category |
| `tdd/ai-band` | AI News Today | AI category, `ai-wash` background |
| `tdd/daily-brief` | DailyTechBrief | the latest newsletter issue (MailPoet archive or `brief` post) |
| `tdd/newsletter-cta`, `tdd/newsletter-signup` | NewsletterCTA (dark), NewsletterSignup + states | REST `tdd/v1/subscribe` |
| `tdd/key-takeaways`, `tdd/why-this-matters` | KeyTakeaways, WhyThisMatters | inner blocks (list / paragraph) |
| `tdd/notice` (update · correction) | Notices | timestamp + text; also writes to post meta for schema |
| `tdd/source-list` | SourceList | structured sources meta (§7) |
| `tdd/data-table` | DataTable (+ mobile stacked rows) | title, subtitle, rows, note, vendor-reported flag |
| `tdd/toc` | TableOfContents / StaticPageTOC | auto from H2 anchors |
| `tdd/author-card`, `tdd/related-coverage`, `tdd/share` | AuthorCard, RelatedCoverage, ShareControls | post author/meta |
| `tdd/category-masthead`, `tdd/topic-nav`, `tdd/feed-date-group`, `tdd/pagination`, `tdd/topic-rail`, `tdd/analysis-item` | Category components | term + query |
| `tdd/search-*` | SearchField, SearchFilters, ResultRow, SearchEmpty | main query |
| `tdd/author-*` | AuthorMasthead, AuthorMeta, AuthorBeatChip, AuthorSocialLinks, FeaturedAuthorStory, AuthorArticleRow, AuthorArchive, AuthorEmptyState | user meta |
| `tdd/policy-callout`, `tdd/policy-notice`, `tdd/policy-table`, `tdd/related-policies`, `tdd/contact-cta` | static page components | inner blocks / attributes |
| `tdd/coverage-list`, `tdd/principles`, `tdd/team`, `tdd/funding`, `tdd/contact-routes`, `tdd/contact-form`, `tdd/secure-tip` | About / Contact | attributes + users with `tdd_show_on_about` |
| `tdd/nl-hero`, `tdd/nl-benefits`, `tdd/nl-sample-issue`, `tdd/nl-promise`, `tdd/nl-faq` | Newsletter | attributes |
| `tdd/notfound-*` | 404 components | latest posts, sections |

Core blocks get **block styles** instead of new blocks where possible: `core/quote` → PullQuote (`tdd-quote`), `core/table` → `tdd-table`, `core/list` → `tdd-list`, `core/details` → FAQ, `core/buttons` → `tdd-btn` variants, `core/image` → `tdd-figure` with caption and credit.

Patterns: one **locked** pattern per template section (for example `tdd/home-hero`, `tdd/home-latest-rail`, `tdd/about-page`, `tdd/policy-page-skeleton`). Editors can change content but not layout.

### 5.4 Design invariants: check these numbers on every screen

Container 1360 site / 1280 section / 740 content. Header 68 (mobile 56), nav 14px/600. Homepage feature headline 50px/1.06/800. Article H1 46 (mobile 32) with body 18/1.7 (mobile 17/1.68), and paragraphs 1.35em apart. Static H1 44 (mobile 32), H2 28 (24), H3 22 (20). The background is `#FAFAF8`, never pure white. TechDose Blue `#2563EB` is the only strong accent. Radii are 4/6/8/10 (max 12). Focus ring is 2px `primary` with a 2px offset. Touch targets are at least 44×44. Mobile has 20px side padding and no horizontal scroll from 360 to 430.

---

## 6. Responsive behaviour

- The approved frames are 1440 and 390. Check every template at **360, 390, 430, 768, 1024, 1280, 1440 and 1920**.
- 768–1023 (tablet) uses an 8-column grid. Article: hide the left rail (TOC becomes the collapsible mobile version), and the sidebar drops below the body. Homepage and category: two-column feature plus supporting stories, with the rail below. Static: no rail; use the mobile collapsible TOC.
- From 1024 up, use the desktop layouts. From 1360 up, the content stays centred at max width.
- **Mobile menu drawer:** out of V1 design scope. Build a minimal accessible version using only existing styles: the full-height `bg` panel, the 9 sections as 48px rows, Search, Subscribe, and policy links in the footer style. Get it approved before polishing. Requirements: focus trap, Esc to close, `aria-expanded`, and body scroll lock.

---

## 7. Content model

**Sections** are WordPress categories: AI, Tech, Software, Cybersecurity, Startups, Big Tech, Cloud, Developer, Guides. Each story has **one primary category**, stored in `tdd_primary_category` and used for the label, breadcrumbs, URL and schema.

**Topics** are tags, curated rather than free-for-all, for example AI Models, AI Agents, AI Coding, AI Infrastructure, Enterprise AI. Topic pages are `noindex` until they have 5 or more stories.

**Format** is a custom taxonomy, `tdd_format`, with the values News (default), Analysis, Explainer, Guide, Review and Sponsored. It drives the coloured labels; colour appears only in labels.

**Post meta** (`register_post_meta`, `show_in_rest`, edited in a "Story details" sidebar panel):

| Key | Purpose |
|---|---|
| `tdd_dek` | Summary/deck (used as meta description and in cards) |
| `tdd_primary_category` | term id |
| `tdd_updated_at`, `tdd_update_note` | Substantive update timestamp and note; drives "Updated" + `dateModified` |
| `tdd_corrections` | array {time, text}; renders the correction notice and the Corrections log |
| `tdd_sources` | array {title, url, type: primary/interview/supporting/public-record/confidential, publisher, date, note} |
| `tdd_vendor_reported` | bool; adds the "vendor-reported" note where figures appear |
| `tdd_breaking` + `tdd_breaking_until` | Breaking label, editor-only, auto-expires (default 6 h). Never a default. |
| `tdd_editor_pick`, `tdd_home_slot` | Homepage curation (feature, picks) |
| `tdd_image_credit`, `tdd_image_source_type` | official / photo / licensed / screenshot / graphic / ai-generated (AI images are labelled on the page) |
| `tdd_ai_assist` | Optional disclosure text when AI-generated material appears |
| `tdd_sponsored_by` | Sponsor name; forces the Sponsored format + label + `rel="sponsored"` |
| `tdd_reading_time` | Computed on save (≈230 wpm) |

**Author (user) meta:** `tdd_title`, `tdd_beats` (term ids), `tdd_location`, `tdd_covering_since` (only if true), `tdd_short_bio`, `tdd_note`, `tdd_photo` (attachment id; show the initials placeholder until a real photo exists), `tdd_social` (LinkedIn, X, …, only accounts that exist), `tdd_public_email` (opt-in), `tdd_show_on_about`, `tdd_about_order`.

Comments are **off** site-wide in V1.

---

## 8. Structured data (JSON-LD, output by `tdd-core` as one `@graph` per page)

Turn off the SEO plugin's own schema (Yoast: `add_filter('wpseo_json_ld_output','__return_false')`) so there is exactly one graph. Keep the IDs stable: `https://techdosedaily.com/#organization`, `#website`, `{url}#webpage`, `{url}#article`, `{author-url}#person`.

- **Every page:** `Organization` (NewsMediaOrganization) with name "Tech Dose Daily", url, logo (ImageObject, at least 112×112), `sameAs` (real accounts only), `publishingPrinciples` (Editorial Standards URL), `correctionsPolicy`, `ethicsPolicy`, `actionableFeedbackPolicy`, `masthead` (About URL), `verificationFactCheckingPolicy` (Source Policy). Also `WebSite` with `SearchAction` (`/?s={search_term_string}`).
- **Article (`single`):** `NewsArticle` with headline (110 characters or fewer), description (dek), `image` (≥1200px wide; supply 16:9, 4:3 and 1:1 crops), `datePublished`, `dateModified` (substantive updates only), `author` → Person (`url` = author page), `publisher` → `#organization`, `mainEntityOfPage`, `articleSection` (primary category), `keywords` (topics), `isAccessibleForFree: true`. Plus `BreadcrumbList` (Home › Section › Headline). Use `AnalysisNewsArticle`, `ReportageNewsArticle` or `ReviewNewsArticle` only if that format is truly set.
- **Author page:** `ProfilePage` with `mainEntity` Person: name, jobTitle, image, description, `sameAs` (real), `knowsAbout` (beats), `worksFor` → `#organization`. Never invent credentials.
- **Category/tag:** `CollectionPage` + `BreadcrumbList`.
- **About:** `AboutPage` + Organization + BreadcrumbList. **Contact:** `ContactPage` + Organization with `contactPoint` (contactType editorial / corrections / advertising), using real role emails only once they exist. **Newsletter:** `WebPage` + BreadcrumbList. **Policy pages:** `WebPage` + BreadcrumbList. **Search:** none (noindex). **404:** none apart from Organization/WebSite if they are global.
- Never add Article or NewsArticle schema to static pages, the newsletter, the 404 or search.

Validate every template with the Rich Results Test and the Schema.org validator. Save the sample outputs to `08-SEO/schema/`.

---

## 9. SEO, Google News and Discover

- **SEO plugin:** Yoast SEO (free) for titles, meta descriptions, canonicals, XML sitemaps, robots and Open Graph. Turn off Yoast schema (§8) and Yoast breadcrumbs, because we render our own.
- **Titles:** `{Headline} | Tech Dose Daily`. Sections: `{Section} news | Tech Dose Daily`. Meta description = dek.
- **Robots:** global `max-image-preview:large`. `noindex` on search results, the 404, thin topic pages, paginated archives beyond page 1 (keep `follow`), and attachment pages (disabled).
- **Canonicals:** self-referencing. Paginated archives canonicalise to themselves, not page 1. The 404 has **no canonical**.
- **News sitemap:** `tdd-core` serves `/news-sitemap.xml` with articles from the last 48 hours (max 1,000), publication name and language. Reference it in `robots.txt` and Search Console.
- **Feeds:** the main RSS feed plus per-section feeds, with excerpt and full-size image, linked from section pages.
- **Google News / Discover readiness:** bylines linking to real author pages; visible published and updated dates; About, Contact, Editorial Standards, Corrections and Source pages (already designed); large hero images (1200px or wider) with no text baked in; no clickbait or fake urgency; Breaking used only for real breaking news; corrections are transparent. Register in Google Publisher Center and Search Console, plus Bing Webmaster Tools.
- **Internal linking:** the breadcrumb, related coverage (same topic first) and section links from every story.

---

## 10. Forms

### 10.1 Newsletter (MailPoet)

- Our markup (`NewsletterSignup`) posts to `POST /wp-json/tdd/v1/subscribe` with email, list key, nonce, honeypot and a time-trap. That endpoint calls `\MailPoet\API\API::MP('v1')->addSubscriber()` and `subscribeToList()`.
- Map responses to the approved states in `03-Approved/newsletter-desktop-v1/TechDoseDaily-NewsletterForm-States`: **invalid email** (field error, `aria-invalid`, message linked via `aria-describedby`, focus back to the field); **already subscribed** (neutral `role="status"` note + Manage subscription link); **submitting** (disabled button, "Subscribing…"); **success** (panel replaces the form, `role="status"`).
- **Open decision, ask the owner:** MailPoet's sign-up confirmation (double opt-in). If it's on, the success copy must change to a "check your inbox" message, which needs a design-approved variant. Don't silently change copy.
- **Sending:** decide between MailPoet Sending Service and SMTP. Check MailPoet's current free-plan subscriber limit and Hostinger's outbound email limits before launch. Set up SPF, DKIM and DMARC for the sending domain.
- **Email template:** build the Daily Tech Brief email in MailPoet to match `NewsletterSampleIssue` (masthead, numbered items with labels, "Why it matters", "Try this", footer with sponsorship labelling, view in browser, unsubscribe). Use system fonts in the email, with the same colours.
- No exact send time is promised anywhere until one is configured.

### 10.2 Contact

- Our markup (`ContactForm`) posts to `POST /wp-json/tdd/v1/contact` with name, email, topic (editorial / correction / tip / partnership / general), optional article URL, message and the not-for-urgent-security-reports checkbox. Add nonce, honeypot, time-trap and per-IP rate limiting. Cloudflare Turnstile is optional and needs owner approval for privacy reasons.
- Store each submission as a private CPT `tdd_message` (status, topic, assigned to) **and** email the role inbox for that topic. Never show personal staff emails.
- Server and client validation produce the approved states in `03-Approved/contact-desktop-v1/TechDoseDaily-ContactForm-States`: error summary (`role="alert"`, focus moves to it, links to fields), field errors, submitting and success.
- `?topic=correction` preselects the topic, and the ContactRoute links use it.
- Retention: delete messages after 12 months unless flagged. Don't log IPs beyond rate-limit windows.
- The secure-tip channel is **not configured**. Show the placeholder exactly as designed.

---

## 11. Performance and Core Web Vitals

**Targets** at p75 on mobile: LCP 2.5s or less, INP 200ms or less, CLS 0.1 or less. Lighthouse mobile scores of 90+ for performance, accessibility, best practices and SEO on the article, homepage and category templates.

- **CSS:** load `tokens.css` + `base.css` + `layout-header-footer.css` on every page, plus one template stylesheet. Inline critical CSS for above-the-fold header + hero (LiteSpeed CCSS or a build step). Remove unused WordPress core block CSS (`should_load_separate_core_block_assets`).
- **Fonts:** self-hosted woff2, latin subset, `font-display: swap`. Preload Manrope 800 and Inter 400 only. Keep to the 8 weights listed.
- **Images:** WebP (AVIF if the host supports it). Registered sizes: `tdd-16x9-1800` (1800×1013), `1200` (1200×675), `800` (800×450), `400` (400×225); `tdd-4x3` (480×360); `tdd-1x1` (400×400, author); `tdd-social` (1200×630). Always include `width`/`height`, `srcset` and `sizes`. The LCP image gets `fetchpriority="high"` and no lazy-loading; everything else lazy-loads.
- **JS:** no jQuery and no front-end frameworks. Defer everything. The TOC uses IntersectionObserver.
- **Caching:** LiteSpeed Cache for page cache, browser cache and object cache if available. Exclude the `tdd/v1/*` REST routes and any page with a nonce. Purge the post, home, section and author pages on publish/update. Use Hostinger's CDN if the plan includes it.
- **No** sliders, autoplay video, parallax, scroll-triggered effects, tickers or third-party social embeds without consent.
- Most Read: count views with a lightweight beacon to a REST endpoint, cached and aggregated hourly. Fall back to editor-curated if analytics isn't available.

---

## 12. Accessibility (WCAG 2.2 AA)

- Semantic landmarks (`header`, `nav`, `main`, `aside`, `footer`), one `h1` per page, logical heading order, and a "Skip to content" link.
- Every interactive element has the visible focus ring from the tokens. Keyboard access for the nav, TOC, collapsible `details`, menu drawer, filters and forms.
- Every form field has a visible label (never placeholder-only). Errors are announced and linked. Success is announced with `role="status"`.
- Text contrast: all text tokens meet 4.5:1 on `bg`/`surface` in both themes, except `text-subtle`, which is never used for meaningful text.
- Images: meaningful alt text from the editor; decorative images get empty alt. Author photo placeholders get `role="img"` with a label.
- Tables keep real `<table>` markup on desktop with `scope`. Mobile stacked rows use the `role="table"` / `row` pattern exactly as in the approved HTML.
- Respect `prefers-reduced-motion`. Touch targets are 44px or larger.
- Automate axe-core checks on every template in CI. A manual keyboard and screen-reader pass (NVDA + VoiceOver) is required before launch.

---

## 13. Security, privacy, plugins, analytics

**Security**
- `DISALLOW_FILE_EDIT`, XML-RPC off, REST user enumeration blocked for anonymous users, and author slugs that never expose logins.
- Enforce 2FA for Administrators and Editors. Use least-privilege roles: Administrator (owner), Editor (standards/desk editors), Author, Contributor (freelancers).
- Sanitise every input and escape every output (WordPress coding standards). Nonces on all forms. Capability checks on all REST writes.
- Security headers: HSTS, `X-Content-Type-Options`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, and a CSP in report-only mode first.
- Auto-update minor core and security releases; stage plugin updates. Daily backups (Hostinger), plus a weekly off-site export. Keep no secrets in the repo; use `wp-config` constants and Hostinger environment settings.

**Privacy:** a cookie/consent banner only if non-essential cookies are used. Follow India's DPDP Act and GDPR basics: minimal data, a clear Privacy Policy, and working unsubscribe and delete-my-data paths.

**Plugin allow-list** (anything else needs owner approval):
- Yoast SEO (free)
- MailPoet
- LiteSpeed Cache
- Redirection
- WP Mail SMTP (only if MailPoet isn't handling transactional mail)
- A security plugin only if Hostinger's tools are insufficient (Wordfence free)

Forbidden: page builders, multipurpose themes, slider, social-share and "related posts" plugins (we have our own), anything that loads jQuery or third-party trackers on the front end.

**Analytics** (ask the owner which one): either a privacy-friendly cookieless tool, or GA4 with Consent Mode v2 and the banner. Track only `newsletter_subscribe`, `contact_submit`, `search` (term length only, never the full query), and outbound clicks on sources. Use Search Console for search data.

---

## 14. Phased plan with checkpoints

Work phase by phase. At the end of **every phase**, stop. Report what you built and attach screenshots at 390 and 1440 next to the matching `03-Approved` PNG, plus any test results. Wait for "approved" before moving on.

| Phase | Scope | Acceptance |
|---|---|---|
| **0. Audit** | Read everything in §1. Reply with: (a) a component inventory mapped to blocks/patterns, (b) any gaps or conflicts found in the designs, (c) questions for the owner, (d) your local dev setup. **No code.** | Owner approves the inventory and answers the questions |
| 1. Scaffolding | Repo, `wp-env` or LocalWP, theme + plugin skeletons, the tokens→`theme.json` script, CSS split script (incl. `.tdd--m` → media query), fonts, logo, favicon, CI (PHPCS-WordPress, ESLint, Stylelint) | Builds clean; `theme.json` validates |
| 2. Global | Header (desktop + mobile), footer, base typography, buttons, inputs, labels, breadcrumbs, menu drawer (minimal) | Visual diff vs approved header/footer ≤ 1% at 1440 and 390 |
| 3. Article | `single.html`, all article blocks, meta sidebar, sources, notices, data table, TOC, NewsArticle schema | Diff vs article-desktop-v1 / mobile-v1; Rich Results pass; axe 0 serious |
| 4. Homepage | `front-page.html`, curation meta, homepage blocks | Diff vs homepage v1.1 / mobile v1 |
| 5. Category/Tag | Masthead, topic nav, pinned feature, date-grouped feed, pagination + Load more (progressive enhancement over real URLs) | Diff vs category v1 |
| 6. Author | Author template, user meta, ProfilePage schema | Diff vs author v1 |
| 7. Search | Filters (section, format, date), highlighting, empty state, noindex | Diff vs search v1 |
| 8. Static pages | page / page-short / About / Contact / Newsletter / 404 templates + blocks | Diff vs each approved screen |
| 9. Forms | Newsletter + contact endpoints, states, MailPoet wiring, email template | All states reproduced; spam tests pass |
| 10. SEO | Yoast config, schema graph, news sitemap, robots, feeds, redirects | Validators clean; sitemap valid |
| 11. Hardening | Performance budget, accessibility pass, security headers, dark mode check | Lighthouse ≥ 90 ×4; CWV lab targets met |
| 12. Staging → launch | Hostinger staging, content load, editor training notes, launch checklist | Owner sign-off |

**Visual regression:** use Playwright screenshots of each template, seeded with the approved sample content, at 1440×900 and 390×844, compared with the `03-Approved` PNGs (pixelmatch, threshold 0.1). Investigate any region over 1%. Keep the sample-content seed for testing only, never in production.

---

## 15. Repository, environments, deployment

- **Git** repository (GitHub, private) containing only `wp-content/themes/techdosedaily`, `wp-content/plugins/tdd-core`, `tools/`, `tests/` and docs. No WordPress core, uploads or other vendors' plugins.
- **Local:** `@wordpress/env` (Docker) or LocalWP, with a seed script for sample content (marked sample).
- **CI** (GitHub Actions): lint → build → PHPUnit (tdd-core) → Playwright visual + axe → Lighthouse CI (budgets) → build zip artefacts.
- **Staging:** a Hostinger staging copy. Deploy code via Hostinger's Git deployment, or a GitHub Action over SSH (rsync of the theme and plugin folders only). Confirm the plan supports it.
- **Production:** code-only deploys after staging sign-off. **Never push a staging database to production after launch**, because content lives in production. Take a backup before every deploy and keep a rollback zip of the previous release.
- **Versioning:** semver tags; `CHANGELOG.md`; the theme version bumps on every deploy for cache-busting.

---

## 16. Do / don't summary

**Do:** reproduce the approved markup and classes exactly; keep the theme presentational and the plugin functional; build mobile-first from the approved 390 frames; ask when unsure; keep pages light; show sources, dates and bylines everywhere; label sponsored and AI-generated material.

**Don't:** redesign; add components, colours, gradients, shadows or animations; use page builders or jQuery; invent content, numbers, people or credentials; add Article schema to non-articles; canonicalise the 404; promise send times or response SLAs; expose personal emails; ship sample content.

---

## 17. Open decisions for the owner (ask during Phase 0)

1. Hostinger plan: does it include staging, SSH, Git deploy and CDN?
2. MailPoet sign-up confirmation (double opt-in): on or off? Which sending service?
3. Analytics: cookieless or GA4 with consent?
4. Real editors and authors for the About page and author pages, with photos, titles and real social accounts.
5. Role inbox addresses (editorial@, corrections@, tips@, partnerships@) and whether they exist yet.
6. Newsletter send schedule (if fixed) and the archive approach (MailPoet archive or a `brief` post per issue).
7. Which sections get the AI News Today band and homepage category row, and whether that changes over time.
8. Cloudflare Turnstile for forms: yes or no.

---

## 18. Your first reply

Start **Phase 0** only. Read every file listed in §1, then reply with:

1. A table mapping every component in `components.md` to a block, block style, pattern or template part.
2. Design gaps, conflicts or ambiguities you found, each with the file and element.
3. Your answers needed from §17, phrased as short questions.
4. Your proposed local setup and repo layout.
5. A list of anything in this prompt you believe is technically wrong for a block theme on Hostinger, with your reasoning.

Write no production code until the owner approves Phase 0. Also create a `CLAUDE.md` in the repo that condenses §0, §2, §5.4 and §16, so these rules persist across sessions.

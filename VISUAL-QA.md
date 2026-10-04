# Visual QA

Each implemented screen is checked against its reference in `../03-Approved` at the same width. Desktop is compared at 1440×900 and mobile at 390×844, with a pixel diff (threshold: channel difference > 40) plus a side-by-side review. Responsive checks run at 360, 390, 430, 768, 1024, 1280, 1440 and 1920.

Legend: **Pass** · **Needs adjustment** · **Known intentional difference**

## Phase 1: global shell (header, footer, grid)

Tested on local WordPress 7.1.2 / PHP 8.3 with the Phase 1 menu fixture.

| Screen / element | Width | Result | Notes |
|---|---|---|---|
| Desktop header | 1440 | **Pass** | 0.000% pixel difference against the approved header. Header height 69 (68 + 1px rule), as approved. |
| Desktop header | 1280, 1920 | **Pass** | Nav fits at 1280; centred 1280 content at 1920. |
| Mobile header | 360, 390, 430 | **Pass** | 56px including the rule, logo 24px, 44px icon targets. |
| Mobile header search icon | 390 | **Known intentional difference (approved normalisation)** | Every V1 template uses **Logo · Search · Menu**. A few approved mobile mock-ups (static, About, Contact, Newsletter) omit Search; that's treated as a reference inconsistency, not page-specific behaviour. Search is a primary navigation tool for the archive. |
| Header 768–1199 | 768, 1024 | **Known intentional difference (approved)** | Logo · Search · Subscribe · Menu; the sections live in the drawer. From 1200px the full nav shows **only if it fits** (content-fit check in `header.js`); otherwise it stays collapsed. Verified: at 1200 the nav doesn't fit and collapses; from 1280 it shows. |
| Compact sticky header | ≥1200 | **Pass** | Compacts to the `header-sticky` token (60px + 1px rule = 61) after 120px of scroll; verified on a long page at 1440. |
| Desktop footer | 1440 | **Pass** | Identical to the approved footer (same menu data). |
| Mobile footer | 390 | **Pass** | Same structure. Link lists come from the same menus as desktop, so the Sections and Follow lists contain the menu data rather than the reduced sample lists in the reference. |
| Footer 768–1023 | 768 | **Known intentional difference** | No tablet design exists. Brand row spans full width, link columns go into a 3-column grid. |
| Footer variants | all | **Known intentional difference** | The approved desktop and mobile footers have different structures, so both render from the same menus and CSS shows one (`display:none` on the other, so assistive technology sees only one). |
| Menu drawer | 390, 1024 | **Approved approach** | Full screen on phones, 400px side panel from 768. Contents: 9 sections, then Latest / Daily Tech Brief / Newsletter, then About. No Search (it's in the header). Re-verified after the change: focus trap, Esc closes, focus returns to the trigger, scroll lock released, `aria-expanded`. Daily Tech Brief appears once that page exists. |
| Horizontal overflow | 360–1920 | **Pass** | `scrollWidth` equals viewport width at all 8 widths. |
| Skip link | all | **Pass** | First focusable element; visible on focus. |

### Phase 1 regression (after moving the data model into TechDoseDaily Core)

| Check | Result |
|---|---|
| Desktop header vs approved, 1440 | **Pass**: 0 px different |
| Desktop footer vs approved, 1440 | **Pass**: identical layout; a 1px vertical offset from page height shows up as 1.1% diff pixels, no visible change |
| Header height | 56 (≤767), 69 (≥768) at all 9 widths |
| Horizontal overflow | None at 360, 390, 430, 768, 1024, 1200, 1280, 1440, 1920 |
| Light mode only | `tokens.css` has no dark block; logo prints the light file only (no hidden dark image download) |

## Phase 2: editorial components

Rendered with local fixture data on the "Phase 2 components" page at 1440, 768, 390 and 360 (no overflow at any width). They were compared with the design-system component previews and the approved homepage references (desktop v1.1, mobile v1).

| Component | Result | Notes |
|---|---|---|
| Labels | **Pass** | One label is a bare span; several get `.tdd-labels` (as approved). Breaking first, then section, then story type (News has no type label). |
| StoryCard: feature | **Pass** | Desktop: text stack then image (approved homepage lead). Mobile: approved `.m-lead` values (32px headline). |
| StoryCard: large / medium | **Pass** | |
| StoryCard: compact + thumb, stacked | **Pass** | Desktop `.tdd-stack`; mobile approved `.m-list` spacing and 96px thumbs. |
| Meta line | **Pass** | "By Name · date · N min read"; compact shows "34 min ago", "3h ago", then a date. |
| SectionHeading | **Pass** | Default / small, description or freshness line, "View all →". |
| Latest Updates | **Pass** | Rows with an image, a deck and 4+ min reading time get the thumbnail row; quick updates are text-only (data-driven rule). Mobile: approved 64px time column, no deck, "10:42" time, "All latest updates" button below the list. |
| Most Read | **Pass** | Real view counts only (fixture uses synthetic counts). Hidden when fewer than 3 stories have data. |
| Daily Tech Brief | **Pass / known difference** | "Edition No." isn't shown (no real issue counter yet). "Read today's brief →" only when a URL is configured. Mobile full-bleed treatment is applied by the homepage template in Phase 3. |
| Newsletter CTA | **Pass** | Approved navy panel and form. |
| Newsletter CTA states | **Pass** (approved after Phase 2) | Navy panel stays stable; errors red, success calm, already-subscribed neutral. Production uses MailPoet double opt-in: the pre-confirmation state is **“Check your inbox” / “We sent a confirmation link to your email address. Confirm it to start receiving Tech Dose Daily.”** The single opt-in “You’re subscribed” state stays available behind the provider abstraction. |
| Trending bar | **Pass** | Desktop separators and date; mobile approved strip (no separators or date, 44px row). |
| Breadcrumbs | **Pass** | Home › Section › Story type; Home › Section; page ancestors; Home › Authors › Name. |
| Pagination | **Pass** | Real `/page/N/` links (Newer · 1 2 3 … N · Older), note "Page 2 of 8 · 16 stories". Load more is progressive (hidden without JS) and is verified with the archive feeds in Phase 3. |

## Phase 3: templates

Method: each template rendered with local fixtures (`tests/fixtures/phase3-*.php`, illustrative content mirroring the approved samples, marked [Sample]) and compared side by side with the frozen references at 1440 (desktop PNG, minus its 28px note bar) and 390 (mobile PNG at 2x, scaled 50%). Every template was also checked at 360, 390, 430, 768, 1024, 1280, 1440 and 1920 for horizontal overflow, one visible H1, duplicate IDs, images without `alt`, PHP notices (WP_DEBUG log) and JS errors: **no failures** (12 URLs × 8 widths). Differences that come only from fixture data (fewer stories, different sample images or headline lengths) are not listed.

### 3A Article (`single.html`) — references: Article-Desktop-1440, Article-Mobile-390

| Area | Result | Notes |
|---|---|---|
| Header, trending, breadcrumbs, labels, H1 46/32, deck 21/18 | **Pass** | Trending bar without date, as in the article reference. |
| Byline (avatar, author + title, editor, How we report) | **Pass** | Editor and "How we report" only when an editor is set / the Editorial Standards page exists. Photo when a real portrait is set, else initials. |
| Times (Published · Updated · read time) | **Pass** | Updated only for a recorded substantive update; same-day update shows time only. |
| Share controls | **Known intentional difference** | "Save for later" is not shipped (V1 has no reader accounts). Copy link and Share are revealed only where the browser supports Clipboard / Web Share; Email is a plain link and always works. |
| Hero figure 960, caption + credit | **Pass** | Caption from the image caption, credit from the attachment (`tdd_credit`). Full-bleed on phones. |
| Rail: sticky TOC + share | **Pass** | TOC only for pieces of 7+ minutes with 2+ sections (approved rule); current section tracked with `aria-current`. |
| Update notice → Key takeaways → body → figure → quote → data table → Why this matters → correction → sources → tags → author card | **Pass** | All from real data; each omitted when empty. Core image/quote/table blocks are mapped to the approved markup. |
| Mobile TOC (`details.m-toc`) after Key takeaways | **Pass** | Closed by default, 48px summary, 44px links. |
| Data table → stacked comparison rows on phones | **Pass** | `tdd/data-table`: ≤3 value columns become `m-cmp` rows (short labels optional); wider tables scroll inside their own box. |
| Sources | **Pass / copy decision** | Numbered `#src-N` anchors match superscript refs; policy link only when the Source Policy page exists. Intro sentence is generic ("…published by the organisations involved…") because the approved one named "the company" of the sample story. |
| Tags order | **Known intentional difference** | Topics print in WordPress order (alphabetical), not hand-ordered. |
| Sidebar: Most Read (no window label), More in section (80px square thumbs), Daily Tech Brief | **Pass** | Brief link only when today's brief has 3+ placed items and the Newsletter page exists; count is the real number of items. |
| Related Coverage (4) | **Pass** | Same topics first, then same section; never repeats "More in"; section collapses when empty. |
| Newsletter (two-column navy) → footer | **Pass** | |
| Mobile order: article → More in AI (3) → Related (rows) → Most Read (3) → Newsletter | **Pass** | Sidebar modules are rendered once more in the approved mobile positions (same stories). |
| 768–1199 | **Pass (approved change, Phase 4)** | Below 1200 the rail folds into the header share row and the sidebar moves under the article. The contents now use the approved collapsible inline TOC (`details.m-toc`, same values as mobile) at every width below 1200, so tablets always have one; the sticky side TOC is used from 1200 up. Verified at 768, 1024, 1199 (inline) and 1200, 1280 (rail). |

### 3B Section (`category.html`; topic / story-type / tag use `archive.html`) — references: Category-AI-Desktop-1440, Category-AI-Mobile-390

| Area | Result | Notes |
|---|---|---|
| Masthead (mark + 64/40 title, description, freshness, counts, newsletter link) | **Pass** | Counts are real ("N stories this month · M today"; "today" omitted at 0). Mobile shows "N stories this month" (bold covers "N stories"). Description = term description. |
| TopicNav | **Pass / known difference** | All + up to 7 topics (section setting, else most used in the section). The desktop "More ▾" button is not built (no approved dropdown); the sidebar topic chips list more. |
| Pinned top stories: split feature + 3 | **Pass** | `section_lead` + `section_secondary`, each slot: active entry → next valid entry → newest story. Mobile: feature + 3 compact rows. |
| Latest feed with date groups, 12 per page, RSS | **Pass** | Pinned stories excluded on every page. Load more appends the next page, merges a day that continues, updates the URL, becomes "Go to page N →" after three loads; real `/ai/page/N/` links. |
| Pagination labels | **Pass** | Desktop "← Newer · 1 2 3 … N · Older →", note with story count; mobile "← Prev … Next →", "Page 1 of N". |
| Sidebar: Most Read in AI (7 days), Follow a topic (real counts), the AI desk, Daily Tech Brief | **Pass / copy decision** | Desk shows only when desk people are set (section settings). Chip note reads "Counts are AI stories tagged with each topic" (the approved "since 2024" would be untrue on a new site). |
| Analysis & Explainers (1 + 4; mobile 1 + 3 + "All analysis") | **Pass** | Never repeats stories shown above. |
| Practical AI (4 tiles; mobile 3 rows) | **Pass** | Guides in the section, most recently updated first. |
| Mobile: no trending bar, topic nav full-bleed, order feed → Most Read → Analysis → Guides → Topics → Desk → Newsletter | **Pass** | |
| Topic / story-type archives | **Derived (no dedicated design)** | Same masthead (no mark), feed, Most Read and newsletter. |

### 3C Homepage (`front-page.html`) — references: Homepage-Desktop-1440-v1.1, Homepage-Mobile-390-v1

| Area | Result | Notes |
|---|---|---|
| Hero: feature (50px, 2:1) + rail (medium + 3 compact) | **Pass** | `homepage_lead` / `homepage_secondary` with fallback; rail labels "Breaking · Section" or one label. Mobile: approved lead + 3 rows. |
| Latest Updates (7) + "Load more updates" / Most Read + Practical Guides | **Pass** | The desktop button links to the Latest page. A story appears once per page (hero stories never repeat in Latest). |
| AI News Today band | **Pass** | `ai_band_lead` + `ai_band_secondary`, falling back to newest AI stories; mobile shows lead + 3. |
| Editor's Picks (primary, secondary, two text-only) | **Pass** | "Long read · 16 min" for 12+ minute picks. |
| Section row: Cybersecurity (alert) · Developer (feature) · Startups (list) | **Pass / known difference** | Severity line ("Patch now · Critical") only from the story's `tdd_severity` field. **Funding rounds list omitted** (no real data source — it was sample data). A section with no stories is dropped and the others share the row (no hole). Mobile order Developer · Cybersecurity · Startups + "more sections" chips. |
| Practical Guides swipe row (mobile) | **Pass** | Same stories as the desktop sidebar list. |
| Daily Tech Brief + Newsletter | **Pass** | "Daily Tech Brief · N things worth knowing today" with the real item count; no edition number; "Read today's brief" only when configured. |

### 3D Author (`author.html`) — references: Author-Desktop-1440, Author-Mobile-390

| Area | Result | Notes |
|---|---|---|
| Masthead: photo 144/96, name, role · site · location, bio, covering-since, note + How we report | **Pass** | Only fields the author has filled in. The design's "PHOTO" caption on the initials placeholder is not shipped. |
| Contact & follow (desktop column; mobile 44px buttons) | **Pass** | Real profiles only; Email only when the author opted in; RSS when they have stories. Tip line link only when a `tips` page exists. |
| Beats chips | **Pass** | |
| Featured Reporting | **Pass** | Editor-selected (user setting, up to 3) — subtitle "chosen by editors"; otherwise the newest analysis/explainers with an honest subtitle; hidden when none. |
| Latest from … (rows, Load more, pager "Page 1 of N") | **Pass** | Mobile: "Latest from Priya", no excerpt/read time (approved CSS). |
| About this reporter, Most read by …, Daily Tech Brief | **Pass** | Rows without data omitted; Most read (30-day window) hidden below 3 stories. |
| Empty state (new writer) | **Pass** | No placeholder stories; points to the section desk they belong to; page is noindex until the first story is live. |

### 3E Search (`search.html`) — references: Search-Desktop-1440, Search-Mobile-390, SearchEmpty

| Area | Result | Notes |
|---|---|---|
| Title, field with clear, summary + Relevance/Newest | **Pass** | Desktop "Search Tech Dose Daily" / mobile "Search"; Search button desktop only. |
| Filters with counts (Section, Format, Date, Author) | **Pass** | Counts are for the search term (each option shows what it would return). Auto-apply on desktop; without JS an "Apply" button is shown. |
| Mobile "Filters (n)" bottom sheet + removable chips | **Pass** | Sheet: focus moves in, Esc / close returns focus, scroll locked. Chips are links that remove one filter. |
| TopicMatch | **Pass** | Exact topic or author name match; shows real story count (no "curated by" claim). |
| Result rows with `<mark>` highlights, 10 per page | **Pass** | Excerpt = deck, else a body snippet around the first match. |
| Pagination "Previous · 1 2 3 … N · Next", "Showing 1–10 of N"; mobile Load more | **Pass** | |
| Empty state | **Pass / known difference** | No spelling suggestion (WordPress search has no suggester). Popular chips come from the Trending menu. |
| noindex | **Pass** | `noindex, follow` on all search URLs. |
| URLs | **Known difference** | `/?s=…&section[]=ai&sort=newest` (WordPress-native) instead of the design's `/search?q=…`. |

### 3F Phase-wide regression

| Check | Result |
|---|---|
| Horizontal overflow, 8 widths × 12 URLs | **Pass** (fixed: mobile pager now wraps on the Latest page at 360) |
| One visible H1 per page, no duplicate IDs, all images have `alt` | **Pass** |
| PHP notices / JS errors | **Pass** (WP_DEBUG log empty; no console errors) |
| Header / footer / Phase 2 components page | **Pass** — unchanged except the search icon is marked current on search pages (approved). |
| Touch targets on phones | **Pass** — only inline text links are below 24px (source superscripts, breadcrumb links), which WCAG 2.5.8 exempts. |
| Block editor | **Pass** — `tdd/key-takeaways`, `tdd/why-this-matters`, `tdd/data-table` load as valid blocks. Editor styling (canvas looks unstyled) is Phase 5. |

## Phase 4: static pages, About, Contact, Newsletter, 404

Fixture: `tests/fixtures/phase4-pages.php` (local only; approved design copy marked as sample in `PRE-LAUNCH-PLACEHOLDERS.md`). Mail is captured by `tests/fixtures/mu-capture-mail.php`.

### 4A Shared static page (`page.html`, `page-short.html`) — references: StaticPage-EditorialStandards-Desktop-1440 / Mobile-390, StaticPage-Short-Advertise-Desktop-1440

| Area | Result | Notes |
|---|---|---|
| Header: kicker, H1 44/32, intro, Last updated · cadence · link | **Pass** | "Last updated" is a real date: `tdd_last_reviewed` (substantive change) or the page's modified date. Cadence and version link appear only when set. |
| Numbered sections from H2s, `#` link (desktop only) | **Pass** | Each H2 starts a `.tdd-spsec`; numbering is a page setting (on for Editorial Standards and About). |
| Contents: sticky rail ≥1200, collapsible inline <1200 | **Pass** | Same rule as articles (approved change). Rail tracks the current section (`aria-current`). Long layout is automatic at 4+ sections; `page-short` forces the short layout. |
| In short / Disclosure callouts, notices, definition list, lists | **Pass** | Core lists get the approved `.tdd-list` markers on static pages. |
| Policy table → mobile labelled rows (`.sp-mrow`) | **Pass** | `tdd/data-table` "Policy" style. Unit line hidden on phones, as approved. |
| Related documents, Related policies, Contact CTA | **Pass** | Related policies list only published pages, never the current one. The CTA is placed at the end whatever its position in the editor (long: after related policies; short: before). |
| Sample pull quote and "Version history / Compare versions" links | **Known intentional difference** | Not in the fixture: the quote is design copy ("Sample pull quote") and no version-history page exists. Both appear when an editor adds them. |
| Short page related policies include Editorial Standards | **Known intentional difference** | The reference omits it; the site lists every published policy except the current page (max 6). |
| Spacing fixes | **Pass** | "In short" keeps its 1.6em top margin and a short page's first H2 keeps its prose margin (the generic first-child reset no longer applies there); mobile bottom space is 96px including the footer margin. |

### 4B About (`page-about.html`) — references: About-Desktop-1440, About-Mobile-390

| Area | Result | Notes |
|---|---|---|
| Mission statement, intro 19px, focus row, numbered sections + rail | **Pass** | No "Last updated" on About (as approved). |
| Coverage list | **Pass / known difference** | Built from live sections with stories, in navigation order (AI, Software, Cybersecurity, Startups, Big Tech, Cloud…), not the design's order. Line = section "short description" (new term meta), else the trimmed description. Section names are the real ones ("Cloud", not "Cloud & Enterprise"). |
| Principles + policy links, AI section + Human review callout | **Pass** | |
| Our editors | **Known intentional difference** | Shows only real users with "Show on About" on, ordered by "About order". Initials without the "Photo" placeholder label; "Author page →" only if they have published stories. The design's fictional people and the "Fictional placeholder people" note are never rendered. With nobody opted in, the whole section and its contents entry disappear. The review screenshots use the existing local [sample] users. |
| Funding + notice | **Pass** | Statuses as entered (Planned/Active). |
| Contact routes (compact) | **Pass** | Links go to the Contact page with the topic preselected. |
| Related policies + newsletter panel (32/36 padding, 30px title) | **Pass** | Approved order with Advertise. |

### 4C Contact (`page-contact.html`) — references: Contact-Desktop-1440, Contact-Mobile-390, ContactForm-States

| Area | Result | Notes |
|---|---|---|
| Header, routes with CTA/policy/notes, 740 + 300 grid | **Pass** | Route notes ("Answered as capacity allows") and the media kit link appear only when configured. |
| Sensitive tip box | **Known intentional difference** | No secure channel exists, so the dashed box says so plainly: "We don’t offer a secure tip channel yet. Until we do, please don’t send sensitive information through this site." Real instructions replace it when configured (Core setting). |
| What to expect | **Pass** | Only commitments the newsroom has written down (Core setting); hidden when empty. Fixture values are the design copy. |
| Mobile order (routes → tip → form → expectations → related) | **Pass** | Same order below 1200 (one column, no sticky aside). "Where to send your message" 24px on phones. |
| Form default / error summary / field errors / success | **Pass** | Summary `role=alert`, focused, "Check N fields", links move focus to the field; fields get `aria-invalid` + `aria-describedby` (hint + error). Success panel `role=status`, focused, route-specific copy. |
| Submitting | **Pass** | "Sending…" with `aria-disabled` (keeps focus). |
| No-JS | **Pass** (updated before Phase 5) | Native validation; the form posts to the Contact page itself. Validation or send failures re-render every entered field — message included, escaped — in the same response; nothing is stored, cached or logged. Success redirects (Post/Redirect/Get). |
| Route links | **Pass** | Preselect the topic (JS: no reload, focus moves to the first empty field; no-JS: `?topic=`). |

### 4D Newsletter (`page-newsletter.html`) — references: Newsletter-Desktop-1440, Newsletter-Mobile-390, NewsletterForm-States

| Area | Result | Notes |
|---|---|---|
| Hero + sign-up, facts row | **Pass** | Mobile puts the facts under the form (approved). |
| What you get, Our promise + policy links, FAQ (first open) | **Pass** | |
| "A look inside" | **Known intentional difference** | Built from today's real Daily Tech Brief placement (needs 3+ stories, otherwise hidden), tagged "Preview · today’s stories from the Daily Tech Brief". No invented intro, "Why it matters", "Try this", view-in-browser or unsubscribe links. |
| Recent issues / View archive / Latest issue | **Known intentional difference** | Omitted: there is no issue archive in V1. |
| Delivery (desktop beside the issue; mobile "Delivery and questions") | **Pass** | Same rows, approved placement per width. Closing sign-up shown on desktop only (as approved). |
| Form states: invalid, already subscribed, pending (double opt-in), no-JS | **Pass** | Same Core endpoint and JS as the newsletter panel; pending panel "Check your inbox" focused. |

### 4E 404 (`404.html`) — references: 404-Desktop-1440, 404-Mobile-390

| Area | Result | Notes |
|---|---|---|
| HTTP status / robots / canonical / title | **Pass** | `404 Not Found`, `noindex, follow`, no canonical, no schema, title "Page not found · Tech Dose Daily", not cacheable. |
| Search, actions, latest 5 (4 on phones), popular sections | **Pass** | Search submits to WordPress search (`?s=`). "All latest →" desktop only, as approved. Section chips use real section names and only existing sections. |

### 4F Phase-wide checks

| Check | Result |
|---|---|
| Overflow, one H1, duplicate IDs, alt text, JS errors: 19 URLs × 8 widths | **Pass**. The local "Phase 2 components" fixture page now has two H1s (page title + a hero card demo) — fixture only. |
| PHP notices (WP_DEBUG log) | **Pass** — empty |
| Contact form tests (JS, REST, no-JS) | **Pass** — 25 checks: client/server validation, nonce, honeypot, token, too-fast, throttle (5 per 10 min), mail failure, Reply-To safety, no message body in logs |
| Newsletter page states at 1440 and 390, no-JS | **Pass** |
| Block editor | **Pass** — all Phase 4 blocks load as valid blocks; list-type blocks are edited as `field | field` lines with a live server preview. Editor styling is Phase 5. |

## Phase 5: newsroom admin

No approved admin designs exist; the admin uses native WordPress components and colours with the brand primary for focus. Field reference: `ADMIN-FIELDS.md`. Screenshots: `tests/review/wp06-phase5-admin.png`.

| Area | Result | Notes |
|---|---|---|
| Story panels (details, status & labels, sources, corrections, hero image credit) | **Pass** — 15 browser checks | Conditional fields verified: sponsor only for Sponsored (cleared when type changes), severity only for Cybersecurity, breaking times only when on, update note only after "Record a substantive update", correction entry only after publication, licence only for licensed/official images. Saved values verified in the database. Generic Categories / Story types / Tags / Excerpt panels removed. |
| Corrections are append-only | **Pass** | Editors can add, not remove or rewrite (REST returns 400); administrators can. |
| Page details panel | **Pass** — 11 checks | Per-template fields (Policy, About, Contact, Newsletter); headline saves and falls back to the title when cleared. |
| Placement editor | **Pass** — server tests | Placed / automatic / scheduled / waiting states, replace with start + end (site time → UTC), change times, two-step remove, end-before-start refused, drafts refused, section required for section slots, Daily Tech Brief never auto-fills, fallback label names the section. |
| Sections screen | **Pass** | One-liner, ordered topic nav, module switch (section page verified to drop the module), desk fields. |
| Profile | **Pass** | Title, beats, photo picker, repeatable social profiles; editors-only block hidden from authors. |
| Site settings | **Pass** | Launch readiness list; invalid inbox rejected with a message and the previous address kept; sample `example.*` addresses flagged. |
| Roles | **Pass** — 8 checks | Authors: no Tech Dose Daily menu, placement editor refused, no placement panel. Editors: Placements + Sections, no Site settings. |
| Editor canvas | **Pass** | Article reading styles in the canvas; phone-only previews hidden. |
| Public site regression (19 URLs × 8 widths) | **Pass** | Unchanged. |
| Contact form no-JS (pre-Phase 5 change) | **Pass** — 29 checks | Validation and send failures re-render every field, message included, escaped, in the same response; nothing stored or logged; success still redirects (PRG). |

## Phase 6: SEO and structured data

No visual change. Robots rules for 404 and empty author pages moved from the theme to Core (`seo.php`); public regression (19 URLs × 8 widths) unchanged. Details and validation results: `SEO-SCHEMA.md`.

Phase 6 re-run with real Yoast SEO 28.6: exactly one `<title>` per page (block-theme duplicate removed), 404 title "Page not found · Tech Dose Daily" kept via the Yoast template, public regression unchanged.

## Phase 7: performance

No visual change after load. Minified and source assets produce pixel-identical screenshots: 10 templates at 390 and 1440 px.

- **Before the web fonts arrive** (first visit, slow network), text renders in a size-adjusted Arial/Liberation Sans fallback that takes the same space. It then swaps to Inter or Manrope without moving the layout. Previously the fallback was unadjusted `system-ui`.
- **Below-the-fold images** are lazy-loaded. A full-page screenshot taken immediately after load can show their grey placeholder (`--bg-muted`) until they arrive. The 16:9 / 4:3 boxes are reserved, so nothing shifts.
- **Mobile search** never shows the filter panel in-flow before the bottom sheet takes over (the CLS fix).

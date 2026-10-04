# Performance (Phase 7)

This phase covers performance only. There is no security hardening and no launch preparation here. SEO output from Phase 6 is unchanged: it was checked byte-for-byte, cached and uncached, in both schema-owner modes (§7).

## 1. What changed

| Area | Change | Where |
|---|---|---|
| Hero image | Only the likely LCP image is `loading="eager"` + `fetchpriority="high"`: the homepage lead, the section lead and the article hero. The homepage rail and the mobile top list were eager before; they are now lazy. | theme `inc/home.php`, `inc/template-tags.php` (`tdd_media`) |
| Content media | Every image and iframe/embed inside story or page content is lazy and never high priority. WordPress had given the first body image `fetchpriority="high"` and left iframes eager. Content images get reading-column `sizes`: `(max-width: 767px) calc(100vw - 40px), 740px`, or `100vw` for wide/full alignments. WordPress had used `(max-width: 1024px) 100vw, 1024px`. | theme `inc/performance.php` |
| Image formats | JPEG uploads keep the original file. Every generated size (the srcset candidates) is WebP when the server can write WebP. Filter: `tdd_core_webp_subsizes`. The local sample images are already WebP. | Core `includes/performance.php` |
| Fonts | 8 woff2 files went from 149 KB to 129 KB. Unused OpenType features (fractions, numerators/denominators, proportional figures) and the STAT table were dropped; every character is kept. The script is `tools/subset-fonts.sh`, run on the original design-system files. | `assets/fonts/` |
| Font fallback | `font-display: swap` stays. Metric-matched local fallback faces ("Inter Fallback", "Manrope Fallback": Arial/Liberation Sans with `size-adjust`, `ascent-override`, `descent-override`, computed from the shipped files) sit second in each stack, so text laid out before the swap takes the same space. Rendering after the swap is unchanged. | `tools/build-tokens.mjs` → `tokens.css`, `theme.json` |
| Preloads | Two fonts are preloaded (Manrope 800, Inter 400), the ones above the fold on every template. This is unchanged and was re-checked. | theme `inc/assets.php` |
| Search CLS | The filter bottom-sheet class is set in `<head>` before first paint, not after the deferred script. Mobile CLS went from 0.573 to 0. Without JS nothing changes. | theme `inc/performance.php` |
| Minification | `tools/minify.mjs` writes `x.min.css` / `x.min.js` next to each source. CSS uses lightningcss, minify only: no targets, prefixing or rule merging. JS uses esbuild, per file, no bundling. The theme serves `.min` automatically unless `SCRIPT_DEBUG` is on, and only when the `.min` file is at least as new as its source. A source edited without rebuilding is served unminified rather than stale; `git archive` deploys give both the same time. Sources stay the files you edit. Screenshots of 10 templates at 390 and 1440 are pixel-identical between minified and source. | `tools/minify.mjs`, theme `inc/performance.php` |
| Asset versions | Every theme CSS/JS URL, including block.json view scripts, carries `ver=<theme version>.<file mtime>`. This makes a one-year immutable browser cache safe after deploys. | theme `inc/assets.php`, `inc/performance.php` |
| Page cache | Editorial-aware `Cache-Control`, the LiteSpeed Cache API, purge signals and no-cache rules. See §3. | Core `includes/performance.php` |
| Placement cache | Cached placement rows are no longer pre-filtered by the clock. Start and expiry are compared on every call, so a cached copy can never show an entry early or late. The version is bumped on placement edits and on status changes of placed stories. Added indexes on `start_at` and `expires_at` (table v2). | Core `includes/placements.php` |
| Queries | Placement and Most Read results load posts, meta, terms and featured-image attachments in batches (`tdd_core_prime_posts`). Homepage queries went from 234 to 161. | Core `includes/api.php` |
| Most Read | Lab and uptime tools are never counted: PageSpeed/Lighthouse, WebPageTest "PTST", GTmetrix, Pingdom, SpeedCurve, Calibre, DebugBear, sitespeed.io, LiteSpeed's crawler, and any request with `X-TDD-Perf-Test`. The beacon also skips automated browsers (`navigator.webdriver`) and waits while a page is only prerendered. | Core `includes/popularity.php`, theme `assets/js/view-beacon.js` |

**Deliberately not changed:**

- WordPress global styles inline CSS (23.5 KB raw, 3.3 KB gzipped). It holds the preset classes that editor-chosen sizes and colours rely on. Trimming it risks unstyled content for a 3 KB gain.
- The default speculation rules (prefetch on hover intent).
- RSS and oEmbed discovery links.

## 2. Performance budgets

These are lab budgets, with Lighthouse mobile = simulated Moto G Power / slow 4G. They are checked per template. Staging will be re-measured on real hosting (§6).

| Metric | Budget | Now (worst template) |
|---|---|---|
| LCP, mobile | ≤ 2.5 s (target ≤ 2.0 s) | 1.96 s uncached homepage |
| CLS | ≤ 0.05 | 0.003 |
| TBT, mobile | ≤ 100 ms | 0 ms |
| Total transfer, first view | ≤ 250 KB mobile, ≤ 300 KB desktop homepage | 198 KB / 227 KB |
| Fonts | ≤ 125 KB, at most 2 preloads | 119 KB, 2 |
| CSS per page (gz) | ≤ 25 KB | 23 KB (newsletter) |
| JS per page (gz) | ≤ 10 KB, all deferred | 4 KB |
| Requests | ≤ 30 | 26 |
| Third-party requests | 0 | 0 |
| High-priority images | ≤ 1 per page | 1 (home, section, article with hero), otherwise 0 |
| Server: cached HTML TTFB | ≤ 200 ms on staging | 0–4 ms locally |
| Server: uncached render | ≤ 600 ms TTFB on staging; ≤ 200 DB queries | 161 queries on the homepage |

## 3. Caching

### 3.1 How a page decides (Core `includes/performance.php`)

| Response | Header |
|---|---|
| Never cached: logged-in users; non-GET/HEAD requests; previews; password-protected posts; Contact page (`DONOTCACHEPAGE`); no-JS form results `?tdd_nl=` / `?tdd_cf=` | `Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private` · LiteSpeed: `litespeed_control_set_nocache` · `X-TDD-Cache: no-cache; <reason>` |
| Everything else (anonymous GET) | `Cache-Control: public, max-age=0, s-maxage=<ttl>` · LiteSpeed: `litespeed_control_set_ttl` · nginx only: `X-Accel-Expires` |

Browsers always revalidate HTML (`max-age=0`). Only shared caches keep it.

**TTL** is the lower of the view's base lifetime and the time to the next scheduled editorial change:

| View | Base TTL | Why |
|---|---|---|
| 404 | 60 s | A story published at that URL must appear quickly |
| Homepage, sections, topics, story types, search | 5 min | Rivers, placements, Most Read, "x min ago" labels |
| Stories, author pages | 15 min | Rails carry relative times and Most Read |
| Static pages (About, policies, Newsletter) | 60 min | Rarely change; every edit purges |

The next editorial change is the earliest upcoming Breaking start or end, placement start or expiry, or scheduled story time. It is cached in the `tdd_core_next_change` transient for up to 10 minutes and cleared by every purge. When a Breaking label ends or a scheduled placement starts or ends, cached pages expire at that moment without any purge. Filters: `tdd_core_cache_ttl`, `tdd_core_cache_nocache_reason`.

### 3.2 Purges

Purges are collected per request and sent once at shutdown.

They are triggered by:

- **Posts:** publish, unpublish, edit of a live story or page, scheduling, trash.
- **Post meta:** `tdd_*`, Yoast fields, featured image, page template and image credit/alt changes outside a post save.
- **Editorial structure:** placements (editor, REST, WP-CLI, and status changes of placed stories); sections, topics and story types; section settings; author profiles; menus; templates and global styles.
- **Settings and software:** Tech Dose Daily settings; site title, tagline and reading settings; Yoast settings; plugin activation or deactivation; theme switches and updates.

Each purge sends:

- `litespeed_purge_all` when LiteSpeed Cache is active;
- the action `tdd_core_cache_purge` for any other cache.

Purging everything is deliberate. Breaking labels and story cards appear on many pages (rails, related stories, sections), and the site is small enough that a full rebuild is cheap.

### 3.3 Exclusions (cache must never store these)

| Never cached | Enforced by |
|---|---|
| Logged-in users, including editors (they also see the admin bar) | Core header + cache cookie rules |
| Contact page and `?tdd_cf=` result | Theme `DONOTCACHEPAGE` + Core header |
| Newsletter no-JS result `?tdd_nl=…` | Core header |
| Form submissions: `POST /wp-admin/admin-post.php`, `POST /wp-json/tdd/v1/subscribe`, `/tdd/v1/contact`, `/tdd/v1/view` | POST is never cached |
| Previews, password-protected posts | Core header |
| Feeds, sitemaps, robots.txt | Not given a page TTL (Yoast and WordPress defaults) |

**Forms stay correct inside cached pages.** The newsletter forms on every page carry a signed time token valid for up to 7 days, not a WordPress nonce, so a cached copy (lifetime ≤ 60 min) always submits successfully. The Contact form uses a nonce and is never cached.

### 3.4 LiteSpeed Cache settings (Hostinger) — staging

The free LiteSpeed Cache plugin; no QUIC.cloud services are required. Correctness never depends on a paid service: without any page cache the site sends the same headers and works the same.

| LiteSpeed setting | Value | Reason |
|---|---|---|
| Enable Cache | On | |
| Cache Logged-in Users | **Off** | Editors must always see live state |
| Cache Commenters / Cache Mobile | Off | No comments; one responsive DOM |
| Cache REST API | On (default) | Only public GET endpoints; our POST endpoints are never cached |
| Default Public Cache TTL | 3600 | Core overrides it per page (§3.1) |
| Default Front Page TTL | 300 | Core overrides it anyway |
| Purge: Auto Purge Rules | Defaults | Core additionally purges everything on editorial changes |
| Do Not Cache URIs | `/contact/` | Belt and braces (Core already says no-cache) |
| Do Not Cache Query Strings | `tdd_nl`, `tdd_cf` | Belt and braces |
| Browser Cache | On, 1 year | Theme assets are file-versioned |
| CSS/JS Minify, Combine, HTTP/2 Push, Load CSS Async, Critical CSS, JS Defer/Delay | **Off** | Assets are already minified and deferred; async CSS/critical CSS can cause FOUC/CLS; delayed JS breaks the forms |
| Lazy Load Images / iframes | **Off** | The theme sets native lazy loading itself; LiteSpeed's version would lazy-load the LCP hero |
| Image Optimization / WebP Replacement | Off | WordPress generates WebP sub-sizes (§1) |
| Guest Mode / Guest Optimization, ESI | Off | Not needed |
| Object Cache | On if Redis/Memcached is offered | Faster uncached renders; Most Read dedupe transients stay out of `wp_options` |
| Crawler | Optional | Its user agent is excluded from Most Read |

**Cron:** set `define( 'DISABLE_WP_CRON', true );` and add a server cron job every minute (`wget -q -O - https://<site>/wp-cron.php?doing_wp_cron >/dev/null 2>&1`). Cached pages never run PHP, so WordPress's request-triggered cron could publish scheduled stories late. Publication purges the cache.

## 4. Before / after (lab)

Measured locally on nginx 1.24 + PHP-FPM 8.3, SQLite and Yoast 28.6 active, with Lighthouse 13.5 (mobile = Moto G Power on slow 4G, 4× CPU; desktop preset). "Before" is the Phase 6 build on the same server.

- "→ x / y": uncached / served from the page cache.
- Scores and LCP move by about ±300 ms between identical runs, from simulated throttling.
- Raw data: `tests/perf/out/lighthouse-*.json`.

| Page | Score | LCP ms | CLS | TBT ms | TTFB ms | Transfer KB | Fonts KB | CSS KB | JS KB | Requests |
|---|---|---|---|---|---|---|---|---|---|---|
| home · mobile | 96 → 99 / 100 | 2470 → 1960 / 1658 | 0 → 0 | 28 → 0 | 240 → 163 / 3 | 224 → 196 | 138 → 119 | 17 → 13 | 3 → 2 | 24 → 22 |
| home · desktop | 100 → 100 / 100 | 543 → 490 / 501 | 0 → 0 | 0 → 0 | 319 → 168 / 0 | 259 → 227 | 138 → 119 | 17 → 13 | 3 → 2 | 28 → 26 |
| article with hero · mobile | 96 → 99 / 100 | 2446 → 1805 / 1656 | 0 → 0.003 | 42 → 0 | 224 → 166 / 0 | 217 → 181 | 138 → 119 | 19 → 15 | 5 → 4 | 23 → 21 |
| article with hero · desktop | 100 → 100 / 100 | 534 → 600 / 485 | 0 → 0.002 | 0 → 0 | 160 → 152 / 2 | 238 → 191 | 138 → 119 | 19 → 15 | 5 → 4 | 24 → 23 |
| article, no hero · mobile | 100 → 100 / 99 | 1506 → 1520 / 1954 | 0.002 → 0 | 0 → 0 | 166 → 132 / 1 | 190 → 167 | 138 → 119 | 19 → 15 | 5 → 4 | 21 → 21 |
| article, no hero · desktop | 100 → 100 / 100 | 456 → 333 / 442 | 0.001 → 0.002 | 0 → 0 | 144 → 154 / 4 | 185 → 162 | 138 → 119 | 19 → 15 | 5 → 4 | 21 → 21 |
| section · mobile | 99 → 100 / 100 | 2031 → 1656 / 1651 | 0 → 0.001 | 0 → 0 | 210 → 165 / 2 | 221 → 198 | 138 → 119 | 17 → 13 | 5 → 4 | 24 → 24 |
| section · desktop | 100 → 100 / 100 | 503 → 455 / 591 | 0.001 → 0.001 | 0 → 0 | 192 → 179 / 0 | 213 → 190 | 138 → 119 | 17 → 13 | 5 → 4 | 24 → 24 |
| author · mobile | 100 → 100 / 100 | 1508 → 1426 / 1440 | 0 → 0 | 0 → 0 | 155 → 137 / 1 | 200 → 177 | 138 → 119 | 19 → 15 | 5 → 4 | 22 → 22 |
| author · desktop | 100 → 100 / 100 | 468 → 404 / 535 | 0 → 0 | 0 → 0 | 134 → 159 / 0 | 200 → 177 | 138 → 119 | 19 → 15 | 5 → 4 | 24 → 24 |
| newsletter · mobile | 100 → 100 / 100 | 1515 → 1432 / 1431 | 0 → 0 | 0 → 0 | 125 → 133 / 2 | 189 → 165 | 138 → 119 | 27 → 23 | 3 → 2 | 21 → 21 |
| newsletter · desktop | 100 → 100 / 100 | 472 → 441 / 445 | 0 → 0 | 0 → 0 | 111 → 111 / 2 | 189 → 165 | 138 → 119 | 27 → 23 | 3 → 2 | 21 → 21 |
| contact (never cached) · mobile | 100 → 100 / 99 | 1508 → 1452 / 1739 | 0 → 0 | 0 → 0 | 145 → 108 / 120 | 185 → 161 | 138 → 119 | 24 → 20 | 4 → 3 | 20 → 20 |
| contact (never cached) · desktop | 100 → 100 / 100 | 449 → 422 / 420 | 0 → 0 | 0 → 0 | 121 → 108 / 118 | 185 → 161 | 138 → 119 | 24 → 20 | 4 → 3 | 20 → 20 |
| search · mobile | **76** → 100 / 99 | 2188 → 1729 / 1879 | **0.573** → 0 | 0 → 0 | 126 → 118 / 1 | 199 → 176 | 138 → 119 | 19 → 15 | 4 → 3 | 25 → 25 |
| search · desktop | 100 → 100 / 100 | 430 → 424 / 420 | 0.002 → 0.002 | 0 → 0 | 126 → 136 / 2 | 203 → 180 | 138 → 119 | 19 → 15 | 4 → 3 | 26 → 26 |

**404** (Lighthouse refuses 404 responses, so it was measured with Playwright using the same throttling; `tests/perf/pw_vitals.py`; no "before" figure exists):

| Run | LCP / FCP | CLS | TTFB | Transfer | Requests |
|---|---|---|---|---|---|
| Mobile, uncached / cached | 808 / 744 ms | 0 | 101 / 6 ms | 154 KB | 17 |
| Desktop, uncached / cached | 248 / 124 ms | 0 | 123 / 6 ms | 154 KB | 17 |

**LCP elements:**

| Page | LCP element |
|---|---|
| Homepage, section | Lead image (eager, high priority) |
| Article with hero | Mobile: H1. Desktop: hero image. |
| Article without hero, author, newsletter, contact, search | Text |

**Database** (uncached render, warm transients; `tests/perf/out/query-counts.json`; cached anonymous hits run 0 queries):

| Page | Queries before → after |
|---|---|
| Homepage | 234 → 161 |
| Section | 174 → 145 |
| Article with hero | 151 → 132 |
| Article without hero | 134 → 118 |
| Newsletter | 102 → 90 |
| About | 102 |
| Author | 116 → 117 |
| Search | 110 |
| Contact | 90 |
| 404 | 73 |

The rest are WordPress/Yoast boot queries (73 on the 404) and per-query batch loads. The six placement lookups on the homepage use the `placement_lookup` index.

**Schema-owner modes:** owner = Yoast and owner = Core differ only by the JSON-LD block in `<head>` (≈ 1–4 KB). The validator results for both modes are identical to Phase 6.

**Third-party request inventory: none.** Every script, style, font, image and iframe on all measured templates is first-party; this is enforced by `tests/perf/markup_test.py`. Embeds an editor adds to a story (e.g. a video) are the only third-party requests a page can make; they load lazily, below the headline.

## 5. Tests

Run after Phase 7, all against the nginx page cache. Commands are in `DEVELOPMENT.md` step 11.

| Suite | Result |
|---|---|
| `tests/perf/cache_test.py`: publishing purges; Breaking expiry, scheduled-placement start/end and scheduled-story publication with no purge; placement edit purge; forms never cached (contact, newsletter/contact results, admin-post POST, REST POST); logged-in editor bypass, private and no anonymous leak; preview; Most Read ignores perf tools, `X-TDD-Perf-Test` and webdriver/prerender | **35 / 35** |
| `tests/perf/markup_test.py`: one high-priority image per page at most; lazy everywhere else; width/height + sizes; content images and embeds lazy with column sizes, with and without a hero; minified + versioned assets; fonts and fallbacks; early search class; no third-party requests (9 templates) | **75 / 75** |
| `tests/perf/seo_parity.py`: title, description, canonical, robots (meta + header), prev/next, OG/article/X, every JSON-LD block, 6 sitemaps. 25 URLs × both owner modes, uncached and cached (36 cache HITs), compared with the build with the Phase 7 modules switched off | **identical** (`tests/perf/out/seo-parity.txt`) |
| `tests/seo/seo_test.py` (real Yoast 28.6, through the cache) | **300 / 300** |
| `tests/seo/seo_hooks.php` | **15 / 15** |
| Schema vocabulary + rich-result rules (`validate.mjs`), Core and Yoast owner | identical to Phase 6 (0 errors) |
| Public pages: 19 URLs × 8 widths (360–1920): overflow, duplicate IDs, one H1, alt text, console errors | Unchanged from Phase 6. The only flags are the expected ones: the components gallery page has 2 H1s, and the 404 page logs its own 404 status. |
| Minified vs source assets, 10 templates × 390/1440 screenshots | Pixel-identical (differences only where a lazy image had not finished loading in a full-page capture) |
| Admin + forms: editor story panels 15, page panel 11, admin forms 11, roles 8, admin UI 12, contact 28, newsletter 12, server-side rules 16 | **all pass** |

Two local test scripts were brought up to date:

- the admin UI test now expects an invalid inbox to keep its previous address, as approved in Phase 5;
- the server-rules test uses a fresh test story, because its old fixture story had been deleted.

## 6. Staging-only checks (left on the pre-launch checklist)

- [ ] **Production cache verification** on Hostinger/LiteSpeed:
  - `x-litespeed-cache: hit` for anonymous pages and `miss`/none for logged-in users, Contact and form results;
  - publish → homepage updates within seconds;
  - a Breaking end and a scheduled placement flip on time with no manual purge;
  - forms submit from a cached page;
  - `X-TDD-Cache` shows the expected TTL.
- [ ] Apply the LiteSpeed settings in §3.4. Set up real cron (`DISABLE_WP_CRON` + a server cron job every minute).
- [ ] Re-measure §4 on staging over the real network (PageSpeed Insights and WebPageTest, mobile + desktop). Check against the §2 budgets, including uncached TTFB ≤ 600 ms with MySQL.
- [ ] Confirm the server can write WebP (`wp_image_editor_supports`), and that Brotli or gzip is on for HTML/CSS/JS/SVG.
- [ ] Object cache (Redis/Memcached) if the plan offers it; re-check query timing.
- [ ] **Google Rich Results Test**, **final Site Icon** and **Yoast SEO data optimisation**. These carry over from Phase 6 and are unchanged.
- [ ] Field data (CrUX / Search Console Core Web Vitals) after launch traffic.

## 7. Local harness (never deployed)

| Item | Purpose |
|---|---|
| `tests/perf/nginx.conf`, `fpm.conf` | nginx with gzip, one-year static caching and a `fastcgi_cache` that stores only what the app gives a TTL. It bypasses logged-in cookies, POST and `X-Cache-Bypass`. Paths are for the local container; adjust them for your machine. |
| `tests/perf/mu-local-page-cache.php` | Connects `tdd_core_cache_purge` to that nginx cache directory |
| `tests/perf/mu-query-log.php` | With the header `X-TDD-Query-Log: 1`, writes a per-request query log |
| `tests/perf/lh.mjs`, `pages.json` | Lighthouse runner. It sends `X-TDD-Perf-Test` so lab runs are never counted as Most Read views. |
| `tests/perf/pw_vitals.py` | Playwright vitals for 404 pages |
| `tests/perf/out/` | Measurements and the SEO parity snapshot |

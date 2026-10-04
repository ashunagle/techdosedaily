# Pre-launch placeholders: replace before production

Every piece of illustrative or provisional material from the design phase and the build. Nothing on this list may reach production in its current form. Tick each item when it's replaced with real, verified data.

## Content shown in the approved designs (03-Approved): design references only, never imported

- [ ] **Sample headlines, decks, summaries and story data** on the homepage, article, category, search, author, 404 and newsletter references. Examples: "Major AI provider unveils a reasoning model…", "Browser makers patch an actively exploited flaw…". Real stories come from the newsroom.
- [ ] **Sample article body, quotes and data table** in the article reference, including the quote attributed to "Dana Whitfield" (fictional) and the benchmark figures marked "illustrative".
- [ ] **Sample sources** (example-ai.dev, eval-lab.example.org, example.org).
- [ ] **Fictional people:** Priya Raman (author page), and Alex Morgan, Daniel Okafor and Mei Lin (About editors). Replace with real staff profiles, real photos and real titles, or don't show them.
- [ ] **Author facts:** "Bengaluru, India" and "Covering software and AI since 2016" are sample data. Show only true, author-approved details.
- [ ] **Editorial Standards / policy text** in the static page references is layout copy, not approved policy. Final policies need editorial and legal review.
- [ ] **Advertise page:** partnership options are illustrative, and the media kit (PDF) link doesn't exist yet.
- [ ] **Funding statuses:** all four sources are "Planned". Update each status when a source goes live.
- [ ] **Secure tip channel:** the design placeholder is not shipped. Until a real channel exists the site says: "We don’t offer a secure tip channel yet. Until we do, please don’t send sensitive information through this site." Add real instructions (`tdd_secure_tip`) only when the channel works.
- [ ] **Newsletter sample issue and recent issues** ("Monday, October 5", "8 things worth knowing today", archive titles) are illustrative. Not shipped: the page previews today's real Daily Tech Brief instead, and Recent issues / archive are omitted in V1 (no issue archive).
- [ ] **Contact role inboxes** (editorial@, corrections@, tips@, partnerships@) must exist and be monitored before the contact routes go live.
- [ ] **Response expectations** wording must match real capacity.

## Build-time placeholders (Phase 1)

- [ ] `tests/fixtures/phase1-menus.php` is a **local-only** fixture: example.com social URLs, `#careers-placeholder`, `#ai-news-today-placeholder` and `#cookie-settings-placeholder`. Never run it on staging or production.
- [ ] **Footer "Follow" menu:** add only accounts that exist. It stays hidden while empty.
- [ ] **Footer "Careers" link:** remove it, or add a real Careers page.
- [ ] **"Cookie settings" link:** only if a consent tool is installed (analytics decision).
- [ ] **"AI News Today" footer link:** needs a real destination, such as an AI section anchor or a newsletter, or removal.
- [ ] **Site Icon:** upload the final icon in Settings → General. The theme falls back to `tdd-favicon.svg` until then.
- [ ] **Theme `screenshot.png`** is taken from the approved homepage reference, which contains sample headlines. It's admin-only, but replace it with a real-content screenshot after launch.
- [x] **Section URLs:** decided `/ai/`, `/tech/` … (TechDoseDaily Core). `/category/…` 301s. Check again on staging after Yoast is configured (canonical and sitemap must use `/ai/`).
- [ ] **Local admin credentials** used for testing in the build workspace are local-only and never used on Hostinger.

## Phase 2 local fixtures (never on staging or production)

- [ ] `tests/fixtures/phase2-content.php`: 16 "[Sample]" stories, user **priya-sample** (fictional), sample images, placements and **synthetic view counts**.
- [ ] `tests/fixtures/phase2-gallery.php`: "Phase 2 components (local fixture)" page, Trending menu with `#topic-…` links, Home/Latest pages, timezone Asia/Kolkata.
- [ ] `tests/fixtures/mu-fake-newsletter-provider.php`: fake provider. Never copy it to a real site.
- [ ] **Daily Tech Brief edition number** ("Edition No. 276" in the designs) isn't rendered. It needs a real issue counter, or stays omitted.
- [ ] **"Read today's brief →"** link only renders when a real brief/archive URL is configured.
- [ ] **"Manage subscription"** link from the approved Already-subscribed state needs MailPoet's manage-subscription URL; it's omitted until configured.
- [ ] **MailPoet list ID** (`tdd_newsletter_mailpoet_list`). **Double opt-in is ON in production** (decided): enable it in MailPoet → Settings → Sign-up confirmation. Pending copy approved: "Check your inbox / We sent a confirmation link…".

## Phase 3 local fixtures and omissions (never on staging or production)

- [ ] `tests/fixtures/phase3-article.php`: turns the sample AI lead into the full illustrative article (fictional quote, figures, sources at example domains, correction and update), user **mira-sample**, author profile fields marked [Sample], reading time forced to 9 minutes.
- [ ] `tests/fixtures/phase3-sections.php`: 23 "[Sample]" AI stories, users **daniel-sample / arjun-sample / leah-sample** (fictional), AI section description, desk statement and desk people, section placements, synthetic 7-day view counts.
- [ ] `tests/fixtures/phase3-home.php`: 10 "[Sample]" stories for the homepage section row and Editor's Picks, a sample severity line, homepage placements.
- [ ] **Funding rounds list** ("Rounds this week · sample" in the homepage Startups block) is not built: no real data source. Needs an editorial decision (structured funding entries) or stays omitted.
- [ ] **"Save for later"** share button is not built (no reader accounts).
- [ ] **Secure tip line** link on author pages appears only once a real `tips` page exists.
- [ ] **Search spelling suggestions** ("Did you mean…") are not available with WordPress search; add only with a real search service.
- [ ] **Section desk, topic nav order, author Featured Reporting and editor** are settings with no values on a fresh site; modules stay hidden until editors fill them in (Sections screen and user profiles).

## Phase 4 local fixtures and decisions (never on staging or production)

- [ ] `tests/fixtures/phase4-pages.php`: fills Editorial Standards, Advertise, About, Contact and Newsletter with the **approved design copy**, sets the privacy page, section one-liners, and turns on "Show on About" for the local **[sample] users** only. All of this copy is provisional until the newsroom (and legal, for policies) approves it.
- [ ] `tests/fixtures/mu-capture-mail.php`: captures `wp_mail` locally. Never copy it to a real site.
- [ ] **Contact inboxes** in the fixture are `@example.com`. Set `tdd_contact_inboxes` to real, monitored role inboxes; without it every route goes to the site admin email.
- [ ] **"What to expect" commitments and route notes** ("Reviewed promptly by an editor", "Answered as capacity allows"…) are design copy: keep only what the desk can honour.
- [ ] **Standards desk email** (`mailto:standards@example.com` on the Editorial Standards CTA) and the **media kit** URL (`https://example.com/sample-media-kit.pdf`) are samples.
- [ ] **Newsletter promises and FAQ answers** need newsroom confirmation (e.g. "No weekend sends", "never sold or shared", manage-subscription link omitted until MailPoet's URL is configured).
- [ ] **Policy pages** other than Editorial Standards and Advertise (Corrections, Source, AI Use, Privacy, Terms) have no content yet.
- [ ] **Real editors on About:** turn on "Show on About" for real staff with real titles, bios and photos; the section stays hidden until then.
- [x] **Startups funding-rounds module:** disabled in V1 (decided). No external funding data source; revisit only with a verified editorial/data workflow.

## Phase 5 local fixtures (never on staging or production)

- [ ] Test story **"[Sample] Phase 5 panel test story"** (`_tdd_fixture = phase5`), with sample sponsor, sources, correction and image credit. Delete before launch (Site settings → Launch readiness counts sample data).
- [ ] Local sample users have a shared local-only password for role testing (not recorded in the repo). Never create them on a real site.
- [ ] Work through **Site settings → Launch readiness** before launch: every "!" item must be resolved.

## Phase 6 (SEO / structured data)

- [x] **Yoast SEO 28.6 verified locally** (owner modes, descriptions, canonicals, robots, OG/X, sitemaps; `SEO-SCHEMA.md` §6–9).
- [ ] **Install Yoast SEO on staging/production**, apply `SEO-SCHEMA.md` §7 (incl. title templates), run Yoast's SEO data optimisation, then re-run `tests/seo/seo_test.py`, `seo_hooks.php` and `validate.mjs` against staging.
- [ ] `tests/vendor-plugins/wordpress-seo.zip` is a local test copy (git-ignored); production installs Yoast from wordpress.org.
- [ ] **Google Rich Results Test and validator.schema.org** on staging URLs (one of each story type, a section, an author, About).
- [ ] **Site Icon** is also the publisher logo in the Core graph; none is printed until it is uploaded.
- [ ] **Organization `sameAs`** (official social accounts) is not emitted; add only verified accounts when they exist.
- [ ] Sample social profiles on [sample] users (`example.com` URLs) appear in `Person.sameAs` locally — they disappear with the sample users.
- [ ] `tests/fixtures/phase6-seo.php` (Sponsored story with sponsor "Example Sponsor Co [Sample]" and a bare news story) and `tests/fixtures/mu-seo-audit.php` (audit switches, Yoast stand-in) are local only.
- [ ] **Google News sitemap:** not built; decide at launch.

## Phase 7 (performance) — staging/pre-launch

- [ ] **Production cache verification** on Hostinger/LiteSpeed (`PERFORMANCE.md` §6): anonymous HIT, logged-in/Contact/form results never cached, publish purges, Breaking end and scheduled placements flip on time, forms submit from cached pages.
- [ ] Apply the **LiteSpeed Cache settings** in `PERFORMANCE.md` §3.4 (lazy load, minify/combine, critical CSS, guest mode and image optimisation **off**; logged-in cache off).
- [ ] **Real cron:** `DISABLE_WP_CRON` + a server cron job every minute (scheduled stories on a cached site).
- [ ] **Re-measure on staging** (PageSpeed Insights, WebPageTest; mobile + desktop) against the budgets in `PERFORMANCE.md` §2.
- [ ] Confirm WebP sub-size support and Brotli/gzip on the server; enable an object cache if offered.
- [ ] Still open from Phase 6: **Google Rich Results Test**, **final Site Icon**, **Yoast SEO data optimisation**.
- [ ] Local harness only, never deployed: `tests/perf/mu-local-page-cache.php`, `tests/perf/mu-query-log.php`, `tests/perf/nginx.conf`, `fpm.conf`.

## Phase 8 (security and privacy) — staging/pre-launch

- [ ] Work through **SECURITY.md §8**: wp-config constants (no debug display, `DISALLOW_FILE_EDIT`, `FORCE_SSL_ADMIN`, `DISABLE_WP_CRON`), server rules (no PHP in uploads, XML-RPC denied, dotfiles hidden), HTTPS + HSTS, real client IP behind any CDN, PHP with Imagick.
- [ ] **Accounts:** personal accounts only (remove the generic `admin` login), 2FA for administrators and editors, registration off, least-privilege roles.
- [ ] **MailPoet double opt-in on**; SPF/DKIM/DMARC for the sender domain; SMTP credentials outside Git.
- [ ] **Backups** per SECURITY.md §8 (daily DB + uploads, 30/12 retention, off-site, encrypted) and a restore test to staging before launch.
- [ ] Re-run `tests/security/security_test.py` and `xss_test.py` against staging before real content exists; securityheaders.com and SSL Labs grade A.
- [ ] Decide the accepted risks in SECURITY.md §6 (newsletter "already subscribed" state; authors deleting their own published stories).
- [ ] Still open: Google Rich Results Test, final Site Icon, Yoast SEO data optimisation, production cache verification.


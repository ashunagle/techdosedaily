# Staging and launch gates

The site stays closed to the public — staging password-protected and noindex, production not opened —
until **every gate below passes**. Sources: `SECURITY.md` §8, `PERFORMANCE.md` §2/§6, `SEO-SCHEMA.md` §7,
`PRE-LAUNCH-PLACEHOLDERS.md`. Automated gates are checked by `tests/staging/gates.py` (IDs in brackets).

Status: **staging deployed and configured; gate run pending** (see `HANDOVER-STAGING.md`) · release candidate **0.9.0** (`dist/` zips + `SHA256SUMS`).

Progress log (2026-10-04)
- Live `techdosedaily.com` (Hostinger default install) closed while staging is prepared: Hostinger maintenance mode on, *Discourage search engines* on, XML-RPC and application passwords off, Force HTTPS on.
- A1 staging `staging.techdosedaily.com` created (folder `public_html/staging`, own database); directory protection: **pending (owner sets the password)**.
- A2 PHP 8.3, Imagick active, `expose_php` off, `display_errors` off, `log_errors` on, session cookies Secure/HttpOnly + strict mode.
- A3 SSH active (port 65002); key login **pending** (owner adds a public key).
- A5 server cron `* * * * * /usr/bin/php ~/domains/techdosedaily.com/public_html/staging/wp-cron.php` saved; `DISABLE_WP_CRON` pending (A6).
- A8 daily backups on; manual pre-deploy backup started 2026-10-04.
- Staging copied Hostinger add-on plugins (AI, Easy Onboarding, Reach, Tools): not approved, removed by `staging-bootstrap.sh`.
- A1 directory protection on. A3 SSH key login works. A6 `staging-config.sh` applied (staging env, new salts, log outside web root, wp-config 600). A7 root + uploads `.htaccess` written.
- B1–B2 `staging-bootstrap.sh` completed (SHA-256 OK, approved plugins only, Core + theme 0.9.0, settings, permalinks, Home/Latest).
- C1 LiteSpeed per §3.4 (`staging-plugins.sh`); object cache on (Memcached). C3 MailPoet active, double opt-in on; **sender still a Gmail address — must become a site mailbox (C4)**.
- D1 `admin` replaced by personal administrator `tdd-ash-7k2`; D2 Two Factor setup to confirm.
- Next: run `gates.py --stage staging` on the server (`/opt/alt/python311/bin/python3`).

Progress log (2026-10-05)
- First gate run (`~/release-0.9.0/run-gates.sh`): directory login rejected → every HTTP gate checked the 401 page. Report discarded; `gates.py` now stops on a 401 and the wrapper verifies the login first (3 tries).
- Gate fixes: U1 flagged every `tdd-` login (the real admin is `tdd-ash-7k2`) — now only the suites' `tdd-sec-/tdd-xss-/tdd-cache-` accounts and `@example.*` emails. I4 passes on a noindex staging home with no canonical (Yoast omits it on noindex; production still needs exactly one). C3 reports a missing page (404) as TODO on staging, FAIL on production. New **P7**: no hPanel leftovers in the web root.
- Staging fixes (backup `~/backups/tdd-staging-20261005-032614-gatefix`): deleted hPanel `create_autologin_*.php` (one-time login script left in the web root) and Hostinger `default.php`; removed Hostinger's `mod_expires` section from the `# BEGIN WordPress` block — it capped CSS/JS at 1 month over LiteSpeed's 1 year (C4). `staging-config.sh` now strips it on every run.
- Gate run `tests/staging/out/gates-staging-20261005-032724Z.md`: **50 pass · 5 to do · 3 fail**. U2 (2FA) pass. TODO: C3 `/contact/` and `/newsletter/` (pages not created), D2, Y2, Y3.
- **Open — S1 (3 fails):** Hostinger's server sends `Content-Security-Policy: upgrade-insecure-requests` (origin LiteSpeed and CDN, also on the live site and on 401s), which replaces Core's CSP (`frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'`). `X-Frame-Options: SAMEORIGIN` still arrives, so clickjacking stays blocked; base-uri/object-src/form-action are lost. Owner to decide: set the full CSP in `.htaccess` (skipping `/embed/`) or ask Hostinger support. Not waived.
- Review of staging (2026-10-05, owner) → **release 0.9.1** deployed (backup `~/backups/tdd-staging-20261005-033733-pre-0.9.1`: database dump + 0.9.0 code; note `wp db export` fails silently on this host — use `mysqldump` with `MYSQL_PWD`):
  - Yoast built **no indexables on staging** (it only indexes when `WP_ENVIRONMENT_TYPE` is production), so the static front page kept its pre-front-page URL: `og:url` and schema `@id` were `/home/`. Core now allows indexables on staging; reindexed; `og:url` = `/`. Gates I8 (home og:url) added. Y2 to re-run after representative content exists.
  - MailPoet `subscriptions` / `captcha` pages: noindex and excluded from Yoast and core sitemaps (Core). Gate I9 added.
  - Homepage without a lead story had no H1 (the lead headline is the H1 by design): one screen-reader-only H1 (site name) until a lead exists. Gate I7 added. No visual change.
  - H3 (HSTS) was PASS with "absent" on staging → now TODO on staging, FAIL on production (Core sends HSTS on production only).
  - **S1 CSP:** merged policy sent from `.htaccess` (`upgrade-insecure-requests` + Core's `frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'`); `/embed/` gets it without `frame-ancestors`; wp-admin should keep the host default — gates S2/S3 check this on the authenticated run (unauthenticated 401 responses show the public policy on wp-admin too).
  - C3/C4 MailPoet sender + reply-to = `newsletter@techdosedaily.com` (was a Gmail address). DNS: SPF `include:_spf.mail.hostinger.com ~all`, DKIM `hostingermail-a` (Hostinger mail), DMARC `p=none`. MailPoet still sends with **PHP mail** — switch to SMTP through the newsletter mailbox (owner enters the mailbox password in MailPoet → Settings → Send With) and verify SPF/DKIM/DMARC pass on a real test message.
- Owner decisions (2026-10-05) applied on staging with `deployment/owner-settings.php` (backup `~/backups/tdd-staging-20261005-035205-owner-settings`):
  - Contact: all five routes → `contact@techdosedaily.com` (receiving only; routes kept separate). Contact sender stays WordPress default until a separately authenticated SMTP mailbox exists. Tips are not described as secure (the Contact page states no secure channel exists).
  - Section one-liners set for all nine sections (owner wording).
  - Newsletter: MailPoet list #3 renamed **TechDoseDaily Newsletter** (name shows in the confirmation email / manage-subscription page), selected in Site settings; double opt-in on.
  - Empty **draft** pages with final slugs/templates: privacy-policy (selected in Settings → Privacy), contact, newsletter, editorial-standards, corrections-policy, source-policy, ai-use-policy, terms (“Terms of Use”), advertise. No wording written.
  - Site Icon: owner's 512×512 mark (attachment 20).
  - **[Sample] review dataset loaded on staging only** (`deployment/staging-samples.sh load`: phase2-content, phase3-article, phase3-sections, phase3-home, phase6-seo — 51 stories, sample authors, images, placements, view counts; not menus, gallery or page copy). Noindex + out of sitemaps (Core `_tdd_fixture`). Gates D1/U3/E2 are TODO on staging and **FAIL on production** until `staging-samples.sh remove`.
  - Y2: Yoast reindex with content — completed (104 indexables). Re-run after real content replaces the samples.
- Gate run `tests/staging/out/gates-staging-20261005-035624Z.md`: **57 pass · 8 to do · 0 fail**. S1 passes on home, login, an article and 404 (merged CSP); S3 embed frameable; I7 one H1; I8 og:url = root; I9 no MailPoet URLs in sitemaps; Y2, Y3 pass. TODO: H3 HSTS (production only), E2/D1/U3 sample dataset, U2 `mira-sample` editor without 2FA (sample user), C3 `/contact/` and `/newsletter/` (drafts → 404), D2 (Privacy Policy, policy pages, sample data).
- S2: the wp-admin exception never matched on LiteSpeed (SetEnvIf does not fire for wp-admin requests here), so wp-admin has carried the merged policy all along. The no-op rule is removed; wp-admin uses the same policy (`form-action 'self'`, `frame-ancestors 'self'`). **Owner: click through wp-admin (MailPoet, Yoast, LiteSpeed, Two Factor, media upload) and report any blocked form or console CSP error.**
- 0.9.2 on staging: Tips route "General, non-sensitive story tips."; MailPoet list "Tech Dose Daily Newsletter"; "Terms of Use" approved; MailPoet usage-data sharing confirmed off (never sent; pinned in owner-settings.php, gate M2).
- **E2 passed (2026-10-05 05:36 UTC): `security_test.py` 193/193, `xss_test.py` 50/50** against staging behind Basic Auth + noindex — `deployment/staging-security.sh`, log `tests/staging/out/security-20261005-053628Z.log`. Snapshot `~/backups/tdd-staging-20261005-053628…-pre-security` (DB + files). Harness for the run only: `mu-capture-mail.php` sha256 `e956229b42dc192b7e4753515e1f4ad98509134be1865995f0fc8d9501ae9616`, `mu-fake-newsletter-provider.php` sha256 `7ed98bc7de853df1aee06911649b116c668222f4b857d14406843e564c729d1f`. Contact/Newsletter drafts published for the run and set back to draft; temporary About (team block only) deleted. Leftover check: harness 0, test users 0, suite fixtures 0, MailPoet test subscribers 0, sending queues 0, XSS marker 0, published pages home + latest only, no inactive plugins. Gates after: 58 pass · 8 to do · 0 fail (D1/P2/P6 as expected).
  - Earlier attempts (3) exposed harness problems on LiteSpeed/Memcached, all fixed in the suites/script: LiteSpeed purge notices in WP-CLI output, block.json path, MailPoet syncing throwaway accounts as subscribers, empty test pages, throttle counters in the object cache, nginx-only cache markers, and a section-settings test that reset staging's desk values (restored from snapshot). A crashed attempt left one XSS-payload post (#108, behind Basic Auth) — deleted; cleanup now removes and scans for the marker.
- **Open, owner:** CSP click-through as Editor and Administrator (public site, login, block editor, media picker, placements, sections, reporter profiles, Site settings; console CSP errors; approved embed blocks). C4: MailPoet SMTP via the newsletter mailbox + one test per contact route over authenticated SMTP. Privacy Policy must mention MailPoet's per-subscriber open/click tracking (level "full") or the level is lowered.
- **C5 probe (2026-10-05/06, `deployment/staging-ip-probe.sh`, temporary token-gated MU-plugin, removed afterwards; addresses masked):**
  - Through Hostinger's CDN, PHP's `REMOTE_ADDR` is the real client: the server over IPv4 arrived as its own IPv4, the owner's browser as its own address, the server over IPv6 as its IPv6. LiteSpeed already maps `X-Forwarded-For`/`X-Real-IP`; `tdd_core_client_ip()` needs no mapping. A forged `X-Forwarded-For` sent **through the CDN is ignored** (the CDN appends the real sender, which PHP uses).
  - **FAIL — origin accepts forged addresses:** a request sent straight to the origin IP (145.79.x.x, Host: staging…) with `X-Forwarded-For: 203.0.113.9` reached PHP as `REMOTE_ADDR = 203.0.113.9`. LiteSpeed trusts the header from any sender, so anyone who learns the origin IP can pick a new address per request. Impact: per-IP throttles are bypassable there. Contact (60/h) and newsletter (200/h) still have site-wide ceilings; **Most Read view counting has none** (per-IP 120/min only), so rankings could be inflated. Fix belongs at the host: LiteSpeed "Use client IP in header" → *Trusted IP only* (Hostinger CDN ranges), or origin reachable only from the CDN. Core mitigation proposed: a site-wide ceiling for counted views.
  - Two dropped SSH sessions left the probe behind once (and the curl login file, 600, outside the web root): removed by hand; the script now cleans up on hang-up, deletes the login file before waiting, and the probe expires after 15 minutes.
- **Release 0.9.3 on staging (2026-10-06, backup `~/backups/tdd-staging-20261006-182347-pre-0.9.3`):** Most Read counting ceilings per UTC hour (per story 1000, site-wide 5000; Site settings / `tdd_core_view_ceilings`), atomic per counter; over a ceiling the view is discarded, the beacon still answers 204, one log line per type per hour, action `tdd_core_view_ceiling_reached`. Gates P8 (MU-plugin inventory by filename + SHA-256, `tests/staging/mu-plugins-approved.json`) and P9 (native updater, core minor, Yoast/LiteSpeed/MailPoet auto-update, Core/theme Git-only).
  - `popularity_ceiling_test.py` (log `tests/staging/out/popularity-20261006-182627Z.log`): **18 pass · 1 fail.** Pass: per-story and site ceilings with 20 rotating addresses, no counter drift, monitoring action per discard, one log line per type, dedupe, hour-rollover recovery, configuration, 8 simultaneous processes land exactly on the ceiling, 20 concurrent forged-address HTTP requests stay within the ceiling, beacon always 204 and never from cache, story page 200 and from the page cache after the ceiling. **Fail (expected until the host changes LiteSpeed): origin retest — five forged addresses sent straight to the origin counted as 3 readers (capped by the test ceiling of 3), so the origin still trusts X-Forwarded-For.** Clean-up: no test stories, 2099 rows or option left.
  - **E2 re-run on 0.9.3: `security_test.py` 193/193, `xss_test.py` 50/50** (log `tests/staging/out/security-20261006-182828Z.log`); clean-up and leftover check all zero. Gates after (`gates-staging-20261006-183428Z.md`): 60 pass · 8 to do · **4 fail, all from Hostinger Smart Auto Updates**: P8 `hostinger-auto-updates.php` not in the MU-plugin inventory; P9 WordPress updater disabled; P9 Yoast/LiteSpeed/MailPoet not auto-updating; P3 MailPoet 5.41.0 pending (5.40.0 installed) because the updater is off. Left for the native updater to install after the hPanel switch, which also proves it works.
  - 2026-10-07: owner switched hPanel to **Native auto-updates**. Checked right after on staging and production: `hostinger-auto-updates.php` still present (unchanged checksum and dates), `automatic_updater_disabled` still forced → updater still off on both. Not removed by hand (owner decision). Staging per-plugin auto-updates set as decided: Yoast, LiteSpeed Cache, MailPoet **on**; Core plugin and theme **off** (Git deployment only); Two Factor off pending a decision.
  - 2026-10-07 Hostinger support (Débora) **confirmed** that a request sent directly to the origin can set a false client IP, and escalated "trust forwarded IPs only from Hostinger CDN, or restrict direct-origin traffic" for both sites to the technical team. **Hostinger ticket #23553480** (opened 2026-10-07; support will contact the owner with the outcome). Interim advice (CAPTCHA; per-email limits) reviewed: forms already have honeypot, timed token and site-wide hourly ceilings (contact 60, newsletter 200) that hold under forged addresses; MailPoet caps confirmation emails at 3 per unconfirmed address (`MAX_CONFIRMATION_EMAILS`), so one inbox cannot be flooded. CAPTCHA not added (third-party data, page weight, new UI on approved forms) unless abuse appears. `x-hcdn-cache-status` cannot authenticate CDN origin requests.
  - **Release 0.9.4 on staging (2026-10-07, backup `~/backups/tdd-staging-20261007-044931-pre-0.9.4`): newsletter per-address limit** — max 3 attempts per normalised address per 24 h, ≥ 15 min apart, on top of per-IP and site-wide limits; HMAC key (lowercase, no +tag, Gmail dots/googlemail folded), timestamps only, INSERT IGNORE lock; over the limit the provider is not called and the reader gets the byte-identical neutral answer in the same minimum time. **E2 on 0.9.4: `security_test.py` 201/201 (8 new per-address checks incl. HTTP byte-identical answer, timing, provider not reached, identical no-JS redirect, 6-way race → 1), `xss_test.py` 50/50**; leftover check all zero (log `tests/staging/out/security-20261007-045043Z.log`). Gates 60/8/4 — the 4 fails are Smart Auto Updates on staging (P3 MailPoet 5.41.0, P8, P9 ×2).
  - Native auto-updates: **production switched** (2026-10-07: `hostinger-auto-updates.php` removed by Hostinger, updater active, `WP_AUTO_UPDATE_CORE` 'minor' unchanged; hPanel core = "Minor updates only", themes/plugins left to WordPress). **Staging not yet:** owner selected Native for staging in hPanel (2026-10-07 ~05:00 UTC); 10 minutes later `hostinger-auto-updates.php` was still present (same checksum) and the updater still disabled — the hPanel setting does not seem to reach the staging copy in `public_html/staging`. To raise on Hostinger ticket #23553480; not removed by hand. Staging per-plugin auto-updates: Yoast, LiteSpeed Cache, MailPoet, **Two Factor** on; Core plugin and theme off.
- **Regression suites on staging** (`deployment/staging-regression.sh`: snapshot → drafts published for the run → suites → **database restored from the pre-run snapshot** → leftover check → gates):
  - Run 1 (2026-10-07 05:09): **markup 75/75**. SEO and cache suites crashed (cache: Python 3.12-only f-string on the server's 3.11; SEO: reported nothing on crash).
  - Run 2 (05:25, log `tests/staging/out/regression-20261007-052532Z.log`): SEO 226 pass / 57 fail, cache crashed (recursion bug in my cache-state helper). Restore verified exact: all 27 table INSERT statements byte-identical to the pre-run snapshot. The 57 SEO fails were all environment or test artefacts, none a site defect: (a) staging is noindex by requirement → `noindex, nofollow`, no canonical link / rel=prev (Yoast omits them on noindex) ≈ 31; (b) the suite resolved `@id` references only against top-level nodes, while the Site Icon logo is a nested node (`Organization.logo` with `@id …/#logo`), valid JSON-LD ≈ 18; (c) **LiteSpeed delivers purges made from WP-CLI/cron with a loopback to `admin-ajax.php`; staging's Basic Auth refuses it (401), so pages stayed cached after CLI changes** (owner switch, edited descriptions, section moves) ≈ 10 — staging-only, production has no Basic Auth (to verify there); (d) no About page, an attachment ID from the local site — 2.
  - Fixes: the SEO suite asserts the staging behaviour on a noindex site (and the full indexable checks on production), resolves nested `@id`s, never crashes on a missing graph; `tests/staging_env.py` relays LiteSpeed's purge loopback with the login after WP-CLI writes; runner reads DB credentials once (never from a stdin carrying a dump), restores exactly once, delivers its own purge.
  - **Run 3 (2026-10-07 14:35, log `tests/staging/out/regression-20261007-143542Z.log`): SEO 297/300, cache 34/34 (G1 on real hosting), markup 75/75 (run 1).** Cache: publish purges and shows the story at once; Breaking ends, scheduled story goes live by cron and placement start/expiry all flip on time without a purge (TTL capped at the next change); forms, no-JS results, previews and logged-in pages never cached; perf/lab traffic not counted in Most Read. Hostinger CDN answered `MISS` while LiteSpeed served a hit — the CDN appears to pass HTML through (recheck on production). SEO fails are content not on staging: no About page (JSON-LD), Editorial Standards intro copy (og:description fallback), and a local attachment ID (test fixed to use any image). Database restored once from the snapshot; leftover check clean.
  - **Regression step 1 (2026-10-07):** SEO hooks 14/15 (`seo_hooks.php`; the remaining fail is the Editorial Standards intro copy — content). **SEO parity: PASS** — 50 URL×owner pairs (both owners, 25 URLs incl. sitemaps), every first copy after the purge fresh, 34 second copies from the page cache, SEO output identical fresh vs cached (log `regression-20261007-151055Z.log`). **Schema.org/rich-result validation (offline, `validate.mjs --dir`, schema.org v30.1): 28 pages × both owners, 0 errors**, 41 warnings (40 optional image-licence fields; 1 Yoast home breadcrumb with a single item). Graphs saved in `tests/staging/out/graphs-20261007-151130Z/`.
  - **Found and fixed: Yoast's graph had no publisher** — no Organization node, Articles without `publisher` — because the Yoast Organization logo was never set (the icon did not exist at bootstrap). SEO-SCHEMA.md §7 requires "Organization, name Tech Dose Daily, logo = final icon". Set on staging (logo = Site Icon #20); Yoast now prints Organization + logo and `publisher` on Article and WebSite. `owner-settings.php` applies it on production; new gate **Y4** checks it.
  - **Staging security validation stays open** until the origin client-IP retest passes (host change requested from Hostinger support).
- **Hostinger Smart Auto Updates** (`mu-plugins/hostinger-auto-updates.php` v1.0.8, sha256 `2492da0336ed34b4…`): appeared on staging 2026-10-06 16:53 (production has had it since install). It **disables WordPress automatic updates (incl. minor security releases)** — contrary to `WP_AUTO_UPDATE_CORE=minor` and SECURITY.md — and routes WordPress.org downloads/API through Hostinger hosts. Gate P6 does not catch it (not `mu-*`). Owner decision pending: keep Hostinger-managed updates (then SECURITY.md and a gate record that) or turn it off in hPanel.
- **Next:** C5 real client IP behind Hostinger's CDN (rate limits are per IP), then the regression suites (SEO, cache timing, markup, visual widths), Lighthouse/WebPageTest, Rich Results, restore test.
- Mailboxes confirmed in hPanel (owner screenshot 2026-10-05): `contact@techdosedaily.com` (active, 8 forwarders) and `newsletter@techdosedaily.com` (active). Plan: Starter Business Email **free trial**, expires 2027-05-16 — renew before then or contact/newsletter mail stops. C4 still needs SMTP for MailPoet and a real test message per contact route.

## How a gate run works

```sh
# HTTP gates (any machine with Python + requests)
BASE=https://<staging-host> TDD_BASIC_AUTH=user:pass python3 tests/staging/gates.py --stage staging
# + server gates over SSH (hPanel → Advanced → SSH Access gives host, port, user)
WP=tests/staging/remote-wp TDD_SSH="-p <port> <user>@<host>" TDD_WP_PATH=<staging root> \
BASE=https://<staging-host> TDD_BASIC_AUTH=user:pass python3 tests/staging/gates.py --stage staging
```

Each run writes `tests/staging/out/gates-<stage>-<time>.md`. A gate marked **TODO** on staging becomes a
**FAIL** with `--stage production`.

## A. Staging environment (hPanel)

| # | Gate | Who | Verified by |
|---|---|---|---|
| A1 | Staging created with Hostinger's WordPress staging tool, **password-protected** (hPanel directory protection) | hPanel (browser) | HTTP 401 without credentials |
| A2 | PHP 8.2/8.3 with Imagick; `expose_php` off; `display_errors` off | hPanel → PHP config | [W2, W4] |
| A3 | SSH enabled with a key (no password logins); WP-CLI available | hPanel → SSH | `remote-wp core version` |
| A4 | Free SSL active; Force HTTPS on | hPanel → SSL | [H1, H2] |
| A5 | Server cron every minute (`php …/wp-cron.php`), `DISABLE_WP_CRON` | hPanel → Cron Jobs + wp-config | [W3, R1] |
| A6 | `deployment/wp-config-additions.php` applied; **staging salts ≠ production**; `WP_ENVIRONMENT_TYPE` = staging | SSH | [W1, W2, W3] |
| A7 | `deployment/htaccess-root-additions.txt` + `deployment/htaccess-uploads.txt` in place | SSH / File Manager | [X1, X3, X4, X7] |
| A8 | Hostinger daily backups on; one manual backup taken before every deploy | hPanel → Backups | screenshot / note |

## B. Deploy (clean install — no sample content)

| # | Gate | Verified by |
|---|---|---|
| B1 | Release zips built from a commit (`npm run release`), SHA-256 checked on the server | `staging-bootstrap.sh` (integrity step) |
| B2 | `deployment/staging-bootstrap.sh` run: approved plugins only, theme + Core active, demo content removed, core + Yoast settings | [P1, P2, P5, Y1, W5] |
| B3 | No fixtures, sample users, `example.com` data, test harness or drop-ins on the server | [D1, E2, U1, P6] |
| B4 | Plugins and core up to date | [P3, P4] |

## C. Configuration

| # | Gate | Verified by |
|---|---|---|
| C1 | LiteSpeed Cache per `PERFORMANCE.md` §3.4 (logged-in cache, lazy load, minify/combine, CSS/JS optimisation, guest mode, ESI, image optimisation **off**) | [L1] |
| C2 | Anonymous pages cached; Contact, form results, logged-in never cached | [C1–C3] + `tests/perf/cache_test.py` on staging (SSH mode) |
| C3 | MailPoet: sender = site mailbox, **double opt-in on**, list set in Site settings | [M1] + Launch readiness |
| C4 | SMTP/mail: SPF, DKIM, DMARC for the sender domain; contact routes delivered to real role inboxes | DNS check + a real test message per route |
| C5 | Real client IP mapped if a CDN/proxy is in front (`tdd_core_client_ip`) | throttle test on staging |
| C6 | Structured-data owner decided (Yoast default) | [I5] + Site settings |

## D. People and access

| # | Gate | Verified by |
|---|---|---|
| D1 | Personal accounts only — no `admin` login; least-privilege roles; nicenames ≠ logins | [U1] + Launch readiness |
| D2 | **2FA (Two Factor) on for every administrator and editor** | [U2] |
| D3 | Registration off, default role subscriber | [W5] |

## E. Security verification (on staging)

| # | Gate | Verified by |
|---|---|---|
| E1 | Server rules, headers, exposure decisions | [S1, X1–X7] |
| E2 | `tests/security/security_test.py` and `xss_test.py` against staging, **before real content exists**, with the two harness mu-plugins installed only for the run and removed after (the suite refuses to run without them, so no real mail or subscribers are created) | 193/193 · 50/50 |
| E3 | securityheaders.com grade A; SSL Labs grade A | external report |
| E4 | Backup **restore test** to a second staging copy; regression + security suites pass on the restored copy | restore log |

## F. SEO (carried forward from Phase 6)

| # | Gate | Verified by |
|---|---|---|
| F1 | Yoast settings `SEO-SCHEMA.md` §7 | [Y1] |
| F2 | **Yoast SEO data optimisation** run | [Y2] |
| F3 | **Final Site Icon** uploaded (also the Core publisher logo) | [Y3] |
| F4 | One canonical, one JSON-LD graph, titles and OG on every template | [I4–I6] + spot checks |
| F5 | **Google Rich Results Test** + validator.schema.org on real staging URLs: one of each story type, a section, an author, About | manual record in this file |
| F6 | Staging stays noindex; production: indexable, sitemap listed in robots.txt, sitemap submitted to Search Console | [I1–I3] |

## G. Performance (carried forward from Phase 7)

| # | Gate | Verified by |
|---|---|---|
| G1 | **Production-cache verification**: HIT for anonymous, purge on publish, Breaking/placement boundaries flip on time | [C1] + `cache_test.py` (SSH mode) |
| G2 | Budgets `PERFORMANCE.md` §2 on real hosting: mobile LCP ≤ 2.5 s, CLS ≤ 0.05, TBT ≤ 100 ms, ≤ 250 KiB transfer, uncached TTFB ≤ 600 ms | `BASE=… node tests/perf/lh.mjs tests/perf/pages.json` + PageSpeed Insights / WebPageTest |
| G3 | Compression and long-lived static caching | [C4, C5] |

## H. Content and launch readiness

| # | Gate | Verified by |
|---|---|---|
| H1 | Real editorial content: sections' one-liners, policy pages (Editorial Standards, Corrections, Privacy, Source Policy, Advertise …), About, Contact, Newsletter page copy — no design copy, no fictional people or numbers | [D2] Launch readiness (every item done) |
| H2 | Real staff profiles (photos, bios) and About listing; Featured Reporting where wanted | Launch readiness |
| H3 | Media kit / secure-tip details real or hidden | Launch readiness |

## I. Go/no-go

Production opens only when `gates.py --stage production` reports **0 fail, 0 to do** on the production
URL, E2–E4 and F5 are recorded here, and the site owner signs off below.

| Date | Run | Result | Sign-off |
|---|---|---|---|
| | | | |

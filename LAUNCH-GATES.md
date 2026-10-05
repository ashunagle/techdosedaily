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

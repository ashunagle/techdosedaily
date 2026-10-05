# Handover — staging and launch preparation (paused 2026-10-04)

For the next session (Claude Code on the owner's PC, in `D:\TechDoseDaily\06-WordPress`). Read `CLAUDE.md`,
`LAUNCH-GATES.md` and `SECURITY.md` §8 first. The rules there still apply: no fabricated content, no secrets in
Git, never weaken a security control for convenience, stop for review after each step.

**Standing instruction from the owner:** do not open the site publicly until every staging launch gate in
`SECURITY.md` §8 and the carried-forward SEO/performance checklists passes.

## 1. Where things stand

| Area | State |
|---|---|
| Phase 8 (security) | Approved and closed. Release **0.9.0** built from a commit (`dist/` zips + `SHA256SUMS`). |
| Live `techdosedaily.com` | Hostinger default install, **closed**: Hostinger maintenance mode ON ("Coming Soon" for visitors), *Discourage search engines* ON, XML-RPC and application passwords OFF, Force HTTPS ON. Its own `admin` account still exists (handle at production setup). |
| Staging | `https://staging.techdosedaily.com` — Hostinger staging copy, folder `~/domains/techdosedaily.com/public_html/staging`, own database. Release 0.9.0 deployed, configured, **gate run not yet done**. |
| Temporary domain | `blue-kudu-754432.hostingersite.com` (Hostinger preview of the main site; `hostinger-preview-domain` mu-plugin). |

## 2. Access (no secrets here)

- **SSH:** `ssh -p 65002 -i "C:\Users\Ashvinee Nagle\.ssh\tdd_hostinger" u137034159@145.79.58.37`. The key is
  added in hPanel → SSH Access. The private key never leaves the PC and is never committed.
  Claude Code can run `ssh`/`scp` itself with this key (if the key has a passphrase, the owner must load it into
  `ssh-agent` or type it).
- **Staging is behind hPanel directory protection** (Password Protect Directories → `/staging`). The browser
  asks for that username/password first, then WordPress. The owner holds both; never ask for them in chat —
  scripts read the directory credentials with `read -rsp` into `TDD_BASIC_AUTH`.
- **WordPress:** one administrator, `tdd-ash-7k2` (ID 2, `ashvineenagle@gmail.com`, nicename `ashvinee-nagle`).
  The copied `admin` account was deleted (`--reassign=2`). Two Factor is installed; owner was setting up TOTP +
  backup codes — **confirm it is on** (gate U2).
- **Release files on the server:** `~/release-0.9.0/` (both zips, `SHA256SUMS`, `staging-bootstrap.sh`,
  `staging-config.sh`, `staging-plugins.sh`, `gates.py`). Backups made by the scripts: `~/backups/tdd-staging-*`.
- **Python on the server:** `/opt/alt/python311/bin/python3` (there is no `python3` on PATH). PC: Python 3.12.

## 3. Done (with gate IDs from LAUNCH-GATES.md)

- **A1** staging created; directory password protection on (verified: browser prompt; `.htaccess` has the Auth lines).
- **A2** PHP 8.3.33 (web), Imagick on, `display_errors` off, `log_errors` on, session cookies Secure/HttpOnly/strict;
  `expose_php` off in hPanel (the SSH CLI still reports 1 — CLI uses its own ini; verify the web header in W-gates).
- **A3** SSH active with key login.
- **A4** SSL + Force HTTPS (hPanel).
- **A5** server cron `* * * * * /usr/bin/php /home/u137034159/domains/techdosedaily.com/public_html/staging/wp-cron.php`; `DISABLE_WP_CRON` true.
- **A6** `deployment/staging-config.sh` applied: `WP_ENVIRONMENT_TYPE=staging`, debug off, debug log in
  `~/logs/wp-debug-staging.log` (outside every web root), `DISALLOW_FILE_EDIT`, `FORCE_SSL_ADMIN`,
  `WP_AUTO_UPDATE_CORE=minor`, **new staging-only salts**; `wp-config.php` is mode 600.
- **A7** root `.htaccess` (`# BEGIN TechDoseDaily` block, keeps hPanel Auth, LSCACHE, WordPress blocks) and
  `wp-content/uploads/.htaccess` written. HTTPS redirect intentionally left to hPanel Force HTTPS (a mod_rewrite
  `%{HTTPS}` redirect can loop behind Hostinger's CDN).
- **A8** daily backups on; manual backup taken 2026-10-04 before deploy.
- **B1–B2** `deployment/staging-bootstrap.sh` run: SHA-256 OK; Yoast 28.6, LiteSpeed 7.9.1, Two Factor 0.17.0,
  MailPoet 5.40.0, Core + theme 0.9.0 active; Hostinger AI / Easy Onboarding / Reach / Tools removed (not approved);
  demo content removed; timezone, registration off, comments closed, `blog_public=0`; permalinks
  `/%category%/%postname%/`; Home + Latest pages as front/posts page; Yoast §7 settings; schema owner `yoast`.
- **C1** `deployment/staging-plugins.sh`: LiteSpeed per PERFORMANCE.md §3.4 (all optimisations off;
  `optm-ccss_gen` does not exist in 7.9.1 — harmless, async CSS is off). Object cache ON (Memcached, LiteSpeed
  drop-in) — allowed by §3.4.
- **C3 (partly)** MailPoet active, double opt-in ON.
- **D1, D3** personal admin only; registration off.

Script fixes made during deploy (already in the repo): bootstrap zip glob matched both zips; plugin steps made
idempotent; unapproved-plugin removal; `staging-config.sh` and `staging-plugins.sh` added; log-path note fixed
in `wp-config-additions.php`.

## 4. Next steps, in order

1. **Done 2026-10-05** — 50 pass · 5 to do · 3 fail (S1 CSP, owner decision pending); see `LAUNCH-GATES.md`
   progress log. Re-run any time with `ssh -t … "bash ~/release-0.9.0/run-gates.sh"` (prompts for the directory
   login, verifies it, then runs the gates). Original instructions:
   ```
   cd ~/domains/techdosedaily.com/public_html/staging
   PY=/opt/alt/python311/bin/python3
   $PY -c "import requests" || $PY -m pip install --user requests
   read -rsp "Directory login as user:pass > " TDD_BASIC_AUTH; echo; export TDD_BASIC_AUTH
   WP="wp --path=$PWD" BASE=https://staging.techdosedaily.com $PY ~/release-0.9.0/gates.py --stage staging
   ```
   Report written to `~/release-0.9.0/out/gates-staging-*.md`; copy it into `tests/staging/out/`. Fix every
   FAIL; TODOs are expected on staging. Things to watch: P6 (host mu-plugin `hostinger-preview-domain` and the
   LiteSpeed `object-cache.php` drop-in are host/plugin files, not test harness — if P6 flags them, adjust the
   gate to allow exactly these two and document it); C5 (real client IP behind Hostinger CDN); W-gates
   (`expose_php` on the web response).
2. **MailPoet sender (C3/C4):** currently `ashvineenagle@gmail.com` — mail sent as @gmail.com from Hostinger fails
   SPF/DKIM/DMARC. Owner creates a site mailbox (e.g. `newsletter@techdosedaily.com`, hPanel → Emails), sets it
   in MailPoet → Settings → Basics, picks the list in Site settings; check SPF/DKIM/DMARC DNS.
3. **Security suites against staging (E2)** before any real content: install the two harness mu-plugins only for
   the run, `TDD_ADMIN_ID=2`, run `tests/security/security_test.py` (193) and `xss_test.py` (50) with
   `WP=tests/staging/remote-wp` (or on the server), then **remove the harness** and re-run gate P6.
4. **Pages (see §5):** create the structural pages and check every template on staging against `VISUAL-QA.md`.
5. **Carried forward:** Site Icon (Y3), Yoast data optimisation (Y2), Rich Results + validator.schema.org on real
   URLs (F5), Lighthouse on staging (`BASE=… node tests/perf/lh.mjs`, G2), `cache_test.py` in SSH mode (G1),
   securityheaders.com + SSL Labs (E3), backup restore test to a second staging copy (E4).
6. Update `LAUNCH-GATES.md` progress log after each step; commit with separate PowerShell commands (PowerShell 5
   rejects `&&`).

## 5. Existing pages on staging (owner's screenshots, 2026-10-04, logged in)

- **Homepage renders with the theme:** header (logo, 9 section links AI … Guides, search, Subscribe), the
  newsletter panel ("Your Daily Dose of Technology", email field, Subscribe, "Free. Unsubscribe anytime."), and
  the dark footer (logo, tagline, Categories column, © 2026 Tech Dose Daily).
- **Main column is empty** — expected on a clean install (no stories). Before launch, check that the homepage's
  no-content state matches the approved design / `VISUAL-QA.md` and is not shown publicly empty; it fills once
  real stories exist. Do not add placeholder stories.
- **Footer shows only the Categories column.** The other footer columns depend on pages/menus that do not exist
  yet. Compare with the approved footer once the pages exist.
- **Only Home and Latest exist as pages.** Still to create (titles/slugs per `IMPLEMENTATION-MAP.md` and
  `PRE-LAUNCH-PLACEHOLDERS.md`; owner supplies the real copy — never ship design copy): About, Contact, Newsletter,
  Editorial Standards, Corrections, Privacy, Source Policy, Advertise, and the other policy/static pages listed
  there. Check with `wp post list --post_type=page --fields=ID,post_title,post_name,post_status`.
- **Red dot in the admin bar** next to the Yoast icon — a Yoast (or LiteSpeed) notification; open it and record
  what it says (likely "SEO data optimisation" — gate Y2).
- Verify on staging: a section page, `/latest/`, search, 404, `/contact/` (never cached), `/newsletter/` form
  with no JavaScript, and the mobile layout (360–430 px).

## 6. Do not

- Do not turn off "Discourage search engines" or maintenance mode on the live site, or remove staging's directory
  password, until `gates.py --stage production` reports 0 fail / 0 to do and the owner signs off in LAUNCH-GATES §I.
- Do not use the Hostinger staging **Publish** button to push staging over production until the production
  checklist is ready (it would overwrite the live database and files).
- Do not commit `wp-config.php`, keys, passwords, or the gate reports if they contain hostnames with credentials.

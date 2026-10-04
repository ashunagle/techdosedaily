# Development setup

## Versions

| Component | Version | Notes |
|---|---|---|
| WordPress | 7.1.x (built and tested against 7.1.2) | Theme header: requires 6.6+, tested up to 7.1 |
| PHP | 8.1 minimum; 8.3 recommended | Phase 1 was tested on PHP 8.3.6. Set the same version in Hostinger hPanel → PHP Configuration. |
| Database | MySQL 8.0 or MariaDB 10.6+ | Use whatever the Hostinger plan provides. Local testing also works with the official SQLite drop-in. |
| Node.js | 20 LTS or newer (tested on 22) | Only needed for the build scripts, not on the server |
| Composer | 2.x | Dev only, for PHPCS with the WordPress coding standards |

## Repository layout (`D:\TechDoseDaily\06-WordPress` = git root)

```
theme/techdosedaily/      the theme (presentation)
plugins/techdosedaily-core/ the data-model plugin (activate before the theme)
design-source/            copies of 01-Design-System/bundle.css + tokens.json used by the build
tools/                    build-tokens.mjs, build-css.mjs, sync-design.mjs, zip-theme.mjs
tests/                    shots.py (responsive screenshots + overflow), fixtures/ (local-only seed data)
plugins-notes/            PLUGIN-DECISIONS.md
deployment/               DEPLOYMENT-CHECKLIST.md (Phase 8)
*.md                      IMPLEMENTATION-MAP, VISUAL-QA, PRE-LAUNCH-PLACEHOLDERS, EDITORIAL-WORKFLOW, CLAUDE
```

## Git (first time)

The remote tools can't write inside `.git`, so the repository is initialised on your machine. From `D:\TechDoseDaily\06-WordPress`:

```
git init -b main
git status --ignored --short    # check: no .env, SQL dumps, uploads, caches, node_modules or vendor
git add -A
git commit -m "chore: establish TechDoseDaily WordPress foundation"
```

Then add a private GitHub remote (`git remote add origin …` and `git push -u origin main`). `.gitignore` already excludes secrets, node_modules, vendor, uploads, caches, SQL dumps and zips. The empty README placeholder folders (`blocks/`, `patterns/`, `theme/README.md`, `deployment/`) from the original folder setup can be committed or deleted; the real blocks and patterns live inside `theme/techdosedaily/`.

## Local WordPress (Windows)

1. Install **LocalWP** (localwp.com). Create a site named `techdosedaily` using PHP 8.3, nginx and MySQL 8.
2. Link the theme into the site rather than copying it. In an elevated PowerShell:
   `New-Item -ItemType SymbolicLink -Path "<LocalSite>\app\public\wp-content\themes\techdosedaily" -Target "D:\TechDoseDaily\06-WordPress\theme\techdosedaily"`
3. Link the plugin the same way: `New-Item -ItemType SymbolicLink -Path "<LocalSite>\app\public\wp-content\plugins\techdosedaily-core" -Target "D:\TechDoseDaily\06-WordPress\plugins\techdosedaily-core"`. Activate **TechDoseDaily Core** first (it seeds sections and story types and creates its tables).
4. Go to WP Admin → Appearance → Themes and activate **Tech Dose Daily**.
5. Go to Settings → Permalinks and choose "Custom structure" `/%category%/%postname%/`. Sections are served at `/ai/` etc. by the plugin, so the category base setting doesn't matter. Settings → Reading: a static front page plus the "Latest" page as the Posts page.
6. Optional sample menus for comparing against the approved designs: `wp eval-file D:\TechDoseDaily\06-WordPress\tests\fixtures\phase1-menus.php` (from Local's "Open site shell"). These are local test fixtures and must never be used on staging or production.
7. Phase 2 fixtures (local only): `wp eval-file tests/fixtures/phase2-content.php` (sample stories, images, placements, synthetic view counts) then `wp eval-file tests/fixtures/phase2-gallery.php` (the "Phase 2 components" page). To exercise the newsletter states without MailPoet, copy `tests/fixtures/mu-fake-newsletter-provider.php` into `wp-content/mu-plugins/` (any address → "Check your inbox" double opt-in state; `instant@example.com` → single opt-in "You're subscribed"; `already@example.com` → already subscribed).
8. Phase 3 fixtures (local only), in this order, with `--user=<an admin>` so block markup is kept: `phase3-article.php`, `phase3-sections.php`, `phase3-home.php`.
9. Phase 4 fixture (local only): `wp eval-file tests/fixtures/phase4-pages.php --user=<an admin>` (Editorial Standards, Advertise, About, Contact, Newsletter with the approved design copy; sample `@example.com` inboxes). To test the contact form without a mail server, copy `tests/fixtures/mu-capture-mail.php` into `wp-content/mu-plugins/` (logs recipient/subject/headers to `wp-content/tdd-mail-test.log`; a sender of `fail@example.com` simulates a delivery failure).
10. Phase 6 SEO checks (local only). For real-Yoast runs, unzip `tests/vendor-plugins/wordpress-seo.zip` (git-ignored local copy) into `wp-content/plugins/` and activate it; the suite detects Yoast and switches to the Yoast checks. `wp eval-file tests/fixtures/phase6-seo.php --user=<an admin>`, copy `tests/fixtures/mu-seo-audit.php` into `wp-content/mu-plugins/`, then `python3 tests/seo/seo_test.py`, `wp eval-file tests/seo/seo_hooks.php`, and for vocabulary/rich-result rules `cd tests/seo && npm install` + download `schemaorg-all-https.jsonld` (schema.org release on GitHub) + `node validate.mjs "$(cat urls.json)"`.
11. Phase 7 performance checks (local only; see `PERFORMANCE.md`). Serve the site through nginx + PHP-FPM with the page cache in `tests/perf/nginx.conf` / `fpm.conf` (adjust paths), copy `tests/perf/mu-local-page-cache.php` (purges that cache) and optionally `tests/perf/mu-query-log.php` into `wp-content/mu-plugins/`, then run `python3 tests/perf/cache_test.py` (~3 min, waits for real time boundaries), `python3 tests/perf/markup_test.py`, `python3 tests/perf/seo_parity.py compare tests/perf/out/seo-snapshot-phase6-modules-off.json`, Lighthouse via `node tests/perf/lh.mjs tests/perf/pages.json` (needs `npm i lighthouse chrome-launcher`, `CHROME_PATH`) and `python3 tests/perf/pw_vitals.py /no-such-page/`. Scripts default to the container paths; set `BASE` and `WP` (the wp-cli command) for your machine. Lab tools must send `X-TDD-Perf-Test: 1` so they are never counted as Most Read views.

## Build

```
cd D:\TechDoseDaily\06-WordPress
npm run sync:design   # copy the approved bundle.css + tokens.json from 01-Design-System
npm install           # once: dev tools for minification (lightningcss, esbuild)
npm run build         # regenerate theme.json, tokens.css and the split stylesheets, then the .min.css/.min.js files
```

The theme serves `assets/css/*.min.css` and `assets/js/*.min.js` (commit them; `npm run minify` alone rebuilds them after editing `layout.css` or a script). Set `define( 'SCRIPT_DEBUG', true );` locally to load the readable sources instead. Fonts: `npm run fonts -- <dir with the original design-system .woff2 files>` (needs `pip install fonttools brotli`).

Never hand-edit the generated files listed in `theme/techdosedaily/README.md`.

## Lint and QA

```
composer install && composer lint:php          # PHPCS, WordPress coding standards
php -l on every PHP file                       # syntax check (passes in Phase 1)
python tests/shots.py http://techdosedaily.local/ tests/output/home   # 360…1920 screenshots + overflow report
```

`tests/shots.py` needs Python 3 with `playwright` installed (`pip install playwright && playwright install chromium`).

## Packaging and deployment

- `npm run zip` produces `dist/techdosedaily-<version>.zip` from the committed theme folder.
- Deploy flow (detail in `deployment/DEPLOYMENT-CHECKLIST.md` in Phase 8): upload the zip to Hostinger staging (or use Git deployment if the plan supports it), run QA, then push code only to production. **Never push a staging database over production after launch.**
- Bump `Version:` in `style.css` and `TDD_VERSION` in `functions.php` on every release; this also busts the CSS cache.

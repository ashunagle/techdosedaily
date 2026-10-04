# Security and privacy (Phase 8)

This phase covers hardening only. Staging deployment and launch setup are not part of it.

- **What it delivers:** hardening of TechDoseDaily Core and the theme, a test suite that sends forged requests as every role, and the hosting, configuration, backup and update requirements that staging must meet.
- **What it preserves:** form, placement, correction, caching, SEO/schema-switching and scheduled-publishing behaviour. The full regression suite passed afterwards (§7).

## 1. Threat model

### Assets

| Asset | Why it matters |
|---|---|
| Published record: stories, **corrections**, Breaking labels, homepage and section placements | Credibility. A rewritten correction or a hijacked homepage is the worst outcome for a newsroom. |
| **Confidential source details** (link, publisher, note on a confidential source), drafts and scheduled stories | Source safety; unpublished reporting |
| Reader data: contact messages, newsletter email addresses | Personal data, sometimes sensitive tips |
| Accounts: logins, account email addresses, roles | Takeover leads to all of the above |
| Operational settings: contact inboxes, sender address, structured-data owner | Mail routing; spoofing; SEO |
| Availability: page cache, forms, view counter | A news site under load, or under attack, during breaking news |

### Actors

| Actor | Can | Main threats |
|---|---|---|
| Anonymous visitor, bot or scraper | GET pages, POST public forms | Spam and floods, enumeration (users, subscribers), injection, Most Read inflation, XML-RPC brute force |
| Third-party website (CSRF) | Make a logged-in staff browser send requests | Forged placement, settings or profile changes |
| Subscriber (if ever created) | Log in, edit own basic profile | Privilege escalation, reading drafts |
| Contributor | Write drafts, no uploads | Publish without review, read others' drafts, stored XSS |
| Author | Publish own stories, upload | Self-promotion to About or Featured, others' stories, malicious uploads, XSS via blocks or fields, rendering loops (DoS) |
| Editor (incl. a compromised editor account) | All stories, placements, sections | Erase corrections, **stored XSS against administrators** (editor → admin), expose confidential sources |
| Administrator | Everything | Out of scope as an attacker; protect the account instead (2FA, no shared "admin") |
| Supply chain and hosting | Plugins, npm tools, server | Vulnerable plugin, leaked secrets, unpatched PHP |

### Trust boundaries

```
Reader browser ──HTTPS──▶ [LiteSpeed cache] ──▶ WordPress PHP ──▶ MySQL / uploads
   (untrusted)            caches anonymous       ├ public pages, feeds, sitemaps       (wp-config secrets outside Git)
                          GETs only (Phase 7)    ├ public REST: /tdd/v1/contact|subscribe|view  (POST, token + throttles)
                                                 ├ admin-post.php (no-JS newsletter), page POST (no-JS contact, nonce)
Staff browser ──HTTPS + auth cookie + nonce──▶   ├ wp-admin, REST with X-WP-Nonce (capability-checked)
                                                 └ outbound: SMTP (contact mail), MailPoet (newsletter)
Server shell / WP-CLI / deploy (git archive) ── trusted operators only
```

- Everything crossing into PHP is untrusted until it has been capability-checked, nonce- or token-checked, sanitised and stored.
- Everything leaving PHP is escaped for its context (HTML, attribute, URL, JSON, email header).

## 2. What changed in Phase 8

| # | Finding (before) | Severity | Fix | Where |
|---|---|---|---|---|
| 1 | Editors had WordPress's `unfiltered_html`, so a stored `<script>` in a story or block attribute could run for administrators (editor → admin escalation) | High | `unfiltered_html` limited to administrators. Editors and authors are filtered the same way, and embed blocks still work. Filter: `tdd_core_editors_unfiltered_html`. | Core `hardening.php` |
| 2 | Any author or editor could make a story render itself forever (body block inside body) and crash the page: PHP memory exhaustion, a denial of service | High | Recursion guard on the content-rendering blocks. A nested copy renders nothing. | theme `inc/performance.php` |
| 3 | Corrections could be re-dated, deleted with a REST `null`, or rewritten after unpublishing the story first | High | Append-only from first publication (`_tdd_first_published`), on every write path: update, delete, REST, code. Text **and** time are compared. Administrators are exempt for legal fixes. | Core `editorial.php` |
| 4 | Authors could add themselves to the About page over REST (`/wp/v2/users/me` meta) | Medium | About listing, order and Featured Reporting need editor rights plus `edit_user`. Featured Reporting is limited to the person's own published stories on every path. | Core `meta.php`, `hardening.php` |
| 5 | Confidential source link, publisher and note were printed on the page and returned by the public REST API | Medium | Only the description, type and date are public. The full record is shown only to people who can edit the story (context=edit). The editor help text now says so. | Core `editorial.php`, `story.js` |
| 6 | Images kept EXIF/IPTC/XMP data (GPS, camera serial, owner name), and REST `image_meta` exposed camera and credit | Medium | Metadata is stripped from the original on upload (media library and REST). Imagick keeps orientation, colour profile and quality; GD re-saves at quality 90. | Core `hardening.php` |
| 7 | Uploads allowed WordPress's long default list (office files, audio, video, archives …) | Medium | Only JPEG, PNG, GIF, WebP, AVIF and PDF (filter: `tdd_core_upload_mimes`). WordPress still checks real file contents. | Core `hardening.php` |
| 8 | `/wp/v2/users` was public: it exposed account slugs (including "admin") and Gravatar hashes of account emails | Medium | Anonymous requests get 401. Logged-in staff keep normal behaviour. Bylines and author pages are unchanged. | Core `hardening.php` |
| 9 | XML-RPC was enabled with no use (brute-force amplification, pingback abuse) | Medium | Off at PHP level (403, no methods, no `X-Pingback`, pings closed). The server rule is in §8. | Core `hardening.php` |
| 10 | Application passwords (REST basic auth) were available with no integration using them | Low | Off (filter: `tdd_core_allow_application_passwords`) | Core `hardening.php` |
| 11 | Login errors revealed whether an account exists | Low | One message for an unknown user, unknown email or wrong password | Core `hardening.php` |
| 12 | No front-end security headers | Low | `nosniff`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, and CSP `frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'`. Sent on public pages and the login page, including cached copies. oEmbed cards stay frameable. HSTS only on HTTPS production. | Core `hardening.php` |
| 13 | Theme/plugin file editor available to administrators | Low | Disabled (filter: `tdd_core_allow_file_edit`); `DISALLOW_FILE_EDIT` too (§8) | Core `hardening.php` |
| 14 | Some links accepted any scheme `esc_url_raw` allows: `mailto:`, `ftp:`, relative paths | Low | One sanitiser for public links (`tdd_core_sanitize_http_url`): absolute http(s) only. Page links may also be site paths. It applies to sources, social profiles, image source links, the media kit, the "How we cover" link, version links and related documents. | Core `meta.php`, `pages.php`, `settings.php` |
| 15 | Field references were not validated: "Edited by" and the reporter's editor could name any account, desk members could be subscribers, topics or sections could be unknown IDs, and the profile photo could be any attachment | Low | "Edited by" and the reporter's editor must be editors. Desk members must be able to write. Topics, beats and sections must exist. The photo must be an image. Lists are capped. | Core `meta.php` |
| 16 | Contact and newsletter throttles were per IP only | Low | Added site-wide ceilings (contact 60/h, newsletter 200/h; filter: `tdd_core_global_limits`). Per-IP limits are unchanged: 5 per 10 min. | Core `security.php` |
| 17 | A placement end time could be moved before its start when only one side was changed, and unknown IDs returned 200 | Low | The effective window is validated; unknown IDs return 404 | Core `placements.php` |
| 18 | Malformed rows in array settings and meta could cause PHP warnings | Low | Type checks and length caps in every array sanitiser | Core `meta.php`, `pages.php`, `settings.php` |
| 19 | Validator test tools used unpinned (`*`) npm versions | Low | Pinned exactly. Build tools are locked by `package-lock.json`; `npm audit`: 0 vulnerabilities. | `tests/seo/package.json` |
| 20 | Launch readiness did not cover security | — | New checks: HTTPS, error display, open registration, an account called "admin", server cron, image-metadata engine | Core `admin/settings.php` |

Reviewed with no change needed:

- every `$wpdb` query is prepared, or uses only constant and table names;
- the placement REST API checks capability and its argument schema enumerates placement keys;
- the contact flow checks nonce, token, honeypot and throttle; reader text is plain text only; header injection is blocked; the reader address is only ever the Reply-To;
- newsletter redirects use `wp_safe_redirect` (same host only);
- settings use `options.php` (`manage_options` + nonce);
- profile and section forms check nonce and capability;
- media fields are checked by WordPress's attachment nonce and `edit_post`;
- the theme escapes every block attribute and field (proved by §7.2).

## 3. Control matrix

| Area | Control | Enforced in | Tested by |
|---|---|---|---|
| Admin actions | Every screen has a capability: placements `edit_others_posts`, sections `manage_categories`, settings `manage_options`, profile fields `edit_user` (+ editor-only fields `edit_others_posts`, reporter's editor `edit_users`) | Core admin modules, `register_meta` auth callbacks | security §placements, settings, profiles, sections |
| Ownership | Stories: WordPress `edit_post` (authors only their own). Featured Reporting: own published stories only. Placing a story: published or scheduled only. | WordPress caps + Core | security §story ownership, profiles |
| CSRF | Admin forms: WordPress nonces (`settings_fields`, `tdd_profile_*`, `tdd_section_settings`). REST: cookie auth requires `X-WP-Nonce`, and without it the request is anonymous. Contact: nonce on an uncached page. Newsletter: signed time token (cached pages) + honeypot + throttles. | Core | forged requests without or with wrong nonces (§7) |
| REST | `permission_callback` on every route (no `__return_true` except the three public forms). Methods are enumerated (GET on a POST route returns 404). Argument schemas with enums and types. | Core | method enforcement, SQL-shaped args, CSRF |
| Abuse | Per-IP throttles (contact 5 / 10 min, newsletter 5 / 10 min, views 120 / min) plus site-wide ceilings. One view per reader per story per 30 min. Bots and lab tools are never counted. Token minimum age is 3 s. | Core `security.php`, `popularity.php` | rate-limit, ceiling, view tests |
| SQL | `$wpdb->prepare` everywhere; inputs typed (absint, enums) | Core | review + SQL-shaped requests |
| Storage | Registered sanitiser for every field (text, textarea, datetime, http(s) URL, ID-must-exist, enums, caps) | Core `meta.php`, `pages.php`, `settings.php` | XSS sweep + field-validation tests |
| Output | Contextual escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` for the secure-tip text); JSON-LD with HEX_TAG / HEX_AMP; emails as text/plain | Theme, Core | XSS sweep: every block attribute raw, all templates and admin screens |
| Links | http(s)-only public links; `rel="noopener"` on external sources | Core, theme | security + XSS tests |
| Uploads | Images + PDF only; real-type check; metadata stripped; no PHP from uploads (server rule) | Core `hardening.php`, server | upload tests |
| Corrections | Append-only after first publication; administrators only may amend | Core `editorial.php` | 8 correction tests |
| Placements | Editors and administrators only; drafts can't be placed; valid windows | Core `placements.php` | placement tests per role |
| Privilege escalation | No raw HTML below administrator; roles can't be self-set (WordPress); editor-only profile fields; no file editor | Core, WordPress | profile and escalation tests |
| Private data | Drafts and others' stories unreadable; confidential source details internal; account emails only shown by the person's own choice (`tdd_public_email`, obfuscated); no public user directory; inboxes never printed; operational settings not in REST | Core, theme | private-data tests |
| Errors | Generic user-facing messages; no paths, queries, traces or submitted text in responses; mail log harness never stores bodies; `WP_DEBUG_DISPLAY` off (readiness check) | Core, config | malformed JSON, send failure, SQL-shaped URLs |
| Caching | Private responses `no-store, private` and never cached (Phase 7). Security headers also travel on cached copies. | Core `performance.php`, `hardening.php` | header + cache tests |
| Headers | See finding 12 | Core `hardening.php` | headers on 6 templates + cache HIT + oEmbed |

## 4. Roles at a glance (after Phase 8)

| Can… | Anonymous | Subscriber | Contributor | Author | Editor | Admin |
|---|---|---|---|---|---|---|
| Read drafts / others' unpublished stories | — | — | — | — | ✅ | ✅ |
| Publish | — | — | — (pending) | own | all | all |
| Add a correction / change one | — | — | — | own story / — | ✅ / — | ✅ / ✅ |
| Place stories, change sections | — | — | — | — | ✅ | ✅ |
| Own public profile fields | — | (no public profile) | ✅ | ✅ | ✅ | ✅ |
| About listing, Featured Reporting | — | — | — | — | own profile | everyone |
| Reporter's editor, site settings, users | — | — | — | — | — | ✅ |
| Upload | — | — | — | images/PDF | images/PDF | images/PDF |
| Raw HTML / scripts | — | — | — | — | — (**changed**) | ✅ |
| Theme/plugin file editor | — | — | — | — | — | — (**changed**) |

## 5. Exposure decisions (based on operational need)

| Surface | Decision | Re-enable with |
|---|---|---|
| XML-RPC (`xmlrpc.php`) | **Off**: no mobile app, Jetpack, pingbacks or remote publishing | `tdd_core_allow_xmlrpc` + remove the server rule |
| Application passwords | **Off**: no integration posts over REST | `tdd_core_allow_application_passwords` |
| `/wp/v2/users` for anonymous visitors | **Off** (401). Bylines and author pages stay public; the editor uses authenticated REST. | `tdd_core_public_user_endpoints` |
| Author archives `/author/<slug>/` | **Kept**: they are the public author pages. Slugs are public by design, so staff accounts must not use their login as the nicename, and the generic "admin" account must go (readiness check). | — |
| oEmbed discovery and cards | **Kept**: other sites can embed story cards (frameable `/embed/` only) | — |
| RSS feeds | **Kept** | — |
| REST posts/pages/media | **Kept**: public content only. Confidential source details are redacted, and drafts need authentication. | — |

## 6. Accepted risks (for review)

1. **The newsletter "already subscribed" state can reveal whether an address is on the list.** It is an approved form state. It is limited to 5 checks per 10 minutes per client and 200 per hour site-wide. Alternative if you prefer: show the same "check your inbox" message for both cases. That is a copy and design change, so I have not made it.
2. **Cross-site newsletter sign-ups.** A third-party page can submit our form (the token is public by design so cached pages work). Mitigation: MailPoet **double opt-in must stay on** (staging check), plus the throttles.
3. **No application-level login rate limiting.** Login brute force is left to the host's WAF and LiteSpeed's login protection, plus 2FA for administrators and editors (staging check). A plugin is only added if the host can't provide this (PLUGIN-DECISIONS).
4. **The CSP does not restrict scripts.** WordPress prints inline data scripts (speculation rules, JSON-LD, the search class), and a strict `script-src` needs nonces throughout core. The current CSP blocks framing, `<base>` and `<object>` injection, and off-site form posts. XSS is prevented by escaping and by removing raw HTML from non-administrators.
5. **Throttles key on `REMOTE_ADDR`.** Behind a CDN or proxy every visitor shares one address, so the real client IP must be mapped through `tdd_core_client_ip` (staging check). Throttles use transients, so counts are approximate under concurrency. An object cache makes them cheaper.
6. **Authors can delete their own published stories** (WordPress default), and corrections go with the story. Administrators can restore from Trash or backups. Removing `delete_published_posts` from Authors is a newsroom policy decision, so I have not changed it.
7. **Editors can only manage About and Featured Reporting on their own profile.** WordPress does not let editors edit other users, and this was the existing Phase 5 behaviour, so I left it. Administrators manage these for others.
8. **Editors lose raw HTML** (finding 1). Custom HTML blocks from editors are filtered: no `<script>`, `<iframe>` or event handlers. Embeds should use embed blocks, or an administrator can add them.

## 7. Test results

All suites were run against the local nginx + PHP-FPM site with the page cache on (Phase 7 harness) and Yoast SEO 28.6 active.

### 7.1 Role-by-role forged requests: `tests/security/security_test.py` — **159 / 159**

The suite creates throwaway accounts for subscriber, contributor, author, a second author, editor and administrator (random passwords, deleted at the end), plus fixture stories. Every check sends the raw request directly: REST with and without `X-WP-Nonce`, `options.php`, `profile.php`, `edit-tags.php`, `admin-post.php`, the contact page POST, `xmlrpc.php`, and media uploads. Groups:

| Group | What is proven |
|---|---|
| Placements (anonymous → admin) | Only editors and admins can list, create, change or end placements. A missing nonce is refused (CSRF). Drafts can't be placed. Invalid windows return 400 and unknown IDs 404. SQL-shaped keys return 400. PUT has no route. |
| Site settings | Forged `options.php` saves fail for every non-admin, and for admins without a nonce. No `tdd_` option appears in `/wp/v2/settings`. Inboxes are never printed. |
| Corrections | An editor can't rewrite, re-date, empty or `null` them, even after unpublishing first. Appending works. An author can't remove a correction on their own story. An admin can amend. |
| Story ownership | Others' stories can't be edited below editor. A contributor can't publish. Drafts are unreadable over REST and `?p=`, and can't be surfaced through a story-card block. Field validation holds ("Edited by", section, javascript:/data: links). An editor's `<script>`/`onerror` is stripped on save; an admin keeps raw HTML. |
| Confidential sources | Page and public REST show the description only. The editor (context=edit) sees everything. Lower roles can't use context=edit. |
| Profiles | No self-promotion to administrator. Other accounts can't be edited. Editor-only fields can't be self-set over REST or a forged profile form. Social `javascript:` links are dropped. The photo must be an image. Featured Reporting keeps only the person's own stories. |
| Sections | Below editor, REST and forged forms are refused. Desk members must have editing rights. `javascript:` links are dropped. |
| Uploads | `.html`, `.svg`, `.php`, `.php.jpg`, `.docx` and `.js` are refused. A contributor gets 403. A JPEG upload loses GPS, camera and artist data, and REST `image_meta` is empty. PDF still works. |
| Exposure | XML-RPC returns 403 (PHP level and server rule), with no `X-Pingback`. Application passwords are off. Anonymous `/wp/v2/users` returns 401 with no emails or avatars. An author's `context=edit` user list is refused. The file editor is off. Login messages are identical. Server rules block `uploads/*.php`, dotfiles, `readme.html` and `wp-config.php`. |
| Public forms | GET on POST routes returns 404. Contact REST needs the page nonce. **No-JS contact (303 → `?tdd_cf=sent`) and no-JS newsletter (303 back, same host) still work.** Delivery failure gives a generic message. Message bodies are never logged. The 6th newsletter request returns 429. The site-wide ceiling holds. Forged tokens and filled honeypots are refused. Malformed JSON returns a clean 400. View counting holds: one per reader, non-integer IDs return 400, drafts are never counted. |
| SQL-shaped URLs | Search filters, `author`, `paged` and `p` with SQL fragments cause no DB errors or internals. |
| Headers and caching | Headers are present on 6 templates, on login, and on cache HITs. Logged-in responses are private, no-store and BYPASS. Authenticated REST responses are no-store. oEmbed cards stay frameable. |

### 7.2 Stored and reflected XSS sweep: `tests/security/xss_test.py` — **50 / 50**

The payload `"'><img onerror><script><svg onload>` (plus `javascript:` for URLs) was placed in:

- every story field and meta field, sources and corrections;
- **every attribute of all 55 `tdd/*` blocks, written raw** (bypassing WordPress filtering, so the theme's own escaping is what is tested);
- image caption, alt text, credit, licence and source link;
- every author profile field and social link;
- section one-liner, desk note, desk link and desk members, and a topic name;
- contact notes and expectations, the secure-tip text, the media kit;
- search, filter, `?tdd_nl=`, `?tdd_cf=`, `?topic=` and 404 paths.

None of it is rendered live on these screens:

- **public:** article, oEmbed card, home, section, author, topic, About, Contact, Newsletter, Advertise, search (and page 2), 404, the feed, and REST;
- **admin, as editor and administrator:** posts, placements, sections, section edit, topics, media, users, settings and the author profile.

The same run proves that a story containing the body, page and contact-page blocks renders (200) instead of looping.

### 7.3 Full regression after Phase 8

| Suite | Result |
|---|---|
| SEO (`tests/seo/seo_test.py`, real Yoast, through the cache) | 300 / 300 |
| SEO hooks | 15 / 15 |
| Schema validator, Core and Yoast owner | identical to Phase 6 |
| SEO parity (title, description, canonical, robots, OG/X, JSON-LD, sitemaps; 25 URLs × both owners, cached and uncached) | identical |
| Cache correctness (`tests/perf/cache_test.py`: Breaking expiry, scheduled placements, **scheduled publishing**, purges, forms never cached, logged-in bypass, Most Read exclusions) | 35 / 35 |
| Front-end markup (`tests/perf/markup_test.py`) | 75 / 75 |
| Public pages: 19 URLs × 8 widths | unchanged (only the known gallery-page H1 and 404 notes) |
| Admin and forms: story panels 15, page panel 11, admin forms 11, roles 8, admin UI 12, contact 28, newsletter 12, server-side rules 16 | all pass |

## 8. Production configuration (hosting/staging checks)

These are **requirements for staging**. Nothing here has been applied to a server.

### wp-config.php (never in Git — `.gitignore` covers `wp-config*.php` and `.env*`)

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );   // 'staging' on staging
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', '/home/<account>/logs/wp-debug.log' ); // outside the web root, or false
@ini_set( 'display_errors', '0' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN', true );
define( 'DISABLE_WP_CRON', true );                // + server cron every minute (below)
define( 'WP_AUTO_UPDATE_CORE', 'minor' );         // security/maintenance releases automatically
// Unique salts per environment (https://api.wordpress.org/secret-key/1.1/salt/); staging ≠ production.
// DB and SMTP credentials only here or in host environment variables — never in the repo.
```

### Server rules (LiteSpeed/Apache `.htaccess` equivalents of `tests/perf/nginx.conf`)

```apache
Options -Indexes
<Files xmlrpc.php>
  Require all denied
</Files>
<FilesMatch "^(wp-config\.php|wp-config-sample\.php|readme\.html|license\.txt)$">
  Require all denied
</FilesMatch>
RedirectMatch 403 /\.(?!well-known/).*
# wp-content/uploads/.htaccess
<FilesMatch "\.(php|phtml|phar|pl|py|cgi|sh)$">
  Require all denied
</FilesMatch>
# Static files (PHP sends these for HTML already)
Header always set X-Content-Type-Options "nosniff"
```

### Checklist

- [ ] **HTTPS only:** certificate valid; http → https 301; `home` and `siteurl` are https (readiness check).
- [ ] **HSTS:** Core sends `max-age=31536000` on HTTPS production. Add `includeSubDomains` and `preload` only when every subdomain is HTTPS.
- [ ] **Cookies:** WordPress auth cookies are `HttpOnly` and, over HTTPS with `FORCE_SSL_ADMIN`, `Secure`. Confirm in the browser.
- [ ] **Real client IP:** behind a CDN or proxy, map the trusted header in `tdd_core_client_ip` (throttles and view dedupe).
- [ ] **Cron:** `DISABLE_WP_CRON` plus a host cron job every minute:
  ```sh
  wget -q -O - https://<site>/wp-cron.php?doing_wp_cron >/dev/null 2>&1
  ```
  Run it as the site user, never as root.
- [ ] **PHP:** a supported 8.2/8.3 release; Imagick enabled (keeps image quality when metadata is stripped; readiness check); `expose_php = Off`; `display_errors = Off`.
- [ ] **Accounts:**
  - personal accounts only, with no "admin" login (readiness check);
  - **2FA for administrators and editors** (the wordpress.org "Two Factor" plugin unless the host provides WordPress 2FA; PLUGIN-DECISIONS);
  - "Anyone can register" off (readiness check);
  - the least role that fits each person;
  - remove leavers the same day.
- [ ] **Login protection:** host WAF or LiteSpeed login rate limiting (accepted risk 3).
- [ ] **MailPoet:** double opt-in **on** (accepted risk 2).
- [ ] **SMTP:** credentials stored outside Git; SPF, DKIM and DMARC for the sender domain (the contact form's From is the site mailbox).
- [ ] **Re-run on staging:** `tests/security/security_test.py` and `xss_test.py` against staging URLs, before any real content exists. The suites create and remove their own test accounts.
- [ ] **External scan:** securityheaders.com and an SSL Labs grade A on staging.

### Plugin, theme and dependency update policy

| Component | Policy |
|---|---|
| WordPress core | Minor (security) releases automatic. Major releases go to staging first, regressions are run, then production within 14 days. |
| Yoast SEO, LiteSpeed Cache, MailPoet (installed from wordpress.org only) | Auto-update **on** for these three (well-maintained, security-sensitive). Weekly review of changelogs. Major versions go to staging first. |
| Any other plugin | Not installed without a PLUGIN-DECISIONS entry. Remove anything unused; deactivated is not enough. |
| TechDoseDaily theme + Core | Deployed only from Git (`git archive` zip), never edited on the server (file editor disabled). Version bump per release. |
| Build tools (`esbuild`, `lightningcss`) | Dev-only, never deployed. Locked by `package-lock.json`. `npm audit` before each release (0 vulnerabilities now). |
| Test tools (Lighthouse, validator, Playwright, PHPCS) | Local only; versions pinned; never deployed |
| PHP, MySQL | Kept on host-supported versions; security patches by the host |
| Review cadence | Weekly: updates and the WP Site Health screen. Monthly: user accounts and roles, plugin list, backups restore-tested. Immediately: any security advisory for an installed component. |

### Backups and restore (requirements; not set up in this phase)

| Requirement | Value |
|---|---|
| What | Full database (including Core tables `wp_tdd_placements` and `wp_tdd_view_buckets`), `wp-content/uploads`, `wp-config.php` (stored **separately and encrypted**: it holds secrets). Theme and Core come from Git. |
| Frequency and retention | Database daily, plus before every deploy or update. Uploads daily, incremental. Keep 30 daily and 12 monthly copies. |
| Location | Off the web server (host backup storage plus one off-site copy), encrypted at rest, never in the web root or Git |
| Targets | RPO ≤ 24 h; RTO ≤ 4 h |
| Restore test | Before launch, then quarterly: restore to staging and run the regression and security suites |
| Access | Administrators only. Restores are logged. |
| Incidents | If a compromise is suspected: take the site offline (maintenance), rotate salts and all passwords, restore from the last clean backup, update everything, review users and roles. |

## 9. Running the tests

See DEVELOPMENT.md step 12. Requirements:

- the Phase 7 local nginx harness;
- `tests/fixtures/mu-capture-mail.php` and `mu-fake-newsletter-provider.php` in `wp-content/mu-plugins`;
- Python with `requests` and `Pillow`.

```sh
python3 tests/security/security_test.py   # ~2 min; writes tests/security/out/security-results.txt
python3 tests/security/xss_test.py        # ~1 min; writes tests/security/out/xss-results.txt; restores everything it changes
```

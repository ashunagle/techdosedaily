#!/usr/bin/env python3
"""Launch-gate checker (LAUNCH-GATES.md). Read-only except one temporary probe file in uploads (SSH mode).

  # HTTP checks only
  BASE=https://<staging-host> python3 tests/staging/gates.py --stage staging
  # + server checks over SSH (WP-CLI)
  WP=tests/staging/remote-wp TDD_SSH="-p 65002 u123@1.2.3.4" TDD_WP_PATH=/home/u123/domains/<host>/public_html \
  BASE=https://<staging-host> python3 tests/staging/gates.py --stage staging
  # behind hPanel password protection:  TDD_BASIC_AUTH=user:pass

--stage staging    : the site must be locked (noindex, not open to search engines); HSTS not required.
--stage production : the site must be indexable with sitemaps; HSTS required; every launch-readiness item done.
Writes tests/staging/out/gates-<stage>-<timestamp>.md and exits 1 if any gate FAILs.
"""
import argparse, base64, datetime, json, os, re, shlex, subprocess, sys, urllib.parse

import requests

ap = argparse.ArgumentParser()
ap.add_argument('--stage', choices=('staging', 'production'), required=True)
args = ap.parse_args()
STAGE = args.stage
BASE = os.environ.get('BASE', '').rstrip('/')
LOCAL = os.environ.get('TDD_ALLOW_HTTP') == '1'  # harness self-test against the local http site only
if not BASE.startswith('https://') and not LOCAL:
    sys.exit('Set BASE to the https:// URL of the site under test.')
HOST = urllib.parse.urlsplit(BASE).netloc
WP = os.environ.get('WP')
AUTH = tuple(os.environ['TDD_BASIC_AUTH'].split(':', 1)) if os.environ.get('TDD_BASIC_AUTH') else None
S = requests.Session()
S.headers.update({'X-TDD-Perf-Test': '1', 'User-Agent': 'TDD-launch-gates/1.0 (+lighthouse-style lab check)'})
if AUTH:
    S.auth = AUTH
# A rejected directory login turns every HTTP gate into a check of the 401 page; stop instead of reporting that.
if S.get(BASE + '/', timeout=30, allow_redirects=False).status_code == 401:
    sys.exit('HTTP 401 on / — directory login missing or rejected (TDD_BASIC_AUTH=user:pass). No report written.')
ROWS = []
APPROVED_PLUGINS = {'techdosedaily-core', 'wordpress-seo', 'litespeed-cache', 'two-factor', 'mailpoet'}


def gate(gid, name, ok, detail='', level='FAIL'):
    status = 'PASS' if ok else level
    ROWS.append((gid, name, status, str(detail)[:300]))
    print(f'  {status:6} {gid:5} {name}' + ('' if ok else f' — {str(detail)[:200]}'))


def get(path, **kw):
    kw.setdefault('timeout', 30)
    return S.get(path if path.startswith('http') else BASE + path, **kw)


def wp(cmd):
    r = subprocess.run(f'{WP} {cmd}', shell=True, capture_output=True, text=True)
    return r.stdout.strip()


def wpeval(php):
    code = "eval(base64_decode('" + base64.b64encode(php.encode()).decode() + "'));"
    return wp('eval ' + shlex.quote(code))


LEAKS = ('/home/', '/var/www', 'Stack trace', 'Fatal error', 'Warning:</b>', 'Notice:</b>', 'SQLSTATE', 'wpdb')

print(f'== {STAGE.upper()} gates for {BASE}' + (' (+SSH)' if WP else ' (HTTP only)'))

print('== HTTPS')
if LOCAL:
    home = get('/')
else:
  r = requests.get('http://' + HOST + '/', allow_redirects=False, timeout=30, auth=AUTH)
  gate('H1', 'http:// redirects to https:// (301/308)', r.status_code in (301, 308) and r.headers.get('Location', '').startswith('https://'), (r.status_code, r.headers.get('Location')))
  try:
    home = get('/')
    gate('H2', 'valid TLS certificate, home 200', home.status_code == 200, home.status_code)
  except requests.exceptions.SSLError as e:
    gate('H2', 'valid TLS certificate', False, e)
    sys.exit(1)
hsts = home.headers.get('Strict-Transport-Security', '')
gate('H3', 'HSTS max-age ≥ 1 year', 'max-age=31536000' in hsts or STAGE == 'staging', hsts or 'absent', level='FAIL' if STAGE == 'production' else 'INFO')

print('== Security headers (SECURITY.md finding 12)')
story = None
for cand in re.findall(r'href="(' + re.escape(BASE) + r'/([a-z0-9-]+)/[a-z0-9-]+/)"', home.text):
    if cand[1] not in ('comments', 'feed', 'author', 'topic', 'story-type', 'wp-json', 'wp-content', 'page', 'tag'):
        story = cand[0]
        break
for path in [p for p in ('/', '/wp-login.php', story, '/no-such-page-gate/') if p]:
    for attempt in (1, 2):  # second request is normally a cache HIT
        x = get(path)
    h = x.headers
    ok = h.get('X-Content-Type-Options') == 'nosniff' and 'frame-ancestors' in h.get('Content-Security-Policy', '') and h.get('X-Frame-Options') == 'SAMEORIGIN' and h.get('Referrer-Policy') and h.get('Permissions-Policy')
    gate('S1', f'headers on {path.replace(BASE, "")} (incl. cached copy)', bool(ok), {k: v for k, v in h.items() if k.lower() in ('x-content-type-options', 'content-security-policy', 'x-frame-options', 'referrer-policy', 'permissions-policy')})

print('== Exposure (SECURITY.md §5, §8 server rules)')
gate('X1', 'XML-RPC refused (403)', get('/xmlrpc.php').status_code == 403)
x = get('/wp-json/wp/v2/users')
gate('X2', 'anonymous /wp/v2/users refused (401), no emails/avatars', x.status_code == 401 and 'avatar' not in x.text, x.status_code)
for p in ('/readme.html', '/license.txt', '/wp-config.php', '/.env', '/.git/config', '/wp-config-sample.php'):
    c = get(p).status_code
    gate('X3', f'{p} not served', c in (403, 404), c)
x = get('/wp-content/uploads/')
gate('X4', 'no directory listing in uploads', x.status_code in (403, 404) or 'Index of' not in x.text, x.status_code)
gate('X5', 'no X-Pingback / generator', 'X-Pingback' not in home.headers and 'name="generator"' not in home.text)
s = requests.Session(); s.auth = AUTH
s.get(BASE + '/wp-login.php', timeout=30)
m1 = s.post(BASE + '/wp-login.php', data={'log': 'no-such-user-gate', 'pwd': 'x', 'testcookie': '1'}, timeout=30).text
gate('X6', 'login error does not reveal accounts', 'is not registered' not in m1 and 'Unknown username' not in m1)

print('== Errors and data hygiene')
for p in ("/?s=%27%22", "/?p=1%27", "/?author=1%27", "/no-such-page-%3Cscript%3E/"):
    x = get(p)
    gate('E1', f'{p} shows no internals', not [l for l in LEAKS if l in x.text], [l for l in LEAKS if l in x.text])
blob = home.text + get('/sitemap_index.xml').text
gate('E2', 'no sample/fixture content visible ([Sample], example.com, -sample)', not re.search(r'\[Sample\]|example\.(com|org|net)|-sample', blob), re.findall(r'.{30}(?:\[Sample\]|example\.(?:com|org|net)|-sample).{10}', blob)[:3])

print('== Indexing (SEO)')
robots_meta = re.search(r"<meta name=['\"]robots['\"] content=['\"]([^'\"]+)", home.text)
rtxt = get('/robots.txt').text
if STAGE == 'staging':
    gate('I1', 'staging is noindex (meta robots)', bool(robots_meta and 'noindex' in robots_meta.group(1)), robots_meta.group(1) if robots_meta else 'none')
else:
    gate('I1', 'home is indexable', not (robots_meta and 'noindex' in robots_meta.group(1)), robots_meta.group(1) if robots_meta else 'none')
    gate('I2', 'robots.txt allows crawling and lists the sitemap', 'Disallow: /\n' not in rtxt + '\n' and 'Sitemap:' in rtxt, rtxt[:200])
sm = get('/sitemap_index.xml')
gate('I3', 'XML sitemap index served', sm.status_code == 200 and '<sitemapindex' in sm.text, sm.status_code, level='FAIL' if STAGE == 'production' else 'INFO')
canon = re.findall(r'<link rel="canonical" href="([^"]+)"', home.text)
# Yoast prints no canonical on noindex pages, so a locked staging home has none.
staging_noindex = STAGE == 'staging' and bool(robots_meta and 'noindex' in robots_meta.group(1))
gate('I4', 'exactly one canonical, https, on this host', (len(canon) == 1 and canon[0].startswith(BASE)) or (staging_noindex and not canon), canon or ('none (noindex staging)' if staging_noindex else []))
ld = re.findall(r'<script type="application/ld\+json"[^>]*class="([^"]+)"', home.text)
gate('I5', 'exactly one JSON-LD graph (single schema owner)', len(ld) == 1, ld)
gate('I6', 'title and Open Graph present', '<title>' in home.text and 'og:title' in home.text)

print('== Caching and compression (PERFORMANCE.md)')
get('/'); x = get('/')
lsc = x.headers.get('x-litespeed-cache', x.headers.get('X-LiteSpeed-Cache', ''))
gate('C1', 'anonymous home served from the page cache', 'hit' in lsc.lower() or x.headers.get('X-Page-Cache') == 'HIT', lsc or x.headers.get('X-Page-Cache') or 'no cache header')
gate('C2', 'Core cache header present (X-TDD-Cache ttl)', 'ttl=' in x.headers.get('X-TDD-Cache', ''), x.headers.get('X-TDD-Cache'))
for p in ('/contact/', '/newsletter/?tdd_nl=pending'):
    get(p); y = get(p)
    yl = y.headers.get('x-litespeed-cache', '')
    if y.status_code == 404:
        gate('C3', f'{p} never served from cache, no-store', False, '404 — page not created yet', level='FAIL' if STAGE == 'production' else 'TODO')
        continue
    gate('C3', f'{p} never served from cache, no-store', 'hit' not in yl.lower() and 'no-store' in y.headers.get('Cache-Control', ''), (yl, y.headers.get('Cache-Control')))
css = re.search(r'href=["\']([^"\']+/themes/techdosedaily/assets/css/[a-z-]+\.min\.css[^"\']*)', home.text)
if css:
    c = get(css.group(1), headers={'Accept-Encoding': 'br, gzip'})
    gate('C4', 'static assets: long cache + compressed', 'max-age=31536000' in c.headers.get('Cache-Control', '') and c.headers.get('Content-Encoding') in ('br', 'gzip'), (c.headers.get('Cache-Control'), c.headers.get('Content-Encoding')))
else:
    gate('C4', 'minified theme CSS referenced', False, 'no .min.css link found')
h = get('/', headers={'Accept-Encoding': 'br, gzip'})
gate('C5', 'HTML compressed', h.headers.get('Content-Encoding') in ('br', 'gzip'), h.headers.get('Content-Encoding'))

if WP:
    print('== Server (SSH / WP-CLI)')
    conf = json.loads(wpeval("""echo wp_json_encode(array(
      'env' => wp_get_environment_type(), 'debug_display' => (defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY)) || ini_get('display_errors') === '1',
      'file_edit_off' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT, 'ssl_admin' => defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN,
      'cron_off' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'imagick' => extension_loaded('imagick'), 'php' => PHP_VERSION,
      'object_cache' => wp_using_ext_object_cache(), 'register' => (int) get_option('users_can_register'), 'default_role' => get_option('default_role'),
      'blog_public' => (int) get_option('blog_public'), 'site_icon' => (int) get_option('site_icon'), 'schema_owner' => function_exists('tdd_core_schema_owner') ? tdd_core_schema_owner() : '',
      'webp' => wp_image_editor_supports(array('mime_type' => 'image/webp')),
    ));""") or '{}')
    gate('W1', f'WP_ENVIRONMENT_TYPE = {STAGE}', conf.get('env') == STAGE, conf.get('env'))
    gate('W2', 'errors never displayed', conf.get('debug_display') is False, conf.get('debug_display'))
    gate('W3', 'DISALLOW_FILE_EDIT, FORCE_SSL_ADMIN, DISABLE_WP_CRON', conf.get('file_edit_off') and conf.get('ssl_admin') and conf.get('cron_off'), conf)
    gate('W4', 'PHP ≥ 8.2 with Imagick and WebP', conf.get('php', '0') >= '8.2' and conf.get('imagick') and conf.get('webp'), (conf.get('php'), conf.get('imagick'), conf.get('webp')))
    gate('W5', 'registration off, default role subscriber', conf.get('register') == 0 and conf.get('default_role') == 'subscriber', (conf.get('register'), conf.get('default_role')))
    gate('W6', 'search-engine visibility matches stage', conf.get('blog_public') == (1 if STAGE == 'production' else 0), conf.get('blog_public'))
    gate('W7', 'object cache', bool(conf.get('object_cache')), conf.get('object_cache'), level='INFO')
    plugins = json.loads(wp('plugin list --format=json') or '[]')
    active = {p['name'] for p in plugins if p['status'] in ('active', 'active-network')}
    gate('P1', 'active plugins = approved set (PLUGIN-DECISIONS.md)', active <= APPROVED_PLUGINS and {'techdosedaily-core', 'wordpress-seo', 'litespeed-cache'} <= active, sorted(active))
    regular = [p for p in plugins if p['status'] in ('active', 'inactive', 'active-network')]
    gate('P2', 'no inactive/leftover plugins', all(p['status'] != 'inactive' for p in regular), [p['name'] for p in regular if p['status'] == 'inactive'])
    gate('P3', 'no plugin updates pending', all(p.get('update') in ('none', '', None) for p in regular), [p['name'] for p in regular if p.get('update') not in ('none', '', None)])
    harness = [p['name'] for p in plugins if p['status'] in ('must-use', 'dropin') and (p['name'].startswith('mu-') or p['name'] == 'db.php')]
    gate('P6', 'no local test harness / drop-ins left on the server (mu-capture-mail, fake newsletter, …)', not harness, harness)
    stray = wpeval("""echo implode( ',', array_map( 'basename', array_merge( (array) glob( ABSPATH . 'create_autologin_*.php' ), (array) glob( ABSPATH . 'default.php' ) ) ) );""")
    gate('P7', 'no host leftovers in the web root (hPanel autologin script, default page)', stray == '', stray)
    gate('P4', 'WordPress core up to date', 'Success' in wp('core check-update') or wp('core check-update --format=count') in ('', '0'), wp('core check-update --format=csv')[:200])
    theme = wp('theme list --status=active --field=name')
    gate('P5', 'TechDoseDaily theme active', theme == 'techdosedaily', theme)
    users = json.loads(wp('user list --fields=ID,user_login,user_email,roles --format=json') or '[]')
    # Test suites create tdd-sec-*, tdd-xss-*, tdd-cache-* accounts at @example.invalid; real logins may also start with tdd-.
    bad_users = [u['user_login'] for u in users if u['user_login'] == 'admin' or 'sample' in u['user_login']
                 or u['user_login'].startswith(('tdd-sec-', 'tdd-xss-', 'tdd-cache-'))
                 or u.get('user_email', '').endswith(('@example.invalid', '@example.com', '@example.org', '@example.net'))]
    gate('U1', 'no account named "admin" and no sample/test accounts', not bad_users, bad_users)
    no2fa = wpeval("""$out = array(); foreach ( get_users( array( 'role__in' => array( 'administrator', 'editor' ) ) ) as $u ) { $p = get_user_meta( $u->ID, '_two_factor_enabled_providers', true ); if ( empty( $p ) ) { $out[] = $u->user_login; } } echo implode( ',', $out );""")
    gate('U2', 'every administrator and editor has 2FA enabled (Two Factor)', no2fa == '', no2fa, level='FAIL' if STAGE == 'production' else 'TODO')
    fx = wpeval("""global $wpdb; echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tdd_fixture'" );""")
    gate('D1', 'no test fixtures', fx == '0', fx)
    todo = wpeval("""if ( function_exists( 'tdd_core_launch_checks' ) ) { foreach ( tdd_core_launch_checks() as $c ) { if ( 'todo' === $c[0] ) { echo $c[1], '; '; } } }""")
    gate('D2', 'Site settings → Launch readiness: nothing left to do', todo == '', todo, level='FAIL' if STAGE == 'production' else 'TODO')
    probe = wpeval("""$u = wp_upload_dir(); $f = $u['basedir'] . '/tdd-gate-probe.php'; file_put_contents( $f, '<?php echo "EXECUTED";' ); echo $u['baseurl'] . '/tdd-gate-probe.php';""")
    pr = get(probe)
    gate('X7', 'PHP never runs from uploads', 'EXECUTED' not in pr.text and pr.status_code in (403, 404), pr.status_code)
    wpeval("""$u = wp_upload_dir(); @unlink( $u['basedir'] . '/tdd-gate-probe.php' );""")
    cron = wpeval("""$late = 0; foreach ( (array) _get_cron_array() as $ts => $hooks ) { if ( $ts < time() - 900 ) { $late += count( $hooks ); } } echo $late;""")
    gate('R1', 'cron is running (no events > 15 min overdue)', cron == '0', cron)
    mp = wpeval("""try { if ( class_exists( '\\MailPoet\\Settings\\SettingsController' ) ) { echo \\MailPoet\\Settings\\SettingsController::getInstance()->get( 'signup_confirmation.enabled' ) ? 'on' : 'off'; } else { echo 'absent'; } } catch ( \\Throwable $e ) { echo 'unknown'; }""")
    gate('M1', 'MailPoet double opt-in on', mp == 'on', mp, level='FAIL' if STAGE == 'production' else 'TODO')
    yo = wpeval("""$t = (array) get_option( 'wpseo_titles' ); $ok = ! empty( $t['disable-date'] ) && ! empty( $t['disable-attachment'] ) && empty( $t['breadcrumbs-enable'] ) && ! empty( $t['noindex-tax-post_tag'] ) && 'Page not found · %%sitename%%' === ( $t['title-404-wpseo'] ?? '' ); $idx = class_exists( 'WPSEO_Options' ) ? (int) WPSEO_Options::get( 'indexables_indexing_completed' ) : 0; echo ( $ok ? 'settings-ok' : 'settings-differ' ), ' indexables=', $idx;""")
    gate('Y1', 'Yoast settings as SEO-SCHEMA.md §7', 'settings-ok' in yo, yo)
    gate('Y2', 'Yoast SEO data optimisation completed', 'indexables=1' in yo, yo, level='FAIL' if STAGE == 'production' else 'TODO')
    gate('Y3', 'Site Icon set (also the Core publisher logo)', conf.get('site_icon', 0) > 0, conf.get('site_icon'), level='FAIL' if STAGE == 'production' else 'TODO')
    ls = json.loads(wpeval("""global $wpdb; $o = array(); foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'litespeed.conf.%'" ) as $r ) { $o[ substr( $r->option_name, 15 ) ] = maybe_unserialize( $r->option_value ); } echo wp_json_encode( $o );""") or '{}')
    want = {'cache': True, 'cache-priv': False, 'cache-mobile': False, 'media-lazy': False, 'media-iframe_lazy': False, 'optm-css_min': False, 'optm-js_min': False, 'optm-css_comb': False, 'optm-js_comb': False, 'optm-ccss_gen': False, 'optm-ucss': False, 'guest': False, 'esi': False}
    bad = {k: ls.get(k) for k, v in want.items() if k in ls and bool(ls.get(k)) != v}
    gate('L1', 'LiteSpeed Cache settings as PERFORMANCE.md §3.4', bool(ls) and not bad, bad or ('LiteSpeed options not found' if not ls else ''))

out = os.path.join(os.path.dirname(__file__), 'out')
os.makedirs(out, exist_ok=True)
stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d-%H%M%SZ')
fails = [r for r in ROWS if r[2] == 'FAIL']
todos = [r for r in ROWS if r[2] == 'TODO']
md = [f'# Launch gates — {STAGE} — {BASE} — {stamp}', '', f'**{len(ROWS) - len(fails) - len(todos)} pass · {len(todos)} to do · {len(fails)} fail**', '', '| Gate | Check | Result | Detail |', '|---|---|---|---|']
md += [f'| {g} | {n} | {s} | {d.replace("|", "/")} |' for g, n, s, d in ROWS]
open(os.path.join(out, f'gates-{STAGE}-{stamp}.md'), 'w').write('\n'.join(md) + '\n')
print(f'\n{len(ROWS) - len(fails) - len(todos)} pass, {len(todos)} to do, {len(fails)} fail → tests/staging/out/gates-{STAGE}-{stamp}.md')
sys.exit(1 if fails else 0)

#!/usr/bin/env python3
"""Phase 7 cache-correctness tests against the local nginx page cache (tests/perf/nginx.conf).

Needs: local site at BASE behind nginx with fastcgi_cache, tests/perf/mu-local-page-cache.php installed,
wp-cli at WP (see DEVELOPMENT.md). Uses only fixture/test stories it creates, and removes them.
Run:  python3 tests/perf/cache_test.py   (takes ~3 minutes: it waits for real time boundaries)
"""
import json, os, re, secrets, subprocess, sys, time, urllib.request, http.cookiejar, urllib.parse

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
ok = fail = 0


def check(name, cond, detail=''):
    global ok, fail
    if cond:
        ok += 1
        print('  PASS', name)
    else:
        fail += 1
        print('  FAIL', name, detail)


def wp(cmd):
    return subprocess.run(f'{WP} {cmd}', shell=True, capture_output=True, text=True).stdout.strip()


def wpeval(php):
    return wp("eval " + json.dumps(php).replace('$', '\\$'))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def get(path, headers=None, opener=None, data=None, method=None):
    req = urllib.request.Request(BASE + path, data=data, headers=headers or {}, method=method)
    try:
        r = (opener or urllib.request.build_opener(NoRedirect)).open(req, timeout=60)
        return r.status, dict(r.headers), r.read().decode('utf8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode('utf8', 'replace')


def ttl(h):
    m = re.search(r's-maxage=(\d+)', h.get('Cache-Control', ''))
    return int(m.group(1)) if m else 0


def iso(ts):
    return time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime(ts))


created = []
try:
    print('== Baseline: anonymous pages are cached, uncached requests bypass')
    get('/'); s, h, b = get('/')
    check('home second request is a cache HIT', h.get('X-Page-Cache') == 'HIT', h.get('X-Page-Cache'))
    s, h, _ = get('/', {'X-Cache-Bypass': '1'})
    check('X-Cache-Bypass request is not served from cache', h.get('X-Page-Cache') == 'BYPASS', h.get('X-Page-Cache'))
    lead_before = re.search(r'hp-hero__main.*?<a href="([^"]+)"', b, re.S).group(1)

    print('== Publishing purges')
    pid = wp('post create --post_status=publish --post_title="Cache probe published story" --post_content="Probe." --porcelain')
    created.append(pid)
    wp(f'post meta set {pid} _tdd_fixture 1')
    s, h, b = get('/')
    check('home is regenerated after publish (MISS)', h.get('X-Page-Cache') == 'MISS', h.get('X-Page-Cache'))
    check('new story appears on the homepage immediately', 'Cache probe published story' in b)

    print('== Time-based state: Breaking expiry, scheduled placement, scheduled story (no purge needed)')
    now = int(time.time())
    bid = wp('post create --post_status=publish --post_title="Cache probe breaking story" --post_content="Probe." --porcelain')
    created.append(bid)
    wp(f'post meta set {bid} _tdd_fixture 1')
    wp(f'post meta set {bid} tdd_breaking_until {iso(now + 45)}')
    pl_start, pl_end = now + 110, now + 140
    plid = wpeval(f'$r = tdd_core_place({bid}, "homepage_lead", 1, 0, "{iso(pl_start)}", "{iso(pl_end)}"); echo is_wp_error($r) ? "ERR " . $r->get_error_message() : $r;')
    check('scheduled placement created', plid.isdigit(), plid)
    fut = wp(f'post create --post_status=future --post_date_gmt="{time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime(now + 90))}" --post_title="Cache probe scheduled story" --post_content="Probe." --porcelain')
    created.append(fut)
    wp(f'post meta set {fut} _tdd_fixture 1')
    art = wp(f'post list --p={bid} --field=url --post_type=post').replace(BASE, '')
    get('/'); s, h, b = get('/')
    check('home cached while breaking', h.get('X-Page-Cache') == 'HIT')
    check('home TTL capped at the Breaking end (≤45s)', 0 < ttl(h) <= 45, ttl(h))
    check('scheduled story not yet visible', 'Cache probe scheduled story' not in b, (wp(f'post get {fut} --fields=post_status,post_date_gmt'), re.findall(r'.{120}Cache probe scheduled story', b)[:1]))
    head = lambda html: html.split('<main')[1].split('tdd-art__body')[0]
    s, h, ab = get(art); s, h, ab = get(art)
    check('article cached with TTL capped at the Breaking end', h.get('X-Page-Cache') == 'HIT' and 0 < ttl(h) <= 45, (h.get('X-Page-Cache'), ttl(h)))
    check('Breaking label shown on the cached article', 'tdd-label--breaking' in head(ab))

    time.sleep(max(0, now + 47 - time.time()))
    s, h, b = get('/')
    check('after Breaking ends: home regenerated (MISS, no purge involved)', h.get('X-Page-Cache') in ('MISS', 'EXPIRED'), h.get('X-Page-Cache'))
    check('home TTL now capped at the scheduled story (≤45s)', 0 < ttl(h) <= 44, ttl(h))
    s, h, ab = get(art)
    check('after Breaking ends: article regenerated (no purge involved)', h.get('X-Page-Cache') in ('MISS', 'EXPIRED'), h.get('X-Page-Cache'))
    check('after Breaking ends: no Breaking label on the article', 'tdd-label--breaking' not in head(ab))

    time.sleep(max(0, now + 92 - time.time()))
    wp('cron event run --due-now')  # Production: real system cron (see PERFORMANCE.md).
    s, h, b = get('/')
    check('scheduled story is live and visible after cron publishes it', 'Cache probe scheduled story' in b and wp(f'post get {fut} --field=post_status') == 'publish')
    lead = re.search(r'hp-hero__main.*?<a href="([^"]+)"', b, re.S).group(1)
    check('homepage lead unchanged before the placement starts', 'cache-probe-breaking' not in lead, lead)

    time.sleep(max(0, pl_start + 2 - time.time()))
    s, h, b = get('/')
    lead = re.search(r'hp-hero__main.*?<a href="([^"]+)"', b, re.S).group(1)
    check('placement start: lead switches without a purge', 'cache-probe-breaking' in lead, (lead, h.get('X-Page-Cache')))
    get('/'); s, h, b = get('/')
    check('placement window: cached, TTL capped at its expiry', h.get('X-Page-Cache') == 'HIT' and 0 < ttl(h) <= pl_end - time.time() + 1, ttl(h))

    time.sleep(max(0, pl_end + 2 - time.time()))
    s, h, b = get('/')
    lead = re.search(r'hp-hero__main.*?<a href="([^"]+)"', b, re.S).group(1)
    check('placement expiry: previous lead returns', 'cache-probe-breaking' not in lead, lead)

    print('== Placement edits purge')
    get('/'); get('/')
    wpeval(f'tdd_core_place({created[0]}, "homepage_lead", 1, 0, null, null);')
    s, h, b = get('/')
    lead = re.search(r'hp-hero__main.*?<a href="([^"]+)"', b, re.S).group(1)
    check('placing a story purges and shows it at once', 'cache-probe-published' in lead and h.get('X-Page-Cache') == 'MISS', (lead, h.get('X-Page-Cache')))

    print('== Forms are never cached')
    s, h, _ = get('/contact/'); s, h, _ = get('/contact/')
    check('contact page never cached', h.get('X-Page-Cache') != 'HIT' and 'no-store' in h.get('Cache-Control', ''))
    s, h, _ = get('/newsletter/?tdd_nl=subscribed'); s, h, _ = get('/newsletter/?tdd_nl=subscribed')
    check('no-JS newsletter result page never cached', h.get('X-Page-Cache') != 'HIT' and 'no-store' in h.get('Cache-Control', ''))
    s, h, _ = get('/contact/?tdd_cf=sent'); s, h, _ = get('/contact/?tdd_cf=sent')
    check('no-JS contact result page never cached', h.get('X-Page-Cache') != 'HIT')
    data = urllib.parse.urlencode({'action': 'tdd_subscribe', 'email': 'x', 'tdd_token': 'bad', 'tdd_hp': ''}).encode()
    s, h, _ = get('/wp-admin/admin-post.php', data=data)
    s2, h2, _ = get('/wp-admin/admin-post.php', data=data)
    check('newsletter POST never cached', h.get('X-Page-Cache') != 'HIT' and h2.get('X-Page-Cache') != 'HIT', (h.get('X-Page-Cache'), h2.get('X-Page-Cache')))
    s, h, _ = get('/wp-json/tdd/v1/subscribe', data=json.dumps({'email': 'x'}).encode(), headers={'Content-Type': 'application/json'})
    s2, h2, _ = get('/wp-json/tdd/v1/subscribe', data=json.dumps({'email': 'x'}).encode(), headers={'Content-Type': 'application/json'})
    check('newsletter REST POST never cached', h2.get('X-Page-Cache') != 'HIT', h2.get('X-Page-Cache'))

    print('== Logged-in editor')
    # A throwaway editor account with a random password, removed at the end (no credentials in the repo).
    ed_login, ed_pass = 'tdd-cache-test-editor', secrets.token_urlsafe(24)
    ed_id = wp(f'user create {ed_login} {ed_login}@example.invalid --role=editor --user_pass={ed_pass} --porcelain')
    ADMIN = (ed_login, ed_pass)
    cj = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    op.open(urllib.request.Request(BASE + '/wp-login.php'))
    op.open(urllib.request.Request(BASE + '/wp-login.php', data=urllib.parse.urlencode({'log': ADMIN[0], 'pwd': ADMIN[1], 'testcookie': '1', 'redirect_to': BASE + '/'}).encode(), headers={'Cookie': 'wordpress_test_cookie=WP%20Cookie%20check'}))
    get('/'); get('/')  # make sure an anonymous copy is cached
    s, h, b = get('/', opener=op)
    check('editor bypasses the page cache', h.get('X-Page-Cache') == 'BYPASS', h.get('X-Page-Cache'))
    check('editor response is private/no-store', 'no-store' in h.get('Cache-Control', '') and 'private' in h.get('Cache-Control', ''))
    check('editor sees the admin bar (not the cached anonymous copy)', 'wpadminbar' in b)
    s, h, b2 = get('/')
    check('anonymous copy never contains editor markup', 'wpadminbar' not in b2 and h.get('X-Page-Cache') == 'HIT')
    s, h, b = get(f'/?p={created[0]}&preview=true', opener=op)
    check('preview is never cached', 'no-store' in h.get('Cache-Control', '') and h.get('X-Page-Cache') == 'BYPASS')

    print('== Most Read ignores performance-test traffic')
    def views(p):
        return int(wpeval(f'global $wpdb; echo (int) $wpdb->get_var("SELECT COALESCE(SUM(views),0) FROM " . tdd_core_views_table() . " WHERE post_id={p}");') or 0)
    v0 = views(created[0])
    for ua in ['Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36 Chrome-Lighthouse',
               'Mozilla/5.0 (X11) AppleWebKit/537.36 HeadlessChrome/120 Safari/537.36',
               'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120 Safari/537.36 PTST/240901',
               'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36 GTmetrix',
               'Mozilla/5.0 (compatible; Pingdom.com_bot_version_1.4)',
               'Mozilla/5.0 (Linux; Android 11) Chrome/120 Mobile Safari/537.36 (compatible; Google-PageSpeed-Insights)',
               'lscache_runner']:
        get('/wp-json/tdd/v1/view', data=json.dumps({'id': int(created[0])}).encode(), headers={'Content-Type': 'application/json', 'User-Agent': ua})
    check('perf/uptime tool user agents are not counted', views(created[0]) == v0, views(created[0]) - v0)
    # Local Lighthouse 10+ uses a plain mobile Chrome UA; tests/perf/lh.mjs adds X-TDD-Perf-Test to every request.
    get('/wp-json/tdd/v1/view', data=json.dumps({'id': int(created[0])}).encode(), headers={'Content-Type': 'application/json', 'X-TDD-Perf-Test': '1', 'User-Agent': 'Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36'})
    check('lab runs marked X-TDD-Perf-Test are not counted', views(created[0]) == v0, views(created[0]) - v0)
    get('/wp-json/tdd/v1/view', data=json.dumps({'id': int(created[0])}).encode(), headers={'Content-Type': 'application/json', 'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15'})
    check('a normal reader is still counted', views(created[0]) == v0 + 1, views(created[0]) - v0)
    beacon = open(os.path.join(os.path.dirname(__file__), '../../theme/techdosedaily/assets/js/view-beacon.js')).read()
    check('beacon skips automated browsers and prerenders', 'navigator.webdriver' in beacon and 'prerendering' in beacon)
finally:
    wp('user delete tdd-cache-test-editor --yes --reassign=1')
    for p in created:
        wpeval(f'global $wpdb; $wpdb->delete(tdd_core_placements_table(), ["post_id" => {p}]); $wpdb->delete(tdd_core_views_table(), ["post_id" => {p}]); tdd_core_placements_bump();')
        wp(f'post delete {p} --force')
    s, h, b = get('/')
    lead = re.search(r'hp-hero__main.*?<a href="([^"]+)"', b, re.S).group(1)
    check('clean-up: homepage lead restored', lead == lead_before, (lead, lead_before))

print(f'\n{ok} passed, {fail} failed')
sys.exit(1 if fail else 0)

#!/usr/bin/env python3
"""Most Read counting ceilings (Core 0.9.3) and the origin client-IP retest (gate C5).

A. In-process (WP-CLI): per-story and site-wide ceilings with rotating client addresses, both counters
   kept consistent when a story ceiling discards a view, the warning logged once per type per hour,
   dedupe unchanged, recovery when the hourly bucket turns, configuration (option, filter, defaults).
   Runs in test-only buckets (year 2099), so real counts are never touched.
B. Concurrency: 8 PHP processes record views for one story at the same moment; the row must land
   exactly on the ceiling (one atomic SQL statement per counter).
C. HTTP, origin retest: POST /tdd/v1/view straight to the origin with five forged, rotating
   X-Forwarded-For addresses. Counted once = the origin ignores forged addresses (host fix active);
   counted more = the origin still trusts them (C5 stays open). Then 20 concurrent forged requests:
   the story ceiling must hold over HTTP too.
D. Cache and delivery: the story page answers 200 and comes from the page cache after its ceiling is
   hit; the beacon always answers 204 and is never served from the cache.

Removes everything it creates: test stories, their view rows, the 2099 buckets and warning flags; the
test views counted in the current hour are subtracted from the site-wide row; the ceiling option is
restored. Env: BASE, WP (wp-cli command), TDD_BASIC_AUTH (staging), TDD_ORIGIN_IP (for C).
Run on the server with deployment/staging-popularity.sh.
"""
import base64, json, os, re, shlex, subprocess, sys
from concurrent.futures import ThreadPoolExecutor

import requests

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090').rstrip('/')
HOST = BASE.split('://', 1)[1]
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
ORIGIN = os.environ.get('TDD_ORIGIN_IP', '')
AUTH = os.environ.get('TDD_BASIC_AUTH', '')
UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36'
R = {'pass': 0, 'fail': 0}


def check(name, cond, detail=''):
    R['pass' if cond else 'fail'] += 1
    print('  ' + ('PASS ' if cond else 'FAIL ') + name + ('' if cond else f' — {str(detail)[:300]}'))


def wp(cmd):
    out = subprocess.run(f'{WP} {cmd}', shell=True, capture_output=True, text=True).stdout
    return re.sub(r'Success: Purged all caches successfully\.\n?', '', out).strip()  # LiteSpeed notice


def wpeval(php):
    return wp('eval ' + shlex.quote("eval(base64_decode('" + base64.b64encode(php.encode()).decode() + "'));"))


def row(post, bucket):
    return int(wpeval(f'global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare("SELECT views FROM " . tdd_core_views_table() . " WHERE post_id = %d AND bucket = %s", {post}, "{bucket}"));') or 0)


def prelude(bucket, story, site):
    """In-process: test bucket and ceilings, a browser user agent, a settable client address."""
    return (f'$_SERVER["HTTP_USER_AGENT"] = "{UA}"; unset($_SERVER["HTTP_X_TDD_PERF_TEST"]);'
            f'add_filter("tdd_core_view_bucket", fn() => "{bucket}");'
            f'add_filter("tdd_core_view_ceilings", fn() => array("story" => {story}, "site" => {site}));'
            '$GLOBALS["tdd_ip"] = ""; add_filter("tdd_core_client_ip", fn() => $GLOBALS["tdd_ip"]);')


def origin_post(post, fake_ip):
    """POST the beacon straight to the origin (curl --resolve keeps TLS/SNI right); login via stdin."""
    cfg = ('user = "' + AUTH.replace('\\', '\\\\').replace('"', '\\"') + '"\n') if AUTH else ''
    r = subprocess.run(['curl', '-s', '-k', '-o', '/dev/null', '-w', '%{http_code}', '--config', '-', '--resolve', f'{HOST}:443:{ORIGIN}',
                        '-H', f'User-Agent: {UA}', '-H', f'X-Forwarded-For: {fake_ip}', '-H', f'X-Real-IP: {fake_ip}', '-H', 'Content-Type: application/json',
                        '--data', json.dumps({'id': post}), f'https://{HOST}/wp-json/tdd/v1/view'], input=cfg, capture_output=True, text=True)
    return r.stdout


T1, T2, T3 = '2099-01-01 00:00:00', '2099-01-01 01:00:00', '2099-01-01 02:00:00'
posts, opt_before = [], None
try:
    print('== Setup')
    opt_before = wpeval('echo wp_json_encode(get_option("tdd_core_view_ceilings", null));')
    ai = wp('term get category ai --by=slug --field=term_id')
    for n in range(3):
        p = int(wp(f"post create --post_type=post --post_status=publish --post_category={ai} --post_title='Counting ceiling test {n + 1}' --post_content='<!-- wp:paragraph --><p>Test.</p><!-- /wp:paragraph -->' --porcelain"))
        wp(f'post meta set {p} _tdd_fixture phase8-pop')
        posts.append(p)
    P1, P2, P3 = posts
    print(f'  test stories {posts}')

    print('== A. Ceilings in-process (story 5, site 8 per hour; rotating addresses)')
    out = json.loads(wpeval(prelude(T1, 5, 8) + f'''
        $log = tempnam(sys_get_temp_dir(), "tddvc"); ini_set("error_log", $log);
        $hits = array("story" => 0, "site" => 0);
        add_action("tdd_core_view_ceiling_reached", function ($k) use (&$hits) {{ $hits[$k]++; }});
        $r1 = 0; for ($i = 1; $i <= 20; $i++) {{ $GLOBALS["tdd_ip"] = "198.51.100.$i"; $r1 += (int) tdd_core_record_view({P1}); }}
        $r2 = 0; for ($i = 1; $i <= 10; $i++) {{ $GLOBALS["tdd_ip"] = "198.51.101.$i"; $r2 += (int) tdd_core_record_view({P2}); }}
        $GLOBALS["tdd_ip"] = "198.51.100.1"; $dup = tdd_core_record_view({P1});
        $lines = array_values(array_filter(explode("\\n", (string) file_get_contents($log)), fn($l) => str_contains($l, "view-count ceiling")));
        @unlink($log);
        echo wp_json_encode(array("r1" => $r1, "r2" => $r2, "dup" => $dup, "hits" => $hits, "lines" => $lines));'''))
    check('per-story ceiling: 20 rotating readers → exactly 5 counted', out['r1'] == 5 and row(P1, T1) == 5, (out['r1'], row(P1, T1)))
    check('site ceiling: the second story gets only the remaining 3', out['r2'] == 3 and row(P2, T1) == 3, (out['r2'], row(P2, T1)))
    check('site-wide counter equals the counted story views (no drift on discards)', row(0, T1) == 8, row(0, T1))
    check('every discarded view raises the monitoring action (15 story, 7 site)', out['hits'] == {'story': 15, 'site': 7}, out['hits'])
    check('warning logged once per ceiling type per hour', len(out['lines']) == 2 and any('per-story' in l for l in out['lines']) and any('site-wide' in l for l in out['lines']), out['lines'])
    check('a reader counted in the last half hour is not counted again', out['dup'] is False, out['dup'])
    r = int(wpeval(prelude(T2, 5, 8) + f'$c = 0; for ($i = 21; $i <= 23; $i++) {{ $GLOBALS["tdd_ip"] = "198.51.100.$i"; $c += (int) tdd_core_record_view({P1}); }} echo $c;') or 0)
    check('recovery: the next hourly bucket counts again', r == 3 and row(P1, T2) == 3 and row(0, T2) == 3, (r, row(P1, T2), row(0, T2)))
    check('the full bucket stays at its ceiling', row(P1, T1) == 5 and row(0, T1) == 8, (row(P1, T1), row(0, T1)))
    cfg = json.loads(wpeval('''add_filter("pre_option_tdd_core_view_ceilings", fn() => array()); $d = tdd_core_view_ceilings();
        remove_all_filters("pre_option_tdd_core_view_ceilings"); add_filter("pre_option_tdd_core_view_ceilings", fn() => array("story" => 7, "site" => 9)); $o = tdd_core_view_ceilings();
        add_filter("tdd_core_view_ceilings", fn() => array("story" => 2, "site" => 0)); $f = tdd_core_view_ceilings();
        echo wp_json_encode(array($d, $o, $f));'''))
    check('configurable: defaults 1000/5000, option respected, filter wins, values kept ≥ 1', cfg == [{'story': 1000, 'site': 5000}, {'story': 7, 'site': 9}, {'story': 2, 'site': 1}], cfg)

    print('== B. Concurrency: 8 PHP processes × 10 rotating readers at once (story ceiling 5)')
    start = __import__('time').time() + 6  # every process waits for this moment, then fires (WordPress boot takes ~1 s)
    def burst(n):
        return int(wpeval(prelude(T3, 5, 1000) + f'time_sleep_until({start}); $c = 0; for ($i = 1; $i <= 10; $i++) {{ $GLOBALS["tdd_ip"] = "203.0.113." . ({n} * 10 + $i); $c += (int) tdd_core_record_view({P3}); }} echo $c;') or 0)
    with ThreadPoolExecutor(8) as ex:
        counted = list(ex.map(burst, range(8)))
    check('concurrent beacons never overshoot: row exactly at the ceiling', row(P3, T3) == 5, (row(P3, T3), counted))
    check('processes report 5 recorded in total (all 8 fired at the same moment)', sum(counted) == 5, counted)
    check('site row for that bucket matches', row(0, T3) == 5, row(0, T3))

    if os.environ.get('TDD_INPROCESS_ONLY'):
        raise SystemExit  # preflight: A and B only (no HTTP, no login needed); clean-up still runs
    print('== C. HTTP: forged rotating X-Forwarded-For straight to the origin (origin client-IP retest)')
    now = wpeval('echo gmdate("Y-m-d H:00:00");')
    wpeval('update_option("tdd_core_view_ceilings", array("story" => 3, "site" => 1000000));')
    if ORIGIN:
        codes = [origin_post(P1, f'192.0.2.{i}') for i in range(1, 6)]
        n = row(P1, now)
        check('origin retest: five forged addresses count as ONE reader (origin ignores X-Forwarded-For)', n == 1, f'{n} counted from forged addresses — the origin still trusts them (HTTP {codes})')
        with ThreadPoolExecutor(10) as ex:
            codes = list(ex.map(lambda i: origin_post(P2, f'192.0.2.{i}'), range(10, 30)))
        check('20 concurrent forged rotating requests over HTTP: story ceiling holds (≤ 3)', row(P2, now) <= 3, row(P2, now))
        check('beacon answers 204 whether counted or not', set(codes) == {'204'}, codes)
    else:
        check('origin retest needs TDD_ORIGIN_IP', False, 'not set')

    print('== D. Page delivery and cache after the ceiling')
    s = requests.Session()
    s.headers.update({'User-Agent': UA})
    if AUTH:
        s.auth = tuple(AUTH.split(':', 1))
    url = wpeval(f'echo get_permalink({P1});')
    a, b = s.get(url, timeout=30), s.get(url, timeout=30)
    check('story page answers 200 after its ceiling is hit', a.status_code == 200 and b.status_code == 200, (a.status_code, b.status_code))
    check('story page still served from the page cache', 'hit' in b.headers.get('X-LiteSpeed-Cache', '').lower() or b.headers.get('X-Page-Cache') == 'HIT', (b.headers.get('X-LiteSpeed-Cache'), b.headers.get('X-Page-Cache')))
    check('story page still loads the view beacon', 'view-beacon' in a.text, 'view-beacon.js not in the page')
    v1 = s.post(BASE + '/wp-json/tdd/v1/view', json={'id': P1}, timeout=30)
    v2 = s.post(BASE + '/wp-json/tdd/v1/view', json={'id': P1}, timeout=30)
    check('beacon answers 204 and is never served from the cache', v1.status_code == v2.status_code == 204 and 'hit' not in v2.headers.get('X-LiteSpeed-Cache', '').lower(), (v1.status_code, v2.status_code, v2.headers.get('X-LiteSpeed-Cache')))
finally:
    print('== Clean-up')
    if posts:
        ids = ','.join(map(str, posts))
        # Test views counted in real hourly buckets also raised those buckets' site-wide rows: take them back out.
        wpeval(f'''global $wpdb; $t = tdd_core_views_table();
            foreach ($wpdb->get_results("SELECT bucket, SUM(views) n FROM $t WHERE post_id IN ({ids}) AND bucket < '2099-01-01 00:00:00' GROUP BY bucket") as $b) {{
                $wpdb->query($wpdb->prepare("UPDATE $t SET views = GREATEST(0, CAST(views AS SIGNED) - %d) WHERE post_id = 0 AND bucket = %s", (int) $b->n, $b->bucket));
            }}
            $wpdb->query("DELETE FROM $t WHERE post_id IN ({ids}) OR bucket >= '2099-01-01 00:00:00'");''')
        for p in posts:
            wp(f'post delete {p} --force')
    wpeval('foreach (array("2099-01-01 00:00:00", "2099-01-01 01:00:00", "2099-01-01 02:00:00") as $b) { foreach (array("story", "site") as $k) { delete_transient("tdd_vcw_" . $k . "_" . md5($b)); } }')
    if opt_before in (None, '', 'null'):
        wp('option delete tdd_core_view_ceilings')
    elif opt_before:
        wpeval(f'update_option("tdd_core_view_ceilings", json_decode({json.dumps(opt_before)}, true));')
    left = wpeval('''global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM " . tdd_core_views_table() . " WHERE bucket >= '2099-01-01 00:00:00'"), " / ",
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tdd_fixture' AND meta_value = 'phase8-pop'"), " / option ", wp_json_encode(get_option("tdd_core_view_ceilings", null));''')
    print(f'  left over — 2099 rows / test stories / ceiling option: {left}')

print(f"\n{R['pass']} passed, {R['fail']} failed")
sys.exit(1 if R['fail'] else 0)

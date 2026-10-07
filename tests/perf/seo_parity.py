#!/usr/bin/env python3
"""Phase 7: SEO output must be identical cached vs uncached, in both schema-owner modes.

  python3 tests/perf/seo_parity.py snapshot <file>   # uncached SEO head of every URL, both owners → file
  python3 tests/perf/seo_parity.py compare <file>    # current uncached AND cached (HIT) output == snapshot
  python3 tests/perf/seo_parity.py pairs             # LiteSpeed/staging: fresh vs cached copy per URL, both owners

Compared per URL: <title>, meta description, canonical, robots (meta + X-Robots-Tag), prev/next,
og:* / article:* / twitter:* tags, every JSON-LD block (class + parsed JSON), plus the XML sitemaps.
"""
import json, os, re, subprocess, sys, urllib.request

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
import staging_env  # noqa: E402,F401 — staging directory login and LiteSpeed purge relay when TDD_BASIC_AUTH is set

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
URLS = json.load(open(os.path.join(os.path.dirname(__file__), '../seo/urls.json')))
URLS.update({'search': '/?s=AI', '404': '/no-such-page-xyz/', 'topic-thin': '/topic/ai-policy/', 'home-audit': '/?tdd_audit=1'})
OWNER_BEFORE = subprocess.run(f'{WP} option get tdd_core_schema_owner', shell=True, capture_output=True, text=True).stdout.strip() or 'yoast'
SITEMAPS = ['/sitemap_index.xml', '/post-sitemap.xml', '/page-sitemap.xml', '/category-sitemap.xml', '/tdd_topic-sitemap.xml', '/author-sitemap.xml']


def fetch(path, bypass):
    h = {'X-TDD-Perf-Test': '1', **({'X-Cache-Bypass': '1'} if bypass else {})}
    try:
        r = urllib.request.urlopen(urllib.request.Request(BASE + path, headers=h), timeout=60)
        return r.status, dict(r.headers), r.read().decode('utf8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), e.read().decode('utf8', 'replace')


def seo(path, bypass):
    st, hd, body = fetch(path, bypass)
    if path.endswith('.xml'):
        return {'status': st, 'robots_header': hd.get('X-Robots-Tag'), 'locs': re.findall(r'<loc>([^<]+)', body)}, hd.get('X-Page-Cache')
    head = body.split('</head>')[0]
    metas = sorted(re.findall(r'<meta\s+(?:name|property)=["\']((?:description|robots|og:[^"\']+|article:[^"\']+|twitter:[^"\']+))["\']\s+content=["\']([^"\']*)["\']', head))
    links = sorted(re.findall(r'<link\s+rel=["\'](canonical|prev|next)["\']\s+href=["\']([^"\']+)["\']', head))
    ld = [(c, json.loads(j)) for c, j in re.findall(r'<script type="application/ld\+json"[^>]*class="([^"]+)"[^>]*>(.*?)</script>', body, re.S)]
    return {'status': st, 'title': re.findall(r'<title>(.*?)</title>', head, re.S), 'meta': metas, 'links': links,
            'robots_header': hd.get('X-Robots-Tag'), 'jsonld': ld}, hd.get('X-Page-Cache')


def collect(bypass):
    out = {}
    for owner in ('yoast', 'core'):
        subprocess.run(f'{WP} option update tdd_core_schema_owner {owner}', shell=True, capture_output=True)
        for k, p in list(URLS.items()) + [(s, s) for s in SITEMAPS]:
            if not bypass:
                fetch(p, False)  # warm
            out[f'{owner}:{k}'], cache = seo(p, bypass)
            out[f'{owner}:{k}']['_cache'] = cache
    subprocess.run(f'{WP} option update tdd_core_schema_owner {OWNER_BEFORE}', shell=True, capture_output=True)
    return out


def cstate(hd):
    """HIT/MISS from the nginx harness (X-Page-Cache) or LiteSpeed (X-LiteSpeed-Cache)."""
    if hd.get('X-Page-Cache'):
        return hd['X-Page-Cache']
    return 'HIT' if 'hit' in (hd.get('X-LiteSpeed-Cache') or '').lower() else 'MISS'


def pairs():
    """Hosts without a bypass header (LiteSpeed): each owner switch purges every page, so per URL the first
    request is a fresh copy and the second the cached one. Both must give identical SEO output."""
    fails = fresh = cached = 0
    for owner in ('yoast', 'core'):
        subprocess.run(f'{WP} option update tdd_core_schema_owner {owner}', shell=True, capture_output=True)  # purges
        for k, p in list(URLS.items()) + [(s, s) for s in SITEMAPS]:
            st1, hd1, b1 = fetch(p, False)
            st2, hd2, b2 = fetch(p, False)
            a, b = seo_of(p, st1, hd1, b1), seo_of(p, st2, hd2, b2)
            c1, c2 = cstate(hd1), cstate(hd2)
            fresh += c1 != 'HIT'
            cached += c2 == 'HIT'
            if c1 == 'HIT':
                fails += 1
                print(f'  FAIL {owner}:{k} first request after the purge came from the cache — no fresh copy to compare')
            elif a != b:
                fails += 1
                print(f'  FAIL {owner}:{k} fresh vs cached', json.dumps({x: (a.get(x), b.get(x)) for x in a if a.get(x) != b.get(x)})[:600])
    subprocess.run(f'{WP} option update tdd_core_schema_owner {OWNER_BEFORE}', shell=True, capture_output=True)
    n = 2 * (len(URLS) + len(SITEMAPS))
    print(f'  {n} URL×owner pairs: {fresh} fresh first copies, {cached} second copies served from the page cache')
    print('PASS: SEO output identical fresh vs cached, both owners' if not fails else f'{fails} differences')
    return fails


def seo_of(path, st, hd, body):
    if path.endswith('.xml'):
        return {'status': st, 'robots_header': hd.get('X-Robots-Tag'), 'locs': re.findall(r'<loc>([^<]+)', body)}
    head = body.split('</head>')[0]
    metas = sorted(re.findall(r'<meta\s+(?:name|property)=["\']((?:description|robots|og:[^"\']+|article:[^"\']+|twitter:[^"\']+))["\']\s+content=["\']([^"\']*)["\']', head))
    links = sorted(re.findall(r'<link\s+rel=["\'](canonical|prev|next)["\']\s+href=["\']([^"\']+)["\']', head))
    ld = [(c, json.loads(j)) for c, j in re.findall(r'<script type="application/ld\+json"[^>]*class="([^"]+)"[^>]*>(.*?)</script>', body, re.S)]
    return {'status': st, 'title': re.findall(r'<title>(.*?)</title>', head, re.S), 'meta': metas, 'links': links, 'robots_header': hd.get('X-Robots-Tag'), 'jsonld': ld}


mode = sys.argv[1]
if mode == 'pairs':
    sys.exit(1 if pairs() else 0)
file = sys.argv[2]
if mode == 'snapshot':
    json.dump(collect(True), open(file, 'w'), indent=1, sort_keys=True)
    print('snapshot written', file)
    sys.exit(0)
snap = json.load(open(file))
fails = 0
for label, bypass in (('uncached', True), ('cached', False)):
    cur = collect(bypass)
    for k, v in snap.items():
        a = {x: y for x, y in v.items() if x != '_cache'}
        b = json.loads(json.dumps({x: y for x, y in cur[k].items() if x != '_cache'}, sort_keys=True))
        same = a == b
        fails += not same
        if not same:
            print(f'  FAIL {label} {k}', json.dumps({x: (a.get(x), b.get(x)) for x in a if a.get(x) != b.get(x)})[:600])
    hits = sum(1 for v in cur.values() if v.get('_cache') == 'HIT')
    print(f'  {label}: {len(snap)} URL×owner outputs compared' + (f', {hits} served from the page cache' if not bypass else ''))
print('PASS: SEO output identical' if not fails else f'{fails} differences')
sys.exit(1 if fails else 0)

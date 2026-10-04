#!/usr/bin/env python3
"""Phase 7: SEO output must be identical cached vs uncached, in both schema-owner modes.

  python3 tests/perf/seo_parity.py snapshot <file>   # uncached SEO head of every URL, both owners → file
  python3 tests/perf/seo_parity.py compare <file>    # current uncached AND cached (HIT) output == snapshot

Compared per URL: <title>, meta description, canonical, robots (meta + X-Robots-Tag), prev/next,
og:* / article:* / twitter:* tags, every JSON-LD block (class + parsed JSON), plus the XML sitemaps.
"""
import json, os, re, subprocess, sys, urllib.request

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
URLS = json.load(open(os.path.join(os.path.dirname(__file__), '../seo/urls.json')))
URLS.update({'search': '/?s=AI', '404': '/no-such-page-xyz/', 'topic-thin': '/topic/ai-policy/', 'home-audit': '/?tdd_audit=1'})
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
    subprocess.run(f'{WP} option update tdd_core_schema_owner yoast', shell=True, capture_output=True)
    return out


mode, file = sys.argv[1], sys.argv[2]
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

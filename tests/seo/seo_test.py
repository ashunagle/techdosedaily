"""Phase 6 SEO / structured-data checks against a local site.

Needs tests/fixtures/mu-seo-audit.php in wp-content/mu-plugins and the Phase 2–6 fixtures.
Usage: python3 tests/seo/seo_test.py [base_url]   (writes tests/seo/out/*.json samples)
"""
import json, os, re, subprocess, sys, urllib.request, urllib.error
from html.parser import HTMLParser

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
import staging_env  # noqa: E402,F401 — staging directory login when TDD_BASIC_AUTH is set

B = sys.argv[1] if len(sys.argv) > 1 else os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site') + ' '
OUT = os.path.join(os.path.dirname(__file__), 'out')


def _report():
    # Results are printed at the end; print them on a crash too, so passed checks are never lost.
    if R and not getattr(_report, 'done', False):
        _report.done = True
        print('\n'.join(R))
        print(sum(r.startswith('PASS') for r in R), 'passed,', sum(r.startswith('FAIL') for r in R), 'failed')


__import__('atexit').register(_report)
os.makedirs(OUT, exist_ok=True)
R = []


def ok(name, cond, extra=''):
    R.append(('PASS' if cond else 'FAIL') + ' ' + name + (' — ' + str(extra) if extra and not cond else ''))


def wp(cmd):
    return subprocess.run(WP + cmd, shell=True, capture_output=True, text=True).stdout.strip()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def fetch(path, follow=True):
    url = path if path.startswith('http') else B + path
    opener = urllib.request.build_opener() if follow else urllib.request.build_opener(NoRedirect)
    try:
        r = opener.open(url)
        return r.status, r.read().decode('utf-8'), dict(r.headers), r.url
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', 'replace'), dict(e.headers), url


class Head(HTMLParser):
    def __init__(self):
        super().__init__()
        self.meta, self.links, self.scripts, self._in, self._cls = {}, {}, [], False, ''

    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag == 'meta':
            k = a.get('name') or a.get('property')
            if k:
                self.meta.setdefault(k, []).append(a.get('content', ''))
        if tag == 'link' and a.get('rel'):
            self.links.setdefault(a['rel'], []).append(a.get('href'))
        if tag == 'script' and a.get('type') == 'application/ld+json':
            self._in, self._cls = True, a.get('class', '')
            self.scripts.append([self._cls, ''])

    def handle_data(self, d):
        if self._in:
            self.scripts[-1][1] += d

    def handle_endtag(self, tag):
        if tag == 'script':
            self._in = False


def parse(html):
    h = Head()
    h.feed(html)
    return h


def graph_of(h, cls='tdd-schema-graph'):
    s = [x for x in h.scripts if cls in x[0]]
    if not s:
        return None
    return json.loads(s[0][1])['@graph']


def types(node):
    t = node.get('@type')
    return t if isinstance(t, list) else [t]


def find(graph, t):
    return [n for n in graph if t in types(n)]


def refs(obj, acc):
    if isinstance(obj, dict):
        if set(obj.keys()) == {'@id'}:
            acc.append(obj['@id'])
        for v in obj.values():
            refs(v, acc)
    elif isinstance(obj, list):
        for v in obj:
            refs(v, acc)
    return acc


def defs(obj, acc):
    """@ids defined anywhere in the graph: top-level nodes and nested node objects (e.g. the inline logo)."""
    if isinstance(obj, dict):
        if '@id' in obj and len(obj) > 1:
            acc.append(obj['@id'])
        for v in obj.values():
            defs(v, acc)
    elif isinstance(obj, list):
        for v in obj:
            defs(v, acc)
    return acc


def check_graph(name, g):
    ids = [n.get('@id') for n in g]
    ok(f'{name}: no duplicate @id', len(ids) == len(set(ids)), ids)
    defined = set(defs(g, []))
    missing = [r for r in refs(g, []) if r not in defined]
    ok(f'{name}: every @id reference resolves inside the graph', not missing, missing)
    ok(f'{name}: no empty values', '""' not in json.dumps(g) and '[]' not in json.dumps(g) and 'null' not in json.dumps(g))
    for n in g:
        if 'Person' in types(n):
            ok(f'{name}: Person has no email/login', not any(k in n for k in ('email', 'telephone')), n)


AUDIT = '?tdd_audit=1'
STORIES = {
    'analysis+correction+update+editor+image': ('/ai/major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents/', ['NewsArticle', 'AnalysisNewsArticle']),
    'explainer': ('/ai/agents-that-use-a-computer-are-moving-from-demos-to-daily-work/', ['NewsArticle', 'BackgroundNewsArticle']),
    'guide': ('/guides/run-a-local-ai-coding-assistant-without-sending-code-off-your-machine/', ['NewsArticle']),
    'review': ('/tech/a-week-with-a-lightweight-laptop-built-around-an-on-device-ai-chip/', ['NewsArticle']),
    'sponsored': ('/developer/sample-sponsored-explainer-what-a-managed-vector-database-does/', ['Article', 'AdvertiserContentArticle']),
    'bare-news': ('/software/sample-a-short-news-story-with-no-image-editor-or-topics/', ['NewsArticle']),
}

YOAST = subprocess.run(WP + 'plugin is-active wordpress-seo', shell=True).returncode == 0
print('Yoast SEO active:', YOAST)
# Staging is noindex by requirement: robots/canonical checks then assert the staging behaviour
# (noindex everywhere, no canonical link, graph URLs = permalinks); production runs the indexable checks.
NOINDEX_SITE = wp('option get blog_public') == '0'
print('Site noindex (staging):', NOINDEX_SITE)
wp('option update tdd_core_schema_owner core')  # Core-graph checks below; owner modes are tested later.

# ---------- Stories ----------
for name, (path, want) in STORIES.items():
    st, html, hd, _ = fetch(path + AUDIT)
    h = parse(html)
    g = graph_of(h)
    ok(f'{name}: 200 + exactly one JSON-LD block', st == 200 and len(h.scripts) == 1, (st, len(h.scripts)))
    if not g:
        continue
    json.dump({'@context': 'https://schema.org', '@graph': g}, open(os.path.join(OUT, f'story-{name}.json'), 'w'), indent=1, ensure_ascii=False)
    check_graph(name, g)
    art = [n for n in g if n.get('@id', '').endswith('#article')]
    ok(f'{name}: one article', len(art) == 1)
    a = art[0]
    ok(f'{name}: type {want}', types(a) == want, types(a))
    canon = (h.links.get('canonical') or [''])[0]
    if NOINDEX_SITE:  # Yoast prints no canonical on noindex pages: the graph must carry the story's permalink.
        ok(f'{name}: no canonical link (noindex site)', not canon, canon)
        canon = B + path
    ok(f'{name}: article url = canonical link', a['url'] == canon, (a['url'], canon))
    ok(f'{name}: mainEntityOfPage → WebPage', a['mainEntityOfPage']['@id'] == canon + '#webpage')
    for k in ('headline', 'datePublished', 'dateModified', 'author', 'publisher', 'articleSection', 'genre', 'isAccessibleForFree', 'wordCount', 'timeRequired'):
        ok(f'{name}: has {k}', k in a)
    ok(f'{name}: no invented properties', not any(k in a for k in ('aggregateRating', 'reviewRating', 'itemReviewed', 'citation', 'backstory', 'isBasedOn')))
    ok(f'{name}: sponsor only on Sponsored', ('sponsor' in a) == (name == 'sponsored'), a.get('sponsor'))
    if name == 'bare-news':
        ok('bare-news: no editor/image/keywords/correction/description', not any(k in a for k in ('editor', 'image', 'keywords', 'correction', 'description')), list(a))
        ok('bare-news: dateModified = datePublished (no recorded update)', a['dateModified'] == a['datePublished'])
        ok('bare-news: no ImageObject', not find(g, 'ImageObject'))
    if name.startswith('analysis'):
        ok('analysis: editor Person present', a['editor']['@id'].endswith('/author/mira-sample/#person'))
        ok('analysis: correction is CorrectionComment with date', a['correction'][0]['@type'] == 'CorrectionComment' and a['correction'][0]['datePublished'])
        ok('analysis: dateModified after datePublished (substantive update)', a['dateModified'] > a['datePublished'])
        img = find(g, 'ImageObject')
        ok('analysis: ImageObject with creditText', img and img[0].get('creditText'))
        ok('analysis: keywords from topics', a.get('keywords'))
    bc = find(g, 'BreadcrumbList')
    ok(f'{name}: breadcrumb Home › Section › story', bc and len(bc[0]['itemListElement']) == 3)

# ---------- Archives and pages ----------
PAGES = {
    'home': ('/', 'WebPage'),
    'latest': ('/latest/', 'CollectionPage'),
    'section': ('/ai/', 'CollectionPage'),
    'section-p2': ('/ai/page/2/', 'CollectionPage'),
    'topic': ('/topic/ai-agents/', 'CollectionPage'),
    'story-type': ('/story-type/analysis/', 'CollectionPage'),
    'author': ('/author/priya-sample/', 'ProfilePage'),
    'about': ('/about/', 'AboutPage'),
    'contact': ('/contact/', 'ContactPage'),
    'policy': ('/editorial-standards/', 'WebPage'),
    'newsletter': ('/newsletter/', 'WebPage'),
}
for name, (path, want) in PAGES.items():
    st, html, hd, _ = fetch(path + AUDIT)
    h = parse(html)
    g = graph_of(h)
    ok(f'{name}: exactly one JSON-LD block', len(h.scripts) == 1, len(h.scripts))
    if not g:
        continue
    json.dump({'@context': 'https://schema.org', '@graph': g}, open(os.path.join(OUT, f'page-{name}.json'), 'w'), indent=1, ensure_ascii=False)
    check_graph(name, g)
    wpage = [n for n in g if n.get('@id', '').endswith('#webpage')]
    ok(f'{name}: page type {want}', wpage and types(wpage[0]) == [want], wpage and types(wpage[0]))
    if name == 'section-p2':
        ok('section-p2: self-referencing page URL', wpage[0]['url'].endswith('/ai/page/2/'), wpage[0]['url'])
    if name == 'author':
        ok('author: ProfilePage.mainEntity = article author Person', wpage[0]['mainEntity']['@id'] == B + '/author/priya-sample/#person')
        p = [n for n in g if n.get('@id') == B + '/author/priya-sample/#person'][0]
        ok('author: sameAs only http(s) URLs', all(u.startswith('http') for u in p.get('sameAs', [])))
    if name in ('about', 'contact'):
        ok(f'{name}: about → organization', wpage[0].get('about', {}).get('@id') == B + '/#organization')
    org = find(g, 'NewsMediaOrganization')
    ok(f'{name}: publisher is NewsMediaOrganization', len(org) == 1)

# ---------- No schema / noindex ----------
for name, path, code in (('search', '/?s=AI', 200), ('404', '/no-such-page-xyz/', 404)):
    st, html, hd, _ = fetch(path)
    h = parse(html)
    rob = ','.join(h.meta.get('robots', []))
    ok(f'{name}: HTTP {code}', st == code, st)
    ok(f'{name}: noindex' + ('' if NOINDEX_SITE else ',follow'), 'noindex' in rob and (NOINDEX_SITE or ('follow' in rob and 'nofollow' not in rob)), rob)
    ok(f'{name}: no JSON-LD', not h.scripts)
    if name == '404':
        ok('404: no canonical', 'canonical' not in h.links)

empty = wp('eval \'foreach(get_users() as $u){ if(!count_user_posts($u->ID,"post",true)){ echo $u->user_nicename; break; } }\'')
if not empty:
    wp('user create seo-empty-sample seo-empty@example.com --role=author --display_name="[Sample] No stories yet"')
    empty = 'seo-empty-sample'
st, html, hd, _ = fetch(f'/author/{empty}/')
h = parse(html)
ok('empty author: noindex + no schema', 'noindex' in ','.join(h.meta.get('robots', [])) and not h.scripts)
st, html, hd, _ = fetch('/topic/ai-policy/' + AUDIT)
h = parse(html)
ok('thin topic (1 story): noindex', 'noindex' in ','.join(h.meta.get('robots', [])), h.meta.get('robots'))
st, html, hd, _ = fetch('/ai/major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents/')
h = parse(html)
ok('fixture story (audit off): noindex', 'noindex' in ','.join(h.meta.get('robots', [])))
st, html, hd, _ = fetch(STORIES['guide'][0] + AUDIT)
h = parse(html)
if NOINDEX_SITE:
    ok('ordinary story (audit on): noindex like the whole site (staging)', 'noindex' in ','.join(h.meta.get('robots', [])), h.meta.get('robots'))
else:
    ok('ordinary story (audit on): indexable', 'noindex' not in ','.join(h.meta.get('robots', [])), h.meta.get('robots'))

# ---------- Redirects and canonical stability ----------
st, _, hd, _ = fetch('/category/ai/', follow=False)
ok('/category/ai/ → 301 /ai/', st == 301 and hd.get('Location', '').rstrip('/').endswith('/ai'), (st, hd.get('Location')))
pid = wp('post list --post_type=post --name=sample-a-short-news-story-with-no-image-editor-or-topics --field=ID')
before = wp(f'post url {pid}') if False else fetch(STORIES['bare-news'][0] + AUDIT)
dev = wp('term get category developer --by=slug --field=term_id')
wp(f'post term set {pid} category {dev} --by=id')
wp(f'post meta update {pid} tdd_primary_section {dev}')
st, html, hd, url = fetch(STORIES['bare-news'][0] + AUDIT)
h = parse(html)
g = graph_of(h)
a = [n for n in g if n.get('@id', '').endswith('#article')][0]
ok('section change: URL and canonical unchanged', st == 200 and a['url'].endswith('/software/sample-a-short-news-story-with-no-image-editor-or-topics/'), (st, a['url']))
ok('section change: articleSection follows the new section', a['articleSection'] == 'Developer', a['articleSection'])
st, _, hd, _ = fetch('/developer/sample-a-short-news-story-with-no-image-editor-or-topics/', follow=False)
ok('section change: other spelling 301s to the stored URL', st == 301 and '/software/' in hd.get('Location', ''), (st, hd.get('Location')))
sw = wp('term get category software --by=slug --field=term_id')
wp(f'post term set {pid} category {sw} --by=id')
wp(f'post meta update {pid} tdd_primary_section {sw}')

# ---------- dateModified only on substantive updates ----------
pub = a['datePublished']
wp(f"post update {pid} --post_content='<!-- wp:paragraph --><p>[Sample] Typo fixed in fixture text.</p><!-- /wp:paragraph -->' --post_date='2020-01-01 00:00:00'")
st, html, hd, _ = fetch(STORIES['bare-news'][0] + AUDIT)
a2 = [n for n in graph_of(parse(html)) if n.get('@id', '').endswith('#article')][0]
ok('typo save + post date edit: datePublished frozen, dateModified unchanged', a2['datePublished'] == pub and a2['dateModified'] == pub, (pub, a2['datePublished'], a2['dateModified']))

# ---------- Attachments ----------
att_id = wp('post list --post_type=attachment --post_mime_type=image --field=ID --posts_per_page=1')  # any image on the site
st, _, hd, _ = fetch(f'/?attachment_id={att_id}', follow=False)
ok('attachment page redirects to the file (no thin page)', st in (301, 302) and '/wp-content/uploads/' in hd.get('Location', ''), (st, hd.get('Location')))

# ---------- Owner switching ----------
def blocks(path):
    return [c for c, _ in parse(fetch(path)[1]).scripts]

OWNER_PAGES = [STORIES['guide'][0] + AUDIT, '/ai/' + AUDIT, '/author/priya-sample/', '/editorial-standards/' + '?tdd_audit=1']
if YOAST:
    wp('option update tdd_core_schema_owner yoast')
    for pth in OWNER_PAGES:
        ok(f'owner=yoast (real Yoast): Yoast graph only on {pth}', blocks(pth) == ['yoast-schema-graph'], blocks(pth))
    wp('option update tdd_core_schema_owner core')
    for pth in OWNER_PAGES:
        b = blocks(pth)
        ok(f'owner=core (real Yoast): Core graph only, Yoast JSON-LD suppressed on {pth}', b == ['tdd-schema-graph'], b)
    wp('option update tdd_core_schema_owner yoast')
    ok('switching back restores the Yoast graph', blocks(OWNER_PAGES[0]) == ['yoast-schema-graph'])
    for pth in ('/no-such-page-xyz/', '/?s=AI'):
        ok(f'no schema from either owner on {pth} (owner=yoast)', blocks(pth) == [], blocks(pth))
    wp('plugin deactivate wordpress-seo')
    ok('setting=yoast + Yoast deactivated: Core graph only (never neither)', blocks(OWNER_PAGES[0]) == ['tdd-schema-graph'], blocks(OWNER_PAGES[0]))
    wp('plugin activate wordpress-seo')
    ok('Yoast reactivated: Yoast graph again', blocks(OWNER_PAGES[0]) == ['yoast-schema-graph'])
else:
    wp('option update tdd_core_schema_owner yoast')
    path = STORIES['guide'][0] + AUDIT
    ok('owner=yoast + Yoast stand-in: Yoast graph only', blocks(path + '&tdd_yoast_stub=1') == ['yoast-schema-graph'])
    ok('owner=yoast + Yoast missing: Core graph only (never neither)', blocks(path) == ['tdd-schema-graph'])
    wp('option update tdd_core_schema_owner core')
    ok('owner=core + Yoast stand-in: Core graph only', blocks(path + '&tdd_yoast_stub=1') == ['tdd-schema-graph'])
wp('option update tdd_core_schema_owner core')  # Escaping checks read the Core graph.

# ---------- Escaping ----------
eid = wp("post create --post_type=post --post_status=publish --post_title='[Sample] Escape </script><b>test</b> & \"quotes\"' --porcelain")
wp(f'post meta update {eid} _tdd_fixture phase6')
wp(f"post meta update {eid} tdd_deck 'Deck with </script><script>alert(1)</script> & ampersand'")
st, html, hd, _ = fetch(wp(f'post url {eid}') + AUDIT) if False else fetch(f'/?p={eid}&tdd_audit=1')
h = parse(html)
raw = h.scripts[0][1] if h.scripts else ''
ok('escaping: no raw </script> or <script inside JSON-LD', '</script' not in raw.lower() and '<script' not in raw.lower())
try:
    d = json.loads(raw)
    art = [n for n in d['@graph'] if n.get('@id', '').endswith('#article')][0]
    ok('escaping: JSON valid; markup stripped, & and quotes round-trip', art['headline'] == '[Sample] Escape test & "quotes"' and '<' not in art['description'], (art['headline'], art['description']))
except Exception as e:  # noqa
    ok('escaping: JSON valid', False, e)
wp(f'post delete {eid} --force')

# ---------- Preview / draft ----------
did = wp("post create --post_type=post --post_status=draft --post_title='[Sample] Draft' --porcelain")
st, html, hd, _ = fetch(f'/?p={did}&preview=true')
ok('draft preview (logged out): not public, no schema', st == 404 and not parse(html).scripts, st)
wp(f'post delete {did} --force')

# ---------- Sitemaps ----------
if YOAST:
    st, _, hd, _ = fetch('/wp-sitemap.xml', follow=False)
    ok('core sitemap redirects to Yoast sitemap index', st == 301 and hd.get('Location', '').endswith('/sitemap_index.xml'), (st, hd.get('Location')))
    st, idx, hd, _ = fetch('/sitemap_index.xml')
    ok('Yoast sitemap index 200', st == 200, st)
    ok('no attachment / tag / post-format sitemaps', not any(x in idx for x in ('attachment-sitemap', 'post_tag-sitemap', 'post_format-sitemap')), re.findall(r'<loc>([^<]+)', idx))
    st, posts, hd, _ = fetch('/post-sitemap.xml')
    ok('fixture stories excluded from the Yoast post sitemap', '/major-ai-provider' not in posts, st)
    st, posts, hd, _ = fetch('/post-sitemap.xml?tdd_audit=1')
    locs = re.findall(r'<loc>([^<]+)', posts)
    ok('(audit) Yoast post sitemap lists stories by canonical URL', B + '/ai/major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents/' in locs, locs[:3])
    ok('(audit) no category-base or attachment URLs in post sitemap', not any('/category/' in l or 'attachment' in l for l in locs if not l.endswith(('.webp', '.jpg', '.png'))))
    st, topics, hd, _ = fetch('/tdd_topic-sitemap.xml')
    ok('thin topics excluded from the Yoast topic sitemap', '/topic/ai-policy/' not in topics and '/topic/ai-agents/' in topics, re.findall(r'<loc>([^<]+)', topics)[:5])
    st, pages, hd, _ = fetch('/page-sitemap.xml')
    ok('fixture pages excluded from the Yoast page sitemap', 'phase-2-components' not in pages and '/about/' not in pages)
    st, cats, hd, _ = fetch('/category-sitemap.xml')
    ok('section sitemap uses /ai/ (no /category/ base)', B + '/ai/' in cats and '/category/' not in cats, (st, re.findall(r'<loc>([^<]+)', cats)[:4], cats[:200]))
    st, auth, hd, _ = fetch('/author-sitemap.xml')
    ok('author sitemap: no author without published stories', '/author/seo-empty-sample/' not in auth)
else:
    st, idx, hd, _ = fetch('/wp-sitemap.xml')
    ok('core sitemap index 200', st == 200, st)
    ok('no attachment / tag / format sitemaps', 'attachment' not in idx and 'post_tag' not in idx, idx[:300])
    st, posts, hd, _ = fetch('/wp-sitemap-posts-post-1.xml')
    ok('fixture stories excluded from sitemap', '/major-ai-provider' not in posts, st)
    st, posts, hd, _ = fetch('/wp-sitemap-posts-post-1.xml?tdd_audit=1')
    ok('(audit) stories listed by canonical URL', '/ai/major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents/' in posts)
    st, topics, hd, _ = fetch('/wp-sitemap-taxonomies-tdd_topic-1.xml')
    ok('thin topics excluded from sitemap', '/topic/ai-policy/' not in topics and '/topic/ai-agents/' in topics, topics[:200])
    st, pages, hd, _ = fetch('/wp-sitemap-posts-page-1.xml')
    ok('fixture pages excluded (component gallery)', 'phase-2-components' not in pages)

# ---------- Real Yoast: descriptions, canonicals, robots, social ----------
if YOAST:
    wp('option update tdd_core_schema_owner yoast')
    G = STORIES['guide'][0]
    gid = wp('post list --post_type=post --name=run-a-local-ai-coding-assistant-without-sending-code-off-your-machine --field=ID')
    deck = wp(f'post meta get {gid} tdd_deck')
    h = parse(fetch(G + AUDIT)[1])
    m = lambda k: (h.meta.get(k) or [''])[0]
    ok('Yoast: no explicit description → meta description = deck', m('description') == deck, (m('description'), deck))
    ok('Yoast: og:description = deck (not the auto-excerpt)', m('og:description') == deck, m('og:description'))
    ok('Yoast: twitter:description = deck', m('twitter:description') == deck, m('twitter:description'))
    wp(f"post meta update {gid} _yoast_wpseo_metadesc 'Explicit editor description [test]'")
    h = parse(fetch(G + AUDIT)[1])
    ok('Yoast: explicit description wins (meta)', m('description') == 'Explicit editor description [test]', m('description'))
    ok('Yoast: explicit description wins (og:description)', m('og:description') == 'Explicit editor description [test]', m('og:description'))
    wp(f"post meta update {gid} _yoast_wpseo_opengraph-description 'Explicit social description [test]'")
    h = parse(fetch(G + AUDIT)[1])
    ok('Yoast: explicit social description wins (og)', m('og:description') == 'Explicit social description [test]', m('og:description'))
    wp(f'post meta delete {gid} _yoast_wpseo_metadesc'); wp(f'post meta delete {gid} _yoast_wpseo_opengraph-description')
    h = parse(fetch(STORIES['bare-news'][0] + AUDIT)[1])
    ok('Yoast: story without deck → no invented meta description', not h.meta.get('description'), h.meta.get('description'))
    # Titles
    h = parse(fetch(G + AUDIT)[1])
    titles = re.findall(r'<title>([^<]*)</title>', fetch(G + AUDIT)[1])
    ok('exactly one <title> (block-theme title tag removed under Yoast)', len(titles) == 1, titles)
    ok('404 title "Page not found · Tech Dose Daily"', re.findall(r'<title>([^<]*)</title>', fetch('/no-such-page-xyz/')[1]) == ['Page not found · Tech Dose Daily'])
    # Canonicals
    for pth, want in (('/ai/' + AUDIT, '/ai/'), ('/ai/page/2/' + AUDIT, '/ai/page/2/'), ('/topic/ai-agents/', '/topic/ai-agents/'), ('/author/priya-sample/', '/author/priya-sample/'), (G + AUDIT, G)):
        h = parse(fetch(pth)[1])
        c = (h.links.get('canonical') or [''])[0]
        if NOINDEX_SITE:
            ok(f'Yoast: no canonical on a noindex site: {want}', not c, c)
        else:
            ok(f'Yoast canonical self-referencing: {want}', c == B + want, c)
    h = parse(fetch('/ai/page/2/' + AUDIT)[1])
    if NOINDEX_SITE:
        print('INFO paginated rel=prev not checked on a noindex site (Yoast omits it); the production run checks it')
    else:
        ok('paginated archive: rel=prev → page 1', (h.links.get('prev') or [''])[0] == B + '/ai/', h.links.get('prev'))
    for pth in ('/no-such-page-xyz/', '/?s=AI', '/topic/ai-policy/' + AUDIT):
        h = parse(fetch(pth)[1])
        ok(f'no canonical on noindex view {pth}', 'canonical' not in h.links, h.links.get('canonical'))
    # Robots
    for pth, want in (('/?s=AI', 'noindex'), ('/no-such-page-xyz/', 'noindex'), ('/topic/ai-policy/' + AUDIT, 'noindex'), ('/author/seo-empty-sample/', 'noindex'), (STORIES['guide'][0], 'noindex'), (G + AUDIT, 'index'), ('/ai/page/2/' + AUDIT, 'index')):
        r = ','.join(parse(fetch(pth)[1]).meta.get('robots', []))
        if NOINDEX_SITE:
            ok(f'Yoast robots noindex on {pth} (whole site noindex on staging)', r.startswith('noindex'), r)
            continue
        good = r.startswith('noindex') and 'follow' in r and 'nofollow' not in r if want == 'noindex' else r.startswith('index') and 'follow' in r
        ok(f'Yoast robots {want},follow on {pth}', good, r)
    st, _, hd, _ = fetch('/2026/10/', follow=False)
    ok('date archives disabled (redirect)', st == 301, st)
    # Social metadata
    A = STORIES['analysis+correction+update+editor+image'][0]
    h = parse(fetch(A + AUDIT)[1])
    g = graph_of(parse(fetch(A + AUDIT + '&x=1')[1]), 'yoast-schema-graph')
    ok('OG story: og:type article', m('og:type') == 'article')
    ok('OG story: og:url = canonical', m('og:url') == ((h.links.get('canonical') or [''])[0] or (B + A if NOINDEX_SITE else '')), m('og:url'))
    ok('OG story: og:title present', bool(m('og:title')))
    ok('OG story: og:image = hero image', m('og:image').endswith('.webp'), m('og:image'))
    wp('option update tdd_core_schema_owner core')
    _st, _html, _hd, _ = fetch(A + AUDIT)
    core = ([n for n in (graph_of(parse(_html)) or []) if n.get('@id', '').endswith('#article')] or [{}])[0]
    ok('owner switch to Core: Core graph served at once (every page cache purged, CDN included)', bool(core), {k: v for k, v in _hd.items() if k.lower() in ('x-hcdn-cache-status', 'x-litespeed-cache', 'cache-control', 'age', 'x-tdd-cache')})
    wp('option update tdd_core_schema_owner yoast')
    from datetime import datetime
    dt = lambda x: datetime.fromisoformat(x)
    if core:
        ok('OG story: article:published_time matches Core datePublished', dt(m('article:published_time')) == dt(core['datePublished']), (m('article:published_time'), core['datePublished']))
        ok('OG story: article:modified_time = last substantive update', dt(m('article:modified_time')) == dt(core['dateModified']), (m('article:modified_time'), core['dateModified']))
    ok('twitter:card summary_large_image', m('twitter:card') == 'summary_large_image')
    ok('link preview reading time = story reading time', any('9 minute' in v for v in h.meta.get('twitter:data2', [])), h.meta.get('twitter:data2'))
    yg = graph_of(h, 'yoast-schema-graph')
    yart = ([n for n in (yg or []) if 'Article' in types(n) and 'datePublished' in n] or [{}])[0]
    if yart and core:
        ok('Yoast schema dates = Core dates', dt(yart['datePublished']) == dt(core['datePublished']) and dt(yart['dateModified']) == dt(core['dateModified']), (yart['datePublished'], yart['dateModified']))
    else:
        ok('Yoast schema dates = Core dates (Yoast Article with dates and Core graph both present)', False, ([types(n) for n in (yg or [])], bool(core)))
    ok('Yoast Person has no Gravatar', 'gravatar' not in json.dumps([n for n in yg if 'Person' in types(n)]))
    h = parse(fetch(STORIES['bare-news'][0] + AUDIT)[1])
    ok('OG story without hero: no og:image (no placeholder)', not h.meta.get('og:image'), h.meta.get('og:image'))
    ok('OG story without update: no article:modified_time', not h.meta.get('article:modified_time'), h.meta.get('article:modified_time'))
    for pth, typ in (('/editorial-standards/?tdd_audit=1', 'article'), ('/ai/' + AUDIT, 'article'), ('/author/priya-sample/', 'profile'), ('/', 'website')):
        h = parse(fetch(pth)[1])
        ok(f'OG {pth}: og:type {typ}, og:title, og:url', m('og:type') == typ and m('og:title') and m('og:url'), (m('og:type'), m('og:url')))
    h = parse(fetch('/editorial-standards/?tdd_audit=1')[1])
    ok('OG page: og:description = page intro fallback', m('og:description').startswith('How Tech Dose Daily reports'), m('og:description'))
    h = parse(fetch('/author/priya-sample/')[1])
    ok('OG author: no Gravatar og:image', 'gravatar' not in m('og:image'), m('og:image'))

_report()

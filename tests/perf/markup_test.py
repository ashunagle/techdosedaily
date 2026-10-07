#!/usr/bin/env python3
"""Phase 7 front-end markup checks: image priority/lazy-loading, sizes, minified + versioned assets,
font loading, early search class, no third-party requests. Creates two probe stories and removes them.
Run: python3 tests/perf/markup_test.py"""
import json, os, re, subprocess, sys, tempfile, urllib.parse, urllib.request

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
import staging_env  # noqa: E402,F401 — staging directory login when TDD_BASIC_AUTH is set

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
ok = fail = 0


def check(name, cond, detail=''):
    global ok, fail
    ok, fail = (ok + 1, fail) if cond else (ok, fail + 1)
    print('  PASS' if cond else '  FAIL', name, '' if cond else detail)


def wp(cmd):
    return subprocess.run(f'{WP} {cmd}', shell=True, capture_output=True, text=True).stdout.strip()


def html(path):
    req = urllib.request.Request(BASE + path, headers={'X-Cache-Bypass': '1', 'X-TDD-Perf-Test': '1'})
    try:
        return urllib.request.urlopen(req, timeout=60).read().decode()
    except urllib.error.HTTPError as e:
        return e.read().decode()


def imgs(h):
    # Raster images only (the logo and small SVG marks/icons are excluded).
    return [t for t in re.findall(r'<img\b[^>]*>', h) if not re.search(r'src="[^"]+\.svg', t)]


PAGES = {
    'home': '/', 'article-hero': '/ai/major-ai-provider-unveils-a-reasoning-model-built-for-long-running-coding-agents/',
    'article-nohero': '/software/sample-a-short-news-story-with-no-image-editor-or-topics/', 'section': '/ai/',
    'author': '/author/priya-sample/', 'newsletter': '/newsletter/', 'contact': '/contact/', 'search': '/?s=AI', '404': '/no-such-page-xyz/',
}
EXPECT_HIGH = {'home': 1, 'article-hero': 1, 'section': 1}

print('== Image priority: only the likely LCP hero is eager + high priority')
for k, p in PAGES.items():
    h = html(p)
    tags = imgs(h)
    high = [t for t in tags if 'fetchpriority="high"' in t]
    eager = [t for t in tags if 'loading="eager"' in t]
    check(f'{k}: {EXPECT_HIGH.get(k, 0)} high-priority image(s)', len(high) == EXPECT_HIGH.get(k, 0), [t[:120] for t in high])
    check(f'{k}: eager only where high priority', len(eager) == len(high), len(eager))
    check(f'{k}: every other content image is lazy', all('loading="lazy"' in t for t in tags if t not in high), [t[:100] for t in tags if t not in high and 'loading="lazy"' not in t][:2])
    check(f'{k}: every image has width/height', all(re.search(r'\bwidth="\d+"', t) and re.search(r'\bheight="\d+"', t) for t in tags))
    check(f'{k}: srcset images carry sizes', all('sizes="' in t for t in tags if 'srcset=' in t))
    req_urls = re.findall(r'<(?:img|script|iframe|source|video|audio)\b[^>]*\ssrcset?=["\']([^"\']+)', h) + re.findall(r'<link\b[^>]*rel=["\'](?:stylesheet|preload|modulepreload|icon|preconnect)["\'][^>]*href=["\']([^"\']+)', h)
    third = {urllib.parse.urlsplit(u.split()[0] if not u.startswith('//') else 'http:' + u).netloc for u in req_urls} - {'', urllib.parse.urlsplit(BASE).netloc}
    check(f'{k}: no third-party requests (scripts, styles, fonts, images, iframes)', not third, third)
    check(f'{k}: theme CSS/JS served minified + file-versioned', all('.min.' in u and re.search(r'ver=[\d.]+\.\d{9,}', u) for u in re.findall(r'(?:href|src)=["\']([^"\']*themes/techdosedaily/assets/(?:css|js)/[^"\']+)', h)))

print('== Story/page content: images and embeds lazy, reading-column sizes')
att = wp("post list --post_type=attachment --post_mime_type=image --field=ID --posts_per_page=1")
url = wp(f"eval 'echo wp_get_attachment_image_url({att}, \"large\");'")
content = (f'<!-- wp:paragraph --><p>Probe.</p><!-- /wp:paragraph --><!-- wp:image {{"id":{att},"sizeSlug":"large"}} --><figure class="wp-block-image size-large">'
           f'<img src="{url}" alt="" class="wp-image-{att}"/></figure><!-- /wp:image --><!-- wp:html --><iframe src="https://example.org/embed" width="560" height="315" title="Probe embed"></iframe><!-- /wp:html -->')
made = []
try:
    for hero in (True, False):
        with tempfile.NamedTemporaryFile('w', suffix='.html', delete=False) as f:
            f.write(content)
        pid = wp(f"post create {f.name} --post_status=publish --post_title='Perf markup probe {int(hero)}' --porcelain")
        os.unlink(f.name)
        made.append(pid)
        wp(f'post meta set {pid} _tdd_fixture 1')
        if hero:
            wp(f'post meta set {pid} _thumbnail_id {att}')
        h = html(wp(f'post list --p={pid} --post_type=post --field=url').replace(BASE, ''))
        body = re.findall(r'<img\b[^>]*wp-image-[^>]*>', h)
        frames = re.findall(r'<iframe\b[^>]*>', h)
        lbl = 'with hero' if hero else 'without hero'
        check(f'{lbl}: body image lazy, not high priority', body and all('loading="lazy"' in t and 'fetchpriority="high"' not in t for t in body), body)
        check(f'{lbl}: body image sizes match the 740px column', body and all('(max-width: 767px) calc(100vw - 40px), 740px' in t for t in body), body)
        check(f'{lbl}: embed iframe lazy', frames and all('loading="lazy"' in t for t in frames), frames)
        highs = [t for t in imgs(h) if 'fetchpriority="high"' in t]
        check(f'{lbl}: {"only the hero" if hero else "no image"} is high priority', len(highs) == (1 if hero else 0) and (not hero or 'wp-image-' not in highs[0]), highs)
finally:
    for pid in made:
        wp(f'post delete {pid} --force')

print('== Fonts and early layout state')
h = html('/')
check('two fonts preloaded (Manrope 800, Inter 400)', len(re.findall(r'rel="preload"[^>]*as="font"', h)) == 2)
css = urllib.request.urlopen(re.search(r'href=["\']([^"\']*tokens\.min\.css[^"\']*)', h).group(1)).read().decode()
check('metric-matched fallback faces shipped', 'Inter Fallback' in css and 'Manrope Fallback' in css and 'size-adjust' in css)
check('web fonts use font-display: swap', 'font-display:swap' in h.replace(' ', ''))
s = html('/?s=AI')
check('search: JS layout class set in <head> before first paint', s.find("classList.add('tdd-js-search')") < s.find('</head>') and "classList.add('tdd-js-search')" in s)

print(f'\n{ok} passed, {fail} failed')
sys.exit(1 if fail else 0)

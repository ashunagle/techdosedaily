#!/usr/bin/env python3
"""Save the JSON-LD of every SEO test URL, for each schema owner, for offline validation (validate.mjs --dir).

Writes <out>/<owner>/<name>.json (one array of JSON-LD blocks per page). Needs mu-seo-audit.php on the target
(?tdd_audit=1 treats [Sample] content as real). Restores the schema owner to yoast.
Usage: BASE=… WP=… python3 tests/seo/save_graphs.py <out-dir>
"""
import json, os, re, subprocess, sys, urllib.request, urllib.error

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
import staging_env  # noqa: E402,F401 — staging directory login and LiteSpeed purge relay when TDD_BASIC_AUTH is set

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090').rstrip('/')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
URLS = json.load(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'urls.json')))
out_dir = sys.argv[1]
saved = 0
try:
    for owner in ('yoast', 'core'):
        subprocess.run(f'{WP} option update tdd_core_schema_owner {owner}', shell=True, capture_output=True)  # purges
        os.makedirs(os.path.join(out_dir, owner), exist_ok=True)
        for name, path in URLS.items():
            try:
                body = urllib.request.urlopen(urllib.request.Request(BASE + path, headers={'X-TDD-Perf-Test': '1'}), timeout=60).read().decode('utf8', 'replace')
            except urllib.error.HTTPError as e:
                print(f'  {owner}:{name} HTTP {e.code} — skipped')
                continue
            blocks = [{'class': c, 'data': json.loads(j)} for c, j in re.findall(r'<script type="application/ld\+json"[^>]*?(?:class="([^"]*)")?[^>]*>(.*?)</script>', body, re.S)]
            json.dump(blocks, open(os.path.join(out_dir, owner, f'{name}.json'), 'w'), indent=1, ensure_ascii=False)
            saved += 1
            print(f'  {owner}:{name} {len(blocks)} JSON-LD block(s) [{", ".join(b["class"] or "-" for b in blocks)}]')
finally:
    subprocess.run(f'{WP} option update tdd_core_schema_owner yoast', shell=True, capture_output=True)
print(f'{saved} pages saved to {out_dir}')

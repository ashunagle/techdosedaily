#!/usr/bin/env python3
"""Phase 8: stored and reflected XSS sweep. Puts a script payload into every editable text field
(story fields and meta, sources, corrections, every tdd/* block attribute — inserted raw, bypassing
WordPress's own content filtering, so the theme's output escaping is what is tested — image fields,
profile fields, section and topic fields, site settings, search parameters), then renders every public
template and the main admin screens and checks that the payload never appears unescaped.
Run: python3 tests/security/xss_test.py   (restores everything it changes)
"""
import json, os, re, secrets, subprocess, sys, tempfile, urllib.parse

import requests

# Staging sits behind hPanel directory protection: every session (and requests.get/post) sends it.
if os.environ.get('TDD_BASIC_AUTH'):
    _auth, _init = tuple(os.environ['TDD_BASIC_AUTH'].split(':', 1)), requests.Session.__init__
    def _session_init(self, *a, **k):
        _init(self, *a, **k)
        self.auth = _auth
    requests.Session.__init__ = _session_init

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
P = 'TDDX"\'><img src=x onerror=alert(1)><script>alert(2)</script><svg onload=alert(4)>'
U = 'javascript:alert(3)'
RAW = ('<img src=x onerror', '<script>alert(2)', '<svg onload', 'href="javascript:', "href='javascript:", 'src="javascript:', 'action="javascript:', 'formaction="javascript:')
R = {'pass': 0, 'fail': 0}
LOG = []


def check(name, cond, detail=''):
    R['pass' if cond else 'fail'] += 1
    LOG.append(('PASS ' if cond else 'FAIL ') + name + ('' if cond else f' — {str(detail)[:400]}'))
    print('  ' + LOG[-1])


def wp(cmd):
    out = subprocess.run(f'{WP} {cmd}', shell=True, capture_output=True, text=True).stdout
    # LiteSpeed Cache prints a purge notice on every write (staging/production); it is not command output.
    return '\n'.join(l for l in out.splitlines() if not l.startswith('Success: Purged')).strip()


def wpeval(php):
    """Run PHP inside WordPress as user 1 (base64 through `wp eval`: works locally and over SSH)."""
    import base64, shlex
    code = "eval(base64_decode('" + base64.b64encode(php.encode()).decode() + "'));"
    return wp('eval ' + shlex.quote(code) + ' --user=' + os.environ.get('TDD_ADMIN_ID', '1'))


def raw_hits(html):
    hits = []
    for m in RAW:
        for i in [x.start() for x in re.finditer(re.escape(m), html)]:
            hits.append(html[max(0, i - 80): i + 60])
    return hits


def blocks_markup():
    """Every tdd/* block with every attribute filled with the payload (URLs get javascript:)."""
    root = os.environ.get('TDD_BLOCKS_DIR') or os.path.join(os.path.dirname(__file__), '../../theme/techdosedaily/blocks')
    out = []
    row = {k: P for k in ('title', 'text', 'label', 'value', 'q', 'a', 'term', 'def', 'name', 'amount', 'date', 'round', 'lead', 'investors', 'body', 'note', 'kind', 'stage', 'company', 'desc')}
    row['url'] = U
    for b in sorted(os.listdir(root)):
        bj = os.path.join(root, b, 'block.json')
        if not os.path.exists(bj):
            continue
        d = json.load(open(bj))
        attrs = {}
        for k, v in d.get('attributes', {}).items():
            t = v.get('type')
            if t == 'string':
                attrs[k] = U if k.lower().endswith('url') else (v['enum'][0] if 'enum' in v else P)
            elif t == 'array':
                attrs[k] = [[P, P, P]] if k in ('rows',) and 'table' in b else ([P, P] if k in ('head', 'short', 'facts', 'delivery', 'chips', 'blocks', 'mobileOrder', 'exclude', 'only') else [row, row])
            elif t == 'integer':
                attrs[k] = 1
            elif t == 'boolean':
                attrs[k] = True
        out.append(f'<!-- wp:{d["name"]} {json.dumps(attrs)} /-->')
    return '\n'.join(out)


uid = 0
saved = {}
made = []
try:
    print('== Planting payloads')
    login = 'tdd-xss-author'
    wp(f'user delete {login} --yes --reassign={os.environ.get("TDD_ADMIN_ID", "1")}')
    uid = int(wp(f'user create {login} {login}@example.invalid --role=author --user_pass={secrets.token_urlsafe(16)} --display_name="{P.replace(chr(34), "")}" --porcelain'))
    ai = int(wp('term get category ai --by=slug --field=term_id'))
    for k in ('tdd_short_description', 'tdd_desk_note', 'tdd_desk_url', 'tdd_desk_members'):
        saved[('term', k)] = wpeval(f'echo wp_json_encode(get_term_meta({ai}, "{k}", true));')
    for k in ('tdd_contact_notes', 'tdd_contact_expectations', 'tdd_secure_tip', 'tdd_media_kit_url'):
        saved[('opt', k)] = wpeval(f'echo wp_json_encode(get_option("{k}"));')
    # Only tdd/* blocks are written raw: core paragraph/heading HTML is the stored content itself (WordPress
    # filters it on save for every non-administrator), so it is not a theme escaping question.
    content = '<!-- wp:paragraph --><p>Body text.</p><!-- /wp:paragraph -->\n' + blocks_markup()
    # Title/excerpt go through WordPress's normal filtering for a non-administrator (what an author or
    # editor can really store); the block markup is then written raw, so the theme's own escaping of
    # every block attribute is what gets tested.
    import base64
    b64 = base64.b64encode(content.encode()).decode()
    pid = int(wpeval(f'wp_set_current_user({uid}); kses_init(); $id = wp_insert_post(wp_slash(array("post_type" => "post", "post_status" => "publish", "post_author" => {uid}, "post_category" => array({ai}), "post_title" => {json.dumps(P)}, "post_excerpt" => {json.dumps(P)}, "post_content" => "x"))); global $wpdb; $wpdb->update($wpdb->posts, array("post_content" => base64_decode("{b64}")), array("ID" => $id)); clean_post_cache($id); echo $id;'))
    made.append(pid)
    topic = wpeval(f'$t = wp_insert_term({json.dumps("TDDX topic " + P)}, "tdd_topic"); echo is_wp_error($t) ? 0 : $t["term_id"];')
    att = int(wp('post list --post_type=attachment --post_mime_type=image --field=ID --posts_per_page=1'))
    saved[('att', att)] = wpeval(f'echo wp_json_encode(array("caption" => wp_get_attachment_caption({att}), "alt" => get_post_meta({att}, "_wp_attachment_image_alt", true), "credit" => get_post_meta({att}, "tdd_credit", true), "url" => get_post_meta({att}, "tdd_source_url", true), "lic" => get_post_meta({att}, "tdd_license_note", true)));')
    wpeval(f"""
$P = {json.dumps(P)}; $U = {json.dumps(U)};
foreach (array('tdd_deck','tdd_short_title','tdd_update_note','tdd_ai_disclosure','tdd_sponsor','tdd_severity') as $k) update_post_meta({pid}, $k, $P);
update_post_meta({pid}, 'tdd_updated_at', gmdate('c', time() + 60));
update_post_meta({pid}, 'tdd_breaking_until', gmdate('c', time() + 3600));
update_post_meta({pid}, 'tdd_sources', array(array('title' => $P, 'url' => $U, 'type' => 'primary', 'publisher' => $P, 'date' => $P, 'note' => $P), array('title' => $P, 'url' => 'https://example.org/"><script>alert(5)</script>', 'type' => 'supporting')));
update_post_meta({pid}, 'tdd_corrections', array(array('time' => gmdate('c'), 'text' => $P)));
update_post_meta({pid}, '_tdd_fixture', 'phase8');
if ({topic}) wp_set_object_terms({pid}, array((int) {topic}), 'tdd_topic');
set_post_thumbnail({pid}, {att});
wp_update_post(array('ID' => {att}, 'post_excerpt' => $P));
update_post_meta({att}, '_wp_attachment_image_alt', $P);
foreach (array('tdd_credit','tdd_license_note') as $k) update_post_meta({att}, $k, $P);
update_post_meta({att}, 'tdd_source_url', $U);
foreach (array('tdd_title','tdd_short_bio','tdd_note','tdd_location','tdd_covering_since') as $k) update_user_meta({uid}, $k, $P);
update_user_meta({uid}, 'tdd_social', array(array('label' => $P, 'url' => $U), array('label' => $P, 'url' => 'https://example.org/me')));
update_user_meta({uid}, 'tdd_show_on_about', '1');
update_term_meta({ai}, 'tdd_short_description', $P);
update_term_meta({ai}, 'tdd_desk_note', $P);
update_term_meta({ai}, 'tdd_desk_url', $U);
update_term_meta({ai}, 'tdd_desk_members', array({uid}));
update_option('tdd_contact_notes', array('general' => $P, 'tip' => $P));
update_option('tdd_contact_expectations', array(array('title' => $P, 'text' => $P)));
update_option('tdd_secure_tip', '<p>Secure ' . $P . '<a href="' . $U . '">x</a></p>');
update_option('tdd_media_kit_url', $U);
echo 'ok';""")
    url = wp(f'post list --p={pid} --post_type=post --field=url').replace(BASE, '')
    author = f'/author/{wp(f"user get {uid} --field=user_nicename")}/'
    topic_url = wpeval(f'echo get_term_link((int) {topic});').replace(BASE, '') if topic and topic != '0' else ''

    print('== Public templates (anonymous, uncached)')
    q = urllib.parse.quote(P)
    pages = {'article': url, 'article-amp-embed': url.rstrip('/') + '/embed/', 'home': '/', 'section': '/ai/', 'author': author, 'topic': topic_url, 'about': '/about/', 'contact': '/contact/',
             'contact-topic': '/contact/?topic=' + q + '&tdd_cf=' + q, 'newsletter': '/newsletter/?tdd_nl=' + q, 'advertise': '/advertise/', 'search': '/?s=' + q + '&section[]=' + q + '&format[]=' + q + '&date=' + q + '&sort=' + q,
             'search-paged': '/page/2/?s=' + q, '404': '/no-such-' + q + '/', 'feed': '/feed/', 'json': '/wp-json/wp/v2/posts/' + str(pid)}
    for k, p in pages.items():
        if not p:
            continue
        r = requests.get(BASE + p, headers={'X-Cache-Bypass': '1', 'X-TDD-Perf-Test': '1'})
        body = r.text
        if k == 'json':  # JSON is escaped by encoding; check that decoding gives no live markup in rendered fields
            body = json.dumps(r.json().get('title', {}).get('rendered', '')) + json.dumps(r.json().get('excerpt', {}).get('rendered', ''))
        hits = raw_hits(body)
        check(f'{k}: payload never rendered unescaped', not hits, hits[:3])
        if k == 'article':
            check('article containing every tdd/* block (incl. body/page renderers) renders without recursion (200)', r.status_code == 200, r.status_code)
        check(f'{k}: payload marker is present (field really rendered)', k in ('json', 'feed', '404', 'search-paged', 'advertise', 'contact-topic', 'newsletter', 'article-amp-embed') or 'TDDX' in body, r.status_code)

    print('== Admin screens (editor and administrator)')
    for role in ('editor', 'administrator'):
        login2 = f'tdd-xss-{role}'
        pw2 = secrets.token_urlsafe(16)
        wp(f'user delete {login2} --yes --reassign={os.environ.get("TDD_ADMIN_ID", "1")}')
        u2 = wp(f'user create {login2} {login2}@example.invalid --role={role} --user_pass={pw2} --porcelain')
        s = requests.Session()
        s.get(BASE + '/wp-login.php')
        s.post(BASE + '/wp-login.php', data={'log': login2, 'pwd': pw2, 'testcookie': '1'})
        screens = {'posts list': '/wp-admin/edit.php', 'placements': '/wp-admin/admin.php?page=tdd-placements', 'sections': '/wp-admin/edit-tags.php?taxonomy=category', 'section edit': f'/wp-admin/term.php?taxonomy=category&tag_ID={ai}', 'topics': '/wp-admin/edit-tags.php?taxonomy=tdd_topic&post_type=post', 'media': f'/wp-admin/post.php?post={att}&action=edit'}
        if role == 'administrator':
            screens.update({'users': '/wp-admin/users.php', 'settings': '/wp-admin/admin.php?page=tdd-settings', 'author profile': f'/wp-admin/user-edit.php?user_id={uid}'})
        for k, p in screens.items():
            r = s.get(BASE + p)
            hits = raw_hits(r.text)
            check(f'{role} · {k}: payload never rendered unescaped', not hits and r.status_code == 200, (r.status_code, hits[:2]))
        nonce = s.get(BASE + '/wp-admin/admin-ajax.php?action=rest-nonce').text.strip()
        b = s.get(BASE + '/wp-json/tdd/v1/placements/board?group=home', headers={'X-WP-Nonce': nonce})
        check(f'{role} · placement board JSON is data only (rendered by React, never as HTML)', b.headers.get('Content-Type', '').startswith('application/json'), b.headers.get('Content-Type'))
        wp(f'user delete {u2} --yes --reassign={os.environ.get("TDD_ADMIN_ID", "1")}')
finally:
    print('== Restoring')
    for p in made:
        wp(f'post delete {p} --force')
    if 'topic' in dir() and topic and topic != '0':
        wp(f'term delete tdd_topic {topic}')
    for (kind, k), v in saved.items():
        if kind == 'term':
            wpeval(f'$v = json_decode({json.dumps(v)}, true); ($v === "" || $v === null || $v === array()) ? delete_term_meta({ai}, "{k}") : update_term_meta({ai}, "{k}", $v);')
        elif kind == 'opt':
            wpeval(f'$v = json_decode({json.dumps(v)}, true); ($v === false) ? delete_option("{k}") : update_option("{k}", $v);')
        elif kind == 'att':
            wpeval(f'$v = json_decode({json.dumps(v)}, true); wp_update_post(array("ID" => {k}, "post_excerpt" => $v["caption"])); update_post_meta({k}, "_wp_attachment_image_alt", $v["alt"]); update_post_meta({k}, "tdd_credit", $v["credit"]); update_post_meta({k}, "tdd_source_url", $v["url"]); update_post_meta({k}, "tdd_license_note", $v["lic"]);')
    if uid:
        wp(f'user delete {uid} --yes --reassign={os.environ.get("TDD_ADMIN_ID", "1")}')

out = os.path.join(os.path.dirname(__file__), 'out')
os.makedirs(out, exist_ok=True)
open(os.path.join(out, 'xss-results.txt'), 'w').write('\n'.join(LOG) + f"\n\n{R['pass']} passed, {R['fail']} failed\n")
print(f"\n{R['pass']} passed, {R['fail']} failed")
sys.exit(1 if R['fail'] else 0)

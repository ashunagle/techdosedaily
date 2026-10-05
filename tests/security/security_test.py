#!/usr/bin/env python3
"""Phase 8 security tests: every role separately (anonymous, subscriber, contributor, author, editor,
administrator) with DIRECT, forged requests — REST with and without nonces, admin-post, options.php,
profile.php, edit-tags.php, xmlrpc.php, uploads — not only through the UI.

Creates throwaway accounts (random passwords, deleted at the end) and its own fixture stories.
Needs: the local site at BASE behind nginx (tests/perf/nginx.conf), wp-cli via WP, Python requests + Pillow,
tests/fixtures/mu-capture-mail.php (contact mail capture) in mu-plugins.
Run: python3 tests/security/security_test.py
"""
import io, json, os, re, secrets, subprocess, sys, time

import requests

# Staging sits behind hPanel directory protection: every session (and requests.get/post) sends it.
if os.environ.get('TDD_BASIC_AUTH'):
    _auth, _init = tuple(os.environ['TDD_BASIC_AUTH'].split(':', 1)), requests.Session.__init__
    def _session_init(self, *a, **k):
        _init(self, *a, **k)
        self.auth = _auth
    requests.Session.__init__ = _session_init
from PIL import Image

BASE = os.environ.get('BASE', 'http://127.0.0.1:8090')
WP = os.environ.get('WP', 'cd /home/claude/wp && php wp-cli.phar --allow-root --path=site')
H = {'X-TDD-Perf-Test': '1'}  # never counted as a Most Read view
R = {'pass': 0, 'fail': 0}
LOG = []


def check(name, cond, detail=''):
    R['pass' if cond else 'fail'] += 1
    line = ('PASS ' if cond else 'FAIL ') + name + ('' if cond else f' — {str(detail)[:300]}')
    LOG.append(line)
    print('  ' + line)


def wp(cmd):
    out = subprocess.run(f'{WP} {cmd}', shell=True, capture_output=True, text=True).stdout
    # LiteSpeed Cache prints a purge notice on every write (staging/production); it is not command output.
    return '\n'.join(l for l in out.splitlines() if not l.startswith('Success: Purged')).strip()


def wpeval(php, user=None):
    """Run PHP inside WordPress. Base64 through `wp eval`, so it works locally and over SSH
    (WP=tests/staging/remote-wp) without temp files or nested shell quoting."""
    import base64, shlex
    code = "eval(base64_decode('" + base64.b64encode(php.encode()).decode() + "'));"
    return wp('eval ' + shlex.quote(code) + (f' --user={user}' if user else ''))


def leaks(text):
    """Server internals that must never reach a response body."""
    return [s for s in ('/home/', '/var/www', 'wp-content/plugins/techdosedaily-core/includes', 'Stack trace', 'Fatal error', 'Warning:</b>', 'Notice:</b>', 'wpdb', 'SQLSTATE', 'mysqli') if s in text]


def reset_throttles():
    wpeval("global $wpdb; $wpdb->query(\"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient%tdd\\_t%' OR option_name LIKE '\\_transient\\_timeout%tdd\\_t%'\");")


class Client:
    def __init__(self, role, login=None, password=None):
        self.role, self.s = role, requests.Session()
        self.s.headers.update(H)
        self.nonce = ''
        self.uid = 0
        if login:
            self.s.get(BASE + '/wp-login.php')
            r = self.s.post(BASE + '/wp-login.php', data={'log': login, 'pwd': password, 'testcookie': '1', 'redirect_to': BASE + '/wp-admin/'}, allow_redirects=False)
            assert any(c.name.startswith('wordpress_logged_in_') for c in self.s.cookies), f'login failed for {role}'
            self.nonce = self.s.get(BASE + '/wp-admin/admin-ajax.php?action=rest-nonce').text.strip()
            self.uid = int(wp(f'user get {login} --field=ID'))

    def rest(self, method, route, nonce=True, **kw):
        headers = dict(kw.pop('headers', {}))
        if nonce and self.nonce:
            headers['X-WP-Nonce'] = self.nonce
        return self.s.request(method, BASE + '/wp-json' + route, headers=headers, **kw)

    def get(self, path, **kw):
        return self.s.get(BASE + path, **kw)

    def post(self, path, **kw):
        return self.s.post(BASE + path, **kw)


ADMIN_ID = os.environ.get('TDD_ADMIN_ID', '1')  # an administrator account on the target site (reassign target)

# Preflight: never send real email or add real newsletter subscribers. The target must run the two
# local test harness mu-plugins (tests/fixtures/mu-capture-mail.php, mu-fake-newsletter-provider.php);
# on staging they are installed only for the duration of the run (LAUNCH-GATES.md).
_pf = wpeval("echo has_filter('pre_wp_mail') ? 'mail-captured' : 'MAIL-LIVE'; echo ' '; echo function_exists('tdd_core_newsletter_provider') ? tdd_core_newsletter_provider()->id() : 'none';")
if 'mail-captured' not in _pf or not _pf.strip().endswith('fake'):
    print('REFUSING TO RUN: install tests/fixtures/mu-capture-mail.php and mu-fake-newsletter-provider.php on the target first (got: ' + _pf + ')')
    sys.exit(2)

ROLES = ['subscriber', 'contributor', 'author', 'editor', 'administrator']
users, created_posts, created_media = {}, [], []
pw = {r: secrets.token_urlsafe(18) for r in ROLES + ['author2']}
try:
    print('== Setup: throwaway accounts and fixture stories')
    for r in ROLES + ['author2']:
        role = 'author' if r == 'author2' else r
        login = f'tdd-sec-{r}'
        wp(f'user delete {login} --yes --reassign={ADMIN_ID}')
        users[r] = int(wp(f'user create {login} {login}@example.invalid --role={role} --user_pass={pw[r]} --porcelain'))
    ai = wp('term get category ai --by=slug --field=term_id')
    # A published story by author2 with a correction and a confidential source; a draft by author2.
    pub = int(wp(f"post create --post_type=post --post_status=publish --post_author={users['author2']} --post_category={ai} --post_title='Security fixture published story' --post_content='<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->' --porcelain"))
    draft = int(wp(f"post create --post_type=post --post_status=draft --post_author={users['author2']} --post_title='Security fixture secret draft headline' --post_content='Unpublished.' --porcelain"))
    own = int(wp(f"post create --post_type=post --post_status=publish --post_author={users['author']} --post_category={ai} --post_title='Security fixture own story' --post_content='Mine.' --porcelain"))
    created_posts += [pub, draft, own]
    for p in created_posts:
        wp(f'post meta set {p} _tdd_fixture phase8')
    wpeval(f"""update_post_meta({pub}, 'tdd_corrections', array(array('time' => '2026-10-01T10:00:00+00:00', 'text' => 'An earlier version misnamed the vendor.')));
update_post_meta({pub}, 'tdd_sources', array(
  array('title' => 'Vendor statement', 'url' => 'https://example.org/statement', 'type' => 'primary', 'publisher' => 'Vendor', 'date' => '', 'note' => ''),
  array('title' => 'A person familiar with the plan', 'url' => 'https://internal.example.org/notes/source-77', 'type' => 'confidential', 'publisher' => 'Insider Name Here', 'date' => '', 'note' => 'Met on 3 Oct, Signal handle @secret')));""")
    pub_url = wp(f'post list --p={pub} --post_type=post --field=url').replace(BASE, '')

    C = {'anonymous': Client('anonymous')}
    for r in ROLES + ['author2']:
        C[r] = Client(r, f'tdd-sec-{r}', pw[r])
    ALL = ['anonymous'] + ROLES

    print('== Placements: editors and administrators only; REST needs a nonce (CSRF)')
    for r in ALL:
        allowed = r in ('editor', 'administrator')
        g = C[r].rest('GET', '/tdd/v1/placements')
        p = C[r].rest('POST', '/tdd/v1/placements', json={'post_id': pub, 'placement': 'homepage_secondary', 'position': 4})
        check(f'{r}: list placements → {"200" if allowed else "denied"}', (g.status_code == 200) == allowed, g.status_code)
        check(f'{r}: create placement → {"200" if allowed else "denied"}', (p.status_code == 200) == allowed, (p.status_code, p.text[:120]))
        b = C[r].rest('GET', '/tdd/v1/placements/board?group=home')
        check(f'{r}: placement board → {"200" if allowed else "denied"}', (b.status_code == 200) == allowed, b.status_code)
        if allowed:
            pid = p.json()['id']
            pa = C[r].rest('PATCH', f'/tdd/v1/placements/{pid}', json={'expires_at': '2000-01-01T00:00:00Z'})
            check(f'{r}: PATCH end before start refused (400)', pa.status_code == 400, (pa.status_code, pa.text[:120]))
            d = C[r].rest('DELETE', f'/tdd/v1/placements/{pid}')
            check(f'{r}: end placement → 200', d.status_code == 200, d.status_code)
            check(f'{r}: end unknown placement → 404', C[r].rest('DELETE', '/tdd/v1/placements/999999').status_code == 404)
    for r in ('editor', 'administrator'):
        x = C[r].rest('POST', '/tdd/v1/placements', nonce=False, json={'post_id': pub, 'placement': 'homepage_secondary', 'position': 4})
        check(f'{r}: placement POST without nonce (forged cross-site) refused', x.status_code in (401, 403), x.status_code)
    for r in ('subscriber', 'author'):
        d = C[r].rest('DELETE', '/tdd/v1/placements/1')
        check(f'{r}: forged DELETE of a placement refused', d.status_code in (401, 403), d.status_code)
    e = C['editor'].rest('POST', '/tdd/v1/placements', json={'post_id': draft, 'placement': 'homepage_lead'})
    check('editor: a draft cannot be placed', e.status_code == 400, e.status_code)
    e = C['editor'].rest('GET', "/tdd/v1/placements?placement=' OR 1=1 --")
    check('editor: SQL-shaped placement key rejected by schema (400)', e.status_code == 400, e.status_code)
    e = C['editor'].rest('PUT', '/tdd/v1/placements')
    check('method enforcement: PUT /placements → 404 no route', e.status_code == 404, e.status_code)

    print('== Site settings: administrators only (options.php needs capability + nonce)')
    before = wp('option get tdd_contact_from')
    for r in ['subscriber', 'contributor', 'author', 'editor']:
        x = C[r].post('/wp-admin/options.php', data={'option_page': 'tdd_core_settings', 'action': 'update', '_wpnonce': 'forged', 'tdd_contact_from': 'attacker@example.net'}, allow_redirects=False)
        check(f'{r}: forged settings save rejected', wp('option get tdd_contact_from') == before, x.status_code)
        page = C[r].get('/wp-admin/admin.php?page=tdd-settings')
        check(f'{r}: settings screen not available', 'Launch readiness' not in page.text, page.status_code)
    x = C['administrator'].post('/wp-admin/options.php', data={'option_page': 'tdd_core_settings', 'action': 'update', 'tdd_contact_from': 'attacker@example.net'}, allow_redirects=False)
    check('administrator: settings save without nonce rejected (CSRF)', wp('option get tdd_contact_from') == before, x.status_code)
    s = C['administrator'].rest('GET', '/wp/v2/settings').json()
    check('operational settings are not exposed in /wp/v2/settings', not any(k.startswith('tdd_') for k in s), [k for k in s if k.startswith('tdd_')])
    contact_html = C['anonymous'].get('/contact/').text
    inboxes = json.loads(wp('option get tdd_contact_inboxes --format=json') or '{}')
    check('contact inboxes never printed on the Contact page', not any(v and v in contact_html for v in inboxes.values()), inboxes)

    print('== Corrections: append-only for everyone but administrators (every write path)')
    old = json.loads(wp(f'post meta get {pub} tdd_corrections --format=json'))
    ed = C['editor']
    rew = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': [{'time': old[0]['time'], 'text': 'Rewritten history.'}]}})
    check('editor: rewriting a published correction refused (400)', rew.status_code == 400, (rew.status_code, rew.text[:150]))
    red = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': [{'time': '2026-10-03T10:00:00+00:00', 'text': old[0]['text']}]}})
    check('editor: re-dating a published correction refused', red.status_code == 400, red.status_code)
    rem = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': []}})
    check('editor: removing all corrections refused', rem.status_code == 400 and json.loads(wp(f'post meta get {pub} tdd_corrections --format=json')) == old, rem.status_code)
    nul = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': None}})
    check('editor: deleting corrections with null refused (record intact)', json.loads(wp(f'post meta get {pub} tdd_corrections --format=json')) == old, nul.status_code)
    app = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': old + [{'time': '', 'text': 'A second correction appended by the editor.'}]}})
    now = json.loads(wp(f'post meta get {pub} tdd_corrections --format=json'))
    check('editor: appending a correction works', app.status_code == 200 and len(now) == 2 and now[0] == old[0], (app.status_code, now))
    unp = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'status': 'draft'})
    rew2 = ed.rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': []}})
    check('editor: unpublishing first does not unlock corrections', rew2.status_code == 400 and len(json.loads(wp(f'post meta get {pub} tdd_corrections --format=json'))) == 2, rew2.status_code)
    ed.rest('POST', f'/wp/v2/posts/{pub}', json={'status': 'publish'})
    a2 = C['author2'].rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': now[:1]}})
    check('author (own story): removing a correction refused', a2.status_code == 400, a2.status_code)
    adm = C['administrator'].rest('POST', f'/wp/v2/posts/{pub}', json={'meta': {'tdd_corrections': now[:1]}})
    check('administrator: may amend corrections (legal fixes)', adm.status_code == 200 and len(json.loads(wp(f'post meta get {pub} tdd_corrections --format=json'))) == 1, adm.status_code)

    print('== Story ownership and field validation')
    for r, ok_ in (('anonymous', False), ('subscriber', False), ('contributor', False), ('author', False), ('editor', True)):
        x = C[r].rest('POST', f'/wp/v2/posts/{pub}', json={'title': f'Hijacked by {r}'})
        check(f'{r}: edit another author’s published story → {"allowed" if ok_ else "denied"}', (x.status_code == 200) == ok_, x.status_code)
    wp(f"post update {pub} --post_title='Security fixture published story'")
    x = C['editor'].rest('POST', f'/wp/v2/posts/{pub}', json={'content': '<!-- wp:html --><p>Ok</p><script>alert(1)</script><img src=x onerror=alert(2)><!-- /wp:html -->', 'title': 'T<script>alert(3)</script>'})
    stored = wpeval(f'$p = get_post({pub}); echo $p->post_title . "|" . $p->post_content;')
    check('editor: <script>/event handlers stripped on save (no unfiltered_html → no editor→admin XSS)', '<script' not in stored and 'onerror' not in stored, stored[:200])
    x = C['administrator'].rest('POST', f'/wp/v2/posts/{pub}', json={'content': '<!-- wp:html --><p>Admin embed</p><script>console.log(1)</script><!-- /wp:html -->'})
    check('administrator: keeps unfiltered HTML (trusted)', '<script>console.log(1)</script>' in wpeval(f'echo get_post({pub})->post_content;'))
    wp(f"post update {pub} --post_title='Security fixture published story' --post_content='<!-- wp:paragraph --><p>Body.</p><!-- /wp:paragraph -->'")
    x = C['contributor'].rest('POST', '/wp/v2/posts', json={'title': 'Contributor tries to publish', 'status': 'publish'})
    st = x.json().get('status') if x.status_code in (200, 201) else 'refused'
    if x.status_code in (200, 201):
        created_posts.append(x.json()['id'])
    check('contributor: cannot publish (saved as pending at most)', st in ('pending', 'draft', 'refused'), st)
    x = C['author'].rest('POST', f'/wp/v2/posts/{own}', json={'meta': {'tdd_editor': users['subscriber'], 'tdd_primary_section': 999999, 'tdd_sources': [{'title': 'Bad link', 'url': 'javascript:alert(1)', 'type': 'primary'}, {'title': 'Data link', 'url': 'data:text/html,<script>alert(1)</script>', 'type': 'supporting'}]}})
    m = x.json().get('meta', {})
    check('author: "Edited by" must be a real editor (subscriber ID dropped)', m.get('tdd_editor') == 0, m.get('tdd_editor'))
    check('author: unknown section ID dropped', m.get('tdd_primary_section') == 0, m.get('tdd_primary_section'))
    check('author: javascript:/data: source links stored empty', all(s['url'] == '' for s in m.get('tdd_sources', [])), m.get('tdd_sources'))
    for r in ('anonymous', 'subscriber', 'contributor', 'author'):
        d = C[r].rest('GET', f'/wp/v2/posts/{draft}')
        check(f'{r}: another author’s draft is unreadable over REST', d.status_code in (401, 403, 404), d.status_code)
        d2 = C[r].get(f'/?p={draft}')
        check(f'{r}: draft headline never rendered', 'secret draft headline' not in d2.text, d2.status_code)
    blk = C['author'].rest('POST', f'/wp/v2/posts/{own}', json={'content': f'<!-- wp:tdd/story-card {{"source":"post","postId":{draft}}} /-->'})
    check('author: story-card block cannot surface another author’s draft', 'secret draft headline' not in C['anonymous'].get(wp(f'post list --p={own} --post_type=post --field=url').replace(BASE, ''), headers={'X-Cache-Bypass': '1'}).text, blk.status_code)
    pp = C['author'].rest('GET', f'/tdd/v1/placements/post/{draft}')
    check('author: placement info of a draft refused', pp.status_code in (401, 403), pp.status_code)

    print('== The published record: authors can’t delete or unpublish it; editors can')
    caps = wp('cap list author').split()
    check('Author role has no delete_published_posts', 'delete_published_posts' not in caps and 'delete_posts' in caps, caps)
    au = C['author']
    x = au.rest('DELETE', f'/wp/v2/posts/{own}')
    check('author: trashing own published story refused', x.status_code in (401, 403) and wp(f'post get {own} --field=post_status') == 'publish', x.status_code)
    x = au.rest('DELETE', f'/wp/v2/posts/{own}?force=true')
    check('author: deleting own published story refused', x.status_code in (401, 403) and wp(f'post get {own} --field=post_status') == 'publish', x.status_code)
    x = au.rest('POST', f'/wp/v2/posts/{own}', json={'status': 'draft'})
    check('author: unpublishing own story refused (would allow delete-as-draft)', x.status_code == 403 and wp(f'post get {own} --field=post_status') == 'publish', (x.status_code, x.text[:120]))
    x = au.rest('POST', f'/wp/v2/posts/{own}', json={'title': 'Security fixture own story (edited)'})
    check('author: can still edit and update own published story', x.status_code == 200, x.status_code)
    wpeval(f"wp_update_post(array('ID' => {own}, 'post_status' => 'draft'));")  # simulate a story taken down by an editor
    x = au.rest('DELETE', f'/wp/v2/posts/{own}')
    check('author: a once-published story stays undeletable after it is taken down', x.status_code in (401, 403) and wp(f'post get {own} --field=post_status') == 'draft', x.status_code)
    wpeval(f"wp_update_post(array('ID' => {own}, 'post_status' => 'publish'));")
    d = au.rest('POST', '/wp/v2/posts', json={'title': 'Author scratch draft', 'status': 'draft'})
    did = d.json().get('id')
    x = au.rest('DELETE', f'/wp/v2/posts/{did}')
    check('author: may still delete own never-published draft', x.status_code == 200, x.status_code)
    created_posts.append(did)
    tmp = int(wp(f"post create --post_type=post --post_status=publish --post_author={users['author']} --post_title='Security fixture removable' --porcelain"))
    created_posts.append(tmp)
    x = C['editor'].rest('POST', f'/wp/v2/posts/{tmp}', json={'status': 'draft'})
    check('editor: may take a published story down', x.status_code == 200 and wp(f'post get {tmp} --field=post_status') == 'draft', x.status_code)
    x = C['editor'].rest('DELETE', f'/wp/v2/posts/{tmp}')
    check('editor: may trash a once-published story', x.status_code == 200 and wp(f'post get {tmp} --field=post_status') == 'trash', x.status_code)

    print('== Confidential source details stay internal')
    page = C['anonymous'].get(pub_url, headers={'X-Cache-Bypass': '1'}).text
    check('public page shows the confidential source description', 'A person familiar with the plan' in page)
    check('public page never shows its link, publisher or note', not any(s in page for s in ('internal.example.org', 'Insider Name Here', 'Signal handle')))
    j = C['anonymous'].rest('GET', f'/wp/v2/posts/{pub}').json()
    src = json.dumps(j.get('meta', {}).get('tdd_sources'))
    check('public REST: confidential link/publisher/note removed', not any(s in src for s in ('internal.example.org', 'Insider Name Here', 'Signal handle')) and 'Vendor statement' in src, src[:200])
    e = C['editor'].rest('GET', f'/wp/v2/posts/{pub}?context=edit').json()
    check('editor (context=edit): full source record available', 'internal.example.org' in json.dumps(e.get('meta', {}).get('tdd_sources')))
    for r in ('subscriber', 'contributor', 'author'):
        x = C[r].rest('GET', f'/wp/v2/posts/{pub}?context=edit')
        check(f'{r}: context=edit on another’s story refused', x.status_code in (401, 403), x.status_code)

    print('== Profiles: no privilege escalation through user fields')
    for r in ('subscriber', 'contributor', 'author'):
        x = C[r].rest('POST', '/wp/v2/users/me', json={'roles': ['administrator']})
        check(f'{r}: cannot grant itself administrator over REST', 'administrator' not in wp(f'user get {users[r]} --field=roles'), x.status_code)
        x = C[r].rest('POST', f'/wp/v2/users/{users["editor"]}', json={'name': 'Hijacked'})
        check(f'{r}: cannot edit another account', x.status_code in (401, 403), x.status_code)
    a = C['author']
    for key, val in (('tdd_show_on_about', True), ('tdd_about_order', 1), ('tdd_featured_posts', [pub]), ('tdd_editor_user', users['author'])):
        x = a.rest('POST', '/wp/v2/users/me', json={'meta': {key: val}})
        stored = wp(f'user meta get {users["author"]} {key} --format=json')
        check(f'author: editors-only field {key} cannot be self-set', x.status_code in (400, 401, 403) and stored in ('', 'null', '""', '[]', '0', 'false'), (x.status_code, stored))
    x = a.rest('POST', '/wp/v2/users/me', json={'meta': {'tdd_social': [{'label': 'Evil', 'url': 'javascript:alert(document.cookie)'}, {'label': 'Site', 'url': 'https://example.org/me'}], 'tdd_photo': pub, 'tdd_title': '<script>alert(1)</script>Reporter'}})
    m = x.json().get('meta', {})
    check('author: javascript: social URL dropped, https kept', [s['url'] for s in m.get('tdd_social', [])] == ['https://example.org/me'], m.get('tdd_social'))
    check('author: profile photo must be an image attachment', m.get('tdd_photo') == 0, m.get('tdd_photo'))
    check('author: title stored without markup', '<script' not in str(m.get('tdd_title')), m.get('tdd_title'))
    prof = a.get('/wp-admin/profile.php').text
    nonce = re.search(r'name="tdd_profile_nonce" value="([^"]+)"', prof).group(1)
    wpn = re.search(r'name="_wpnonce" value="([^"]+)"', prof).group(1)
    a.post('/wp-admin/profile.php', data={'_wpnonce': wpn, 'action': 'update', 'user_id': users['author'], 'email': f'tdd-sec-author@example.invalid', 'nickname': 'tdd-sec-author', 'display_name': 'tdd-sec-author', 'role': 'administrator', 'tdd_profile_nonce': nonce, 'tdd_show_on_about': '1', 'tdd_about_order': '1', 'tdd_editor_user': users['editor'], 'tdd_featured_posts_present': '1', 'tdd_featured_posts[]': [pub]})
    check('author: forged profile form cannot set role or editors-only fields', 'administrator' not in wp(f'user get {users["author"]} --field=roles') and wp(f'user meta get {users["author"]} tdd_show_on_about') in ('', '0') and wp(f'user meta get {users["author"]} tdd_editor_user') in ('', '0'))
    x = C['editor'].rest('POST', f'/wp/v2/users/{users["author"]}', json={'meta': {'tdd_featured_posts': [pub]}})
    check('editor: cannot write another user’s account over REST (no edit_user)', x.status_code in (401, 403), x.status_code)
    x = C['administrator'].rest('POST', f'/wp/v2/users/{users["author"]}', json={'meta': {'tdd_featured_posts': [pub, own]}})
    check('administrator: Featured Reporting keeps only the person’s own published stories', json.loads(wp(f'user meta get {users["author"]} tdd_featured_posts --format=json') or '[]') == [own], wp(f'user meta get {users["author"]} tdd_featured_posts --format=json'))

    print('== Reporter profiles screen: editors manage only About listing/order and Featured Reporting')
    wp(f'user meta update {users["author"]} tdd_featured_posts "[]" --format=json')
    other_draft = draft  # author2's draft; pub is author2's published story; own is author's published story
    for r in ('anonymous', 'subscriber', 'contributor', 'author'):
        pg = C[r].get('/wp-admin/admin.php?page=tdd-reporters')
        check(f'{r}: Reporter profiles screen not available', 'Save reporter profile' not in pg.text and 'All reporters' not in pg.text and 'About-page listing and Featured' not in pg.text, pg.status_code)
        x = C[r].post('/wp-admin/admin-post.php', data={'action': 'tdd_reporter_profile', 'user_id': users['author'], 'tdd_reporter_nonce': 'forged', 'tdd_show_on_about': '1'}, allow_redirects=False)
        check(f'{r}: forged reporter-profile save refused', wp(f'user meta get {users["author"]} tdd_show_on_about') in ('', '0'), x.status_code)
    ed = C['editor']
    lst = ed.get('/wp-admin/admin.php?page=tdd-reporters').text
    check('editor: reporter list shows reporters (not administrators)', 'tdd-sec-author' in lst and 'tdd-sec-administrator' not in lst)
    page = ed.get(f'/wp-admin/admin.php?page=tdd-reporters&user={users["author"]}').text
    rn = re.search(r'name="tdd_reporter_nonce" value="([^"]+)"', page).group(1)
    check('editor: edit form offers only About, order and Featured Reporting', not re.search(r'name="(email|role|pass1|user_login|display_name|tdd_editor_user|tdd_title)"', page))
    before_email = wp(f'user get {users["author"]} --field=user_email')
    x = ed.post('/wp-admin/admin-post.php', data={'action': 'tdd_reporter_profile', 'user_id': users['author'], 'tdd_reporter_nonce': rn, 'tdd_show_on_about': '1', 'tdd_about_order': '7',
                                                   'tdd_featured_posts_present': '1', 'tdd_featured_posts[]': [own, pub, other_draft, 999999],
                                                   'role': 'administrator', 'email': 'hijack@example.net', 'user_email': 'hijack@example.net', 'pass1': 'x', 'tdd_editor_user': users['editor'], 'tdd_title': 'Hijacked'}, allow_redirects=False)
    check('editor: save works (303 back to the screen)', x.status_code in (302, 303) and 'updated=1' in x.headers.get('Location', ''), (x.status_code, x.headers.get('Location')))
    check('editor: About listing and order stored', wp(f'user meta get {users["author"]} tdd_show_on_about') == '1' and wp(f'user meta get {users["author"]} tdd_about_order') == '7')
    check('editor: Featured Reporting keeps only that reporter’s own published stories', json.loads(wp(f'user meta get {users["author"]} tdd_featured_posts --format=json') or '[]') == [own], wp(f'user meta get {users["author"]} tdd_featured_posts --format=json'))
    check('editor: role, email, password, editor and title untouched', wp(f'user get {users["author"]} --field=roles') == 'author' and wp(f'user get {users["author"]} --field=user_email') == before_email and wp(f'user meta get {users["author"]} tdd_editor_user') in ('', '0') and wp(f'user meta get {users["author"]} tdd_title') != 'Hijacked')
    x = ed.post('/wp-admin/admin-post.php', data={'action': 'tdd_reporter_profile', 'user_id': users['author2'], 'tdd_reporter_nonce': rn, 'tdd_show_on_about': '1'}, allow_redirects=False)
    check('editor: a nonce for one reporter can’t be replayed for another', wp(f'user meta get {users["author2"]} tdd_show_on_about') in ('', '0'), x.status_code)
    pa = ed.get(f'/wp-admin/admin.php?page=tdd-reporters&user={users["administrator"]}')
    x = ed.post('/wp-admin/admin-post.php', data={'action': 'tdd_reporter_profile', 'user_id': users['administrator'], 'tdd_reporter_nonce': 'forged', 'tdd_show_on_about': '1'}, allow_redirects=False)
    check('editor: administrator accounts are out of reach', 'Save reporter profile' not in pa.text and wp(f'user meta get {users["administrator"]} tdd_show_on_about') in ('', '0'), (pa.status_code, x.status_code))
    pa = ed.get(f'/wp-admin/admin.php?page=tdd-reporters&user={users["subscriber"]}')
    check('editor: subscribers (no byline) are out of reach', 'Save reporter profile' not in pa.text, pa.status_code)
    x = ed.rest('POST', f'/wp/v2/users/{users["author"]}', json={'meta': {'tdd_show_on_about': False}})
    check('editor: still no general account editing over REST', x.status_code in (401, 403) and wp(f'user meta get {users["author"]} tdd_show_on_about') == '1', x.status_code)
    adm = C['administrator']
    page = adm.get(f'/wp-admin/admin.php?page=tdd-reporters&user={users["editor"]}').text
    check('administrator: can manage any reporter here too', 'Save reporter profile' in page)
    wp(f'user meta update {users["author"]} tdd_show_on_about ""'); wp(f'user meta update {users["author"]} tdd_featured_posts "[]" --format=json')

    print('== Sections: manage_categories only')
    for r in ('subscriber', 'contributor', 'author'):
        x = C[r].rest('POST', f'/wp/v2/categories/{ai}', json={'meta': {'tdd_desk_note': f'Defaced by {r}'}})
        check(f'{r}: section settings over REST refused', x.status_code in (401, 403) and 'Defaced' not in wp(f'term meta get {ai} tdd_desk_note'), x.status_code)
        x = C[r].post('/wp-admin/edit-tags.php', data={'action': 'editedtag', 'tag_ID': ai, 'taxonomy': 'category', 'name': 'AI', 'tdd_section_nonce': 'forged', 'tdd_desk_note': 'Defaced'})
        check(f'{r}: forged section form refused', 'Defaced' not in wp(f'term meta get {ai} tdd_desk_note'), x.status_code)
    x = C['editor'].rest('POST', f'/wp/v2/categories/{ai}', json={'meta': {'tdd_desk_members': [users['subscriber'], users['editor']], 'tdd_desk_url': 'javascript:alert(1)'}})
    m = x.json().get('meta', {})
    check('editor: desk members must have editing rights (subscriber dropped)', m.get('tdd_desk_members') == [users['editor']], m.get('tdd_desk_members'))
    check('editor: javascript: desk link stored empty', m.get('tdd_desk_url') == '', m.get('tdd_desk_url'))
    C['editor'].rest('POST', f'/wp/v2/categories/{ai}', json={'meta': {'tdd_desk_members': [], 'tdd_desk_url': ''}})

    print('== Uploads: images + PDF only; metadata removed')
    def jpeg_with_gps():
        im = Image.new('RGB', (64, 48), (200, 30, 30))
        ex = Image.Exif()
        ex[0x010F] = 'SecretCam'      # Make
        ex[0x013B] = 'Jane Photographer'  # Artist
        gps = ex.get_ifd(0x8825)
        gps[1], gps[2], gps[3], gps[4] = 'N', (51.0, 30.0, 12.0), 'W', (0.0, 7.0, 39.0)
        b = io.BytesIO(); im.save(b, 'JPEG', exif=ex.tobytes(), quality=92); return b.getvalue()
    bad = {'evil.html': (b'<script>alert(1)</script>', 'text/html'), 'evil.svg': (b'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'image/svg+xml'),
           'shell.php': (b'<?php echo 1;', 'application/x-php'), 'shell.php.jpg': (b'<?php echo 1; ?>', 'image/jpeg'), 'doc.docx': (b'PK\x03\x04', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
           'script.js': (b'alert(1)', 'text/javascript')}
    for name, (data, ctype) in bad.items():
        x = C['editor'].rest('POST', '/wp/v2/media', headers={'Content-Disposition': f'attachment; filename="{name}"', 'Content-Type': ctype}, data=data)
        if x.status_code in (200, 201):
            created_media.append(x.json()['id'])
        check(f'editor: upload of {name} refused', x.status_code >= 400, x.status_code)
    x = C['contributor'].rest('POST', '/wp/v2/media', headers={'Content-Disposition': 'attachment; filename="c.jpg"', 'Content-Type': 'image/jpeg'}, data=jpeg_with_gps())
    check('contributor: no upload rights (403)', x.status_code in (401, 403), x.status_code)
    x = C['author'].rest('POST', '/wp/v2/media', headers={'Content-Disposition': 'attachment; filename="gps.jpg"', 'Content-Type': 'image/jpeg'}, data=jpeg_with_gps())
    check('author: JPEG upload accepted', x.status_code in (200, 201), (x.status_code, x.text[:150]))
    if x.status_code in (200, 201):
        mid = x.json()['id']; created_media.append(mid)
        f_url = wpeval(f'echo wp_get_original_image_url({mid}) ?: wp_get_attachment_url({mid});')
        exif = Image.open(io.BytesIO(requests.get(f_url, headers=H).content)).getexif()  # the stored original, over HTTP
        check('uploaded original has no GPS, camera make or artist', not exif.get_ifd(0x8825) and 0x010F not in exif and 0x013B not in exif, dict(exif))
        meta = x.json().get('media_details', {}).get('image_meta', {})
        check('REST image_meta carries no camera/credit data', not meta.get('camera') and not meta.get('credit'), meta)
    pdf = b'%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF'
    x = C['editor'].rest('POST', '/wp/v2/media', headers={'Content-Disposition': 'attachment; filename="media-kit.pdf"', 'Content-Type': 'application/pdf'}, data=pdf)
    if x.status_code in (200, 201):
        created_media.append(x.json()['id'])
    check('editor: PDF upload still allowed (media kit, policies)', x.status_code in (200, 201), (x.status_code, x.text[:150]))

    print('== Exposure decisions: XML-RPC, application passwords, users directory, file editor, login errors')
    x = C['anonymous'].post('/xmlrpc.php', data='<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>', headers={'Content-Type': 'text/xml'})
    check('XML-RPC refused (403)', x.status_code == 403, x.status_code)
    out = wpeval("echo json_encode(array(apply_filters('xmlrpc_enabled', true), count(apply_filters('xmlrpc_methods', array('a' => 1))), wp_is_application_passwords_available(), current_user_can('edit_themes')));")
    check('PHP level too: XML-RPC off, no methods, app passwords off (independent of the server rule)', out.startswith('[false,0,false'), out)
    for path in ('/wp-content/uploads/x.php', '/.env', '/.git/config', '/readme.html', '/wp-config.php'):
        x = C['anonymous'].get(path)
        check(f'server rule (local mirror of production config): {path} → 403/404', x.status_code in (403, 404), x.status_code)
    check('no X-Pingback header', 'X-Pingback' not in C['anonymous'].get('/', headers={'X-Cache-Bypass': '1'}).headers)
    x = C['administrator'].rest('POST', f'/wp/v2/users/{users["administrator"]}/application-passwords', json={'name': 'test'})
    check('application passwords unavailable', x.status_code >= 400, (x.status_code, x.text[:100]))
    for path in ('/wp/v2/users', f'/wp/v2/users/{users["author"]}', '/wp/v2/users?search=admin'):
        x = C['anonymous'].rest('GET', path)
        check(f'anonymous: {path} refused', x.status_code == 401 and '@' not in x.text and 'avatar' not in x.text, x.status_code)
    x = C['author'].rest('GET', '/wp/v2/users?context=edit')
    check('author: user list with emails (context=edit) refused', x.status_code in (401, 403), x.status_code)
    x = C['administrator'].get('/wp-admin/theme-editor.php')
    check('administrator: theme/plugin file editor disabled', x.status_code == 403 or 'not allowed' in x.text.lower() or 'Sorry' in x.text, x.status_code)
    s = requests.Session()
    s.get(BASE + '/wp-login.php')
    r1 = s.post(BASE + '/wp-login.php', data={'log': 'no-such-user-xyz', 'pwd': 'x', 'testcookie': '1'}).text
    r2 = s.post(BASE + '/wp-login.php', data={'log': 'tdd-sec-author', 'pwd': 'wrong-password', 'testcookie': '1'}).text
    msg = lambda t: re.sub(r'\s+', ' ', (re.search(r'id="login_error"[^>]*>(.*?)</div>', t, re.S) or [None, ''])[1])
    check('login: same message for unknown user and wrong password', msg(r1) and msg(r1) == msg(r2) and 'tdd-sec-author' not in msg(r2), (msg(r1)[:100], msg(r2)[:100]))

    print('== Public forms: method enforcement, CSRF, rate limits, no-JS paths, error hygiene')
    reset_throttles()
    for route in ('/tdd/v1/contact', '/tdd/v1/subscribe', '/tdd/v1/view'):
        x = C['anonymous'].rest('GET', route)
        check(f'GET {route} → 404 (POST only)', x.status_code == 404, x.status_code)
    home = C['anonymous'].get('/', headers={'X-Cache-Bypass': '1'}).text
    token = re.search(r'name="tdd_token" value="([^"]+)"', home).group(1)
    time.sleep(3.2)
    x = C['anonymous'].rest('POST', '/tdd/v1/contact', json={'name': 'A', 'email': 'a@example.org', 'topic': 'general', 'message': 'Hi', 'tdd_token': token, 'tdd_hp': ''})
    check('contact REST without the page nonce refused', x.status_code == 400 and not leaks(x.text), (x.status_code, x.text[:120]))
    cpage = C['anonymous'].get('/contact/').text
    cnonce = re.search(r'name="_tdd_nonce" value="([^"]+)"', cpage).group(1)
    ctoken = re.search(r'name="tdd_token" value="([^"]+)"', cpage).group(1)
    time.sleep(3.2)
    form = {'tdd_contact': '1', 'cf_name': 'No JS Reader', 'cf_email': 'reader@example.org', 'cf_topic': 'general', 'cf_message': 'Sent without JavaScript.', '_tdd_nonce': cnonce, 'tdd_token': ctoken, 'tdd_hp': ''}
    x = C['anonymous'].post('/contact/', data=form, allow_redirects=False)
    check('no-JS contact form still sends (303 → ?tdd_cf=sent) with security headers', x.status_code == 303 and 'tdd_cf=sent' in x.headers.get('Location', ''), (x.status_code, x.headers.get('Location')))
    # Mail capture log written by tests/fixtures/mu-capture-mail.php (local, or temporarily on staging).
    read_mail_log = lambda: wpeval("echo (string) @file_get_contents(WP_CONTENT_DIR . '/tdd-mail-test.log');")
    log_start = len(read_mail_log())
    bad = dict(form, cf_email='fail@example.com', cf_message='Delivery failure test: this opening line becomes the subject, and the rest stays in the body only SECRET-MSG-123')
    x = C['anonymous'].post('/contact/', data=bad)
    check('send failure: generic message, no internals', x.status_code == 200 and not leaks(x.text), leaks(x.text))
    log = read_mail_log()[log_start:]
    check('message bodies are never logged (subject excerpt only)', 'SECRET-MSG-123' not in log)
    reset_throttles()
    codes = []
    for i in range(7):
        x = C['anonymous'].rest('POST', '/tdd/v1/subscribe', json={'email': f'rate{i}@example.org', 'tdd_token': token, 'tdd_hp': ''})
        codes.append(x.status_code)
    check('newsletter: 6th+ request from one client within 10 min → 429', codes[:5].count(429) == 0 and codes[5] == 429 and codes[6] == 429, codes)
    print('== Newsletter: no subscriber enumeration (identical answer for new / already / pending)')
    NEUTRAL = 'If this address can be subscribed, check your inbox for the next step.'
    answers = {}
    for label, email in (('new', 'brand-new-reader@example.org'), ('already subscribed', 'already@example.com'), ('single opt-in', 'instant@example.com')):
        reset_throttles()
        t0 = time.time()
        x = C['anonymous'].rest('POST', '/tdd/v1/subscribe', json={'email': email, 'tdd_token': token, 'tdd_hp': ''})
        answers[label] = (x.status_code, x.text, round(time.time() - t0, 2))
    bodies = {a[1] for a in answers.values()}
    check('REST: same status code for new / already / pending', len({a[0] for a in answers.values()}) == 1 and answers['new'][0] == 200, answers)
    check('REST: byte-identical body for all three', len(bodies) == 1, bodies)
    check('REST: neutral wording, no provider details', NEUTRAL in answers['new'][1] and not re.search(r'already|subscribed|mailpoet|provider', answers['new'][1].lower().replace('be subscribed', '')), answers['new'][1])
    times = [a[2] for a in answers.values()]
    check('REST: response time padded and alike (spread < 0.3 s)', min(times) >= 1.1 and max(times) - min(times) < 0.3, times)
    locs = {}
    for label, email in (('new', 'brand-new-reader@example.org'), ('already', 'already@example.com'), ('instant', 'instant@example.com')):
        reset_throttles()
        x = C['anonymous'].post('/wp-admin/admin-post.php', data={'action': 'tdd_subscribe', 'email': email, 'tdd_token': token, 'tdd_hp': ''}, headers={'Referer': BASE + '/newsletter/'}, allow_redirects=False)
        locs[label] = (x.status_code, x.headers.get('Location'))
    check('no-JS: identical 303 redirect for all three', len(set(locs.values())) == 1 and locs['new'][0] == 303, locs)
    pages = {C['anonymous'].get(f'/newsletter/?tdd_nl={st}', headers={'X-Cache-Bypass': '1'}).text.count(NEUTRAL) for st in ('pending', 'already', 'subscribed')}
    check('no-JS result page: old and new state URLs all show the same neutral text', pages == {1}, pages)
    reset_throttles()
    x = C['anonymous'].post('/wp-admin/admin-post.php', data={'action': 'tdd_subscribe', 'email': 'nojs@example.org', 'tdd_token': token, 'tdd_hp': ''}, headers={'Referer': BASE + '/newsletter/'}, allow_redirects=False)
    check('no-JS newsletter form still works (303 back with state)', x.status_code == 303 and 'tdd_nl=' in x.headers.get('Location', '') and x.headers['Location'].startswith(BASE), (x.status_code, x.headers.get('Location')))
    x = C['anonymous'].post('/wp-admin/admin-post.php', data={'action': 'tdd_subscribe', 'email': 'nojs@example.org', 'tdd_token': token, 'tdd_hp': ''}, headers={'Referer': 'https://evil.example.net/phish'}, allow_redirects=False)
    check('no-JS newsletter never redirects off-site (open redirect)', x.headers.get('Location', '').startswith(BASE), x.headers.get('Location'))
    x = C['anonymous'].rest('POST', '/tdd/v1/subscribe', json={'email': 'x@example.org', 'tdd_token': '123.forged', 'tdd_hp': ''})
    check('forged form token refused', x.status_code == 400, x.status_code)
    x = C['anonymous'].rest('POST', '/tdd/v1/subscribe', json={'email': 'x@example.org', 'tdd_token': token, 'tdd_hp': 'i am a bot'})
    check('honeypot filled → refused', x.status_code == 400, x.status_code)
    out = wpeval("add_filter('tdd_core_global_limits', fn() => ['subscribe' => [3, 3600]]); $r=[]; for($i=0;$i<5;$i++){ $r[] = tdd_core_throttle_global('subscribe-test-'.getmypid(), 3, 3600) ? 1 : 0; } echo implode(',', $r);")
    check('site-wide ceiling stops a distributed flood', out == '1,1,1,0,0', out)
    x = C['anonymous'].rest('POST', '/tdd/v1/contact', data='{not json', headers={'Content-Type': 'application/json'})
    check('malformed JSON → clean 400, no internals', x.status_code == 400 and not leaks(x.text), (x.status_code, x.text[:150]))
    views = lambda: int(wpeval(f'global $wpdb; echo (int) $wpdb->get_var("SELECT COALESCE(SUM(views),0) FROM " . tdd_core_views_table() . " WHERE post_id={pub}");') or 0)
    reset_throttles()
    v0 = views()
    for i in range(3):
        requests.post(BASE + '/wp-json/tdd/v1/view', json={'id': pub}, headers={'User-Agent': 'Mozilla/5.0 (Macintosh) Safari/605.1.15'})
    check('view count: one per reader per 30 min (refreshes ignored)', views() == v0 + 1, views() - v0)
    x = requests.post(BASE + '/wp-json/tdd/v1/view', json={'id': "1 OR 1=1"}, headers={'User-Agent': 'Mozilla/5.0 Safari/605'})
    check('view endpoint: non-integer id rejected (400)', x.status_code == 400, x.status_code)
    x = requests.post(BASE + '/wp-json/tdd/v1/view', json={'id': draft}, headers={'User-Agent': 'Mozilla/5.0 (Windows) Firefox/130'})
    check('view endpoint: drafts are never counted', int(wpeval(f'global $wpdb; echo (int) $wpdb->get_var("SELECT COALESCE(SUM(views),0) FROM " . tdd_core_views_table() . " WHERE post_id={draft}");') or 0) == 0)
    reset_throttles()

    print('== SQL-shaped input on public pages')
    for path in ("/?s=%27%20OR%201%3D1--&section[]=ai%27--&format[]=x&date=week%27&sort=newest", "/?author=1%27", "/ai/?paged=1%27", "/?p=1%27%20UNION%20SELECT%201--"):
        x = C['anonymous'].get(path, headers={'X-Cache-Bypass': '1'})
        check(f'{path[:40]}… → no DB error or internals', x.status_code in (200, 301, 302, 404) and not leaks(x.text), (x.status_code, leaks(x.text)))

    print('== Response headers and private caching')
    for path in ('/', pub_url, '/contact/', '/?s=AI', '/wp-login.php', '/no-such-page-xyz/'):
        x = C['anonymous'].get(path, headers={'X-Cache-Bypass': '1'})
        hd = x.headers
        ok_ = hd.get('X-Content-Type-Options') == 'nosniff' and 'frame-ancestors' in hd.get('Content-Security-Policy', '') and hd.get('Referrer-Policy') and hd.get('X-Frame-Options') == 'SAMEORIGIN'
        check(f'headers on {path}', ok_, dict((k, v) for k, v in hd.items() if k.lower().startswith(('x-', 'content-security', 'referrer', 'permissions'))))
    C['anonymous'].get('/'); x = C['anonymous'].get('/')
    check('headers also on page-cache HITs', x.headers.get('X-Page-Cache') == 'HIT' and x.headers.get('X-Content-Type-Options') == 'nosniff', x.headers.get('X-Page-Cache'))
    x = C['editor'].get('/')
    check('logged-in pages: private, no-store, never from the cache', 'no-store' in x.headers.get('Cache-Control', '') and x.headers.get('X-Page-Cache') == 'BYPASS', (x.headers.get('Cache-Control'), x.headers.get('X-Page-Cache')))
    x = C['editor'].rest('GET', f'/wp/v2/posts/{pub}?context=edit')
    check('authenticated REST responses: no-store', 'no-store' in x.headers.get('Cache-Control', '') or 'private' in x.headers.get('Cache-Control', ''), x.headers.get('Cache-Control'))
    x = C['anonymous'].get(pub_url.rstrip('/') + '/embed/', headers={'X-Cache-Bypass': '1'})
    check('oEmbed card stays embeddable by other sites', 'X-Frame-Options' not in x.headers and 'frame-ancestors' not in x.headers.get('Content-Security-Policy', ''), dict(x.headers))
finally:
    print('== Clean-up')
    for m in created_media:
        wp(f'post delete {m} --force')
    for p in created_posts:
        wpeval(f'global $wpdb; $wpdb->delete(tdd_core_placements_table(), ["post_id" => {p}]); $wpdb->delete(tdd_core_views_table(), ["post_id" => {p}]);')
        wp(f'post delete {p} --force')
    for r, uid in users.items():
        wp(f'user delete {uid} --yes --reassign={ADMIN_ID}')
    reset_throttles()

out = os.path.join(os.path.dirname(__file__), 'out')
os.makedirs(out, exist_ok=True)
open(os.path.join(out, 'security-results.txt'), 'w').write('\n'.join(LOG) + f"\n\n{R['pass']} passed, {R['fail']} failed\n")
print(f"\n{R['pass']} passed, {R['fail']} failed")
sys.exit(1 if R['fail'] else 0)

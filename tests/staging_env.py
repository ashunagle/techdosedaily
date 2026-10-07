"""Import at the top of a test suite to run it against staging behind hPanel directory protection.

With TDD_BASIC_AUTH=user:pass set, every request to the BASE host (urllib and requests) carries the
directory login; requests to any other host never do. Without it, importing changes nothing.
"""
import base64
import os
import urllib.parse
import urllib.request

AUTH = os.environ.get('TDD_BASIC_AUTH', '')
HOST = urllib.parse.urlsplit(os.environ.get('BASE', '')).hostname or ''


def _ours(url):
    return bool(HOST) and urllib.parse.urlsplit(url).hostname == HOST


if AUTH and HOST:
    _header = 'Basic ' + base64.b64encode(AUTH.encode()).decode()

    class _AuthHandler(urllib.request.BaseHandler):
        handler_order = 100

        def http_request(self, req):
            if _ours(req.full_url):
                req.add_unredirected_header('Authorization', _header)
            return req

        https_request = http_request

    _build = urllib.request.build_opener

    def _build_with_auth(*handlers):
        # Suites that build their own openers (e.g. no-redirect fetches) get the login too.
        return _build(_AuthHandler(), *handlers)

    urllib.request.build_opener = _build_with_auth
    urllib.request.install_opener(_build_with_auth())

    # LiteSpeed queues purges made from WP-CLI and delivers them with a loopback request to admin-ajax.php
    # (works on production); staging's Basic Auth refuses that request, so cached pages would stay stale.
    # After each WP-CLI command that may write, make that same request with the login so the purge arrives.
    import re
    import subprocess

    _run = subprocess.run
    _wp = os.environ.get('WP', '')
    _read_only = re.compile(r'\s*(?:(?:post|term|user|option|plugin|theme|menu)\s+(?:get|list|is-active|url|exists|status)\b|config get\b|cron event list\b)')

    def _run_with_purge_relay(*a, **k):
        r = _run(*a, **k)
        cmd = a[0] if a else k.get('args')
        if isinstance(cmd, str) and _wp and cmd.startswith(_wp) and not _read_only.match(cmd[len(_wp):]):
            try:
                urllib.request.urlopen(urllib.request.Request(os.environ['BASE'].rstrip('/') + '/wp-admin/admin-ajax.php', headers={'X-TDD-Perf-Test': '1'}), timeout=20).read()
            except Exception:  # admin-ajax answers 400 without an action; the purge header still goes out
                pass
        return r

    subprocess.run = _run_with_purge_relay

    try:
        import requests

        _send = requests.Session.send

        def _send_with_auth(self, request, **kw):
            if _ours(request.url) and 'Authorization' not in request.headers:
                request.headers['Authorization'] = _header
            return _send(self, request, **kw)

        requests.Session.send = _send_with_auth
    except ImportError:
        pass

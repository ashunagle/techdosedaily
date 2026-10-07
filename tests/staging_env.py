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

#!/usr/bin/env python3
"""Live acceptance with a temporary database and local PHP server."""
from http.cookiejar import CookieJar
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, Request
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from pathlib import Path
import json
import os
import re
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
DOCKER_PHP = Path("/usr/local/bin/php8.5-docker").exists()


def php_command(tmp, arguments, port=None, db_path=None):
    """Use native PHP normally, or Docker with explicit DB_PATH when PHP is wrapped."""
    if not DOCKER_PHP:
        return ["php", *arguments]
    command = [
        "docker", "run", "--rm", "-i",
        "-v", str(ROOT) + ":" + str(ROOT),
        "-v", tmp + ":" + tmp,
        "-w", str(ROOT),
        "-e", "DB_PATH=" + str(db_path if db_path is not None else Path(tmp) / "review.sqlite"),
        "-e", "SESSION_COOKIE_SECURE=0",
    ]
    if port is not None:
        command.extend(["-p", f"127.0.0.1:{port}:{port}"])
    return [*command, "php:8.5-cli", "php", *arguments]


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def client():
    return build_opener(HTTPCookieProcessor(CookieJar()), NoRedirect())


def request(opener, base, path, data=None):
    post = None if data is None else urlencode(data).encode()
    headers = {'Content-Type': 'application/x-www-form-urlencoded'} if post is not None else {}
    req = Request(base + path, data=post, headers=headers)
    try:
        response = opener.open(req, timeout=8)
    except HTTPError as error:
        response = error
    with response:
        headers = dict(response.headers)
        assert headers.get('X-Content-Type-Options') == 'nosniff', (path, response.code, 'nosniff')
        assert headers.get('Cache-Control') == 'no-store', (path, response.code, 'cache')
        assert headers.get('Referrer-Policy') == 'no-referrer', (path, response.code, 'referrer')
        assert headers.get('X-Frame-Options') == 'DENY', (path, response.code, 'frame')
        policy = headers.get('Content-Security-Policy', '')
        assert "script-src 'self'" in policy, (path, response.code, 'CSP')
        assert "script-src 'unsafe-inline'" not in policy, (path, response.code, 'inline script')
        assert 'Strict-Transport-Security' not in headers, 'HSTS must not be sent over local HTTP'
        return response.code, headers, response.read()


def csrf(body):
    match = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body)
    assert match, "No CSRF hidden field"
    return match.group(1).decode()



def main():
    """Run isolated production-style HTTP/API acceptance, without deprecated server-rendered forms."""
    import accounts_api_smoke
    import user_slices_smoke
    import route_cutover

    accounts_api_smoke.main()
    user_slices_smoke.main()
    route_cutover.main()
    print("LIVE SPA ACCEPTANCE: PASS; cookies, CSRF, roles, audit, native binary and SPA-only routing")


if __name__ == "__main__":
    main()

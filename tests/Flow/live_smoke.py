#!/usr/bin/env python3
"""Live acceptance with a temporary database and local PHP server."""
from http.cookiejar import CookieJar
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, Request
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from pathlib import Path
import os
import re
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
DOCKER_PHP = Path("/usr/local/bin/php8.5-docker").exists()


def php_command(tmp, arguments, port=None):
    """Use native PHP normally, or Docker with explicit DB_PATH when PHP is wrapped."""
    if not DOCKER_PHP:
        return ["php", *arguments]
    command = [
        "docker", "run", "--rm", "-i",
        "-v", str(ROOT) + ":" + str(ROOT),
        "-v", tmp + ":" + tmp,
        "-w", str(ROOT),
        "-e", "DB_PATH=" + str(Path(tmp) / "review.sqlite"),
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
        return response.code, dict(response.headers), response.read()


def csrf(body):
    match = re.search(rb'name="csrf_token" value="([^"]+)"', body)
    assert match, "No CSRF hidden field"
    return match.group(1).decode()


def main():
    with tempfile.TemporaryDirectory(prefix="fast-task-review-") as tmp:
        env = os.environ.copy()
        env["DB_PATH"] = str(Path(tmp) / "review.sqlite")
        env["APP_DEBUG"] = "0"
        env["SESSION_COOKIE_SECURE"] = "0"
        first = subprocess.run(
            php_command(tmp, ["bin/migrate.php"]), cwd=ROOT, env=env,
            capture_output=True, text=True, check=True)
        second = subprocess.run(
            php_command(tmp, ["bin/migrate.php"]), cwd=ROOT, env=env,
            capture_output=True, text=True, check=True)
        assert "Applied" in first.stdout and "No pending migrations." in second.stdout
        admin = subprocess.run(
            php_command(tmp, ["bin/create-admin.php", "review-admin", "--password-stdin"]),
            input="review-secret\n", cwd=ROOT, env=env,
            capture_output=True, text=True)
        assert admin.returncode == 0, "Admin CLI failed: " + admin.stderr
        sock = socket.socket()
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
        sock.close()
        base = "http://127.0.0.1:" + str(port)
        server = subprocess.Popen(
            php_command(tmp, ["-S", ("0.0.0.0:" if DOCKER_PHP else "127.0.0.1:") + str(port), "-t", "public", "public/index.php"], port),
            cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        try:
            user = client()
            for _ in range(70):
                try:
                    status, _, body = request(user, base, "/register")
                    break
                except (URLError, TimeoutError):
                    time.sleep(0.08)
            assert status == 200
            status, headers, _ = request(user, base, "/register", {
                "username": "review-user", "password": "review-password",
                "csrf_token": csrf(body)})
            assert status == 303 and headers.get("Location") == "/login?registered=1"
            status, _, body = request(user, base, "/login")
            assert status == 200
            status, headers, _ = request(user, base, "/login", {
                "username": "review-user", "password": "review-password",
                "csrf_token": csrf(body)})
            assert status == 303 and headers.get("Location") == "/"
            status, _, body = request(user, base, "/")
            assert status == 200 and b"review-user" in body
            status, _, body = request(user, base, "/page-a")
            assert status == 200 and b"Buy a cow" in body
            status, _, body = request(user, base, "/buy-cow", {"csrf_token": csrf(body)})
            assert status == 200 and b"thankYou" in body
            assert b">Buy a cow</button>" not in body
            status, _, body = request(user, base, "/page-b")
            assert status == 200 and b"Download" in body
            status, headers, data = request(user, base, "/download", {"csrf_token": csrf(body)})
            assert status == 200 and data[:2] == b"MZ"
            assert "sample.exe" in headers.get("Content-Disposition", "")
            status, _, body = request(user, base, "/")
            status, headers, _ = request(user, base, "/logout", {"csrf_token": csrf(body)})
            assert status == 303 and headers.get("Location") == "/login"

            admin_user = client()
            status, _, body = request(admin_user, base, "/login")
            assert status == 200
            status, _, _ = request(admin_user, base, "/login", {
                "username": "review-admin", "password": "review-secret",
                "csrf_token": csrf(body)})
            assert status == 303
            status, _, body = request(admin_user, base, "/statistics")
            assert status == 200
            assert all(part in body for part in
                       [b"registration", b"login", b"logout", b"page-a", b"download"])
            status, _, body = request(
                admin_user, base, "/statistics?user=review-user&action=button-click")
            assert status == 200 and b"review-user" in body and b"button-click" in body
            status, _, body = request(admin_user, base, "/reports")
            assert status == 200
            assert b"<svg" in body and b"<polyline" in body and b"Daily totals" in body
            print("LIVE ACCEPTANCE: PASS")
            print("Fresh and idempotent migrations: PASS")
            print("CLI admin bootstrap: PASS")
            print("User signup/login/cow/download/logout: PASS")
            print("Admin statistics/filters/reports: PASS")
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()


if __name__ == "__main__":
    main()

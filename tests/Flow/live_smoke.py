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
        for username, invalid_password, explanation in [
            ("nul-password-admin", "safe\0unsafe", "NUL"),
            ("long-password-admin", "a" * 73, "72 bytes"),
        ]:
            rejected = subprocess.run(
                php_command(tmp, ["bin/create-admin.php", username, "--password-stdin"]),
                input=invalid_password + "\n", cwd=ROOT, env=env,
                capture_output=True, text=True)
            assert rejected.returncode == 2, "Invalid CLI password must fail cleanly"
            assert explanation in rejected.stderr and "Fatal error" not in rejected.stderr
        invalid_admin = subprocess.run(
            php_command(tmp, ["bin/create-admin.php", "not valid", "--password-stdin"]),
            input="valid-admin-password\n", cwd=ROOT, env=env,
            capture_output=True, text=True)
        assert invalid_admin.returncode == 2 and "Username" in invalid_admin.stderr
        sock = socket.socket()
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
        sock.close()
        base = "http://127.0.0.1:" + str(port)
        server_log = Path(tmp) / "web-server.log"
        server_log_stream = server_log.open("wb")
        server = subprocess.Popen(
            php_command(tmp, ["-S", ("0.0.0.0:" if DOCKER_PHP else "127.0.0.1:") + str(port), "-t", "public", "public/index.php"], port),
            cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=server_log_stream)
        try:
            user = client()
            for attempt in range(70):
                try:
                    status, _, body = request(user, base, "/register")
                    break
                except (URLError, TimeoutError, ConnectionResetError, ConnectionAbortedError):
                    if attempt == 69 or server.poll() is not None:
                        raise AssertionError(
                            "Web server was not ready; diagnostics: "
                            + server_log.read_text(errors="replace")
                        )
                    time.sleep(0.08)
            assert status == 200
            status, headers, _ = request(user, base, "/register", {
                "username": "review-user", "password": "review-password",
                "csrf_token": csrf(body)})
            assert status == 303 and headers.get("Location") == "/login?registered=1"
            status, _, body = request(user, base, "/login")
            assert status == 200
            anonymous_token = csrf(body)
            status, denial_headers, _ = request(user, base, "/login", {
                "username": "review-user", "password": "incorrect-password",
                "csrf_token": anonymous_token})
            assert status == 401
            login_denied_id = denial_headers.get("X-Request-ID")
            assert re.fullmatch(r"[0-9a-f]{24}", login_denied_id or ""), "Login denial lacks correlation ID"
            status, headers, _ = request(user, base, "/login", {
                "username": "review-user", "password": "review-password",
                "csrf_token": anonymous_token})
            assert status == 303 and headers.get("Location") == "/"
            status, denial_headers, _ = request(user, base, "/buy-cow", {"csrf_token": anonymous_token})
            assert status == 403, "Pre-login CSRF token remained valid after authentication"
            csrf_denied_id = denial_headers.get("X-Request-ID")
            assert re.fullmatch(r"[0-9a-f]{24}", csrf_denied_id or ""), "CSRF denial lacks correlation ID"
            status, admin_headers, _ = request(user, base, "/statistics")
            assert status == 403
            admin_denied_id = admin_headers.get("X-Request-ID")
            assert re.fullmatch(r"[0-9a-f]{24}", admin_denied_id or ""), "Authorization denial lacks correlation ID"
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
            status, headers, body = request(user, base, "/logout")
            assert status == 405 and headers.get("Allow") == "POST"
            assert b"Method Not Allowed" in body

            status, headers, body = request(user, base, "/page-a", {})
            assert status == 405 and headers.get("Allow") == "GET"

            # A real failing activity insert must reach the outer HTTP 500 boundary,
            # not be misreported as a missing download artifact (503).
            status, _, download_form = request(admin_user, base, "/page-b")
            assert status == 200
            subprocess.run(
                php_command(tmp, [
                    "-r",
                    '$pdo = new PDO("sqlite:" . getenv("DB_PATH")); '
                    '$pdo->exec("DROP TABLE activity_events");',
                ]),
                cwd=ROOT, env=env, capture_output=True, text=True, check=True)
            status, headers, failure_body = request(admin_user, base, "/download", {
                "csrf_token": csrf(download_form)})
            assert status == 500, "Audit database failure was masked as an artifact failure"
            audit_request_id = headers.get("X-Request-ID")
            assert re.fullmatch(r"[0-9a-f]{24}", audit_request_id or ""), "Missing 500 request correlation ID"
            assert failure_body == b"Internal Server Error"
            assert headers.get("Cache-Control") == "no-store"
            assert headers.get("X-Content-Type-Options") == "nosniff"

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
            server_log_stream.close()

        diagnostics = server_log.read_text(errors="replace")
        for event_id, event in [
            (login_denied_id, "auth.login_denied"),
            (csrf_denied_id, "security.csrf_denied"),
            (admin_denied_id, "security.admin_denied"),
            (audit_request_id, "runtime.unhandled"),
        ]:
            assert '"request_id":"' + event_id + '"' in diagnostics
            assert '"event":"' + event + '"' in diagnostics
        assert '"exception_class":"PDOException"' in diagnostics
        assert "SQLSTATE[" not in diagnostics, "Raw database exception leaked to diagnostics"
        assert "incorrect-password" not in diagnostics
        print("Download audit DB failure -> safe HTTP 500 and server diagnostics: PASS")

        # Exercise actual front-controller behavior when PDO fails before routing.
        failure_env = env.copy()
        failure_env["DB_PATH"] = tmp
        failure_log = Path(tmp) / "failure.log"
        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            error_port = listener.getsockname()[1]
        error_base = "http://127.0.0.1:" + str(error_port)
        with failure_log.open("wb") as diagnostic:
            broken_server = subprocess.Popen(
                php_command(tmp, [
                    "-S", ("0.0.0.0:" if DOCKER_PHP else "127.0.0.1:") + str(error_port),
                    "-t", "public", "public/index.php",
                ], error_port, db_path=tmp),
                cwd=ROOT, env=failure_env,
                stdout=subprocess.DEVNULL, stderr=diagnostic)
            try:
                for attempt in range(70):
                    try:
                        error_status, error_headers, error_body = request(
                            client(), error_base, "/register")
                        break
                    except (URLError, TimeoutError, ConnectionResetError, ConnectionAbortedError):
                        if attempt == 69 or broken_server.poll() is not None:
                            raise AssertionError(
                                "Failure server was not ready; diagnostics: "
                                + failure_log.read_text(errors="replace")
                            )
                        time.sleep(0.08)
                assert error_status == 500
                startup_request_id = error_headers.get("X-Request-ID")
                assert re.fullmatch(r"[0-9a-f]{24}", startup_request_id or ""), "Missing startup failure correlation ID"
                assert error_body == b"Internal Server Error", "Server details exposed to client"
                assert error_headers.get("Cache-Control") == "no-store"
                assert error_headers.get("X-Content-Type-Options") == "nosniff"
            finally:
                broken_server.terminate()
                try:
                    broken_server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    broken_server.kill()
                    broken_server.wait()
        failure_diagnostics = failure_log.read_text(errors="replace")
        assert '"exception_class":"PDOException"' in failure_diagnostics
        assert '"request_id":"' + startup_request_id + '"' in failure_diagnostics
        assert '"event":"runtime.unhandled"' in failure_diagnostics
        assert "SQLSTATE[" not in failure_diagnostics
        print("Safe 500 on PDO initialization failure: PASS")


if __name__ == "__main__":
    main()

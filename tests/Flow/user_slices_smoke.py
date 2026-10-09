#!/usr/bin/env python3
"""End-to-end same-origin user slices: authenticated views, click audit and native binary delivery."""
from urllib.parse import urlencode
from urllib.request import Request
from urllib.error import HTTPError, URLError
from pathlib import Path
from tempfile import TemporaryDirectory
from contextlib import closing
import hashlib
import json
import os
import re
import socket
import subprocess
import time

import live_smoke as base


def main():
    with TemporaryDirectory(prefix="fast-user-slices-") as tmp:
        db = Path(tmp) / "accounts.sqlite"
        env = os.environ.copy()
        env.update({"DB_PATH": str(db), "SESSION_COOKIE_SECURE": "0", "APP_DEBUG": "0"})
        migrated = subprocess.run(base.php_command(tmp, ["bin/migrate.php"], db_path=db), cwd=base.ROOT,
                                  env=env, capture_output=True, text=True, check=True)
        assert "Applied" in migrated.stdout

        with closing(socket.socket()) as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        origin = f"http://127.0.0.1:{port}"
        log = open(Path(tmp) / "php.log", "wb")
        server = subprocess.Popen(base.php_command(tmp, ["-S",
            ("0.0.0.0:" if base.DOCKER_PHP else "127.0.0.1:") + str(port),
            "-t", "public", "public/index.php"], port, db_path=db), cwd=base.ROOT, env=env,
            stdout=subprocess.DEVNULL, stderr=log)
        client = base.client()

        def send(path, body=None, token=None):
            headers = {"Accept": "application/json"}
            payload = None if body is None else json.dumps(body).encode("utf-8")
            if payload is not None:
                headers["Content-Type"] = "application/json"
                if token is not None:
                    headers["X-CSRF-Token"] = token
            request = Request(origin + path, data=payload, headers=headers)
            try:
                response = client.open(request, timeout=8)
            except HTTPError as error:
                response = error
            with response:
                raw = response.read()
                if response.code == 204:
                    assert not raw
                    return response.code, None
                assert "application/json" in response.headers.get("Content-Type", "")
                return response.code, json.loads(raw)

        try:
            for iteration in range(60):
                try:
                    status, guest = send("/api/session")
                    break
                except (URLError, ConnectionError, TimeoutError):
                    if iteration == 59 or server.poll() is not None:
                        raise
                    time.sleep(.08)
            assert status == 200 and guest == {"user": None}
            status, token_json = send("/api/csrf")
            token = token_json["csrfToken"]
            assert re.fullmatch(r"[a-f0-9]{64}", token)

            credentials = {"username": "spauser", "password": "strong-password"}
            status, error = send("/api/accounts/register", credentials, token="invalid")
            assert status == 403 and error["error"]["code"] == "csrf_denied"
            status, registered = send("/api/accounts/register", credentials, token)
            assert status == 201 and registered == {"registered": True}
            assert send("/api/session")[1] == {"user": None}
            status, duplicate = send("/api/accounts/register", credentials, token)
            assert status == 409 and duplicate["error"]["code"] == "username_taken"
            status, invalid = send("/api/accounts/login", {"username": "spauser", "password": "wrong"}, token)
            assert status == 401 and invalid["error"]["code"] == "invalid_login"
            status, login = send("/api/accounts/login", credentials, token)
            assert status == 200 and login["user"]["role"] == "user"
            assert login["csrfToken"] != token
            assert send("/api/session")[1]["user"]["username"] == "spauser"
            live_token = login["csrfToken"]

            status, _ = send("/api/cow-offer/view", {}, live_token)
            assert status == 204
            status, clicked = send("/api/cow-offer/buy", {}, live_token)
            assert status == 200 and clicked == {"outcome": "thankYou"}
            status, _ = send("/api/downloads/page/view", {}, live_token)
            assert status == 204

            def binary(form_token):
                data = urlencode({"csrf_token": form_token}).encode()
                form = Request(origin + "/api/downloads/sample", data=data,
                               headers={"Content-Type": "application/x-www-form-urlencoded"})
                try:
                    response = client.open(form, timeout=8)
                except HTTPError as error:
                    response = error
                with response:
                    return response.code, dict(response.headers), response.read()

            bad_code, bad_headers, bad_body = binary("bad-csrf")
            assert bad_code == 403 and json.loads(bad_body)["error"]["code"] == "csrf_denied"
            status, headers, file_body = binary(live_token)
            assert status == 200
            assert headers["Content-Type"] == "application/octet-stream"
            assert 'attachment; filename="sample.exe"' == headers["Content-Disposition"]
            assert len(file_body) == 1024 and file_body[:2] == b"MZ"
            assert hashlib.sha256(file_body).hexdigest() == "31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80"

            status, denied = send("/api/accounts/logout", {}, token)
            assert status == 403 and denied["error"]["code"] == "csrf_denied"
            assert send("/api/session")[1]["user"] is not None
            status, _ = send("/api/accounts/logout", {}, login["csrfToken"])
            assert status == 204 and send("/api/session")[1] == {"user": None}
            # In Docker mode the SQLite file may be root-owned; inspect through the same PHP runtime.
            query = '$db = new PDO("sqlite:" . getenv("DB_PATH")); echo json_encode($db->query("SELECT action, target FROM activity_events ORDER BY id")->fetchAll(PDO::FETCH_ASSOC));'
            observed = subprocess.run(base.php_command(tmp, ["-r", query], db_path=db),
                                      cwd=base.ROOT, env=env, capture_output=True, text=True, check=True)
            actions = json.loads(observed.stdout)
            assert actions == [
                {"action": "registration", "target": None},
                {"action": "login", "target": None},
                {"action": "view-page", "target": "page-a"},
                {"action": "button-click", "target": "buy-cow"},
                {"action": "view-page", "target": "page-b"},
                {"action": "button-click", "target": "download"},
                {"action": "logout", "target": None},
            ], actions

            module = client.open(origin + "/app/Accounts/showAccounts.js", timeout=8)
            with module:
                assert module.status == 200 and "javascript" in module.headers["Content-Type"]
            print("USER SLICES HTTP ACCEPTANCE: PASS; auth/CSRF, CowOffer, binary SHA-256, exact audit sequence")
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait(timeout=5)
            log.close()


if __name__ == "__main__":
    main()

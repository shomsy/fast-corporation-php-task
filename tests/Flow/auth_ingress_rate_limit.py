#!/usr/bin/env python3
"""Test a real, disposable Nginx ingress before two isolated upstreams."""
from pathlib import Path
from tempfile import TemporaryDirectory
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen
import os
import socket
import subprocess
import time
import uuid

ROOT = Path(__file__).resolve().parents[2]
SNIPPETS = ROOT / "ops/nginx"
IMAGE = "nginx@sha256:65645c7bb6a0661892a8b03b89d0743208a18dd2f3f17a54ef4b76fb8e2f2a10"

def response(url, method="GET", spoof=None):
    headers = {"Content-Type": "application/x-www-form-urlencoded"}
    if spoof is not None:
        headers["X-Forwarded-For"] = spoof
    request = Request(url, method=method, headers=headers,
                      data=(b"username=do-not-log&password=SECRET-NOT-TO-LOG"
                            if method == "POST" else None))
    try:
        with urlopen(request, timeout=3) as answer:
            return answer.status, dict(answer.headers), answer.read()
    except HTTPError as answer:
        return answer.code, dict(answer.headers), answer.read()

def main():
    with TemporaryDirectory(prefix="fc-ingress-") as temporary:
        root = Path(temporary)
        conf = root / "nginx.conf"
        conf.write_text("""
worker_processes 1;
error_log /dev/stderr notice;
events { worker_connections 256; }
http {
    include /etc/nginx/mime.types;
    include /etc/fast-auth/http-auth-limits.conf;
    access_log /dev/stdout fc_auth_rate;
    upstream app_pool {
        server 127.0.0.1:8081;
        server 127.0.0.1:8082;
    }
    server {
        listen 8080;
        include /etc/fast-auth/server-auth-errors.conf;
        location / {
            include /etc/fast-auth/location-auth-limit.conf;
            proxy_pass http://app_pool;
        }
    }
    server {
        listen 8081;
        location / {
            add_header X-Backend first always;
            return 200 "upstream-first";
        }
    }
    server {
        listen 8082;
        location / {
            add_header X-Backend second always;
            return 200 "upstream-second";
        }
    }
}
""", encoding="utf-8")
        mount = ["--mount", f"type=bind,src={conf},dst=/etc/nginx/nginx.conf,readonly",
                 "--mount", f"type=bind,src={SNIPPETS},dst=/etc/fast-auth,readonly"]
        check = subprocess.run(["docker", "run", "--rm", *mount, IMAGE, "nginx", "-t"],
                               capture_output=True, text=True, timeout=25)
        if check.returncode != 0:
            raise AssertionError("Nginx config rejected: " + check.stderr)
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        name = "fc-auth-check-" + uuid.uuid4().hex[:12]
        output = root / "nginx.log"
        with output.open("wb") as log:
            server = subprocess.Popen(["docker", "run", "--rm", "--name", name,
                "-p", f"127.0.0.1:{port}:8080", *mount, IMAGE],
                stdout=log, stderr=subprocess.STDOUT)
            try:
                url = f"http://127.0.0.1:{port}"
                for attempt in range(70):
                    try:
                        status, _, _ = response(url + "/ready")
                        if status == 200:
                            break
                    except (URLError, TimeoutError, ConnectionResetError):
                        if server.poll() is not None or attempt == 69:
                            raise RuntimeError("Ingress container did not start")
                        time.sleep(.08)
                assert response(url + "/login")[0] == 200
                accepted = []
                paths = ["/api/accounts/login", "/login", "/register"]
                for index, path in enumerate(paths):
                    status, headers, body = response(
                        url + path, "POST", spoof=f"198.51.100.{index + 1}")
                    assert status == 200, (path, status, headers)
                    accepted.append(headers.get("X-Backend"))
                assert set(accepted) == {"first", "second"}, accepted
                status, headers, body = response(
                    url + "/api/accounts/register", "POST", spoof="203.0.113.99")
                assert status == 429, (status, headers, body)
                assert headers.get("Retry-After") == "60", headers
                assert headers.get("Cache-Control") == "no-store", headers
                assert response(url + "/page-a", "POST")[0] == 200
                assert response(url + "/login", "GET")[0] == 200
                assert response(url + "/api/accounts/login",
                                "POST", spoof="192.0.2.200")[0] == 429
            finally:
                subprocess.run(["docker", "stop", "--time", "2", name],
                               capture_output=True, timeout=15)
                try:
                    server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=5)
        logs = output.read_text(errors="replace")
        assert "limit=REJECTED" in logs, logs[-1800:]
        assert "SECRET-NOT-TO-LOG" not in logs and "do-not-log" not in logs
        print("INGRESS ABUSE GATE: PASS; multi-upstream 429/Retry-After, XFF spoof ignored, sanitized log")

if __name__ == "__main__":
    main()

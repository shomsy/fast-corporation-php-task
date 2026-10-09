#!/usr/bin/env python3
"""Bounded, reproducible HTTP capacity probe against the disposable interview stack."""
import concurrent.futures
import hashlib
import json
import math
import os
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import threading
import time

from live_smoke import ROOT, DOCKER_PHP, client, csrf, php_command, request

CLIENTS = 4
ITERATIONS = 8
EXPECTED_SHA = "31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80"


def percentile(samples, percentage):
    if not samples:
        return 0
    ordered = sorted(samples)
    return round(ordered[max(0, math.ceil(len(ordered) * percentage / 100) - 1)], 2)


def main():
    with tempfile.TemporaryDirectory(prefix="fast-capacity-") as temporary:
        db = Path(temporary) / "capacity.sqlite"
        env = os.environ.copy()
        env.update(DB_PATH=str(db), APP_DEBUG="0", SESSION_COOKIE_SECURE="0")
        subprocess.run(php_command(temporary, ["bin/migrate.php"], db_path=db), cwd=ROOT, env=env,
                       capture_output=True, text=True, check=True)
        admin = subprocess.run(php_command(temporary, ["bin/create-admin.php", "capacity-admin", "--password-stdin"], db_path=db),
                               input="capacity-admin-password\n", cwd=ROOT, env=env, text=True, capture_output=True, check=True)
        assert admin.returncode == 0

        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        base = "http://127.0.0.1:" + str(port)
        server_log = Path(temporary) / "web.log"
        with server_log.open("wb") as logfile:
            server = subprocess.Popen(
                php_command(temporary, ["-S", ("0.0.0.0:" if DOCKER_PHP else "127.0.0.1:") + str(port),
                                        "-t", "public", "public/index.php"], port, db_path=db),
                cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=logfile,
            )
            try:
                monitor = client()
                for attempt in range(100):
                    try:
                        status, _, body = request(monitor, base, "/healthz")
                        if status == 200:
                            break
                    except Exception:
                        if server.poll() is not None:
                            raise
                        time.sleep(0.08)
                else:
                    raise AssertionError("Local test server failed to start")

                start = time.monotonic()
                barrier = threading.Barrier(CLIENTS)
                def worker(index):
                    opener = client()
                    username = f"capacity-user-{index}"
                    secret = f"capacity-user-password-{index}"
                    status, _, body = request(opener, base, "/register")
                    assert status == 200
                    status, _, _ = request(opener, base, "/register", {
                        "username": username, "password": secret, "csrf_token": csrf(body)})
                    assert status == 303
                    status, _, body = request(opener, base, "/login")
                    assert status == 200
                    status, _, _ = request(opener, base, "/login", {
                        "username": username, "password": secret, "csrf_token": csrf(body)})
                    assert status == 303
                    # Exclude bcrypt/signup queueing from the steady-state request percentiles.
                    barrier.wait(timeout=25)

                    samples = []
                    for _ in range(ITERATIONS):
                        for endpoint in ("/page-a", "/page-b", "/healthz", "/readyz"):
                            t = time.perf_counter_ns()
                            code, _, response = request(opener, base, endpoint)
                            delta = (time.perf_counter_ns() - t) / 1e6
                            assert code == 200, (endpoint, code)
                            samples.append((endpoint, delta))
                            if endpoint == "/page-b":
                                token = csrf(response)
                                t = time.perf_counter_ns()
                                code, headers, data = request(opener, base, "/download", {"csrf_token": token})
                                elapsed = (time.perf_counter_ns() - t) / 1e6
                                assert code == 200, code
                                assert headers.get("Content-Length") == "1024"
                                assert hashlib.sha256(data).hexdigest() == EXPECTED_SHA
                                samples.append(("/download", elapsed))
                    return samples

                with concurrent.futures.ThreadPoolExecutor(max_workers=CLIENTS) as pool:
                    results = list(pool.map(worker, range(CLIENTS)))

                administrator = client()
                code, _, form = request(administrator, base, "/login")
                assert code == 200
                code, _, _ = request(administrator, base, "/login", {
                    "username": "capacity-admin", "password": "capacity-admin-password",
                    "csrf_token": csrf(form)})
                assert code == 303
                for endpoint in ("/statistics", "/reports"):
                    for _ in range(8):
                        t = time.perf_counter_ns()
                        code, _, _ = request(administrator, base, endpoint)
                        assert code == 200
                        results.append([(endpoint, (time.perf_counter_ns() - t) / 1e6)])

                elapsed = round(time.monotonic() - start, 2)
                by_endpoint = {}
                for group in results:
                    for endpoint, milliseconds in group:
                        by_endpoint.setdefault(endpoint, []).append(milliseconds)
                status = {name: {"requests": len(samples), "p95_ms": percentile(samples, 95),
                                 "p99_ms": percentile(samples, 99), "max_ms": round(max(samples), 2)}
                          for name, samples in by_endpoint.items()}

                probe_php = (
                    '$pdo = new PDO("sqlite:" . getenv("DB_PATH"));'
                    '$count = $pdo->query("SELECT COUNT(*) FROM activity_events")->fetchColumn();'
                    '$journal = $pdo->query("PRAGMA journal_mode")->fetchColumn();'
                    'echo json_encode(["events" => (int)$count, "journal" => $journal]);'
                )
                checked = subprocess.run(
                    php_command(temporary, ["-r", 'require "vendor/autoload.php";' + probe_php], db_path=db),
                    cwd=ROOT, env=env, capture_output=True, text=True, check=True,
                )
                state = json.loads(checked.stdout)
                events = state["events"]
                journal = state["journal"]
                assert journal == "wal"
                assert events == CLIENTS * (2 + ITERATIONS * 3) + 1, events

                files = {label: (db.parent / (db.name + suffix)).stat().st_size
                         if (db.parent / (db.name + suffix)).exists() else 0
                         for label, suffix in (("sqlite_bytes", ""), ("wal_bytes", "-wal"), ("shm_bytes", "-shm"))}

                # PHP allocated-heap figures do not equal PHP-FPM worker RSS or container memory.
                # The 2 MiB application read cap is independently validated in PHPUnit.
                summary = {
                    "environment": "PHP CLI built-in server (not PHP-FPM/production)",
                    "clients": CLIENTS,
                    "iterations_per_client": ITERATIONS,
                    "requests": sum(len(v) for v in by_endpoint.values()),
                    "failed_requests": 0,
                    "elapsed_seconds_including_auth": elapsed,
                    "endpoint_ms": status,
                    "events": events,
                    "sqlite": files,
                    "download_max_bytes": 2097152,
                    "php_worker_rss": "not measured (CLI server / Docker wrapper not representative)",
                }
                print(json.dumps(summary, indent=2))
                print("HTTP CAPACITY PROBE: PASS")
            finally:
                server.terminate()
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=10)


if __name__ == "__main__":
    main()

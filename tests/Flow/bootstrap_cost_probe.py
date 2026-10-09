#!/usr/bin/env python3
"""Measure actual PHP HTTP bootstrap routes on an isolated migrated SQLite database."""
import json
import os
import socket
import statistics
import subprocess
import tempfile
import time
from pathlib import Path
from urllib.error import URLError

from live_smoke import ROOT, DOCKER_PHP, client, php_command, request

SAMPLES = 40
PATHS = {
    "health": ("/healthz", 200),
    "ready": ("/readyz", 200),
    "missing": ("/nonexistent-example", 404),
    "spa_login": ("/app/login", 200),
    "legacy_login_alias": ("/login", 303),
    "session_json": ("/api/session", 200),
}

def measure(opener, base, path, expected):
    observations = []
    for _ in range(SAMPLES):
        started = time.perf_counter_ns()
        status, _, _ = request(opener, base, path)
        ms = (time.perf_counter_ns() - started) / 1_000_000
        assert status == expected, (path, status, expected)
        observations.append(ms)
    observations.sort()
    return {
        "p50_ms": round(statistics.median(observations), 3),
        "p95_ms": round(observations[int((SAMPLES - 1)*.95)], 3),
        "p99_ms": round(observations[int((SAMPLES - 1)*.99)], 3),
    }

def main():
    with tempfile.TemporaryDirectory(prefix="fast-bootstrap-benchmark-") as tmp:
        db = str(Path(tmp)/"benchmark.sqlite")
        env = os.environ.copy()
        env.update({"DB_PATH": db, "APP_ENV":"test", "SESSION_COOKIE_SECURE":"0"})
        prepare = subprocess.run(php_command(tmp, ["bin/migrate.php"], db_path=db),
                                 cwd=ROOT, env=env, capture_output=True, text=True, check=True)
        assert "Applied" in prepare.stdout
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        address = "0.0.0.0" if DOCKER_PHP else "127.0.0.1"
        base = f"http://127.0.0.1:{port}"
        log = Path(tmp)/"php-server.log"
        with log.open("wb") as output:
            server = subprocess.Popen(
                php_command(tmp, ["-S", f"{address}:{port}", "-t", "public", "public/index.php"],
                            port=port, db_path=db),
                cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=output,
            )
            try:
                opener = client()
                for attempt in range(80):
                    try:
                        status, _, _ = request(opener, base, "/healthz")
                        assert status == 200
                        break
                    except (URLError, ConnectionError, TimeoutError, OSError):
                        if attempt == 79 or server.poll() is not None:
                            raise RuntimeError("Server failed before measurements: "+log.read_text(errors="replace")[-1200:])
                        time.sleep(.08)
                results = {}
                for name,(path,status) in PATHS.items():
                    results[name] = measure(opener, base, path, status)
                print(json.dumps({"requests_per_path": SAMPLES,"php_server":"built-in CLI single worker",
                                  "routes": results}, indent=2))
                assert results["health"]["p95_ms"] < 1000
                print("BOOTSTRAP COST MEASUREMENT: PASS")
            finally:
                server.terminate()
                try:
                    server.wait(timeout=8)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=8)

if __name__ == "__main__":
    main()

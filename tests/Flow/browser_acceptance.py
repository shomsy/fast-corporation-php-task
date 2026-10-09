#!/usr/bin/env python3
"""Run browser E2E against a disposable migrated PHP/SQLite server."""
from pathlib import Path
from tempfile import TemporaryDirectory
from urllib.error import URLError
import os
import socket
import subprocess
import time

import live_smoke as base


def main():
    with TemporaryDirectory(prefix="fast-browser-e2e-") as tmp:
        db = Path(tmp) / "browser.sqlite"
        env = os.environ.copy()
        env.update({"DB_PATH": str(db), "APP_DEBUG": "0", "SESSION_COOKIE_SECURE": "0"})
        subprocess.run(base.php_command(tmp, ["bin/migrate.php"], db_path=db),
                       cwd=base.ROOT, env=env, text=True, capture_output=True, check=True)
        admin = subprocess.run(base.php_command(tmp, ["bin/create-admin.php", "review-admin", "--password-stdin"], db_path=db),
                               input="review-secret\n", cwd=base.ROOT, env=env, text=True, capture_output=True)
        if admin.returncode != 0:
            raise RuntimeError("Isolated admin bootstrap failed: " + admin.stderr)
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        origin = f"http://127.0.0.1:{port}"
        log = Path(tmp) / "server.log"
        with log.open("wb") as stderr:
            server = subprocess.Popen(base.php_command(tmp, ["-S",
                ("0.0.0.0:" if base.DOCKER_PHP else "127.0.0.1:") + str(port),
                "-t", "public", "public/index.php"], port, db_path=db),
                cwd=base.ROOT, env=env, stdout=subprocess.DEVNULL, stderr=stderr)
            try:
                for attempt in range(70):
                    try:
                        status, _, _ = base.request(base.client(), origin, "/api/session")
                        if status == 200:
                            break
                    except (URLError, TimeoutError, ConnectionResetError):
                        if attempt == 69 or server.poll() is not None:
                            raise RuntimeError("PHP browser-test server failed:\n"
                                               + log.read_text(errors="replace"))
                        time.sleep(0.08)
                result = subprocess.run(["node", "tests/Browser/journeys.mjs", origin],
                                        cwd=base.ROOT, env=env, capture_output=True, text=True, timeout=90)
                if result.returncode != 0:
                    raise AssertionError("Browser E2E failed:\n" + result.stdout
                                         + "\n" + result.stderr + "\nPHP diagnostic:\n"
                                         + log.read_text(errors="replace")[-6000:])
                print(result.stdout, end="")
            finally:
                server.terminate()
                try:
                    server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=5)


if __name__ == "__main__":
    main()

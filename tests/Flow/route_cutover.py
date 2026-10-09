#!/usr/bin/env python3
"""Verify SPA-only document routing; all former SSR entry points are redirects."""
from pathlib import Path
import os
import socket
import subprocess
import tempfile
import time
from urllib.error import URLError
import live_smoke as base

ALIASES = {
    '/': '/app/home', '/login': '/app/login', '/register': '/app/register',
    '/page-a': '/app/page-a', '/page-b': '/app/page-b',
    '/statistics': '/app/statistics', '/reports': '/app/reports',
}

def main():
    with tempfile.TemporaryDirectory(prefix='fast-spa-routes-') as tmp:
        root = Path(tmp)
        db = root / 'only-spa.sqlite'
        env = os.environ.copy()
        env.update(DB_PATH=str(db), APP_DEBUG='0', SESSION_COOKIE_SECURE='0', UI_ENTRYPOINT='spa')
        subprocess.run(base.php_command(tmp, ['bin/migrate.php'], db_path=db),
                       cwd=base.ROOT, env=env, check=True, capture_output=True)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        url = f'http://127.0.0.1:{port}'
        with (root / 'spa.log').open('wb') as log:
            launch = base.php_command(tmp, ['-S',
                ('0.0.0.0:' if base.DOCKER_PHP else '127.0.0.1:') + str(port),
                '-t', 'public', 'public/index.php'], port, db_path=db)
            if base.DOCKER_PHP:
                launch[launch.index('php:8.5-cli'):launch.index('php:8.5-cli')] = ['-e', 'UI_ENTRYPOINT=spa']
            server = subprocess.Popen(launch, cwd=base.ROOT, env=env,
                                      stdout=subprocess.DEVNULL, stderr=log)
            try:
                browser = base.client()
                for attempt in range(75):
                    try:
                        if base.request(browser, url, '/healthz')[0] == 200:
                            break
                    except (URLError, TimeoutError, ConnectionResetError):
                        if attempt == 74 or server.poll() is not None:
                            raise RuntimeError('SPA server failed to boot')
                        time.sleep(.08)
                for alias, screen in ALIASES.items():
                    code, headers, body = base.request(browser, url, alias)
                    assert code == 303 and headers.get('Location') == screen and body == b'', (alias, code)
                    code, headers, body = base.request(browser, url, screen)
                    assert code == 200 and b'<div id="app-root"' in body, (screen, code)
                    assert b'Existing server-rendered application' not in body
                code, headers, _ = base.request(browser, url, '/statistics?date=2026-10-08&user=alice')
                assert code == 303 and headers['Location'] == '/app/statistics?date=2026-10-08&user=alice'
                assert base.request(browser, url, '/app/unknown')[0] == 404
                assert base.request(browser, url, '/api/session')[0] == 200
                for old_post in ['/login', '/register', '/logout', '/buy-cow', '/download']:
                    code, _, _ = base.request(browser, url, old_post, {'unused': 'value'})
                    assert code in (404, 405), (old_post, code)
                print('SPA ROUTES: PASS; sole document owner, legacy GET aliases, no SSR POSTs, safe 404')
            finally:
                server.terminate()
                try:
                    server.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    server.kill()
                    server.wait(timeout=5)

        bad = env.copy()
        bad['UI_ENTRYPOINT'] = 'ssr'
        reject_cmd = base.php_command(tmp, ['-r', 'require "bootstrap/config.php";'], db_path=db)
        if base.DOCKER_PHP:
            reject_cmd[reject_cmd.index('php:8.5-cli'):reject_cmd.index('php:8.5-cli')] = ['-e', 'UI_ENTRYPOINT=ssr']
        rejected = subprocess.run(reject_cmd, cwd=base.ROOT, env=bad, capture_output=True, text=True)
        assert rejected.returncode != 0 and 'Unsupported UI entrypoint' in (rejected.stdout + rejected.stderr), (rejected.returncode, rejected.stdout[-400:], rejected.stderr[-900:])
        print('SPA BOOT CONFIG: PASS; removed SSR mode fails closed')

if __name__ == '__main__':
    main()

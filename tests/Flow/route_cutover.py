#!/usr/bin/env python3
"""Verify that GET route ownership changes only under explicit UI_ENTRYPOINT."""
from pathlib import Path
import os
import socket
import subprocess
import tempfile
import time
from urllib.error import URLError
import live_smoke as base

ALIASES = {'/': '/app/home', '/login': '/app/login', '/register': '/app/register',
           '/page-a': '/app/page-a', '/page-b': '/app/page-b',
           '/statistics': '/app/statistics', '/reports': '/app/reports'}

def check(root, db, mode):
    env = os.environ.copy()
    env.update({'DB_PATH': str(db), 'APP_DEBUG': '0', 'SESSION_COOKIE_SECURE': '0', 'UI_ENTRYPOINT': mode})
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    url = f'http://127.0.0.1:{port}'
    with (root / (mode + '.log')).open('wb') as log:
        launch = base.php_command(str(root), ['-S',
            ('0.0.0.0:' if base.DOCKER_PHP else '127.0.0.1:') + str(port),
            '-t', 'public', 'public/index.php'], port, db_path=db)
        if base.DOCKER_PHP:
            launch[launch.index('php:8.5-cli'):launch.index('php:8.5-cli')] = [
                '-e', 'UI_ENTRYPOINT=' + mode,
            ]
        server = subprocess.Popen(launch, cwd=base.ROOT, env=env,
                                  stdout=subprocess.DEVNULL, stderr=log)
        try:
            for i in range(70):
                try:
                    if base.request(base.client(), url, '/healthz')[0] == 200:
                        break
                except (URLError, TimeoutError, ConnectionResetError):
                    if i == 69 or server.poll() is not None:
                        raise RuntimeError('Server startup failed')
                    time.sleep(.08)
            browser = base.client()
            for legacy, spa in ALIASES.items():
                status, headers, body = base.request(browser, url, legacy)
                if mode == 'spa':
                    assert status == 303 and headers.get('Location') == spa and body == b'', (legacy, status)
                    assert base.request(browser, url, spa)[0] == 200
                elif legacy in ('/login', '/register'):
                    assert status == 200 and b'csrf_token' in body, (legacy, status)
                else:
                    assert status == 303 and headers.get('Location') == '/login', (legacy, status)
            assert base.request(browser, url, '/app/unknown')[0] == 404
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait(timeout=5)

def main():
    with tempfile.TemporaryDirectory(prefix='fast-ui-cutover-') as temporary:
        root = Path(temporary)
        db = root / 'same.sqlite'
        env = os.environ.copy()
        env['DB_PATH'] = str(db)
        subprocess.run(base.php_command(temporary, ['bin/migrate.php'], db_path=db),
                       cwd=base.ROOT, env=env, check=True, capture_output=True)
        check(root, db, 'spa')
        check(root, db, 'ssr')
        print('CUTOVER: PASS; SPA URL ownership and same-database SSR rollback')

if __name__ == '__main__':
    main()

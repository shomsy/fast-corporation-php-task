# Fast Corporation — PHP Developer Technical Task

A small web application that tracks user activity, built with **native PHP, SQLite and Vanilla JavaScript**. It includes authentication, two interactive pages, activity statistics and daily reports. No PHP framework, frontend framework, external service or JavaScript build process is required.

## Requirements

- PHP 8.3 or newer with `pdo_sqlite`, `sqlite3` and `session` extensions
- Composer 2
- SQLite writable private database directory
- Optional for frontend/browser tests: Node.js 24, npm, Chrome and Python 3

## Quick start

```bash
git clone https://github.com/shomsy/fast-corporation-php-task.git
cd fast-corporation-php-task
composer install
cp .env.example .env
php bin/migrate.php
php bin/create-admin.php admin
php -S 127.0.0.1:8080 -t public public/index.php
```

Open http://127.0.0.1:8080. Register a normal account or sign in using the administrator account created by the CLI. Administrator creation prompts for a password; automated setups may pass `--password-stdin` instead of exposing a password as a command-line argument.

The default database is `var/database/app.sqlite`. You can configure `DB_PATH` in `.env`. Serve **only `public/`** as the web document root.

## Functional requirements

| Feature | Access | Behavior |
| --- | --- | --- |
| Registration | Public | Register a new user |
| Login / Logout | Public / authenticated | Establish or revoke a secure session |
| Page A — `/app/page-a` | Authenticated | **Buy a cow** button changes to `thankYou` after a successful click |
| Page B — `/app/page-b` | Authenticated | **Download** starts a real file download |
| Statistics — `/app/statistics` | Administrator | Activity events filterable by UTC date, user and action |
| Reports — `/app/reports` | Administrator | Daily chart and table for Page A views, Page B views, Buy a cow clicks and Download clicks |

Statistics includes registration, login, logout, page-view and button-click events. Navigation uses **session-signed keyset cursors**, showing up to 50 events per page. The API rejects legacy offset/page-number navigation.

Reports display up to the latest 30 active UTC dates; the chart and table share the same persisted event source. Buy a cow changes the current page state but does not create a purchase or charge. Reloading Page A restores the button. The bundled `assets/sample.exe` is a fixed harmless PE sample for demonstrating browser downloads; it is not executed by the server.

## Architecture

A small **modular monolith**, organized by business capability:

```text
src/
  Accounts/
  ActivityTracking/
  CowOffer/
  Downloads/
bootstrap/         # Composition and HTTP adapters
runtime/           # HTTP request/response and routing
infrastructure/    # SQLite, sessions, time and diagnostics
public/app/        # Vanilla JavaScript SPA
tests/             # Unit, integration, browser and flow tests
```

Each module owns its invariants and technical persistence/query implementations. The PHP API serves JSON; the frontend uses native ES modules, browser History API and same-origin requests.

## Security

- Passwords are bcrypt-hashed, with bounded byte-length validation.
- Sessions and CSRF context rotate on successful login; logout revokes authentication.
- Idle and absolute session expiration and administrator authorization are enforced server-side.
- Database queries use PDO prepared statements and SQLite constraints.
- Registration and its accepted activity event are written in one transaction.
- Frontend user-provided values are rendered safely through DOM text APIs.
- Downloads use a fixed private artifact with size and digest checks.

For internet-facing deployment, configure HTTPS and `SESSION_COOKIE_SECURE=1`, restrict filesystem privileges, enforce ingress abuse controls and validate off-host backup/recovery. Passing local checks is **not** proof of public-production readiness.

## Validation

```bash
composer quality
npm ci --ignore-scripts
npm run test:frontend
npm run test:browser
python3 tests/Flow/live_smoke.py
python3 tests/Flow/route_cutover.py
python3 tests/Flow/sqlite_backup_restore.py
python3 tests/Flow/auth_ingress_rate_limit.py
```

`composer quality` runs Composer validation, dependency audit, coding-convention checks, PHPDoc checks, PHPStan, Deptrac and PHPUnit. Browser and flow tests require their corresponding local runtime dependencies.

See [TASK.md](TASK.md) for the supplied assignment and explicit implementation assumptions.

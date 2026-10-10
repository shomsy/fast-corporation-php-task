# Fast Corporation — PHP developer technical task

A small, runnable activity-tracking web application built for the Fast Corporation PHP interview exercise. It uses **PHP 8.3+, SQLite and browser-native JavaScript**—no Laravel, JavaScript framework, frontend build step, Redis or external service.

**Reviewer shortcut:** install the prerequisites, run `composer install`, then `./bin/fast-test start`. The command prints the actual local URL and an automatically generated administrator password. Sign in as `admin` to see statistics and reports; use **Register** to create a normal user. Stop everything started by the launcher with `./bin/fast-test stop`.

## What the task required and what I built

| Requested feature | Implementation | Who can use it |
| --- | --- | --- |
| Registration | Username/password validation, bcrypt password hashing; a successful registration is recorded as an activity event | Visitor |
| Login / logout | Server-side sessions, CSRF protection, accepted authentication events | Visitor / signed-in user |
| Page A — Buy a cow | Tracks the page view and the accepted click; replaces the button with **`thankYou`** | Signed-in user |
| Page B — Download | Tracks page view and download, then serves a fixed `sample.exe` attachment through a native browser download | Signed-in user |
| Statistics | Filterable history by date, exact username and action; up to 50 records per cursor page | Administrator |
| Reports | A daily line chart and matching table for page views and button clicks | Administrator |

The **Buy a cow** button is a demonstration, not an actual purchase. Its success state lasts until the page is reloaded. The downloadable file is a bundled demonstration artifact; it is never executed by the server.

All tracked times are recorded in UTC. Reports cover up to 30 days **on which tracked activity occurred**, so dates without activity may be absent.

## Quick start: isolated local review (recommended)

Prerequisites:

- **Docker**, with permission to run containers, and the `php:8.5-cli` image (run `docker pull php:8.5-cli` if needed).
- **Composer 2** and installed PHP dependencies (`vendor/`). PHP 8.3+ with `pdo_sqlite`, `sqlite3` and `session` is required for native PHP operation.
- `bash`, `python3`, `curl`, `flock` and `realpath` for the local launcher.

```bash
git clone https://github.com/shomsy/fast-corporation-php-task.git
cd fast-corporation-php-task
composer install
./bin/fast-test start
```

The launcher automatically:

1. Creates/updates the **separate test database** `var/database/fast-test.sqlite` through the project's real migrations.
2. Creates an `admin` account with a generated password (or verifies the existing one).
3. Starts the local PHP server inside Docker, bound **only to 127.0.0.1**, using the first free port in **8081–8090**.
4. Checks `/readyz` and prints the **actual** URL and administrator credentials.

Open the printed `http://127.0.0.1:<port>/app` address. Do not assume a fixed port. The launcher never changes your `.env`, `app.sqlite`, `manual-test.sqlite` or unrelated Docker containers. Its generated credentials live in a private, Git-ignored `var/local-test/` directory.

### Start, stop, inspect

Run these **from the project root**:

```bash
./bin/fast-test start       # Start (or report an already-running instance)
./bin/fast-test status      # Display URL and readiness result
./bin/fast-test open        # Open the browser
./bin/fast-test logs        # Show recent PHP server logs
./bin/fast-test stop        # Stop only this test server; keep accounts and events
./bin/fast-test restart     # Restart the server; keep accounts and events
./bin/fast-test help        # Show available commands
```

**Normal stopping does not delete the database.** A later `start` uses the same accounts and activity history. `restart` is not a database reset.

### Migrations and test database management

These commands intentionally mimic the *developer experience* of Laravel's migration tooling, **without adding Laravel**. They operate on **`fast-test.sqlite` only**.

| Command | Effect | Data preserved? |
| --- | --- | --- |
| `./bin/fast-test migrate` | Apply missing SQL migrations; the test server must first be stopped | Yes |
| `./bin/fast-test db:status` | Print applied migrations and account/event counts; read-only | Yes |
| `./bin/fast-test db:seed` | Create/verify the single local `admin` account, without fake business data; server must be stopped | Yes |
| `./bin/fast-test migrate:fresh --yes` | **Stop the test server, DELETE test accounts/events, re-create its schema and admin account** | **No** |
| `./bin/fast-test reset --yes` | **Stop the test server and DELETE the isolated test database and generated admin password** | **No** |

After `migrate:fresh --yes`, run `./bin/fast-test start` to start serving again. After `reset --yes`, `start` performs the initial migrations and admin provisioning again. The `--yes` flag is required for destructive actions; the test file path and container ownership are checked before deletion.

**No production-data reset, drop-all-tables or arbitrary database-target option is exposed.** Destructive operations are deliberately limited to the isolated test database.

### Manual acceptance path

1. Open `/app/register`. Register a normal user, then sign in. The user should see Home, Page A and Page B.
2. Open Page A, click **Buy a cow**, and verify that the button disappears and `thankYou` appears.
3. Open Page B, click **Download**, and verify that the browser downloads `sample.exe`.
4. Sign out, sign back in as `admin` using the generated credentials, and open **Statistics** and **Reports**.
5. In Statistics, try the date/user/action filters, pagination, and **Clear filters**. In Reports, verify that the chart and table show corresponding daily totals.
6. Optionally try an existing username, an invalid username, a short password, reversed dates and a non-admin account accessing admin screens; the interface should provide understandable errors.

The interface intentionally uses human-readable validation messages rather than password byte counts, raw exceptions, or technical CSRF/cursor messages. Passwords must contain **at least 12 visible characters**. The independent bcrypt limit is **72 bytes**; sufficiently long non-ASCII/emoji passwords can reach that bound earlier.

## Run without the Docker helper

A conventional local PHP setup is supported too:

```bash
composer install
cp .env.example .env
chmod 600 .env
php bin/migrate.php
php bin/create-admin.php admin
php -S 127.0.0.1:8080 -t public public/index.php
```

Visit `http://127.0.0.1:8080/app`. In this mode, `DB_PATH` from `.env` (default `var/database/app.sqlite`) is used. Stop the foreground server with **Ctrl+C**. **Do not run the `fast-test` reset commands as a substitute for production migrations**; they manage a distinct database.

Administrator creation asks for a password interactively; use `--password-stdin` for noninteractive setup. The web server document root must always be `public/`.

## Implementation decisions worth reviewing

- **Small modular monolith:** business capabilities are `Accounts`, `ActivityTracking`, `CowOffer` and `Downloads`. HTTP transport is in `bootstrap/Http`; SQLite, session and diagnostic adapters are in `infrastructure/`.
- **Authoritative backend:** PHP validates credentials, permissions, session state, activity events, reporting queries and downloads. Browser JavaScript owns presentation and transient screen state, not security decisions.
- **Consistent events:** accepted registration and its audit event are part of the same SQLite transaction. Other authenticated actions record accepted events with UTC timestamps.
- **Security:** bcrypt hashes; private SQLite/session files; same-origin session cookies and CSRF checks; server-enforced admin access; PDO prepared statements; output via DOM text APIs; verified fixed download artifact.
- **Bounded queries:** activity history uses signed cursor pagination; reports and graphs share the same authoritative stored daily counts.
- **Accessible UI:** labels, status/alert announcements, keyboard focus handling and clearly worded loading/empty/error states.

Source layout:

```text
src/Accounts/           registration, authentication, roles
src/ActivityTracking/   events, filtering, statistics, reports
src/CowOffer/           Page A behavior
src/Downloads/          Page B artifact and audit
bootstrap/Http/         JSON endpoints and authorization
runtime/Http/           request, response, routing
infrastructure/         SQLite, sessions, diagnostics
public/app/             native JavaScript SPA
tests/                  PHP, frontend, browser and flow tests
bin/fast-test           isolated Docker-based local review commands
```

## Automated checks

Install optional Node.js/npm/Playwright browser dependencies before running frontend/browser tests.

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

`composer quality` includes Composer/package validation, security audit, PHP conventions/PHPDoc, PHPStan, Deptrac architecture checks and PHPUnit. Some integration probes need the appropriate local runtime setup. Passing these checks demonstrates local acceptance; it does not certify public-internet production readiness.

## Scope and additional documentation

The original task and chosen assumptions are in [TASK.md](TASK.md). Engineering details, failure semantics, data isolation, migration command contracts and test/review notes are in [TECHNICAL-NOTES.md](TECHNICAL-NOTES.md).

An internet-facing deployment would additionally need correctly configured HTTPS, least-privilege service execution, ingress abuse/rate controls, and verified external backup/restore operations. These production responsibilities are **not** claimed as complete by the interview exercise.

# Fast Corporation PHP Technical Task

A small **Vanilla PHP** web application that tracks user activity. It implements registration, login, logout, Page A (“Buy a cow”), Page B (download), administrator Statistics and daily Reports. No framework, external service or JavaScript build process is required.

## Requirements

- PHP **8.3 or newer**, with `pdo_sqlite` and `session` extensions
- Composer 2
- Python 3 (standard library only) **optional**, for the live reviewer smoke test

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

The admin CLI will securely prompt for a password in an interactive terminal. For noninteractive setup, pipe a password over standard input instead of placing it on the command line:

```bash
printf '%s\n' 'a-local-demo-password' | php bin/create-admin.php admin --password-stdin
```

Open **http://127.0.0.1:8080**. Register a normal user and sign in; the admin account created by the CLI can also sign in at `/login`. Run `php bin/migrate.php` again to verify that there are no pending migrations.

The default database is `var/database/app.sqlite`; `.env` and local database files are ignored by Git. Use an alternate `DB_PATH` in `.env` to isolate an installation. Keep `public/` as the only web document root.

## Pages and roles

| Page | Access | Behavior |
| --- | --- | --- |
| `/register` | Public | Creates a `user` account; cannot register an admin |
| `/login`, `/logout` | Public / signed-in | Password authentication and session revocation |
| `/page-a` | Signed-in user | Records a page view; **Buy a cow** replaces the button with `thankYou` |
| `/page-b` | Signed-in user | Records a page view; downloads the fixed `sample.exe` |
| `/statistics` | Admin only | Event history with combinable UTC date, user and action filters |
| `/reports` | Admin only | Daily graph and table, both from one four-metric aggregate |

Statistics includes registration, login, logout, page views and button clicks. Reports count Page A views, Page B views, Buy a cow clicks and Download clicks, grouped by **UTC date**.

The button state on Page A is intentionally transient; a fresh GET shows it again. There is no inventory, payment or persistent purchase because the task does not require one. The downloadable `assets/sample.exe` is a harmless minimal PE sample, outside the public document root. The server never executes it.

## Security and data correctness

Passwords use PHP's `password_hash`/`password_verify`. Sessions regenerate their ID upon successful authentication. Cookies are HttpOnly with SameSite=Lax; set `SESSION_COOKIE_SECURE=1` and serve the application over HTTPS outside local development. All state-changing HTTP forms require a session-backed CSRF token, and access is checked server-side. Templates escape HTML output, SQL filters use prepared statements, and downloads use a fixed server-side path rather than input from the request.

An append-only activity history stores account ID, historical username snapshot, action, optional target and UTC timestamp. Account registration and its event are committed in one local database transaction. Logout invalidates the session even if activity recording fails. No background infrastructure is needed.

## Validation

```bash
composer test                 # complete PHPUnit suite
python3 tests/Flow/live_smoke.py  # fresh DB, CLI admin, local PHP server, user/admin HTTP journeys
composer quality              # composer validation, dependency audit, style, PHPStan, Deptrac, PHPUnit
```

The smoke script uses a temporary SQLite database. On a development machine where `/usr/bin/php` is a Docker wrapper, it starts the same PHP version with explicit database environment and mapped temporary directory.

For architecture rationale, see `IMPLEMENTATION-PLAN.md`, `docs/architecture/ADR-0002-system-architecture.md` and the pinned Architecture Governance 1.7.1 document.

## PHP standards and maintainability

Composer PSR-4 autoloads all project classes, including `InterviewTask\Bootstrap\`.
The explicit HTTP adapter classes live in `bootstrap/Http/` and keep routing,
security response headers and transport mapping separate from business ownership.
Run `composer dump-autoload --optimize --strict-psr` to check autoload integrity.

PHP-CS-Fixer uses `@PER-CS3x0` (PER Coding Style 3.0), plus project-specific strict
rules. This does **not** assert complete PER Coding Style 3.1 verification.
See `docs/PHP-STANDARDS-VNEXT.md` for the decision and acceptance evidence.

# Fast Corporation PHP Technical Task

A small **Vanilla PHP** web application that tracks user activity. It implements registration, login, logout, Page A
(“Buy a cow”), Page B (download), administrator Statistics and daily Reports. No framework, external service or
JavaScript build process is required.

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

The admin CLI will securely prompt for a password in an interactive terminal. For noninteractive setup, pipe a password
over standard input instead of placing it on the command line:

```bash
printf '%s\n' 'a-local-demo-password' | php bin/create-admin.php admin --password-stdin
```

Open **http://127.0.0.1:8080**. Register a normal user and sign in; the admin account created by the CLI can also sign
in at `/login`. Run `php bin/migrate.php` again to verify that there are no pending migrations.

The default database is `var/database/app.sqlite`; `.env` and local database files are ignored by Git. Use an alternate
`DB_PATH` in `.env` to isolate an installation. Keep `public/` as the only web document root.

**Provisioning and production boot:** Only `php bin/migrate.php` is permitted to create the database and its parent directory. HTTP requests and admin account creation require an existing database; HTTP startup also verifies that the migration ledger and owner tables exist. Always execute migrations *before* the web server accepts traffic. Migration SQL is hashed (SHA-256, exact source bytes) in `schema_migrations`: reruns reject changes to applied files and missing recorded sources. One SQLite `BEGIN IMMEDIATE` protects the entire batch and ledger from concurrent migrators and partial SQL application. CLI provisioning also enables SQLite WAL after a measured two-process contention check; the trade-offs, backup/restore requirements and retry policy are recorded in [ADR-0004](docs/architecture/ADR-0004-sqlite-wal-and-recovery.md). **Legacy two-column ledgers** gain hashes from the currently deployed SQL on their first trusted upgrade; this cannot prove that the historical SQL was unchanged before that upgrade, so preserve a trusted Git release and a database backup. For a public deployment explicitly set `APP_ENV=production`, `APP_DEBUG=0`, `APP_URL=https://your-host.example`, and `SESSION_COOKIE_SECURE=1`. Production HTTP handling additionally requires the trusted PHP server TLS indication (`HTTPS=on` or `HTTPS=1`); configure your trusted reverse proxy/web server accordingly. Do not trust browser-supplied `X-Forwarded-Proto` as proof of HTTPS. A missing database, unapplied migrations, or unsafe production settings fail closed with a generic HTTP 500.

## Pages and roles

| Page                | Access             | Behavior                                                                               |
|---------------------|--------------------|----------------------------------------------------------------------------------------|
| `/register`         | Public             | Creates a `user` account; cannot register an admin                                     |
| `/login`, `/logout` | Public / signed-in | Password authentication and session revocation                                         |
| `/page-a`           | Signed-in user     | Records a page view; **Buy a cow** replaces the button with `thankYou`                 |
| `/page-b`           | Signed-in user     | Records a page view; downloads the fixed `sample.exe`                                  |
| `/statistics`       | Admin only         | Paginated activity history (50/page) with combinable UTC date, user and action filters |
| `/reports`          | Admin only         | Bounded daily graph and table (latest 30 active dates), with optional UTC date range   |

Statistics includes registration, login, logout, page views and button clicks. Reports count Page A views, Page B views,
Buy a cow clicks and Download clicks, grouped by **UTC date**.

Statistics reads 50 rows plus a one-row lookahead to show **Next** only when another page exists. There is no artificial
1,000-page/50,000-event visibility cap; deep offset pagination still incurs increasing SQLite scan/skip cost, so a
measured large audit workload would justify a cursor/keyset change. Reports shows at most the **30 most recent active
UTC dates** matching the optional range (not every calendar date and not every active date in a larger range). Its limit
bounds response size, **not** the cost of scanning/aggregating history. Representative 5,000-event query/index tests are
included; production-scale performance is not claimed.

The button state on Page A is intentionally transient; a fresh GET shows it again. There is no inventory, payment or
persistent purchase because the task does not require one. The downloadable `assets/sample.exe` is a harmless minimal PE
sample, outside the public document root. The server never executes it.

## Security and data correctness

New usernames use 1–64 ASCII letters/digits/dots/underscores/hyphens (starting with a letter or digit) aligned with SQLite NOCASE uniqueness; historical names remain valid at login. New user/admin passwords use explicit bcrypt cost 12 with a strict **12–72-byte** limit and no NUL (bcrypt would silently
truncate longer inputs). Existing bcrypt accounts with historically overlong secrets should reset passwords. Sessions
regenerate their ID and their CSRF token upon successful authentication, require strict session IDs, expire after 30 minutes idle or 12 hours
absolute lifetime, and invalidate the cookie at logout. Native PHP session garbage collection is configured to preserve files for at least 12 hours, instead of the default 24 minutes; application authorization still checks the 30-minute idle and 12-hour absolute limits. In production provision a dedicated, writable, non-symlinked private directory (mode 0700) outside `public/`, configure `SESSION_STORAGE_PATH=/absolute/private/path`, and keep the web worker as its sole owner. Session files must not share a system-wide GC directory with unrelated PHP apps. File-session locking serializes requests with the same cookie: an isolated two-process measurement showed ~0.42 s acquisition separation for the same ID versus ~0.10 s for different IDs under a 0.42 s held session; this is expected native locking, not a reason to add a custom handler. Cookies are HttpOnly with SameSite=Lax; set
`SESSION_COOKIE_SECURE=1` and serve the application over HTTPS outside local development. All state-changing HTTP forms
require a session-backed CSRF token, and access is checked server-side. Templates escape HTML output, SQL filters use
prepared statements, and downloads use a fixed server-side path rather than input from the request.

An append-only activity history stores account ID, historical username snapshot, action, optional target and UTC
timestamp. The accepted-event contract, caller authority, retention and tamper limits are documented in
[ADR-0003](docs/architecture/ADR-0003-accepted-activity-events.md). `view-page` means an accepted authorized request (not confirmation of browser rendering);
`button-click/buy-cow` means accepted authorized interaction; `button-click/download` means a server-side file was
available/read and a response was prepared, not proof the client finished transfer. Account registration and its event
are committed in one local database transaction; `AccountLifecycle::register()` returns no private account object.
Logout invalidates the session even if activity recording fails. The only exported account mutation entry point is
`AccountLifecycle`; `CurrentAccount` is a read-only authentication view and owner-internal registration/login/session
mechanisms are not module exports. The redundant internal Logout pass-through class was removed. An unavailable fixed
download artifact returns 503, but a failure to write its required activity event propagates to the generic 500 HTTP
boundary and is logged on the server. Only the specific SQLite username UNIQUE violation is translated to
`UsernameTaken`; other constraint failures are not concealed. No background infrastructure is needed.

Dynamic HTTP responses now include centralized browser/privacy headers for HTML,
redirects, errors and binary downloads. CSP does not permit inline JavaScript,
and HSTS is emitted only on proven HTTPS requests. Legacy inline CSS is a
temporary SSR exception until the SPA cutover. Denied login, CSRF, admin and
unexpected runtime failures use structured, redacted security diagnostics and
correlation IDs; product activity tracking remains independent. See
[security diagnostics](docs/security/SECURITY-DIAGNOSTICS.md) for remaining
production log retention and alerting work.

## Validation

```bash
composer test                 # complete PHPUnit suite
python3 tests/Flow/live_smoke.py  # fresh DB, CLI admin, local PHP server, user/admin HTTP journeys
composer quality              # strict PSR-4, audit, PHPDoc, PHPStan, Deptrac, PHPUnit
```

The smoke script uses a temporary SQLite database. On a development machine where `/usr/bin/php` is a Docker wrapper, it
starts the same PHP version with explicit database environment and mapped temporary directory.

**Current canonical roadmap:** [Fast Corporation 2.0 — 48 canonical work packets, 142-source traceability, Vanilla JS SPA LLD, execution program and RP prompt](docs/roadmap/README.md). M00/M01 are verified; 15/48 canonical work packets are implemented; M02 security and M03/M04 runtime/data hardening remain in progress. Public-production readiness has not yet been demonstrated.

Original earlier worklist (archived input): **[OOP / Architecture Remediation TODO](docs/OOP-ARCHITECTURE-REMEDIATION-TODO.md)** (18 review findings).

Independent review pending reconciliation: **[ClaudeCode original text](docs/reviews/CLAUDE-CODE-REVIEW-2026-10-08.txt)** and its **[62-finding index](docs/reviews/CLAUDE-CODE-REVIEW-INDEX.md)**. These are archived claims, not yet merged into the canonical remediation TODO.

Separate **[LLD design & code review](docs/reviews/LLD-CODE-REVIEW-2026-10-08.md)**: 26 independently tracked findings (8 high, 14 medium, 4 low), with roles, risks and proposed fixes. It is not yet reconciled with the OOP or ClaudeCode lists.

New independent **[ClaudeCode LLD review — original 26 TODOs](docs/reviews/CLAUDE-CODE-LLD-REVIEW-2026-10-08.md)** and its **[LLD findings index](docs/reviews/CLAUDE-CODE-LLD-REVIEW-INDEX.md)** (8 high, 10 medium, 8 low). Kept verbatim, unmerged and unimplemented pending cross-review reconciliation.

Additional independent **[Gemini review — original 10 findings](docs/reviews/GEMINI-REVIEW-2026-10-08.md)** and **[fact-check index](docs/reviews/GEMINI-REVIEW-INDEX.md)** (4 high, 4 medium, 2 low). Original preserved verbatim; several findings are contradicted by the current source. **Five reports, 142 source items before deduplication; none merged or implemented by archiving.**

For architecture rationale, see `IMPLEMENTATION-PLAN.md`, `docs/architecture/ADR-0002-system-architecture.md` and the
pinned Architecture Governance 1.7.1 document. The architecture tests use `nikic/php-parser` to verify statically named
cross-owner references (normal/grouped imports, fully-qualified names, type hints, static calls), and inspect literal
SQL table references for owner-only data access. Runtime dynamic class names, reflection and computed SQL identifiers
still require review; module manifests are enforced code contracts, not a PHP runtime sandbox.

## PHP standards and maintainability

Composer PSR-4 autoloads all project classes, including `InterviewTask\Bootstrap\`.
The explicit HTTP adapter classes live in `bootstrap/Http/` and keep routing,
security response headers and transport mapping separate from business ownership.
Every `composer quality` run verifies PSR-4 namespaces and duplicate class definitions via
`composer dump-autoload --optimize --strict-psr --strict-ambiguous`.

**PhpStorm MOI is the only PHP formatter.** It is the selected project code
style and the author-owned visual standard. PHP-CS-Fixer is not executed by
`composer quality`; CI checks correctness but cannot independently check
exact MOI whitespace. See `docs/MOI-FORMATTING.md` for IDE formatting.
Class and attribute references normally use file-level `use` imports, including
simple PHPDoc annotations (`@throws`, `@see`). PhpStorm recommends and organizes
imports using MOI. Exact import formatting is no longer enforced by Composer CI.
Time-dependent use cases accept the standard `Psr\Clock\ClockInterface` (PSR-20),
implemented locally by `Infrastructure\Time\SystemClock` in UTC. Tests inject fixed clocks;
no bespoke clock interface or extra clock implementation package is needed.
See `docs/PHP-STANDARDS-VNEXT.md` for the decision and acceptance evidence.

For complete PHPDoc contracts (classes, constructors, parameters, returns, exceptions, properties, typed arrays and test methods), see `docs/PHPDOC-STANDARD.md`. Run `composer docs:check` to enforce structural DocBlock coverage.

## Automated refactoring (Rector)

Rector 2.7 uses the maximum relevant PHP 8.3 and PHPUnit refactoring presets.
Run `composer rector:scan` to see its **dry-run suggestions**.

Identifier naming is **manual**: type-derived renaming rules for properties,
constructor parameters, local and catch variables are disabled in `rector.php`.
This protects business vocabulary and the public contracts of PHP named arguments.
Review remaining suggestions against PHPDoc, module boundaries, existing behavior
and tests. The evidence is in `docs/RECTOR-MAX-PHP83-REPORT.md`.

The original global MOI scheme is the sole visual formatter; Rector is
an advisory dry-run scanner without automatic visual style transformations.
`composer quality` checks correctness, not exact formatting.

## Deployment scope and remaining hardening

This is a reviewer-oriented local demo. Before public internet exposure, configure HTTPS,
`SESSION_COOKIE_SECURE=1`, an edge/reverse-proxy login rate limit and request-size limits,
plus centralized operational logging. Per-IP/session-only throttling in PHP is not treated
as a production brute-force boundary. Unexpected HTTP failures return a generic 500
without leaking exception details; the server error log retains diagnostic context.
The live HTTP smoke test deliberately triggers both a PDO bootstrap failure and a
missing audit table during an otherwise valid download, verifying HTTP 500 and
server-side exception diagnostics in both scenarios. Internet deployment remains conditional on the edge controls above.

# C-26 — Deployment, probes, rollback and recovery runbook

**Audience:** fresh operator or reviewer. **Status:** locally exercised reference deployment; **public-production NO-GO** until host-specific evidence and service objectives exist.  
**Owner:** Platform/Delivery. **Dependency:** [ADR-0012](architecture/ADR-0012-private-db-and-recovery.md) — private SQLite and verified online backups.

## 1. Distinguish liveness from readiness

| Probe | Success | Failure | Dependencies and side effects |
|---|---|---|---|
| `GET /healthz` | 200 `ok` | 5xx only if the PHP entrypoint itself fails | Runs **before** application/bootstrap, does not open SQLite, start a session, or write an event |
| `GET /readyz` | 200 `ready` | 503 `Not Ready`, with opaque diagnostic request ID | Loads validated deployment config; opens existing DB, checks account/activity columns, verifies that every local owner migration appears exactly once in the ledger with matching SHA-256 |
| `HEAD` variants | Corresponding status, no body | Corresponding status, no body | Still executes readiness on HEAD; unlike ordinary HEAD routing it must verify dependencies |
| `OPTIONS` | 204 / `Allow: GET, HEAD, OPTIONS` | — | No DB or session |
| Other methods | 405 / `Allow` | — | No DB or session |

Do not route probes through authenticated UI handlers. They must not count page views, trigger download/purchase, or issue cookies. All probe responses inherit centralized `no-store` and browser privacy headers. A failed readiness check logs a server-side error **class only**, correlated to the response's `X-Request-ID`; no SQL, user input, credentials or exception strings are sent to the browser.

**What readiness actually proves:** at the instant of the check, config and the file-backed SQLite database are openable, expected business columns can be read, and source-to-ledger migration hashes match this release. It does **not** guarantee available disk space, successful future writes, network availability, non-expiring session storage, off-host backups, or recoverability under host loss. Do not use green readiness as the only deployment validation.

## 2. Fresh local setup and HTTP acceptance

With PHP 8.3+, Composer 2 and the SQLite PDO extension:

```bash
git clone https://github.com/shomsy/fast-corporation-php-task.git
cd fast-corporation-php-task
composer install
cp .env.example .env
php bin/migrate.php
php bin/migrate.php                       # No pending migrations.
php bin/create-admin.php admin
php -S 127.0.0.1:8080 -t public public/index.php
```

Open a second terminal:

```bash
curl --fail-with-body -i http://127.0.0.1:8080/healthz
curl --fail-with-body -i http://127.0.0.1:8080/readyz
composer quality
python3 tests/Flow/live_smoke.py
python3 tests/Flow/sqlite_backup_restore.py
```

The live smoke uses **its own temporary database** and does not edit your normal application database. It must prove liveness remains 200 even when a business table is removed, readiness returns 503, and the normal UI journeys still work. These are integration fixtures, not permission to drop a real deployment table.

## 3. Reference production deployment contract

Before any external traffic:
1. Select a specific **unprivileged** PHP service account and local filesystem with SQLite WAL locking support; confirm its UID, ACLs, parent directory traversal and all mounted volumes. Serve only `public/`; keep source, `.env`, DB, sessions and backups outside the document root.
2. Provision privately owned directories mode **0700**, and `.env`, SQLite, session and backup files mode **0600**. Set `APP_ENV=production`, `APP_DEBUG=0`, `SESSION_COOKIE_SECURE=1`, `APP_URL=https://your-host.example`, absolute private `DB_PATH` and `SESSION_STORAGE_PATH`. The trusted web server must set PHP `HTTPS=on` (or `1`). Do **not** infer TLS from a user-controlled `X-Forwarded-Proto`.
3. Freeze a reviewed release Git SHA and dependency lockfile; install as the release owner, restrict writer permissions and check `composer audit`, PHPStan, Deptrac and tests **before** routing public requests.
4. Drain requests / stop writes for an incompatible release. Run `php bin/backup.php /absolute/private/backups/unique-snapshot.sqlite`. Verify output, file ownership and standalone snapshot integrity; store a protected **off-host** copy under a defined retention policy.
5. Run `php bin/migrate.php` exactly once or safely rerun (checksum drift rejected); never run DDL during HTTP requests. No destructive migration is approved by this runbook.
6. Bring up a candidate release on a private port. Check `/healthz` 200 and `/readyz` 200; perform **real** login, privileged role checks, CSRF, Page A/B, download and reports against non-destructive fixtures.
7. Only after successful ready + acceptance, switch ingress to the candidate, and monitor readiness, p95/error rate, SQLite `BUSY`, WAL size, session failures, disk availability, 5xx and correlated security logs. An upstream load balancer should remove a pod/instance on repeated readiness failure rather than restart it blindly.

**Public GO is still blocked:** real proxy rate-limits (C-05: 429/Retry-After), verified service identity, reliable off-host backup retention, actual RPO/RTO, load measurements and explicit privacy/retention decisions (C-47) are not established.

## 4. Safe rollback versus forward recovery

**Code-only rollback:** remove unhealthy candidate from ingress; if the *database schema has not changed*, point the previous reviewed release back at the still-valid DB and verify its readiness and user journeys. Do not merely change the binary version if a migration makes old code incompatible.

**Schema-changing failure:** stop writers and preserve source SQLite **plus WAL/SHM** as a forensic copy. Never copy only the live main `.sqlite` file or delete WAL/SHM as a checkpoint shortcut. Restore the last verified standalone backup **into a new private directory**, do not overwrite the primary DB. Run SQLite `PRAGMA integrity_check`, inspect the ledger hashes and representative row totals, start a compatible candidate release with `DB_PATH` pointing at the restored copy, and verify `/readyz` and actual authenticated behavior. Only then switch ingress. Any post-backup writes are missing and require product-owner reconciliation; the database backup cannot restore those events automatically.

**Forward recovery:** if migration/code mismatch caused readiness failure, deploy a reviewed compatible migration + application release forward **after** a protected backup and full isolated test. Do not hand-edit `schema_migrations`, disable checksum checks, force a restored old snapshot over a current DB, or run unsafe unbounded replay.

**Release decision log:** record old/candidate commit, migration hashes, backup unique path and integrity result, traffic-stop and recovery timestamps, row counts, measured RPO/RTO versus the team's *actual* objectives, observed errors, operator and next action. Without independently demonstrated RPO/RTO on the target host, publish **NO-GO** for public production.

## 5. Evidence and limitations

- `tests/Runtime/HealthProbesTest.php` verifies liveness on missing DB, readyz checksum drift, and method negotiation.
- `python3 tests/Flow/live_smoke.py` exercises the real HTTP server, both probes, and simulated missing ActivityTracking table. No session cookies are produced by probes.
- `python3 tests/Flow/sqlite_backup_restore.py` tests WAL-consistent online backup, restore parity, no-overwrite and simulated SQLITE_FULL on disposable DB files.
- `docs/architecture/ADR-0012-private-db-and-recovery.md` documents private worker rights and restore rationale.

The principles follow SRE's distinction between process liveness, user-facing readiness and recovery objectives; the exact probe behavior and runbook claims above are grounded in executable tests, not theoretical availability.

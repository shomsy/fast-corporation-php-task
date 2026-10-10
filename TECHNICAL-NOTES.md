# Engineering notes — Fast Corporation interview task

**Audience:** reviewers and engineers who want to inspect design choices, invariants, error handling and local operations beyond the two-minute README. The external task is [TASK.md](TASK.md); the practical entry point is [README.md](README.md).

## Scope and architecture

The application deliberately uses PHP 8.3+ with Composer, SQLite and browser-native ES modules. A small modular monolith lets the evaluator trace each user story without the indirection and bootstrapping cost of an unnecessary framework. Modules under `src/` are named for business capabilities. `bootstrap/Http/` is the HTTP adapter, `runtime/Http/` handles protocol mechanics, `infrastructure/` handles persistence, sessions and operational services, and `public/app/` renders the SPA.

The implementation is not a commerce or package-delivery platform: Buy a cow is a UI/demo event and the download serves a fixed private sample. It does not charge money or execute `sample.exe`.

## User-visible behavior and failure contracts

1. **Registration:** trim the username (never mutate password contents), require an ASCII username of 1–64 characters matching the documented syntax, and enforce a 12-visible-character password minimum. Count user-perceived grapheme clusters (including accented characters and emoji), not PHP string bytes. Reject NUL/invalid UTF-8. Separately reject >72 bytes to prevent silent bcrypt truncation. Hash using bcrypt; store no plaintext password. Duplicate usernames are surfaced without SQL diagnostics.
2. **Login:** accept the same stored account security policy while preserving compatibility with existing hashes; incorrect username/password has the same external error message. Login and logout update server-side session state and audit only accepted events.
3. **Page A:** an accepted click returns the exact external task outcome `thankYou`; the button disappears for the current page. A page reload resets the demonstration state.
4. **Page B:** the server alone selects the executable, verifies type/path/size/digest constraints and audits the accepted download. The browser uses a regular form request to save an attachment; JavaScript does not fetch an executable blob. An unavailable artifact yields a safe 503 response, not an exception trace.
5. **Statistics and reports:** server authorization is required even if the user manually opens a URL. Filters are parsed on the server; invalid values return a safe explanation with HTTP 422. Statistics uses a signed, session-bound cursor; date ranges are UTC. Reports render the same saved values in both table and chart.
6. **UX contract:** neutral language, one actionable remedy when it is safe, no SQL/class/exception/debug jargon. Screen-reader alerts are attached to failed actions and status messages to loading/empty states. Native HTML required-field validation remains browser-controlled; PHP is always the final authority. Statistics action IDs stay stable in the API/storage while the UI presents familiar display labels. The required `thankYou` string is not reworded.
7. **Uncertain effects:** a network disconnect or server failure during a state-changing HTTP call does not prove the operation failed. The interface asks the user to check current activity before resubmitting, rather than automatically replaying a possibly completed mutation. A product with payment semantics would need explicit idempotency and reconciliation guarantees.

### Public HTTP error behavior

| Situation | HTTP | External behavior |
| --- | --- | --- |
| Username collision | 409 | Explain the username is already used |
| Rejected registration/login request | 422 | Explain credential or input requirement |
| Invalid login credentials | 401 | Same message regardless of which credential was incorrect |
| No active account | 401 | Request sign-in |
| Non-admin attempting protected analytics | 403 | Explain lack of permission |
| Invalid session/CSRF check | 403 | Ask the user to refresh and retry |
| Bad filters / invalid report dates | 422 | Help correct filters/dates |
| Download artifact missing or invalid | 503 | Tell the user download is unavailable |
| Unexpected server failure | 500 | Suppress sensitive details; capture server-side diagnostic ID |

Not every backend exception is safe for direct display. Domain/infrastructure error details remain diagnostic data, while API errors provide bounded user-facing strings. Authentication endpoints never identify whether a username exists.

## Activity persistence and correctness boundaries

SQLite stores registered accounts, canonical activity names and UTC timestamps. Registration and its registration event share a transaction; if the event cannot be written, registration is rolled back. Login audit failure revokes the newly established session rather than falsely reporting complete success. A logout attempts session revocation even if audit writing fails. Statistics query ownership remains server-side; views and clicks are distinguished by typed target and action values.

The database uses WAL mode established at provisioning time. Migration checksums are tracked in `schema_migrations`; changing a migration after it is applied is rejected. The deployment/recovery runbook and original architecture decision records live in the project's local engineering documentation, but this file and the README are sufficient to understand and operate the reviewed Git checkout.

## Local commands — operational contract

The self-contained `bin/fast-test` script is a **test-only convenience launcher** built around the user's local `php:8.5-cli` Docker image. It has no privileged API, no automatic production database discovery and no implicit external service calls.

- Fixed test database: `var/database/fast-test.sqlite`.
- Generated administrator credentials: private `var/local-test/admin-password`, printed by `start`, excluded from Git.
- Container: labeled with the canonical checkout path and calling user's UID; foreign containers with the same name are rejected.
- Address: loopback interface only, selected available port 8081–8090. The endpoint `/readyz` must return `ready`.
- `start`: apply pending migrations, prepare/verify admin, start the server and check readiness. Running it twice does not reset data.
- `stop`: stop only the container belonging to this checkout. `restart`: stop and start while preserving test state.
- `migrate`: apply pending migrations **only while the test server is stopped**. Safe on a previously migrated database and not a destructive reset.
- `db:status`: query the migration ledger and account/event counts without writes.
- `db:seed`: prepare/verify only the admin account. This is not a fake-data seeder and is not a general-purpose database fixture loader.
- `migrate:fresh --yes`: **destructive**, first stop the test server, delete only the fixed test SQLite file and generated test admin credential, run SQL migrations, prepare the new admin, leave the server stopped.
- `reset --yes`: **destructive**, stop server, delete only isolated test database and password. Next `start` provisions a new database.
- Refusal safeguards: explicit `--yes`, test-only fixed path, checkout-specific container ownership, no symlinked test database/state, and fail-closed behavior where dependencies are missing.

Destructive migration/reset never targets `.env`, `app.sqlite`, `manual-test.sqlite`, or user-provided paths. Never use `migrate:fresh` on real data. The usual production provisioning entry point is `php bin/migrate.php` with separately configured `DB_PATH`. No generic `DROP TABLE` command is provided because the interview task does not justify that risk.

If starting fails, run `./bin/fast-test status` and `./bin/fast-test logs`. If migrations are missing, first stop the test server and run `./bin/fast-test migrate`. If you intentionally want to discard demo data, use `migrate:fresh --yes` and then `start`. Do not delete files to investigate a health failure.

## Testing and review

PHPUnit covers domain policies, persistence/HTTP contracts and rejection paths. Node's built-in test runner checks frontend message/error states and chart behavior. Browser acceptance exercises registration, authentication, duplicate usernames, invalid credentials, normal/admin authorization, Buy a cow, downloads and analytics filters. Composer quality gates cover dependency audit, PHPStan, Deptrac, PHPDoc and local conventions. Additional Python flows exercise routing, migration/recovery and ingress behavior where the test environment supports them.

**Evidence must be re-collected per revision.** A prior successful audit does not imply the current checkout passed unchanged. The evaluation claim is an interview-task acceptance result, not a production deployment guarantee.

## Trade-offs and out of scope

SQLite keeps reviewer startup self-contained; a different datastore would be justified only by operational load or team constraints. Vanilla JS is sufficient for this feature count. A single process/container is intentionally not a high-availability deployment. Password length feedback prioritizes recognizable characters over cryptographic byte terminology; the underlying bcrypt safety cap is still enforced. No fake event seeding is shipped because fabricated history would make the statistics misleading.

For production use, add capacity measurement, managed ingress rate controls, HTTPS, service least privilege, strong release/rollout controls and verified off-host backup/recovery with defined RPO/RTO. The local Docker launcher is not production orchestration.

## Review guidance and provenance

These engineering decisions were checked against the project's actual PHP/JavaScript behavior and the following guidance. Guidance is advisory; **code and executed tests are the acceptance evidence**.

- Compass Engineering: *How to Keep API and Operational Documentation Current*, *How to Design Safe Startup and Shutdown*, *How to Design Form Validation Across Client and Server* (task-oriented documentation; protected, reversible tooling; backend authority over validation).
- LLD: `check-doc-examples` and `check-input-validation` source-backed specialists (verify documentation against real commands/types and validate untrusted requests at the owning boundary).
- Jeffallan/claude-skills: original `php-pro` and `devops-engineer` skills (type/security/test discipline; explicit operational safety and failure checks). Their Laravel/Kubernetes template recommendations are not requirements for this intentionally framework-free task.
- *Docs for Developers: An Engineer's Field Guide to Technical Writing*, 2nd edition, Jared Bhatti, Sarah Corleissen, Jen Lambourne, David Nunez and Heidi Waterhouse: private original PDF (not included in this repository); directly reviewed physical PDF pages 105–107 (printed pages 79–81, chapter 4). The documentation checklist emphasizes clear purpose, tested steps, prerequisites, working links and peer/technical review. This is reference inspiration, not required software behavior; private book contents are not bundled in Git.

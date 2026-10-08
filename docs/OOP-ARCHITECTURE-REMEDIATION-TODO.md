# OOP / Architecture / Production-Readiness — Remediation TODO

Status: **PLANNED — not implemented**
Review basis: `main` at `f4136f4` (2026-10-08); Compass Engineering and local books corpus.
Scope: Fast Corporation vanilla PHP technical task, **not** an assumed internet-scale product.
Rule: **MOI is the sole PHP visual formatter.** Do not edit the user's personal MOI preferences or re-enable PHP-CS-Fixer as a formatting gate. Rector remains a reviewed dry-run tool; identifier names are author-owned.

**Parallel independent review, not yet consolidated:** [ClaudeCode verbatim review](reviews/CLAUDE-CODE-REVIEW-2026-10-08.txt) and [its 62-item source index](reviews/CLAUDE-CODE-REVIEW-INDEX.md). Preserve original Claude IDs and evidence labels until a deliberate reconciliation pass; existing tasks are not superseded.

## 0. How to use this plan

- [ ] Work in priority/dependency order. Mark a task complete **only** after its acceptance checks pass.
- [ ] For each fix: capture failing regression test first (where practicable), implement the smallest safe change, run scoped tests, then `composer quality` and `python3 tests/Flow/live_smoke.py`.
- [ ] Record **evidence** beside completed tasks: commit, test name/result, important behavior change and risk.
- [ ] Review behavior, authorization, schema and failure contracts before changing public named-argument parameter names.
- [ ] No wholesale rewrite without documented change pressure. Preserve business-first folders and explicit ownership boundaries.
- [ ] Existing green results at review baseline: 86 PHPUnit tests / 5,958 assertions; PHPStan, PHPDoc structural check, Deptrac and live smoke green. Green tests **do not** imply these TODOs are fixed.

### Severity / evidence legend

- **P0:** immediately exploitable or destructive blocker, if substantiated. No P0 was established by this review.
- **P1:** important security or correctness problem with real deployment impact.
- **P2:** significant failure-mode, operability, data-consistency or trust-boundary concern.
- **P3:** code/API clarity, hardening, or measured improvement.
- **Confirmed:** reproduced locally or directly observed from source/filesystem.
- **Conditional:** depends on scale, threat model, host configuration or requirements.
- **Design proposal:** not a proven bug; implement only if the named change pressure exists.

## Phase 1 — Correctness and reproducible failure paths (first merge)

### OOP-01 — Invalid date input must never become HTTP 500 [P1; CONFIRMED]
Owners: ActivityTracking, HTTP adapter.
Files: `src/ActivityTracking/Statistics.php:74-83`, `src/ActivityTracking/DailyReports.php:50-59`, `bootstrap/Http/ActivityPages.php`.

- [ ] Add HTTP regression tests for NUL in `date`, `from`, and `to`. Current `DateTimeImmutable::createFromFormat()` throws `ValueError`, bypassing the HTTP handler's `InvalidArgumentException` catch.
- [ ] Reject input that fails exact `YYYY-MM-DD` lexical validation *before* calling DateTime.
- [ ] Validate real calendar dates (e.g. reject 2026-02-30) and `from <= to`; retain valid UTC date semantics.
- [ ] Convert invalid user input to the existing 422 response; preserve 500 for real unexpected dependency failures.
- [ ] Remove duplicated date parsing with a small `UtcDay` immutable value object **only if** its single shared invariant makes both callers simpler.

**Acceptance:** NUL, arrays, malformed/invalid dates, and reversed ranges return 422 without leaking stack traces; valid reports and statistics unchanged; regression tests fail on old code.

### OOP-02 — Renderer must clean up *all* buffers it owns [P2; CONFIRMED]
Owner: Infrastructure/Templating.
File: `infrastructure/Templating/Renderer.php:47-65`.

- [ ] Reproduce a template that calls `ob_start()` and throws. Observed: `ob_get_level()` grew 0 → 1 after exception.
- [ ] Capture initial buffer nesting level; always unwind to that level in `finally`, preserving original exception.
- [ ] Define behavior if a template changes the expected buffer nesting on successful return; fail explicitly rather than silently leaking/returning incomplete HTML.
- [ ] Cover nested buffer, exception, normal render and subsequent render in tests.

**Acceptance:** no leaked output buffers or partial output after exceptions; original throwable preserved; existing escaping/XSS tests pass.

### OOP-03 — Recheck error boundaries after refactoring [P2; TEST]
Files: `public/index.php`, HTTP adapters, account lifecycle.

- [ ] Verify truly unexpected `Throwable` becomes safe 500 with server-side diagnostics only.
- [ ] Verify expected 401, 403, 409, 422, 503 never reveal internal stack traces or SQL.
- [ ] Verify logout invalidates the session even when its audit write fails; registration + registration audit rollback atomically.
- [ ] Exercise the real HTTP entry point where appropriate; avoid mocks of the failure mechanism being tested.

**Acceptance:** explicit status/body plus authoritative DB/session state assertions for each failure path.

## Phase 2 — Security and session lifecycle (before public deployment)

### OOP-04 — Remove authentication timing enumeration channel [P1; CONFIRMED]
Owner: Accounts.
File: `src/Accounts/Accounts.php:133-165`.

- [ ] Add a deterministic test proving verification executes the intended path for unknown users and known users with wrong passwords (not a brittle millisecond threshold).
- [ ] Use an appropriate *precomputed* dummy bcrypt hash for absent accounts; run equivalent verification work without creating a new hash per login.
- [ ] Preserve non-disclosing invalid-credentials responses and 72-byte bcrypt/NUL password policy.
- [ ] Decide and document whether registration's explicit `409 Username is already taken` is acceptable user enumeration by product policy.
- [ ] Add production edge login throttling/account-abuse monitoring; do not claim timing normalization replaces rate limiting.

**Evidence:** isolated baseline measured ~0.012 ms for absent user versus ~135 ms for existing user with wrong password (illustrative run, not a stable performance benchmark).
**Acceptance:** both branches verify a hash; public errors remain consistent; no regression in login/session tests; abuse controls documented for deployment.

### OOP-05 — Rotate CSRF token on authentication boundary [P2; CONFIRMED]
Files: `src/Accounts/SessionIdentity.php`, `infrastructure/Session/Csrf.php`.

- [ ] Test that successful login rotates session ID **and** invalidates the anonymous CSRF token. Baseline: session ID rotated but CSRF token did not.
- [ ] Ensure a new token is issued on authenticated forms; previously rendered anonymous tokens are no longer accepted.
- [ ] Preserve signed-in behavior and valid logout; verify session expiry/revocation invalidates CSRF state.
- [ ] Review session fixation, privilege changes and cross-tab behavior deliberately.

**Acceptance:** deterministic token lifecycle regression tests; correct behavior through actual HTTP forms. This is defense in depth, **not** a demonstrated standalone CSRF exploit.

### OOP-06 — SQLite permissions and secure deployment defaults [P1; CONFIRMED host mode, CONDITIONAL exposure]
Files: `infrastructure/Database/SqliteConnection.php`, deployment docs/scripts, `.env.example`.

- [ ] Record minimum ownership/permission contract for DB, containing directory, and private `.env`.
- [ ] On real deployment use a dedicated service identity and least privilege, with a writable DB directory only where SQLite needs it.
- [ ] Audit existing DB mode (`777` observed) **and ancestor traversal**: `/home/shomsy/projects/interviews` was `700`, so do not misstate current exposure to other local users.
- [ ] Avoid mutating global `umask` on each web request; implement installer/deployment checks or provisioning steps.
- [ ] Require HTTPS + secure cookies, safe session storage, proxy request limits, login rate limiting, logging and restart/recovery procedure for public exposure.

**Acceptance:** isolated deployment permission test, no world-accessible DB under intended host policy; application works as its service identity; documented threat model and setup check.

### OOP-07 — Authentication/authorization complete mediation [P2; CONDITIONAL]
Files: `RequireSignedIn`, `RequireAdmin`, HTTP adapter entrypoints, `AccountLifecycle`.

- [ ] Enumerate all current and future entry points for sensitive effects (HTTP, CLI, background if ever introduced).
- [ ] Confirm admin policy reloads authoritative role and handles deletion/demotion during active sessions.
- [ ] Add regression test for role demotion, deleted account, expired session, missing/stale session metadata and all forbidden routes.
- [ ] Distinguish identity verification from permission to act; do not rely on display/UI checks.

**Acceptance:** no unauthorized mutation or admin read on any enumerated entry path; no role-cache staleness beyond documented bounds.

## Phase 3 — Persistence integrity / consistency

### OOP-08 — Detect modified applied migrations [P2; CONFIRMED]
File: `infrastructure/Database/Migrator.php`.

- [ ] Store SHA-256 SQL-content checksum alongside applied migration identifier (with safe forward migration of existing ledgers).
- [ ] Fail clearly if an applied SQL file changes; do not silently skip a drifted migration.
- [ ] Define behavior for missing historical migration files and multiple concurrent migrator processes.
- [ ] Test fresh bootstrap, idempotent rerun, modified file, concurrent runs, rollback of failed migration, and interrupted run.

**Evidence:** changing an applied test SQL file and rerunning `migrate()` returned `[]` with no alert.
**Acceptance:** no silent ledger/schema drift; happy-path reviewer setup unchanged; migration recovery steps documented.

### OOP-09 — Test SQLite lock contention and durability [P2; CONDITIONAL]
Files: `SqliteConnection.php`, `Transaction.php`, `Migrator.php`, integration tests.

- [ ] Run real concurrent *file-backed* SQLite connections and measure `SQLITE_BUSY` under mixed writes/analytics reads.
- [ ] Test disk-full/permission-denied scenarios with safe isolation, and transaction commit/rollback semantics.
- [ ] Evaluate WAL only if workload supports the benefit; decide checkpoint/backup approach with real evidence.
- [ ] Document what happens if audit write fails after authentication/session change.

**Acceptance:** reproducible concurrency/failure tests, agreed error/retry policy and explicit acceptable operational limits. Do not add generic retries without identifying safe idempotent effects.

### OOP-10 — Audit integrity and retention policy [P2; CONDITIONAL]
Owner: ActivityTracking.
Files: `ActivityLog.php`, `001_create_activity_events.sql`.

- [ ] Distinguish accepted user interaction from delivery/render completion (download event currently means prepared response, not client receipt).
- [ ] Define trust of `actorId`/`actorLabel` caller inputs and restrict writers at owned boundaries.
- [ ] Decide whether app-level append-only semantics suffice; define retention and DBA threat model.
- [ ] If audit is evidentiary/compliance-relevant, design appropriate write/read privileges and tamper-evidence/archival controls; no premature hash chain/broker.

**Acceptance:** documented audit guarantees accurately match code and operational threats; invariant tests cover allowed actions/targets and failure behavior.

### OOP-11 — Stable pagination under concurrent inserts [P2; CONFIRMED]
Owner: ActivityTracking.
File: `src/ActivityTracking/Statistics.php:101-128`.

- [ ] Add regression that inserts a new event between page 1 and page 2; baseline yielded a repeated ID.
- [ ] Decide contract: best-effort numbered pages **or** stable traversal of one logical history snapshot.
- [ ] If stable traversal required, use keyset/cursor on `(occurred_at, id)`, preserve all filters and deterministic ties; add cursor validation.
- [ ] Measure offset/deep pagination costs before changing interface/UI; keep compatibility policy explicit.

**Acceptance:** once a stable contract is chosen, test no duplicate/skipped entries under representative concurrent inserts and equal timestamps; bounded query plan.

### OOP-12 — Reports query cost [P3; CONDITIONAL]
Owner: ActivityTracking.
File: `src/ActivityTracking/DailyReports.php`.

- [ ] Benchmark larger datasets with representative date/action distribution and query plans (5,000-event tests alone do not establish production capacity).
- [ ] Record target p95/p99 and ingest throughput, index trade-offs and SQLite lock effect.
- [ ] Optimize filtering/aggregation/index strategy only when measurements show a bottleneck.
- [ ] Preserve up-to-30 **active UTC dates** semantics and identical chart/table dataset.

**Acceptance:** before/after plan and latency evidence under a defined workload; no overclaim about scalability.

## Phase 4 — OOP design and information hiding

### OOP-13 — Clarify download command semantics [P3; DESIGN PROPOSAL]
Owner: Downloads.
Files: `src/Downloads/DownloadFile.php`, `bootstrap/Http/DownloadPages.php`.

- [ ] Review whether `read()` misleadingly suggests a side-effect-free query; it also appends an audit event.
- [ ] Consider `prepareDownload()`/explicit outcome naming **only when** it clarifies the public effect and tests.
- [ ] Retain fixed trusted artifact path and guarantee no event for unreadable artifact.
- [ ] For genuinely large artifacts, measure full-buffer memory and consider streaming; keep a small fixed sample as simple string now.

**Acceptance:** names communicate effects, no behavior/HTTP regression; avoid adding DTOs/repositories without a need.

### OOP-14 — Keep deep modules, prune accidental wrappers [P3; DESIGN REVIEW]
Files: `AccountLifecycle`, `CurrentAccount`, `RequireSignedIn`, `RequireAdmin`, `CowOffer`, `DownloadFile`.

- [ ] Keep `AccountLifecycle`: it hides account/audit/session orchestration behind a real policy.
- [ ] Keep `CurrentAccount` unless an equally safe alternative preserves the read-only session boundary.
- [ ] Review `SignedInAccount|Response` return types in HTTP adapters; simplify if readable and no new behavior needed.
- [ ] Avoid one-interface-per-class, generic repositories, artificial domain/service layers or DI container.
- [ ] Preserve one owner for each authoritative fact, business names in business slices and minimal cross-owner exports.

**Acceptance:** each proposed abstraction change names the concealed complexity, reduced coupling or improved invariant; no design-only churn.

### OOP-15 — Tighten public contracts and PHPDocs [P3; DESIGN REVIEW]
- [ ] Inspect placeholder phrases (e.g. “Input value for”, “Result described by the native PHP return type”) and replace with meaningful behavior/contracts where valuable.
- [ ] Distinguish expected business exceptions vs real infrastructure failures; document state transitions and transaction guarantees, not merely native types.
- [ ] Preserve complete useful PHPDocs without redundant boilerplate or exposing implementation internals as public promises.
- [ ] Do not rename named-argument parameters automatically; preserve MOI’s visual style.

**Acceptance:** PHPDoc checker and PHPStan green; API callers remain compatible and docs match tested semantics.

### OOP-16 — Explicit transaction nesting contract [P3; CONDITIONAL]
File: `infrastructure/Database/Transaction.php`.

- [ ] Determine whether nested transactions can occur in real flows.
- [ ] If unsupported, fail fast with clear precondition and test; implement savepoints only after valid use case.
- [ ] Preserve original throwable if rollback itself fails, recording both failure contexts.

**Acceptance:** precise transaction contract, no silent partial writes.

## Phase 5 — Operational readiness and release gates

### OOP-17 — Production deployment contract [P1 gate; CONDITIONAL]
- [ ] Provide deployment reference for supported PHP extensions/runtime, document root `public/`, HTTPS, secure cookies, PHP session storage, reverse proxy body/rate limits and least-privilege filesystem.
- [ ] Add bounded request/response error handling, server-side redacted logs, correlation where useful, and simple health/readiness checks.
- [ ] Ensure secrets and generated DB files are never packaged or served.
- [ ] Define SQLite backup/restore and **test actual restore**; document acceptable RPO/RTO.
- [ ] Define migration release/rollback/forward-recovery runbook; verify non-root runtime.
- [ ] Confirm observability for auth failures, audit-write failures, SQLite contention and 5xx spikes.

**Acceptance:** a fresh operator follows runbook to deploy, fail safely, recover a backup and verify application; no claim of internet-production readiness without these results.

### OOP-18 — Quality gate and release acceptance [P1 gate; TEST]
- [ ] Run `composer quality` (Composer, PHPDocs, PHPStan, Deptrac, PHPUnit), `python3 tests/Flow/live_smoke.py` and matrix CI for PHP 8.3/8.4/8.5.
- [ ] Keep PhpStorm **MOI** as only visual formatter; never let formatter CI overwrite owner style. Rector suggestions reviewed manually.
- [ ] Add targeted real-world failure and security regression tests for completed IDs above.
- [ ] Verify `git diff --check`, no secrets or generated artifacts, clean working tree; commit and push reviewed changes.
- [ ] Record production **GO/NO-GO** separately from technical interview completion.

**Acceptance:** user-visible tests and release evidence accompany every closed item.

## Execution order / milestones

| Milestone | Items | Merge condition |
|---|---|---|
| **M1 — Safe behavior** | OOP-01, OOP-02, OOP-03 | 422 + buffer cleanup proven, HTTP failure contract green |
| **M2 — Security baseline** | OOP-04, OOP-05, OOP-06, OOP-07 | timing path mitigated, CSRF lifecycle, privilege and filesystem contract tested |
| **M3 — Persistence correctness** | OOP-08, OOP-09, OOP-10 | migration drift and relevant DB failure cases tested |
| **M4 — Data access at scale** | OOP-11, OOP-12 | stable traversal policy decided; improvements measured, not assumed |
| **M5 — Elegance without ceremony** | OOP-13–OOP-16 | changes justified by behavior and public contract simplification |
| **M6 — Public production GO/NO-GO** | OOP-17, OOP-18 | real deployment/failure/restore validation passed |

## Explicit non-goals until earned

- [ ] **Do not** add microservices, Redis, broker/queues, CQRS, event sourcing, an ORM, shared domain mega-owner or DI container without a measured/change-pressure trigger.
- [ ] **Do not** rewrite `AccountLifecycle`, `CurrentAccount`, or split all folders into technical layers merely to increase class count.
- [ ] **Do not** assume AI/agent infrastructure is needed: this product has no AI trust/tool execution surface.
- [ ] **Do not** claim the checked local SQLite mode alone proves external data exposure: deployment path isolation matters.

## Verified review references

- Compass: architecture boundaries/invariants, authorization/session trust boundaries, failure-path and real DB testing.
- Local book corpus: Steve McConnell, *Code Complete*, 2nd ed., §5.3 (information hiding); Vlad Khononov, *Balancing Coupling in Software Design*, approx. pp. 158–159 (deep modules); *Learning Domain-Driven Design* (bounded contexts); Martin Kleppmann, *Designing Data-Intensive Applications*, transactions/isolation, around p. 261; Martin Fowler, *Patterns of Enterprise Application Architecture*, transaction/concurrency patterns.
- Evidence is from the original reviewed source and isolated probe runs. **No application fixes were implemented by creating this plan.**

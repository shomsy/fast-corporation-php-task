# Implementation Plan

Status: COMPASS DEEP REVIEW HARDENING — latest candidate passes local quality; exact candidate SHA GitHub CI pending
Project: Fast Corporation PHP Developer technical task
Architecture level: L3 greenfield, applied proportionally
Governance: Architecture Governance 1.7.1
Repository language: English only

## Compass deep-review remediation (2026-10-08)

This follow-up started from synchronized main at
2833238552aa016f80344b5399bb71f8dcba7d3f. The prior CI evidence below
remains historical for that commit, not proof of the follow-up.

- **Lifecycle public contract:** AccountLifecycle is the only exported
  registration/login/logout mutation facade. Raw Register/Login/Logout and
  SessionIdentity are private implementation types. CurrentAccount exposes only
  current() to HTTP adapters; the composition root constructs internal types.
  Owner-contract tests detect lower-level exports and auth mutation leaks.
- **Public-surface enforcement:** parser-based static class references replace
  regex use-only detection: grouped imports, FQCN, aliases, constructors, type
  hints, return types, static calls, ::class, etc. Mutation forms are tested.
  Name generation via runtime strings/reflection remains review-enforced.
- **Statistics correctness/completeness:** 51-row lookahead gives exactly 50
  visible events and a truthful Next; 50 and 51 boundary cases tested in
  activity and real HTTP adapter tests. Removed the arbitrary 1,000-page
  cutoff while guarding overflow. Deep OFFSET remains O(offset) work;
  keyset pagination is a REVISIT trigger when representative audit size
  demonstrates the cost.
- **Reports:** replaced invalid calendar-date fixtures; added a representative
  5,000-event SQLite aggregate and EXPLAIN/index check. The last 30 *active*
  UTC dates inside optional from/to are returned. Output size is bounded;
  processing large history is not proven bounded or production-scale.
- **Data ownership:** AST inspection of PHP SQL string literals and owner-local
  .sql migrations detects static direct references to another owner's table;
  negative owner/table mutation probes added. Dynamically computed SQL/table
  identifiers remain REVIEW-ENFORCED and are explicitly not claimed covered.
- **Local candidate quality:** composer validate/strict PSR-4, dependency audit,
  style, PHPStan max, Deptrac (zero violations/uncovered) and PHPUnit
  **79 tests / 5,929 assertions PASS**; final GitHub CI for follow-up SHA
  must be verified before calling this candidate submission-ready.
- **Scope boundary:** no new framework, ORM, queue, Redis, CQRS or DI container.
  This is a reviewer demo, not a publicly deployed internet service:
  HTTPS, edge login throttling, request size, operational logging and dynamic
  architecture references require deployment/review decisions.
- **Governance status:** PILOT; no claim that all 192 governance obligations
  have been production-validated by these targeted checks.

## Hardening release acceptance (2026-10-08)

The evidence below verifies the hardening application-code commit. A subsequent
documentation-only evidence commit must pass its own exact-SHA CI check before
being considered the final delivered source revision. Earlier acceptance entries
further below are historical and do not replace this validation.

- Source base before hardening: local/remote `main` at `4233b83`.
- Original user task: registration, login/logout, CowOffer, Downloads,
  administrator Statistics and Reports are implemented.
- Session security: explicit bcrypt 72-byte upper limit; sign-in session ID
  rotation; strict rejection of unknown IDs; server-side idle/absolute expiry;
  logout cookie invalidation; live role-downgrade authorization enforcement;
  Secure/HttpOnly/SameSite-Lax cookie configuration tests.
- Account lifecycle orchestration owns registration+activity transaction,
  failure-safe login recording, and irrevocable logout even if logging fails.
- Unexpected HTTP/PDO failure: safe generic 500 response, underlying exception
  retained in server log, real HTTP failure injected and tested.
- 405 response includes correct Allow header; UI copy updated.
- Bounded Statistics (50/page) and Reports (30 active UTC dates); SQLite query
  plan tests verify index use on the relevant queries.
- Owner-local views, module exports, statically enforced public surface and
  Deptrac rules remain under **Governance PILOT**, not globally validated.
- Negative mutation proof: insert an unexported Accounts type import into a
  temporary CowOffer source copy; PHPUnit fails as required (exit 1).
- Final local `composer quality`: **PASS** — strict PSR-4, Composer validation,
  dependency audit (no advisories), PHP-CS-Fixer, PHPStan max (0 errors),
  Deptrac (0 violations, 0 uncovered) and **PHPUnit 73 tests/644 assertions**.
- Live PHP HTTP/CLI acceptance: **PASS** including fresh/idempotent migrations,
  admin CLI, user/admin journeys, HTTP 405 and deliberately failed PDO startup.
- GitHub Actions **PASS** at application commit
  `2891c7729660ac15b31e38b1a6d2e6806f826f81`, run
  `37720344044`: PHP **8.3 / 8.4 / 8.5** each completed successfully,
  including `Run quality suite` and `Live HTTP and CLI acceptance`.
  See https://github.com/shomsy/fast-corporation-php-task/actions/runs/37720344044.
  Verify the latest `main` workflow again after this evidence-only update.
- Internet-facing deployment is *outside* the interview demo's accepted scope.
  It requires real HTTPS, `SESSION_COOKIE_SECURE=1`, reverse-proxy/edge login
  rate limiting, request-size bounds and operational log routing. These are
  explicit residual deployment risks, not claimed PASS.
- Interview-task scope satisfies acceptance; exact-final-SHA CI and clean/synced
  `main` must still be verified after the documentation-only commit.
  The public-internet edge controls above are **not implemented or validated**,
  and no unconditional public-deployment readiness is claimed.

## Goal

Deliver a small, secure, reviewer-friendly Vanilla PHP application that satisfies the task exactly and demonstrates senior engineering judgment without speculative architecture.

## Accepted ownership model

One deployable modular monolith with synchronous in-process collaboration.

### Accounts
Owns account identity, credentials, roles, authenticated identity/session meaning and access policy. Behaviors: Register, Login, Logout, CreateAdmin.

### ActivityTracking
Owns activity vocabulary, append-only activity history, Statistics filtering semantics and Reports metrics. Behaviors: record activity, Statistics, Reports.

### CowOffer
Owns Page A and Buy Cow behavior. No durable purchase state is invented. Behaviors: ViewPageA, BuyCow.

### Downloads
Owns Page B and fixed downloadable-artifact delivery. Behaviors: ViewPageB, DownloadFile.

Dependency direction:

```text
Accounts  ---------+
CowOffer  ---------+--> ActivityTracking
Downloads ---------+

Runtime --------X--------> business owners
Infrastructure -X--------> business owners
Bootstrap ----------------> all (wiring only)
```

A local dependency, public contract or transaction is a cost to evaluate, not evidence that distinct owners should be merged (Governance 1.7.1 P14/CMP-2).

## Physical direction

```text
src/
├── Accounts/
├── ActivityTracking/
├── CowOffer/
└── Downloads/

runtime/
└── Http/

infrastructure/
├── Database/
├── Session/
├── Templating/
└── Time/
```

Each business owner gains business-named behavior slices and one `module.php` public-surface manifest when code exists. No `Owner/`, `Domain/`, `Features/`, `Capabilities/` or `Implementations/` directory is created merely to mirror governance vocabulary.

## Data model

`accounts`: id, username, password_hash, role, created_at. Username is unique; public registration always creates role `user`; plaintext passwords are never stored.

`activity_events`: id, actor_id, actor_label, action, target, occurred_at. Actions: registration, login, logout, view-page, button-click. Targets: page-a, page-b, buy-cow, download, or null where no target is meaningful.

ActivityTracking stores a historical actor-label snapshot and does not read private Accounts storage. No `cow_purchases` table exists without a durable-purchase requirement.

## Security baseline

Use password_hash/password_verify, session-id regeneration after login, server-side authorization, safe logout invalidation, HttpOnly/SameSite cookies, Secure under HTTPS, POST+CSRF for state-changing browser actions, prepared PDO statements, centralized HTML escaping, generic auth failures, a fixed server-side download path, and no credentials/session ids in logs.

## Tracking semantics

Activity tracking is product behavior, not best-effort telemetry. Record successful registration/login, Page A/B views, Buy Cow click and Download click. Use the smallest local transaction that keeps accepted actions consistent. A local transaction spanning owner tables does not merge ownership.

Logout is security-sensitive: activity recording may fail, but session destruction must still complete.

## Statistics and Reports

Statistics is admin-only with date/user/action filters. Reports is admin-only as a documented assumption. One aggregate dataset feeds graph and table with daily Page A views, Page B views, Buy Cow clicks and Download clicks. No projection table until measured evidence requires one.

## Delivery loop

The project is executed as a sequence of small, independently reviewable delivery units. Do not accumulate several phases into one large commit.

### Temporary delivery validation order — operator instruction 2026-10-08

**Scope:** This Fast Corporation interview task, until final submission acceptance.
**Reason:** Prioritize complete business behavior before running repeated project-wide static and style checks.
**Authorizing instruction:** The accountable human explicitly changed the execution order in this conversation. This temporarily supersedes the earlier per-phase `composer quality` gate and its matching delivery-loop wording. It does not weaken any quality rule, disable any tool, waive security or permit calling the project submission-ready without final proof.
**Expiry:** After the final project-wide validation and submission readiness checkpoint, this sequencing exception expires.

For each coherent implementation scope:

1. Inspect the current repository state and do not overwrite prior work.
2. **Write the functional code first**, including only the smallest justified architecture.
3. **Then write and run the unit tests for that scope**, fixing actual failures.
4. **Then run the entire PHPUnit suite**, fixing regressions before continuing.
5. Record factual test results in the workstream; continue with the next scope.

Only **after the complete task's implementation and functional tests**:

6. Run PHPStan max, Deptrac, PHP-CS-Fixer, Composer validation/security audit and remaining full quality gates.
7. Fix every issue at its actual owner, without suppressions or weakening architecture constraints; rerun all quality gates until green.
8. Run security, clean-install, user/admin browser journeys and download acceptance.
9. Review the final diff, update documentation/evidence, commit coherent changes and push `origin/main`; verify the exact commit's GitHub Actions result.
10. Declare `SUBMISSION READY` only after all final acceptance conditions pass.

Do not make unverified intermediate commits merely to meet a clock-driven cadence. The earlier per-phase commit cadence is deferred during this temporary sequencing exception to keep `main` deployable.

Commit cadence:

- one finished phase may be one commit when it remains cohesive;
- a phase may use several commits when a foundation/behavior/security fix is independently valuable;
- avoid micro-commits for formatter-only churn unless they repair a pushed failure;
- never leave an intentionally broken commit on `main`;
- keep the working tree clean at every pushed checkpoint.

Remote `main` is the delivery source of truth. Each loop iteration resumes from the latest synchronized clean checkpoint rather than relying on uncommitted local memory.

## Acceptance evidence — local final gates (2026-10-08)

- Functional task coverage: registration/login/logout; authenticated Page A, Buy a cow -> `thankYou`; authenticated Page B -> fixed `assets/sample.exe`; admin Statistics date/user/action filters; admin Reports SVG and table from one UTC daily aggregate.
- Owner boundaries: Accounts, ActivityTracking, CowOffer, Downloads. Technical HTTP transport mapping belongs to four focused adapters under `bootstrap/Http/`; no business owner imports Runtime HTTP types.
- Security: password hashing, generic invalid login, regeneration on sign-in, CSRF for POST actions, server-side authorization, escaped HTML, prepared queries, session destruction on logout, fixed download path, failure-visible activity logging and transaction rollback.
- Scoped HTTP tests: **PASS, 12 tests / 77 assertions**.
- Complete PHPUnit: **PASS, 50 tests / 400 assertions** (PHP 8.5.5). Superseded Accounts HTTP adapter test was replaced by actual composition-boundary Flow coverage.
- `composer quality`: **PASS**. Composer validation PASS, dependency security audit PASS (no advisories), PHP-CS-Fixer PASS, PHPStan max PASS (0 errors), Deptrac PASS (0 violations, 0 skipped), PHPUnit PASS.
- Isolated live HTTP smoke: **PASS** for fresh and idempotent SQLite migrations, CLI admin bootstrap, complete user registration/login/cow/download/logout and admin Statistics/filters/Reports using actual local HTTP server.
- Release gate: commit `f319e57fd02e8beebeec57a5f32adb2269039668` is pushed to `origin/main`; GitHub Actions run `37710008109` completed successfully on PHP 8.3, 8.4 and 8.5; clean working tree verified.

## Standards & Maintainability — vNext

A behavior-preserving L1 hardening after the original accepted release added PSR-4
autoloading for `InterviewTask\Bootstrap\`, focused business-named HTTP adapters
under `bootstrap/Http/` in place of the old `WebPages` class, and the explicit
`@PER-CS3x0` preset without formatting churn. This does not add any owner or
claim full PER-CS 3.1 compliance. See `docs/PHP-STANDARDS-VNEXT.md` for
the implementation decisions, validation criteria, and evidence.

## PSR-20 and strict PSR-4 (2026-10-08)

Adopted PHP-FIG `Psr\Clock\ClockInterface` via the direct `psr/clock:^1.0`
dependency. Removed the duplicate local `Infrastructure\Time\Clock` interface;
the existing `SystemClock` remains a UTC implementation and injected test clocks
continue to make time deterministic. The smallest compatibility change updates
type-hints in Accounts, ActivityTracking, bootstrap and tests without adding layers.

`composer quality` now invokes `check:psr4` with optimized strict PSR namespace
mapping and duplicate-class detection.
Deptrac models the exact external `Psr\Clock\ClockInterface` contract with a
`PsrClock` layer, explicitly whitelists only its consumers, and fails on
uncovered architecture edges. Scoped tests and the full PHPUnit suite
were run before the final static/quality acceptance gates.
See `docs/PHP-STANDARDS-VNEXT.md` for exact coverage and decision rationale.

## Delivery phases

- Phase 0 — Planning / architecture — DONE (`113e2d4`)
- Phase 1 — Tooling / quality / CI — DONE (`59e327f`)
- Architecture alignment — Governance 1.7.1 — DONE (`2c3b2e5`)
- Phase 2 — Runtime + persistence — DONE
- Phase 3 — Accounts and browser authentication — DONE locally
- Phase 4 — ActivityTracking with registration/login/logout integration — DONE locally
- Phase 5 — CowOffer + Downloads — DONE locally
- Phase 6 — Statistics — DONE locally
- Phase 7 — Reports — DONE locally
- Phase 8 — Security and failure hardening — DONE locally, quality PASS
- Phase 9 — Acceptance and reviewer experience — DONE (live HTTP smoke, full quality suite, synchronized Git, and remote PHP 8.3/8.4/8.5 CI PASS)

### Alignment exit

Governance snapshot, ADRs, owner skeleton, namespaces and architecture fitness config agree and `composer quality` is green.

### Phase 2

Neutral HTTP runtime; neutral driven database/session/template/time mechanisms; SQLite connection and transaction; migration runner; deterministic test database; owner-local initial migrations.

### Phase 3

Registration, login, logout, secure sessions, access policy, admin bootstrap and denied-path tests.

### Phase 4

Activity vocabulary, append-only log, actor snapshot, recording surface, account-flow integration and transaction/failure tests.

### Phase 5

Page A/B view tracking, Buy Cow `thankYou` transition, fixed-file download and click tracking.

### Phase 6

Admin-only Statistics event table with date/user/action filters and deterministic ordering.

### Phase 7

Daily aggregate query and one dataset for the graph/table four required metrics.

### Phase 8

CSRF, injection, escaping, authorization bypass, session lifecycle, missing/unreadable download and activity-write failure tests.

### Phase 9

Complete user/admin journeys, clean-install rehearsal, final README and full quality/architecture acceptance.

## Definition of done

Functional requirements pass; authorization is server-enforced; requested events appear in Statistics; report metrics are correct; architecture tests preserve accepted ownership; PHPUnit/PHPStan/style/architecture/security checks are green; clean reviewer setup works.

## REVISIT triggers

- CowOffer gains durable purchase/payment/inventory lifecycle -> re-evaluate its state and boundaries.
- Downloads gains catalog/version/per-user authorization/lifecycle -> re-evaluate its state and boundaries.
- Statistics/Reports miss an accepted latency target on representative measured data -> consider index/derived aggregate.
- Activity correctness can no longer be maintained by local synchronous writes -> reconsider delivery mechanism.
- Owner-local technical realizations become a coherent body whose grouping materially improves navigation/information hiding/enforcement -> consider `Implementations/`.
- HTTP edge becomes materially complex -> evaluate an established framework.

Until a trigger fires, do not build for it.

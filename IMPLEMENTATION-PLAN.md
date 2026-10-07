# Implementation Plan

Status: Active
Project: Fast Corporation PHP Developer technical task
Architecture level: L3 greenfield, applied proportionally
Governance: Architecture Governance 1.7.1
Repository language: English only

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

## Delivery phases

- Phase 0 — Planning / architecture — DONE (`113e2d4`)
- Phase 1 — Tooling / quality / CI — DONE (`59e327f`)
- Architecture alignment — Governance 1.7.1 — CURRENT
- Phase 2 — Runtime + persistence
- Phase 3 — Accounts
- Phase 4 — ActivityTracking
- Phase 5 — CowOffer + Downloads
- Phase 6 — Statistics
- Phase 7 — Reports
- Phase 8 — Security + failure hardening
- Phase 9 — Acceptance + reviewer experience

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

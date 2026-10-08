# ADR-0002 — System Architecture

Status: Accepted  
Level: L3  
Date: 2026-10-07  
Decider: Project owner / accountable human  
Governance: 1.7.1

## Facts

- Registration, login, logout, Page A + Buy a cow, Page B + Download, Statistics and Reports are required.
- Activity actions are registration, login, logout, view-page and button-click.
- Statistics filter by date/user/action; Reports aggregate four daily metrics.
- The implementation is exercised by PHPUnit and live HTTP smoke; subsequent Compass review identified
  owner-public-surface and pagination boundary issues and prompted targeted hardening.

## Inferences

- Account/credential/access knowledge changes independently from activity taxonomy/history/reporting knowledge.
- Cow Offer behavior changes independently from download-delivery behavior and from identity/activity concerns.
- Statistics and Reports are derived ActivityTracking behaviors.
- Local cross-owner calls are cheaper than collapsing distinct knowledge into one owner.

## Assumptions

SQLite is acceptable; Reports are admin-only; Buy Cow is not persistent across reload; only successful actions are
tracked; registration does not log in; timestamps are UTC; the downloadable executable is a fixed harmless project
artifact.

## Decision

One deployable modular monolith with four business owners:

1. **Accounts** — accounts, credentials, roles, authenticated identity/session meaning and access policy.
2. **ActivityTracking** — event vocabulary, append-only activity history, Statistics semantics and Reports metrics.
3. **CowOffer** — Page A and Buy Cow behavior; no invented purchase state.
4. **Downloads** — Page B and fixed artifact delivery behavior.

Dependency direction is `Accounts -> ActivityTracking`, `CowOffer -> ActivityTracking`, `Downloads -> ActivityTracking`.
ActivityTracking has no business-owner dependency and stores actor id plus a historical actor-label snapshot.

`runtime/` contains neutral driving execution only. `infrastructure/` contains neutral driven resource mechanisms.
Business schema remains owner-local. `module.php` is each owner's public surface. `AccountLifecycle` alone exports
tracked authentication mutations; `CurrentAccount` provides read-only session lookup, while lower-level
Register/Login/Logout/SessionIdentity remain internal. `tests/Architecture/PublicSurfaceTest.php` uses a PHP AST and
name resolution to check static class references across owners and HTTP adapters.
`tests/Architecture/DataOwnershipTest.php` catches direct foreign-table references in literal SQL and migrations. The
explicit composition root is exempt. Dynamic class names, reflection and computed SQL identifiers remain review-owned
rather than machine-guaranteed.

Do not create `Owner/`, `Domain/`, `Features/`, `Capabilities/` or `Implementations/` merely to mirror governance
vocabulary. No CQRS, event sourcing, broker, queue, cache, ORM, microservices or DI container without a concrete signal.

## Why not UserActivity

Register/Login/Logout touching identity and tracking is a Flow fact, not proof of one authority. Password/session/role
changes belong to Accounts; event taxonomy/retention/filter/report changes belong to ActivityTracking; Page A behavior
belongs to CowOffer; artifact delivery belongs to Downloads. Governance 1.7.1 P14/CMP-2 forbids merging those distinct
reasons for change merely to remove a local dependency/contract/transaction.

## Alternatives

- `UserActivity`: rejected; semantic mega-owner.
- `Accounts + ActivityTracking`: rejected; would make tracking own Page A/B product behavior.
- `Accounts + ActivityTracking + Storefront`: plausible, but CowOffer and Downloads share no authoritative knowledge.
- Selected four-owner model: smallest honest ownership map.
- Distributed/event-driven: rejected; no evidence.

## Validation before PASS

Change/Invariant/Knowledge-hiding tests; owner-surface tests; authorization/session tests; DB constraints/table
ownership; Deptrac; clean install; PHPUnit/PHPStan/style/CI.

GOVERNANCE: PILOT — dependency graph, AST export contract, static SQL table ownership checks and behavior evidence
exist; dynamic sources and global operational validation remain outside the automation. This does not claim
whole-Governance VALIDATED status. CowOffer owner remains REVISIT if product meaning changes.

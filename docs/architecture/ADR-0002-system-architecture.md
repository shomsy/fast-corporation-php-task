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
- No business implementation exists yet.

## Inferences

- Account/credential/access knowledge changes independently from activity taxonomy/history/reporting knowledge.
- Cow Offer behavior changes independently from download-delivery behavior and from identity/activity concerns.
- Statistics and Reports are derived ActivityTracking behaviors.
- Local cross-owner calls are cheaper than collapsing distinct knowledge into one owner.

## Assumptions

SQLite is acceptable; Reports are admin-only; Buy Cow is not persistent across reload; only successful actions are tracked; registration does not log in; timestamps are UTC; the downloadable executable is a fixed harmless project artifact.

## Decision

One deployable modular monolith with four business owners:

1. **Accounts** — accounts, credentials, roles, authenticated identity/session meaning and access policy.
2. **ActivityTracking** — event vocabulary, append-only activity history, Statistics semantics and Reports metrics.
3. **CowOffer** — Page A and Buy Cow behavior; no invented purchase state.
4. **Downloads** — Page B and fixed artifact delivery behavior.

Dependency direction is `Accounts -> ActivityTracking`, `CowOffer -> ActivityTracking`, `Downloads -> ActivityTracking`. ActivityTracking has no business-owner dependency and stores actor id plus a historical actor-label snapshot.

`runtime/` contains neutral driving execution only. `infrastructure/` contains neutral driven resource mechanisms. Business schema remains owner-local. `module.php` becomes each owner's authoritative machine-checkable public surface when behavior code exists.

Do not create `Owner/`, `Domain/`, `Features/`, `Capabilities/` or `Implementations/` merely to mirror governance vocabulary. No CQRS, event sourcing, broker, queue, cache, ORM, microservices or DI container without a concrete signal.

## Why not UserActivity

Register/Login/Logout touching identity and tracking is a Flow fact, not proof of one authority. Password/session/role changes belong to Accounts; event taxonomy/retention/filter/report changes belong to ActivityTracking; Page A behavior belongs to CowOffer; artifact delivery belongs to Downloads. Governance 1.7.1 P14/CMP-2 forbids merging those distinct reasons for change merely to remove a local dependency/contract/transaction.

## Alternatives

- `UserActivity`: rejected; semantic mega-owner.
- `Accounts + ActivityTracking`: rejected; would make tracking own Page A/B product behavior.
- `Accounts + ActivityTracking + Storefront`: plausible, but CowOffer and Downloads share no authoritative knowledge.
- Selected four-owner model: smallest honest ownership map.
- Distributed/event-driven: rejected; no evidence.

## Validation before PASS

Change/Invariant/Knowledge-hiding tests; owner-surface tests; authorization/session tests; DB constraints/table ownership; Deptrac; clean install; PHPUnit/PHPStan/style/CI.

GOVERNANCE: NOT VERIFIED — accepted architecture, no implementation evidence yet.

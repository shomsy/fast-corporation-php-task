# ADR-0001 — System Architecture

Status: Proposed  
Level: L3  
Date: 2026-10-06  
Decider: accountable human not yet formally designated  
Rules cited: BEH-1, SCR-1, CMP-1, CMP-2, CMP-4, BND-1, KNW-1, DEP-1, DST-3, CPX-1, CPX-5, DOM-2, STR-5, STR-7

## FACTS

- The task requires registration, login, logout, two tracked interaction pages, Statistics and Reports.
- Required activity actions are registration, login, logout, view-page and button-click.
- Statistics require date/user/action filtering.
- Reports require daily Page A/Page B view counts and Buy Cow/Download click counts.
- The recruiter recommended Vanilla PHP and did not mandate a PHP version.
- Architecture Governance 1.3.2 governs structural work.

## INFERENCES

- Accounts/security knowledge changes for different reasons than activity/reporting knowledge.
- Page A/Page B do not currently contain enough independent authoritative state or lifecycle to justify separate owners.
- Statistics and Reports can be derived directly from the authoritative append-only activity log.

Verification: implement representative changes and validate ownership/dependency tests.

## ASSUMPTIONS

- SQLite is acceptable as the default reviewer database.
- Reports are admin-only.
- Buy-a-cow state is not persistent across reloads.
- Only successful actions are tracked.
- Registration does not automatically authenticate.
- Timestamps are stored in UTC.

## UNKNOWNS

- Exact reviewer runtime/environment.
- Whether Reports are intended for admin only or all authenticated users.
- Whether the reviewer expects persistent Buy Cow state despite the wording not requiring it.

## CONSTRAINTS

See `constraints.md`.

## DECISIONS

- Use one in-process deployable.
- Use two business owners: Accounts and Activity.
- Organize behavior as business-named slices inside each owner.
- Keep HTTP/database/clock mechanics at the technical edge.
- Use PDO with SQLite by default.
- Use an append-only activity table as the authoritative tracked-activity record.
- Derive Statistics and Reports from that log.
- Use plain orchestration/transaction scripts.
- Use server-side sessions and server-side authorization.
- Use manual composition; no DI container.
- Do not introduce CQRS, event sourcing, queues, caches, repository interfaces or microservices.

## ALTERNATIVES

1. **One business owner.** Lower structural cost but mixes credential/security authority with activity/reporting knowledge.
2. **Selected: Accounts + Activity.** Smallest structure that protects the real identity/security and activity knowledge differences.
3. **Accounts + Activity + Storefront.** Rejected for now: Page A/B have no demonstrated independent authoritative state/lifecycle and the extra owner would create a contract before the pressure exists.

## TRADE-OFFS

- One deployable means one release train.
- Accounts intentionally depends on Activity's small recording contract for accepted account actions.
- SQLite optimizes reviewer setup rather than production database operations.
- A small custom HTTP edge has maintenance cost and must remain deliberately small.

## VALIDATION

- owner-surface and journey tests;
- authorization and session abuse-path tests;
- database constraint tests;
- Deptrac dependency rules;
- table-ownership check;
- clean-install rehearsal;
- PHPUnit/PHPStan/style checks.

## REVISIT

See `IMPLEMENTATION-PLAN.md` section **REVISIT triggers**.

## OVERRIDES

None.

## Governance status

NOT VERIFIED — design only. Application code, tests and architecture checks do not exist yet.

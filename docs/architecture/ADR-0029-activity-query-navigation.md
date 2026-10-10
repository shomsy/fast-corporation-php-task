# ADR-0029: Group private activity query mechanics

Status: Accepted, 2026-10-10, for the user-authorized source navigation refactor.

## Context

The ActivityTracking owner had its public behaviors (`ActivityLog`, `Statistics`, `DailyReports`) and public action/target vocabulary beside three private query-support types: `ActivityFilters`, `UtcDate` and `ActivityCursor`. The latter are used to validate read criteria, normalize report dates and encode a session/filter-bound keyset cursor. Their shared role is query navigation, while SQL execution already belongs to `Persistence/`.

Other owners do not have the same navigation pressure. `CowOffer` has one behavior class and its manifest. `Downloads` has two public behavior/error types and one private `Storage/ReadArtifact`. Creating new folders there would add hierarchy without grouping a responsibility family.

## Decision

Move the three private types under `ActivityTracking/Query/` and use the matching `InterviewTask\ActivityTracking\Query` namespace. Keep the public activity behavior classes, canonical action and target types, and module manifest at the owner root. Keep SQL realizations in `Persistence/`. Leave the compact CowOffer and Downloads layouts as they are.

## Consequences and invariants

- The owner still exports the same six public FQNs; this change does not alter its manifest or cross-owner API.
- HTTP JSON, filter rules, UTC semantics, cursor bytes, signing context, SQL, ordering and pagination behavior remain unchanged.
- The PHP FQNs of three owner-private implementation types change. They have no published consumers; first-party tests and persistence callers move with them.
- PSR-4 strict validation, the ActivityTracking and architecture suites, PHPStan, Deptrac and end-to-end release checks prove the move.
- No class, interface, runtime indirection, owner or dependency layer is added.

## Alternatives

- Keep the three private types at the root: no churn, but query mechanics remain mixed among the owner entry points.
- Create Domain/Application/Infrastructure folders: they cut across the concrete behavior and storage ownership already visible in the owner.
- Group CowOffer or Downloads merely for symmetry: rejected because one-file wrappers do not improve navigation.

The source tree should mirror meaningful responsibility families, not force every owner into identical directory shapes.

# ADR-0025: Owner-owned activity history criteria and private SQLite read model

**Status:** Accepted for the scoped ActivityTracking refactor; 2026-10-10.

## Problem

`Statistics.php` previously mixed three independent reasons for change: canonical filter validation, SQLite SQL queries/plan hints, and HMAC-signed cursor navigation. The first two belong in different owner-private responsibilities. An uncommitted parallel workstream has moved `encodeCursor()` and imported `JsonException`; its work must remain unmodified and out of our commits.

## Decision

Keep `Statistics` as the exported history/pagination behavior with unchanged HTTP-facing filters, bounded page size, opaque session-bound v1 cursor encoding, navigation direction and public return values.

Create the following concrete, private owner classes:

- `ActivityFilters`: validates date/action, trims exact-match actor label, stores typed canonical criteria. Does not contain SQL or PDO.
- `UtcDate`: immutable calendar value constructed only after validating exact `YYYY-MM-DD` syntax **and** actual Gregorian date; shared by `Statistics` and `DailyReports`. Existing error messages remain owned by the calling API.
- `Persistence/ReadActivityHistory`: performs original offset/keyset SELECTs, parameter binding, date-index hint, order/tie handling, page+1 lookahead, and the reversal after backward traversal. No filter policy or signing decisions.

Production composition creates the concrete SQLite reader and hands it to `Statistics`; no single-implementation interface, generic repository or DI framework was introduced. `Statistics` remains the public module contract; the new reader/filter/date types are not exported.

**Explicit compatibility choices:** preserve original cursor fingerprint `hash('sha256', json_encode([$date??'', trim($user), $action??'']))` (including existing null/empty semantics), signed body JSON field order `v,t,id,d,f`, URL-safe base64, HMAC-SHA256 and session secret, before/after keyset comparisons, and date-selected SQLite index `idx_activity_day`. Keep request parameters and API responses unchanged.

A new characterization test independently rebuilds the **old signed cursor bytes** and proves they match and traverse the post-refactor implementation. Existing tests cover identical timestamps, concurrent appended events, backwards traversal, tampering, filter/key reuse and report dates.

## Evidence and limits

- Original John Ousterhout, *A Philosophy of Software Design*, 2nd ed., chapter 4, **physical PDF pp. 31–34**, `/home/shomsy/Documents/Books/architecture/A Philosophy of Software Design — Second Edition — John Ousterhout.pdf`: simple interface hiding implementation knowledge; public signatures and documented semantics are part of the interface.
- Original Martin Fowler, *Refactoring: Improving the Design of Existing Code*, **physical PDF pp. 64–66**, `/home/shomsy/Documents/Books/Software/Backend/Refactoring Improving the Design of Existing Code.pdf`: choose meaningful extractions with behavior retained; resist excessive delegation and parameter lists.
- Compass Engineering: `how-to-refactor-safely`, `how-to-model-business-invariants-with-value-objects`, `how-to-decide-where-state-and-knowledge-should-live`. Architecture Governance 1.7.1 STR-17–19.
- Real SQLite-backed activity tests (including v1 signed cursor), PHPStan, Deptrac, MoI conventions, frontend and browser must pass after any follow-up modification.

## Rejected/left open

- **No cursor rewrite or new token version:** Claude's optional codec remains for a separate stage; do not invalidate client cursors as a side effect of LLD work. No proof exists that a separate `ActivityCursor` class would yet simplify callers more than the current private two methods; decide after concurrent edit reconciliation.
- **No mandatory `Actor` parameter object:** actor validation already has a single owner (`ActivityLog`); wrapping its public primitive API without reducing duplication would be a ceremonial type.
- **No schema/trigger migration:** owner semantics and backup/restore/retention constraints must be decided separately.
- The legacy PhpStorm XML export is **pre-refactor**; it cannot prove current error/smell counts. A fresh full-project export is needed for objective post-change scoring.
- Current code still has uncommitted edits from a different workstream in `Statistics.php`. Stage our change **only** with a HEAD-relative patch, and preserve that other workstream's import/encode method movement unchanged.

## Verification target

`composer quality` green including PHPStan, Deptrac and PHPUnit; Vanilla JS 29/29; Playwright and live HTTP/CSRF; 1:1 cursor token behavior under older and new events; SQLite WAL recovery and abuse guard unaffected. This change is local architecture refactoring only, not an authorization for public production deployment.

# ADR-0027 — Observe session establishment before accepting login

Status: Accepted under the project owner's autonomous Accounts/LLD refactoring instruction
Level: L2 — consequential session failure/acceptance contract
Date: 2026-10-10
Decider: Project owner / accountable human
Rules cited: P3, P5, CPX, KNW, INV, DEP; PHP-L04, PHP-L08, PHP-L10, PHP-L13, PHP-D02, PHP-D06, PHP-D07

## Facts and failure pressure

- `SessionIdentity::signIn` published the account ID before reading its clock. A clock failure could switch an
  existing authenticated session to another account without accepted login activity. The regression preserves the
  complete prior session, identifier, CSRF token and identity when this prerequisite fails.
- `AccountLifecycle` and `Transaction` independently implemented the same secondary diagnostic policy with hidden
  static I/O: attempt reporting without replacing the primary error. Their revocation/rollback policies are distinct.
- PHP native session warnings contain storage paths and potentially bearer IDs. `display_errors=0` does not prevent
  logging. A custom error handler scoped to the native operation translates these warnings to a fixed message.
- Native session state was written only at request shutdown, after successful login activity/HTTP response. Actual
  native file storage under an isolated zero-byte file-size limit accepted login while leaving an empty session file;
  `session_write_close()` returned true despite failed-write warnings. This is a correctness/availability defect.

## Decision and ownership

1. Read the clock before session mutation. The Accounts identity owner establishes, explicitly persists and reopens
   the rotated session before returning an identity to the tracked login lifecycle. Session establishment failure
   attempts revocation and preserves its exact original exception.
2. `PhpSession` owns bounded native warning translation and write/close/reopen mechanics. Destruction reopens a
   retained inactive session ID so failed persistence or subsequent audit failure can revoke stored state. Even a
   failed reopen clears in-memory identity and attempts cookie expiry.
3. `CleanupFailures` owns only best-effort secondary diagnostics. Its required closure is selected in composition;
   production retains the existing allowlisted `SecurityEvents` format. Accounts and transaction objects still
   decide when and how to revoke/roll back. No generic compensation execution is extracted.
4. `ActivityFilters` rejects malformed UTF-8 actor labels at the existing validation boundary. Valid Unicode
   historical labels remain accepted; the existing API maps invalid input to 422 rather than a JSON-driven 500.

No new business owner, data owner, dependency direction, public HTTP route or schema is introduced. The existing
Business -> Infrastructure boundary covers diagnostics. Boundary and recurring-cost entries live in
`boundaries.md` and `complexity.md`; hard constraints remain in `constraints.md` and `TASK.md`.

## Alternatives and trade-offs

- Do less: retain shutdown-only writes and duplicate static reporting. Rejected by observed false acceptance and
  session-ID leakage; error policy and diagnostic I/O would remain difficult to test independently.
- Wrap every collaborator behind an interface or introduce a transaction/session compensation framework. Rejected:
  there is one concrete technical mechanism and three existing cleanup owners, with no supported algorithm variants.
- Flush all HTTP requests through a new global coordinator. Rejected: authentication needs one observed write before
  audit; a system-wide transaction protocol would add unrelated behavior and complexity.

The cost is one extra write/close/reopen at successful authentication and explicit diagnostic constructor wiring.
Business callable names and named parameters remain unchanged. Internal construction requires `CleanupFailures`;
all composition roots and test fixtures are updated together. Native PHP sessions, CSRF rotation, role reload,
SQLite atomic registration, signed v1 cursor bytes, binary attachment behavior and frontend routes are retained.

## Limits, assumptions and revisit

SQLite, native session storage and HTTP delivery are separate resources. An observed session write does not prove
fsync durability, eventual browser receipt, or atomicity with SQLite. Revocation/rollback remains best effort under
storage failure, as in ADR-0011. Reopening retains the active-session contract and creates a normal later shutdown
write/timestamp-update cycle; this change does not guarantee all future writes succeed.

Assumption: the trusted composition root selects the native file session mechanism and the redacted diagnostic sink.
Unknown: real production filesystem durability, capacity and log transport; public-production acceptance remains
outside this local interview refactoring. Revisit for a proven need for cross-resource atomicity, a new session
backend, divergent cleanup error policy, or measured authentication I/O cost that violates a real budget.
Overrides: n/a — no governance exception required.

## Sources and validation

Original PDFs were read directly, without using the book index or cached summaries:

- Ousterhout, *A Philosophy of Software Design*, second edition: physical pp. 41–42 (shared hidden knowledge), 87–88
  (partial initialization and recovery failures), 119–120 (effects and exception contracts).
- Fowler, *Refactoring: Improving the Design of Existing Code*: physical pp. 53, 55, 57–58 (preserve behavior and
  published interfaces; avoid speculative flexibility).
- Compass implementation guides on error handling, resource lifetime and safe refactoring; current canonical PHP
  LLD, contracts/failures and runtime guides; original Jeffallan PHP, architecture, security and test review guidance.
- [PHP error-handler semantics](https://www.php.net/manual/en/function.set-error-handler.php) and
  [native session close](https://www.php.net/manual/en/function.session-write-close.php); actual PHP 8.5 behavior is
  established by failure injection rather than return-type assumptions.

Change/invariant/knowledge-hiding review: session persistence stays with its adapter; acceptance stays with Accounts;
diagnostic destination changes only at composition. Credential/audit/cursor/module invariants are regression-tested.
Focused tests cover clock failure, native write warnings, durable close/reopen/destruction, login audit failure,
logout lookup failure, rollback failure, tertiary diagnostic failure, UTF-8 validation and v1 compatibility.

## Executed local acceptance

- `composer quality`: PASS — **175 PHPUnit tests / 6,877 assertions**, PHPStan max **0 errors**, Deptrac
  **0 violations / 0 uncovered / 0 warnings**, Composer strict validation/PSR-4/dependency audit, PHPDoc structural
  coverage and MOI syntax checks PASS. No package upgrade or schema change.
- Vanilla JavaScript frontend: **29/29 PASS**. Playwright browser acceptance PASS (authentication, roles, accepted
  activity, native binary, filtered statistics and report chart).
- Real HTTP acceptance: Accounts JSON, user slices, live SPA/CSRF and sole-frontend route/cutover checks PASS.
- SQLite WAL online backup/restore, failed-capacity rollback, snapshot integrity and file permissions PASS.
  Isolated Nginx ingress gate PASS (429/Retry-After across upstreams, spoofed forwarding ignored, redacted logs).
- `tests/Flow/session_storage_failure.php` with actual default file sessions in a disposable `fsize=0` container:
  failure before API success, generic local response 500, **0 accepted login events / 0 escaped native warnings /
  0 remaining session files**, cleared memory. This probe constructs its 500 response locally; real HTTP middleware
  acceptance is established separately by the HTTP tests, not inferred from this probe.
- Native PhpStorm MOI formatter PASS on all **27 intentionally modified PHP files**; global MOI unchanged.
  Native IDE `lint_files` timed out on the first item (`timedOut=true`, `more=true`): **INCONCLUSIVE**, not a claim of
  zero IDE warnings. Automated PHPDoc/static-analysis checks pass independently.
- Independent final specification, maintainability, security and regression review found **no confirmed Critical/High
  findings in the inspected changes**. Clock/audit/lookup/cleanup/diagnostic-failure regressions, UTF-8 behavior and
  byte-exact v1 cursor compatibility pass. Existing 48/48 remediation history and user-owned XML/archive retained.

This is verified local interview acceptance. Production deployment and physical durability qualification are not
claimed; the recorded best-effort cleanup and separate-resource limits remain.

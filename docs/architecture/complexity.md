# Complexity Ledger

Accepted mechanisms for the current pilot:

- Clock seam for deterministic time-dependent behavior and tests.
- Deptrac for objective dependency-boundary checks across the accepted four-owner graph.
- Table-ownership check for Accounts and ActivityTracking authoritative data.

- Explicit session persistence before accepted login (ADR-0027): one extra native write/close/reopen at authentication;
  retains file sessions and their normal request locking. Remove only if a replacement proves equivalent acceptance.
- `CleanupFailures` (ADR-0027): one immutable object and an injected diagnostic closure shared by session establishment,
  account lifecycle and transactions; callers retain their cleanup policy. Revisit if these error policies diverge.

No container, generic compensation framework, additional datastore or deployment boundary is introduced.

- Accounts `Access/` and `Credentials/` groups (ADR-0028): namespaces make existing responsibility families easier
  to navigate without adding objects, state or runtime indirection. Class references change together; module exports,
  named parameters and HTTP/session behavior remain enforced by the existing boundary and behavior tests.

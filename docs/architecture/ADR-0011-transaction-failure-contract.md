# ADR-0011 — Transaction ownership and failure priority

Status: Accepted / C-24 (2026-10-09). Owners: Database and Accounts.

## Invariants

1. `Transaction::run()` owns one connection-local PDO transaction. Calling it inside an active transaction is a programming error; it rejects the call **before running its callback** or rolling back the outer owner's work. Savepoints/nesting are not an earned requirement for this interview workload.
2. Normal success means the operation and its required audit records committed together. On callback failure or commit exception, attempt rollback. If rollback itself throws, keep the **original exception object** and write a redacted diagnostic for the secondary failure. Do not substitute a cleanup exception for the original cause.
3. A thrown `PDO::commit()` may have an **unknown outcome**: the DB may have committed before reporting the error. No automatic retry, duplicate registration or audit replay is safe without a business idempotency key and reconciliation. The outer HTTP boundary returns a generic 500.
4. Successful login requires its accepted event. If audit persistence fails, attempt session destruction; if native destruction also fails, preserve the audit error and record the cleanup error separately. Session state is cleared in memory before native destruction, but persistent cleanup after an OS/session-handler failure is **best effort**, not a proof that every old cookie was invalidated.
5. Logout attempts revocation even when lookup or audit fails. When both audit and session cleanup fail, the original audit/lookup failure has precedence; when only session cleanup fails, propagate that failure. Never claim session revocation succeeded after a storage error.

## Evidence

- `tests/Infrastructure/TransactionFailureTest.php` injects nesting, rollback and commit+rollback failure.
- `tests/Accounts/SessionCompensationTest.php` uses a separate process and a faulting `SessionHandler` to test database-audit failure plus native session-destroy failure.
- Existing `tests/Flow/HttpFlowTest.php` covers account registration rollback, failed login audit cleanup and logout errors. Real HTTP smoke verifies the user-facing error contract.
- DDIA, Chapter 7, motivates separating transaction atomicity, uncertain outcomes and cleanup failures. A visible error alone is not proof of rollback after an uncertain commit.

**Operational rule:** investigate the correlated server diagnostic, inspect the database and session-store state, and reconcile before retrying an action whose commit outcome is unknown. Do not introduce nested transactions or blind retries.

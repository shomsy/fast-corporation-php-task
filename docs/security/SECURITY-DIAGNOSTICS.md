# Security diagnostics contract

Owner: Platform / Security. Canonical task: C-27.

The business activity table records accepted product events only. Authentication,
CSRF and admin denials, SQLite contention and unexpected server errors are
operational diagnostics in PHP's configured error_log output.

Each diagnostic has JSON schema 1, a fixed allowlisted event name, server-generated
24-hex-character request ID and UTC time. On failure, it carries only the
exception class (never exception message, SQL queries, full traces or user input).
No username, password, token, session cookie, request body or arbitrary user
string is recorded.

The same X-Request-ID is returned with denied login, CSRF, admin and unexpected
HTTP 500 responses. This identifier is a search key, not a bearer credential
or delegated authorization. Never trust client-provided IDs as evidence.

## Deployment requirements (not yet proven)

- Route PHP error logs outside public/ to an access-controlled sink, readable
  only by authorized operations/security staff.
- Set an explicit retention and rotation schedule (provisional 14 days), based
  on threat model, legal/privacy needs and operational capacity.
- Alert on repeated authentication/CSRF/admin denial, db.contention and 5xx.
- Prevent unauthorized modification/deletion, monitor loss of logging, and
  bound the log sink's disk usage.
- PHP error_log is not a guaranteed durable audit transaction. No immutable
  or compliance-grade log claim is made.

C-26/C-47 remain responsible for real deployment verification of log transport,
retention, alerts and operations ownership.

# ADR-0007 — Stable administrative activity pagination

Status: Accepted / C-16, 2026-10-09. Owner: ActivityTracking and HTTP adapter.

## Problem and decision

The existing numbered `?page=N` route uses SQLite `LIMIT/OFFSET`. When accepted activity events arrive between page requests, rows shift: a user can see duplicates or miss events. This is not a failure of the `ORDER BY occurred_at DESC,id DESC` tie breaker. The offset itself is unstable under concurrent inserts.

**Default admin HTML navigation now uses keyset cursors** on exactly that tuple: `(occurred_at,id)`. New requests select rows strictly older than the last seen tuple; going backward selects strictly newer tuples in reverse index order, then restores descending presentation. Fetch PAGE_SIZE+1 to establish continuation without a costly count. Equal timestamps are ordered deterministically by increasing/decreasing unique ID as appropriate.

Tokens contain version, UTC tuple, direction and exact filter fingerprint. An HMAC-SHA-256 binds each cursor to a random server-side PHP session secret; the secret is never sent to the browser. Filters must match the originating request; invalid, cross-session, mutated or malformed cursors fail with HTTP 422. The normal admin guard still authorizes **every** request; cursor signatures are not authorization credentials. The signing key is cleared by session revocation.

## Compatibility and limits

Explicit legacy `?page=N` URLs and the existing `Statistics::page()`/ `events()` APIs remain for backwards compatibility and recruiter tests; **legacy numbered pagination remains unstable under concurrent inserts**. The default `/statistics` and generated Next/Previous links use the new keyset mode. A supplied cursor and page together yield 422. Cursor navigation intentionally displays an unnumbered `Activity history` label; no misleading page number is inferred.

A keyset is a stable *position* rather than a database snapshot across multiple HTTP requests. New records with an earlier backdated `occurred_at` may still enter later pages, and removed/edited historical entries affect the traversal. The accepted audit events are append-only via application code, not forensic/tamper-proof; no snapshot isolation is promised across requests. A long-lived session renewal can invalidate its own old links, which yields a safe 422 and allows restarting at `/statistics`.

## Verification

`tests/ActivityTracking/StablePaginationTest.php` covers 115 equal-timestamp events, five interleaved writes, traversal and reverse navigation, cursor signature tampering, session key changes and cross-filter reuse. `tests/Flow/HttpFlowTest.php` exercises actual signed Next URLs, appends another event, verifies 15 remaining distinct rows, rejects filter modification and checks legacy `?page=2` compatibility. Full quality and live HTTP smoke are required before merge.

Principles: deterministic ordering, seek-based continuation, server-side authorization and narrow separation between SQL activity owner and session-owned HTTP signing state. No pagination framework or speculative materialized snapshot.

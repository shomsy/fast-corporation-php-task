# ADR-0026: Owner-private signed cursor codec

**Date:** 2026-10-10
**Status:** Accepted, subject to complete regression acceptance.

## Context

The ActivityTracking owner already separates filter policy (`ActivityFilters`), read-model SQL (`Persistence/ReadActivityHistory`) and page navigation (`Statistics`). However `Statistics` still carried base64url, JSON, HMAC signature, filter fingerprint and untrusted token decoding in two private helpers, mixing presentation of a page with a wire/security mechanism.

Historical v1 cursor tokens may remain in browsers during deployment. An internally cleaner architecture is not an excuse to invalidate them or change public JSON / HTTP semantics.

## Decision

Introduce `ActivityTracking/ActivityCursor.php` as one **owner-private, final readonly object**. At construction it accepts the **current session secret** and validated `ActivityFilters`, rejects an empty secret, and computes the stable SHA-256 filter fingerprint. `encode` produces a v1 token and `decode` verifies its signature, payload shape, filter fingerprint, timestamp syntax, ID and direction. The existing `InvalidArgumentException('Invalid activity cursor.')` remains the public failure for malformed / forged tokens; malformed signed JSON is wrapped with the underlying `JsonException`.

`Statistics` owns the business operation (which page, hasNext, next/previous navigation). It instantiates this concrete private codec for the current call; `ReadActivityHistory` continues to own only SQL and indexed keyset slices.

No new interface or repository: one concrete cryptographic representation and no legitimate second implementation. No public module export of `ActivityCursor`.

## Observable compatibility invariant

Preserve v1 field order `v,t,id,d,f`, JSON encoding, URL-safe base64 without padding, HMAC-SHA256 over the encoded body using the exact session secret, and fingerprint `sha256(json_encode([date-or-empty,trimmed-user-or-empty,action-or-empty]))`. Preserve before/after direction, signed-token length limit, ASCII input grammar and old cursor acceptance. Never expose the secret or private hash to the browser.

## Acceptance and failure cases

Characterization tests must compare **exact old token bytes**, not merely round-tripping the new implementation. Test invalid HMAC, changed session/filter context, valid-MAC malformed JSON, empty filters and real SQLite pagination with equal timestamps and reverse traversal. Run PHPStan, Deptrac, composer quality, frontend, Playwright, live HTTP/CSRF, backup/restore, native MOI formatter and code review.

## Trade-offs

An additional concrete class is justified here because HMAC format, decoding and data validation have a different reason to change than page navigation. `Statistics` still creates a short-lived codec per request to make session/filters explicit; introducing an injectable generic Codec interface without an actual alternative is not earned.

An earlier independent workstream moved the original private `Statistics::encodeCursor` method without changing its semantics. The full local pre-cleanup file was preserved at `/tmp/fc-statistics-before-codec-cleanup-20261010.php.bak` while reconciling it. The duplicate method is removed from the application once tests pass. The temporary backup is intentionally deleted only after full acceptance.

## Source basis

Architecture Governance 1.7.1 STR-17/18/19, LLD `PHP-L01/L04/L13/L17`, Compass `how-to-refactor-safely`, Jeffallan Code Reviewer spec-compliance-before-quality. Original John Ousterhout *A Philosophy of Software Design* (2nd ed., chapter on deep modules and information hiding), and Martin Fowler *Refactoring* (safe behavioral preservation) inform the boundary. These are design guides, not substitutes for tests.

## Verified acceptance

Scoped ActivityTracking suite: **41 tests / 5,956 assertions**. Complete `composer quality`: **157 PHPUnit tests / 6,765 assertions**, PHPStan **0 errors**, Deptrac **0 violations / 0 uncovered**, MOI / PHPDoc / dependency audit **PASS**. Native PhpStorm formatter ran over Statistics, ActivityCursor and ActivityCursorTest. Vanilla JS **29/29**; Playwright, live HTTP/CSRF/SPA-only routes, SQLite WAL backup/restore and ingress abuse protection **PASS**. Byte-level v1 HMAC token regression **PASS**. This evidence is for local acceptance, not live production readiness.

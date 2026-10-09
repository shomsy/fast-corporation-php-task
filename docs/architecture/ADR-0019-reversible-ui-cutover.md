# ADR-0019 — Reversible document route cutover

**Decision:** Accepted for the local interview application, 2026-10-09. **Owner:** HTTP delivery boundary.

**Superseded:** ADR-0022 removes the SSR fallback and makes SPA the only presentation. The remainder of this ADR records the historical rollout, not current runtime behavior.

## Problem

The recruiter-facing URL paths need to transition from SSR document handlers to the already-verified Vanilla JS SPA without duplicating business writes or losing a working rollback. A wholesale template removal would risk unverified clients and is not justified.

## Decision

- `UI_ENTRYPOINT=ssr` is the safe default. The original GET handlers retain their server-side access policy and the existing POST forms remain operational.
- An explicit `UI_ENTRYPOINT=spa` deployment setting switches only the known document GET paths: `/`, `/login`, `/register`, `/page-a`, `/page-b`, `/statistics`, `/reports`. Their 303 redirects target `/app/home`, `/app/login`, `/app/register`, `/app/page-a`, `/app/page-b`, `/app/statistics` and `/app/reports`, respectively.
- Allowlisted statistics and report query keys are encoded into the redirect URL. The authoritative PHP API still validates filters and enforces admin identity. GET redirects do not write audit events; mounting the SPA screen performs its existing explicit activity request.
- Legacy SSR handlers, templates and POST contracts remain in the repository. No schema migration, authentication reimplementation, new credential token or third-party runtime is introduced.
- Unsupported configuration values fail at boot instead of silently choosing a different owner.

## Rollout and rollback

1. Run `composer quality`, `npm run test:frontend`, `python3 tests/Flow/live_smoke.py`, `python3 tests/Flow/route_cutover.py`, and `npm run test:browser` on the candidate release.
2. Start a controlled application deployment with `UI_ENTRYPOINT=spa` after those checks pass. Confirm old-path redirects, anonymous/user/admin roles and audit semantics; do not interpret the UI switch as public-production security approval.
3. To rollback, set `UI_ENTRYPOINT=ssr` in the process environment and reload/restart that application's PHP workers. Confirm SSR GET and existing POST flow against the *same* database. No data rollback, user-session reset or migration is required.
4. Remove SSR templates only after a separate observed rollout and evidence that supported clients no longer depend on the old presentation. The current task does **not** remove them.

## Evidence and limits

The isolated dual-mode HTTP test uses one migrated SQLite database for both modes. The real Chrome test runs with the SPA entrypoint and validates login, registration, Page A, native binary download, admin filters and reports. The preexisting SSR smoke remains a separate regression gate. Public reverse-proxy limits, HTTPS, session host permissions, PHP-FPM capacity and backup objectives remain deployment gates.

**Source:** Jez Humble and David Farley, *Continuous Delivery* (First Edition), Chapter 1, printed pp. 14–16, original physical PDF pp. 48–50 at `/home/shomsy/Documents/Books/Software/management/SDLC/Continuous Delivery — First Edition — Jez Humble, David Farley.pdf`. Only these three physical pages were inspected; they support automated functional and nonfunctional acceptance gates, not unmeasured production assurances.

# C-47 — Final recruiter acceptance and public-production boundary

**Observed on:** 2026-10-09. **Candidate:** `main` at `f83211106a1e56e45b4870c7a0816c23f987e546` (before this acceptance record).
**Authority:** `TASK.md` for requested behavior; `docs/roadmap/CANONICAL-REMEDIATION-PLAN.md` for evidence obligations. This record is a **test-backed release assessment**, not proof of a public deployment or recruiter approval.

## Explicit decisions

- **Recruiter/interview functional acceptance: GO for an isolated demonstration.** The required features and role boundaries were verified by real HTTP, SQLite and Chrome runs. A human recruiter has not yet reviewed or accepted the submission.
- **Public internet production: NO-GO.** Deployment ingress and host-specific reliability/security criteria are not verified. The application must not be represented as publicly production-ready based on local tests.
- **Remote GitHub Actions status: UNKNOWN.** The workflow defines PHP 8.3/8.4/8.5 quality tests and PHP 8.5 Chrome/Node tests. The connected GitHub workflow-run lookup returned no PR-triggered runs for this commit, and the combined commit status lookup returned no status entries; these interfaces do not confirm whether push-triggered Actions ran. CI matrix results outside the observed local PHP 8.5 execution are unverified.

## TASK.md acceptance — isolated real system

| Required scenario | Evidence | Decision |
| --- | --- | --- |
| Registration, sign-in and sign-out | `tests/Flow/live_smoke.py`, `tests/Flow/accounts_api_smoke.py`, `tests/Browser/journeys.mjs`; bad login error, session identity and CSRF lifecycle | PASS |
| Regular user sees Page A; accepted Buy a cow displays `thankYou` | PHP use cases, real user HTTP + Chrome one-view/one-click behavior; transient UI reset is intentional | PASS |
| Page B native executable download | Browser download of `sample.exe`: exactly 1,024 bytes, SHA-256 `31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80`; fixed server path, CSRF and audit | PASS |
| Statistics with UTC date, user and action filters | Authenticated admin HTTP and Chrome navigation, SQLite-backed paginated events and signed keyset; anonymous/user denied | PASS |
| Daily Reports graph and table | Shared server-derived latest 30 active UTC dates, four action series, gap-aware accessible SVG and tabular parity in Chrome | PASS |
| Roles: user versus admin | Real HTTP `401`/`403` and browser restricted views; registration cannot grant admin, trusted CLI provisions it | PASS |
| Accepted actions tracked | Registration, login, logout, Page A/B views, button clicks; audit acceptance/failure contracts verified with real SQLite | PASS |
| Browser navigation / rollback | Real Chrome deep links and redirect aliases; `UI_ENTRYPOINT=spa` plus same-database `UI_ENTRYPOINT=ssr` rollback HTTP acceptance, preserving all POST routes | PASS |

These checks exercise automated user flows; they are not a usability study or an independent penetration test.

## Gate execution on this candidate

| Gate | Observed result |
| --- | --- |
| `composer quality` (PHP 8.5.5) | PASS — 158 PHPUnit tests, 6,737 assertions; PHPStan/Deptrac 0 violations, Composer audit clear |
| `npm run test:frontend` | PASS — 21/21 ES module/component tests |
| `npm run test:browser` | PASS — real installed Chrome/Playwright against disposable PHP+SQLite |
| `python3 tests/Flow/live_smoke.py` | PASS — user/admin flows and intentional safe dependency errors |
| `python3 tests/Flow/route_cutover.py` | PASS — SPA GET ownership and SSR rollback with the same SQLite database |
| `python3 tests/Flow/sqlite_backup_restore.py` | PASS — SQLite WAL snapshot, restore, SQLITE_FULL injection, directory/file protection and no overwrite; 25 activity rows and four migrations restored |
| `python3 tools/check_execution_ledger.py` | PASS — 48/48 uniquely tracked and locally closed; public-production gates remain NO-GO |
| `python3 tests/Flow/auth_ingress_rate_limit.py` | PASS — pinned Docker Nginx, two mock upstreams, mixed-auth POST 429, `Retry-After: 60`, spoofed XFF ignored, safe logs; target public ingress unverified |

**Scope update after C-05 local probe:** ADR-0021 records the checked-in Nginx snippets and negative real-HTTP test. C-05 is locally reproducible, but those snippets have not been attached to a deployed public hostname and do not establish shared global limits across independently exposed ingress replicas.

## Public release gates not met

| Owner / gate | Missing evidence | Required proof before public GO |
| --- | --- | --- |
| Ingress security / C-05 | Locally tested Nginx ingress returns real `429` and `Retry-After: 60` across two upstreams, ignores forged XFF and omits POST secrets from logs; **no target public deployment** or independent ingress-replica shared state verified | Install/verify policy on actual trusted ingress; prove public routing cannot bypass gateway, origin attribution behind real proxies, TLS and aggregation/monitoring on the deployed topology |
| TLS and identity | No verified public domain, certificate chain, trusted proxy `HTTPS` signaling, deployment worker UID/ACL or actual PHP-FPM session/database directory ownership | Test HTTPS, secure cookies, least privilege, parent-directory traversal and deny access to all non-`public/` files on a deployed host |
| Persistence / recovery | Offline test only; no off-host backup retention and timed disaster recovery on target host; RPO/RTO objectives not agreed | Write measurable RPO/RTO objectives with service owner, restore a retained off-host WAL-consistent snapshot in the target environment and observe downtime/data loss |
| Capacity / operations | 4-client/176-request local baseline, not PHP-FPM worker RSS/CPU, public latency/throughput SLO or traffic saturation | Test representative ingress and PHP-FPM workload, establish budget/capacity, alerting and runbook operations with target host metrics |
| Product policy | Account disable/reset/lost-admin recovery (C-20) deferred; security log and activity retention/deletion not agreed for public personal data | Product/security owner approves identity-lifecycle, audit retention and privacy requirements before exposing external users |
| Remote quality | No confirmed push-triggered Actions result on the candidate commit | Review actual workflow run job results and retained logs, with matrix PHP 8.3–8.5 before trusting green CI status |

`GET /healthz` and `GET /readyz` provide process/readiness evidence but **not** user-facing availability, durable backup or external ingress protection.

## Release and rollback procedure

1. Select the candidate commit, pin Composer and dev-only npm lockfiles, run checks listed above with an isolated database. Keep the migration and runtime environment explicit.
2. Set `UI_ENTRYPOINT=spa` **only for an authorized deployment**, leaving `UI_ENTRYPOINT=ssr` as the safe default. Startup validates mode, production HTTPS and file permissions.
3. Expose only after ingress/C-05 and production C-47 gates above are objectively verified; watch HTTP `5xx`, auth/CSRF denials, SQLite busy/WAL, disk/session errors and business flow success. Abort on permission bypass, lost accepted audit write, broken download hash, invalid read/write state or severe 5xx.
4. For presentation-only rollback without schema change, reset `UI_ENTRYPOINT=ssr`, reload application PHP workers and recheck native routes on the same database. For schema-related failures follow `docs/DEPLOYMENT-RECOVERY-RUNBOOK.md`; do not overwrite a live SQLite DB with a raw file snapshot.
5. Re-run public checks and record operator, deployment commit, host, test outputs, measured RPO/RTO and rollout result. Until then: **public-production NO-GO**.

**Primary original book consulted:** Jez Humble and David Farley, *Continuous Delivery* (First Edition), Chapter 1, printed pp. 14–15, physical PDF pages 48–49 at `/home/shomsy/Documents/Books/Software/management/SDLC/Continuous Delivery — First Edition — Jez Humble, David Farley.pdf`. The pages distinguish passing functional acceptance from nonfunctional criteria and emphasize automated release feedback; **only those pages were read**, not the entire book.

**Related decisions:** `docs/architecture/ADR-0019-reversible-ui-cutover.md`, `ADR-0020-account-recovery-scope.md`, `docs/roadmap/C35-QUALITY-EVIDENCE.md`, and `docs/DEPLOYMENT-RECOVERY-RUNBOOK.md`.

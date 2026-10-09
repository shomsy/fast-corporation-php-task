# ADR-0018 — Bootstrap cost and authoritative account revalidation

Status: Accepted for C-32 (local measurement), 2026-10-09  
Owners: Bootstrap / Accounts / Platform

## Measured workload

Run `python3 tests/Flow/bootstrap_cost_probe.py`. The probe prepares a fresh SQLite database using the migration CLI and runs the actual PHP built-in web server and public routes, 40 measured GET requests per route, no external network or production database.

| Path | p50 observed | p95 observed | p99 observed |
|---|---:|---:|---:|
| `/healthz` | 0.329 ms | 0.365 ms | 0.378 ms |
| `/readyz` | 0.634 ms | 0.719 ms | 0.750 ms |
| Missing exact route → 404 | 0.677 ms | 0.763 ms | 0.778 ms |
| Anonymous `/login` | 0.634 ms | 0.721 ms | 0.788 ms |
| `/api/session` | 0.602 ms | 0.689 ms | 0.704 ms |

These are local warm **single-worker PHP built-in-server** response timings. They are neither PHP-FPM production latencies nor a load or capacity forecast. The paths each return the expected status and security headers; the test harness discards the temporary database afterwards.

## Decisions

- **Retain current eager wiring for business requests.** The measured difference between an intentionally early `/healthz` and the missing route is about **0.4 ms p95** in this environment, not evidence that lazy providers or a dependency injection framework earn their ongoing cost.
- `/healthz` correctly short-circuits before DB/session/route graph. `/readyz` intentionally verifies DB schema and migration checksums. Do not make readiness falsely fast by removing this verification.
- An authenticated protected route can read the account from SQLite in the central guard (`CurrentAccount::current()`) and then again via an authorized handler (`RequireSignedIn` or `RequireAdmin`). These reads preserve **server-authoritative role deletion/demotion checking** across the request; a request-local cache or routing of verified identity would need explicit correctness and concurrency tests before replacing them.
- This probe **does not measure the incremental cost of duplicate account reads in an authenticated protected request**, so make no numerical savings claim about that question. Benchmark under representative authenticated traffic and role changes if later PHP-FPM profiles identify database reads as material.
- No new runtime abstraction, lazy container, cache, or hidden cross-slice session state is introduced by C-32.

## Revisit conditions

Use representative production hardware, PHP-FPM worker count and database storage with authentication enabled. If 404 route bootstrap or repeated authoritative reads become meaningful contributors to a documented p95/p99 latency budget, profile with per-query counters, test role demotion and session invalidation, and only then choose a narrow optimization.

Source: McConnell, *Code Complete (2nd ed.)*, performance optimization should follow quantitative measurement (Chapter 25). Existing verified code: `public/index.php`, `bootstrap/Http/HealthProbes.php`, `bootstrap/routes.php`, `SessionIdentity::current()`.

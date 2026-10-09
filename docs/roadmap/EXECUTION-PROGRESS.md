# Execution Progress Ledger — Fast Corporation 2.0

**Status:** M00/M01 DONE; M02/M03/M04 IN PROGRESS; M05 C-14/C-15/C-17 done. 18/48 complete. Public production not yet certified.

**Canonical authority:** [48 packets](CANONICAL-REMEDIATION-PLAN.md), [execution program](EXECUTION-PROGRAM.md), [SPA LLD](FRONTEND-SPA-LLD.md), [full source crosswalk](REVIEW-TRACEABILITY.md). This ledger records **verified work**; do not treat an unchecked checkbox as failure or a checked one as proof without evidence. Homer Workstream is the durable continuation owner when available.

**Homer workstream:** `ws_ydFN6Y8-oBpWPEXoFmcht78m` (alias `fast-corporation-v2`); this is the stable continuity identity for every future RP turn. Open it on each new conversation; never create another for this project.
**Last implementation commit:** d5e0843ecc597100b39cdf4c5342cf1ee543a2f1 (C-17); C-15 050510492ddfccced2aaa22d33b307d4d27d576c; C-14 8697e633772eb66473c582d5fd51f04d7edfd3e5.
**Next legal action:** C-16 stable pagination under concurrent event insertions, then C-18 accepted click/replay semantics and C-19 Page A/B navigation semantics if unblocked. Other unblocked C-22/C-24/C-25 remain. Public deployment C-05 still needs actual edge/proxy evidence. Batch 2–4 tested tasks per turn.

## Milestone status

| Phase | Goal | State | Evidence (commit/test) |
|---|---|---|---|
| M00 | Baseline & evidence | **DONE — 2026-10-09** | `bb420841`: `composer quality` PASS (86 tests, 5,958 assertions; PHPStan and Deptrac 0, audit clear); `python3 tests/Flow/live_smoke.py` PASS (registration, admin, cow, download and safe failures). |
| M01 | Confirmed correctness | **DONE — 2026-10-09** | C-01 `e209775`, C-02 `31d7484`, C-31 `c6f003d`, C-21 `6cc9e1d`; composer quality 98/98 tests, 6038 assertions; PHPStan/Deptrac/audit PASS; live HTTP smoke PASS. |
| M02 | Security/auth | **IN PROGRESS — C-03/C-04/C-06/C-07/C-09 DONE** | C-03/C-04 `7905867`, C-06 `87cc139`, C-07 `c62a03d`; quality PASS 104 tests/6067 assertions, PHPStan/Deptrac/audit PASS; HTTP/admin CLI smoke PASS. |
| M03 | Runtime/session/config | **IN PROGRESS — C-08/C-10 DONE** | `effca0e`: native GC now retains up to 12h and production uses private session storage; measured native file locking. `c5b5b80`: fail-closed production boot; quality/smoke PASS. |
| M04 | DB/audit/durability | **IN PROGRESS — C-11/C-12/C-13 DONE** | `e635944` atomic checksummed migrations; `c539e96` accepted-event audit contract; `50ef500` measured WAL concurrency and safe backup policy; quality/smoke PASS. |
| M05 | Analytics/performance | **IN PROGRESS — C-14/C-15/C-17 DONE** | `8697e63` measured 100k-event daily SQL report and rejected speculative rollup; `0505104` selective actor index with date-plan guard; `d5e0843` accurate sparse UTC chart/accessible ticks. Quality 127/6195, HTTP smoke PASS. |
| M06 | Backend LLD & API | NOT STARTED | — |
| M07 | SPA shell & security | NOT STARTED | — |
| M08 | User screens | NOT STARTED | — |
| M09 | Admin screens/tests | NOT STARTED | — |
| M10 | Cutover | NOT STARTED | — |
| M11 | Final acceptance | NOT STARTED | — |

## Canonical packet status — 18 DONE / 48 TOTAL (30 OPEN)

- [x] **C-01** — Reject malformed UTC calendar dates before DateTime parsing. **DONE:** `e209775`; strict YYYY-MM-DD lexical + calendar checks before parsing; non-string HTTP query values yield 422. RED regression reproduced first; 38 scoped tests/5700 assertions, full composer quality PASS (89 tests/6007 assertions), HTTP smoke PASS.
- [x] **C-02** — Restore the entry output-buffer depth for both successful and exceptional template rendering. DONE in `31d7484`. Two regressions failed before the fix; Renderer scoped tests PASS (4/11), composer quality PASS (92 tests/6017 assertions; PHPStan/Deptrac/audit PASS), HTTP smoke PASS.
- [x] **C-03** — Shared Account creation policy: 1–64 ASCII username characters matching SQLite NOCASE, 12–72-byte passwords without NUL, for registration and admin CLI. **DONE:** `7905867`; RED username/short-secret regressions, GREEN full quality and real CLI/HTTP smoke. Historical usernames/credentials can still authenticate.
- [x] **C-04** — Unknown accounts pay dummy bcrypt cost 12; explicit cost independent of PHP defaults, legacy hashes safely upgraded using compare-and-swap. **DONE:** `7905867`; RED missing-user timing test, GREEN login/rehash tests and full quality.
- [ ] **C-05** — Add enforceable login/registration abuse limits at deployment boundary. *(OPEN; no implementation evidence yet)*
- [x] **C-06** — Make authorization and CSRF an explicit fail-closed route policy. **DONE:** `87cc139`; every route has access contract (public, signed-in, admin), new routes default to protected and unguarded unsafe requests deny. Central server-owned cookie/CSRF policy plus existing adapter checks; Router 7 tests/14 assertions, full quality and HTTP smoke PASS. Origin/Fetch Metadata can be considered as later defense in depth.
- [x] **C-07** — Session ID and CSRF token rotate on login; pre-login tokens cannot authorize authenticated POST, and logout/expiry revoke session. **DONE:** `c62a03d`; RED token test, GREEN session and HTTP smoke with role-demotion regression.
- [x] **C-08** — Harden session storage/GC/cookie settings and measure session-lock serialization. **DONE:** `effca0e37aaf0e8ce131737830ff2009bb239248`; native PHP session GC covers 12-hour absolute expiry, production requires preprovisioned mode-0700 private storage, hardened cookie settings retained. RED GC+missing-storage tests; measured same-ID ~0.42s vs different-ID ~0.10s lock acquisition; full quality 123 tests/6177 assertions and HTTP smoke PASS.
- [x] **C-09** — Unified privacy and browser security headers on every dynamic response, including 200/3xx/4xx/5xx, JSON and binary; CSP disallows inline JS; HSTS only on trusted HTTPS. DONE bd62a5a3abfb473ac45a604d40616013229809e5. RED then GREEN 9 Router tests/65 assertions, quality 106/6118 and live smoke PASS. Legacy inline CSS retained until SPA.
- [x] **C-10** — Reject unsafe production configuration. DONE `c5b5b80a8e4335f48d3c3450719d449d3b9c2b60`: only trusted CLI migrator provisions DB; production checks HTTPS cookie, no debug, https URL and trusted TLS; existing migrated DB required for HTTP. RED missing DB / production tests, GREEN 6 C-10 tests/11 assertions, full quality 114/6141, live HTTP smoke PASS.
- [x] **C-11** — Make migrations deterministic and drift-detectable with SQL-content checksums and safe concurrent runs. DONE `e6359440b1cf3af55975be8beb5bf6e3a821919d`: SHA-256 detects applied SQL drift, BEGIN IMMEDIATE serializes full batch and prevents partial schema writes. Legacy ledgers receive current-source hashes once; pre-upgrade edits cannot be detected. RED drift/partial/legacy regressions, GREEN 7 migrations tests/25 assertions, full quality 118/6161 and live HTTP smoke PASS.
- [x] **C-12** — Define accepted-event audit semantics, caller authority, retention, tamper threat model and UTC policy. **DONE:** `c539e965128573ec40002a8ebefc51171850f37e`; ADR-0003 defines exact accepted actions and server-owned actor authority, historical snapshots, UTC, disposable-demo retention and absence of DB-privileged tamper evidence. Rejected-login separation regression + existing CSRF/transaction/download tests PASS; full quality 124/6181 and HTTP smoke PASS. Public retention/deletion remain C-25/C-26/C-47.
- [x] **C-13** — Measure file-backed concurrent readers/writers, SQLITE_BUSY, WAL/rollback journaling, connection lifecycle and backup. **DONE:** `50ef5001facc64be7f8891cf5b9afa9079400699`; separate-process rollback reader blocks writer (~151ms at probe timeout), WAL reader permits writer (~0.1ms); second writer still blocks, subsequent writes succeed; WAL checkpoint and online-backup restore PASS. Trusted CLI provisioning now enables and verifies WAL, no web journal mutation or unsafe automatic replay. RED default-journal regression, GREEN quality 124/6183 + HTTP smoke PASS; ADR-0004 limits conclusions and documents release backup gate.
- [x] **C-14** — Benchmark report aggregation over growing history and choose an earned query/rollup policy. **DONE:** `8697e633772eb66473c582d5fd51f04d7edfd3e5`; 100k synthetic events/180 days, 20-query warm p95 current SQL aggregation ~21ms using idx_activity_day; lower-window variant ~16ms changes dataset. Retain 30 recent active dates, no unearned rollup. Reproducible benchmark + ADR-0005; scoped reports 20/5547 PASS.
- [x] **C-15** — Evaluate actor/action/date filter plans and measured index write overhead. **DONE:** `050510492ddfccced2aaa22d33b307d4d27d576c`; 100k skewed actors: rare lookup ~13.7ms to ~0.019ms via idx_activity_actor_recent; mixed common/date could regress ~0.5ms to ~6.6ms so date filters use idx_activity_day explicitly (~0.48ms). 3000 inserts ~5.2ms before index vs ~6.84ms after in synthetic batch. Versioned migration 003, RED missing-index plan regression then 21/5551 scoped PASS, quality 125/6188, live HTTP smoke PASS; ADR-0006.
- [ ] **C-16** — Define stable pagination contract. *(OPEN; no implementation evidence yet)*
- [x] **C-17** — Repair sparse UTC day-axis, omitted zero-event day gaps, unique Y ticks and chart accessibility. **DONE:** `d5e0843ecc597100b39cdf4c5342cf1ee543a2f1`; chart x values now proportional to real UTC date spans, disconnected lines across omitted no-activity days, 2 distinct 0/1 ticks when max=1, per-point keyboard label, four visually differentiated series. Chart+table reuse authoritative 30-active-day dataset; no fake zero rows. RED two regressions then GREEN 22 scoped tests/5554 assertions, quality 127/6195, HTTP smoke PASS.
- [ ] **C-18** — Specify whether raw clicks or accepted unique actions are counted. *(OPEN; no implementation evidence yet)*
- [ ] **C-19** — Clarify Page A/B view counts: accepted authorized navigation versus actual rendering. *(OPEN; no implementation evidence yet)*
- [ ] **C-20** — Assess account disable/reset/admin recovery scope separately from interview acceptance. *(OPEN; no implementation evidence yet)*
- [x] **C-21** — Differentiate client validation/authorization from dependency errors. **DONE:** `6cc9e1d`; existing 4xx/503/500 mappings verified, both real 500 causes remain safe generic responses with 24-hex-character X-Request-ID and matching server log reference. RED smoke before fix; GREEN after; quality 98/6038 PASS.
- [ ] **C-22** — Define safe URI parsing, HEAD/OPTIONS/Allow handling and output rules. *(OPEN; no implementation evidence yet)*
- [ ] **C-23** — Keep fixed server-selected artifact. *(OPEN; no implementation evidence yet)*
- [ ] **C-24** — Define non-nesting transaction contract and preserve original failure if rollback/session compensa. *(OPEN; no implementation evidence yet)*
- [ ] **C-25** — Enforce least privilege for DB/config/session files and rehearse durable SQLite backup/restore. *(OPEN; no implementation evidence yet)*
- [ ] **C-26** — Define deployment/runbook/health/readiness/schema checks and safe rollback/forward recovery. *(OPEN; no implementation evidence yet)*
- [x] **C-27** — Allowlisted structured security logs for auth/CSRF/admin denials and failures; 24-hex request ID links response and server log; no raw exception message, SQL or credentials. DONE ee0cff65d1f3c90861a1846aa1961e660c7d9a13. RED then GREEN HTTP smoke + logger tests; quality 108/6128 PASS. Log retention/alert deployment remains C-26/C-47.
- [ ] **C-28** — Measure latency, memory, DB lock, file download and resource utilization under target load. *(OPEN; no implementation evidence yet)*
- [ ] **C-29** — Improve true responsibility boundaries: HTTP/JSON transport, auth policy, application orchestratio. *(OPEN; no implementation evidence yet)*
- [ ] **C-30** — Review shallow wrappers, misleading side-effect names, redundant route helpers and module APIs wit. *(OPEN; no implementation evidence yet)*
- [x] **C-31** — Validate untrusted input at transport boundary, typed invariants at owner. **DONE:** `c6f003d`; arrays/oversized credentials and activity filters rejected as 422 before business logic; wrong scalar password still 401. RED 2 tests first plus oversized filter; quality PASS 95/6031, HTTP smoke PASS.
- [ ] **C-32** — Measure eager PDO/config/router wiring and duplicate authoritative account lookups before lazy opt. *(OPEN; no implementation evidence yet)*
- [ ] **C-33** — Evaluate maintain/replace options for PHP HTTP stack, templating retirement, logging, native JS ro. *(OPEN; no implementation evidence yet)*
- [ ] **C-34** — Keep useful PHPDoc, remove generic boilerplate where it hides semantics, retain MOI and named-argu. *(OPEN; no implementation evidence yet)*
- [ ] **C-35** — Extend tests beyond happy paths and preserve Composer/PHPStan/Deptrac baseline, pinned CI where ju. *(OPEN; no implementation evidence yet)*
- [ ] **C-36** — Create project-specific agent guidance and deterministic RP iteration, checkpoint, reconciliation . *(OPEN; no implementation evidence yet)*
- [ ] **C-37** — Introduce no-framework, no-bundler ES-module SPA shell served from same origin. *(OPEN; no implementation evidence yet)*
- [ ] **C-38** — Implement minimal navigation/history/deep-link behavior and bounded screen lifecycle, not a generi. *(OPEN; no implementation evidence yet)*
- [ ] **C-39** — One same-origin fetch client, cookie session, CSRF bootstrap, 401/403/419/429/5xx handling, no tok. *(OPEN; no implementation evidence yet)*
- [ ] **C-40** — Migrate registration/login/logout/home to Vanilla JS vertical slice with owned form state, accessi. *(OPEN; no implementation evidence yet)*
- [ ] **C-41** — Migrate Page A + Buy cow. *(OPEN; no implementation evidence yet)*
- [ ] **C-42** — Migrate Page B + native file download. *(OPEN; no implementation evidence yet)*
- [ ] **C-43** — Migrate protected statistics filters/pagination and reports table/chart into owner frontend slice. *(OPEN; no implementation evidence yet)*
- [ ] **C-44** — Enforce textContent/safe DOM, accessible focus/forms/keyboard/contrast. *(OPEN; no implementation evidence yet)*
- [ ] **C-45** — Add zero-cost unit/component/browser E2E and backend API contract tests. *(OPEN; no implementation evidence yet)*
- [ ] **C-46** — Migrate one vertical slice at a time, preserve old SSR behavior until verified, switch route owner. *(OPEN; no implementation evidence yet)*
- [ ] **C-47** — Verify recruiter task fully, all production security/persistence gates and honest GO/NO-GO split. *(OPEN; no implementation evidence yet)*
- [ ] **C-48** — Track optional features separately: password reset, account status, token/JWT, framework migration. *(OPEN; no implementation evidence yet)*

| 2026-10-09 | M03 / C-10 | `c5b5b80a8e4335f48d3c3450719d449d3b9c2b60` | RED missing DB / unsafe prod settings; GREEN 6 C-10 tests, quality 114/6141 and real HTTP smoke PASS | CLI-only provisioning, fail-closed production startup. |
| 2026-10-09 | M04 / C-11 | `e6359440b1cf3af55975be8beb5bf6e3a821919d` | RED drift/legacy/partial batch; GREEN 7 migration tests/25 checks, quality 118/6161 and real HTTP smoke PASS | Hash checks and atomic writer-locked migration batches; legacy trust boundary documented. |

## Current blockers

- No active blocker. Root-owned generated PHPStan cache quarantined under gitignored `var/phpstan/cache/PHPStan.root-owned-archive-20261009`, new user-owned cache works. Live smoke startup now retries transient connection resets with diagnostics.

## Phase evidence log

| UTC time | Phase/packet | Commit / operation reference | Exact commands/tests | Outcome + risk / next action |
|---|---|---|---|---|
| 2026-10-09 | M00 baseline | `bb420841c8331d7064931115510b91f51f609206` | `composer quality` => PASS; `python3 tests/Flow/live_smoke.py` => PASS; 142/142 review IDs and 48/48 canonical IDs verified | Historical M00 baseline; C-01 later fixed. |
| 2026-10-09 | M01 / C-01 | `e20977586b6da783b2bd43d04a1186be7a4d7263` | RED: 2 unit and 1 HTTP regression tests failed before fix; GREEN: 38 scoped tests / 5700 assertions; `composer quality` PASS 89 tests / 6007 assertions, PHPStan/Deptrac/audit PASS; `python3 tests/Flow/live_smoke.py` PASS | C-01 fixed with safe 422 for malformed inputs. Next C-02. |

| 2026-10-09 | M01 / C-02 | `31d748482fd6ff930377e847a00898c699cf15ef` | RED 2 Renderer regressions; GREEN 92/92 PHPUnit (6017 assertions), PHPStan, Deptrac, audit, live HTTP smoke | Original exception preserved, output buffer depth restored; next C-21. |

| 2026-10-09 | M01 / C-31 | `c6f003d4b4087bab43ed2660ca98d95004b94742` | Malformed login/register inputs RED before fix; 95/6031 quality PASS and HTTP smoke PASS | Safe 422 for array/oversized form and query fields; preserve 401 for incorrect credentials. |
| 2026-10-09 | M02 / C-06 | `87cc1397262c120d9b3e582ab194749f62ab28bc` | New Router guard contract tests; 98/6038 quality PASS; live HTTP smoke PASS | Default protected routes, central CSRF for unsafe methods and admin access scope. |
| 2026-10-09 | M01 / C-21 | `6cc9e1d4d4528d93190fcbfea65ba00c6c04c479` | HTTP smoke initially RED for missing 500 request ID; then PASS for DB initialization and audit failure; 98/6038 quality PASS | HTTP 500 stays generic, correlation header links to server log. |

| 2026-10-09 | M02 / C-03+C-04 | `79058675a1ba3dbe5978f0e41f518d24fdefce02` | 12-byte password/username and missing-user timing RED before fix; full quality 104 tests/6067 assertions PASS; live CLI/HTTP smoke PASS | Unified creation policy; legacy credentials compatible; bcrypt cost 12 fixed. |
| 2026-10-09 | M02 / C-07 | `c62a03d4f62e9d09804d819ac041b243b79df3d3` | RED old anonymous CSRF token; GREEN session tests and live HTTP smoke; quality PASS | Login invalidates anonymous forms; rotating CSRF with session ID. |

| 2026-10-09 | M02/C-09 | bd62a5a3abfb473ac45a604d40616013229809e5 | RED 2 header tests; GREEN composer quality 106/6118, full live HTTP smoke | Shared browser headers; HTTPS only HSTS, transitional inline CSS. |
| 2026-10-09 | C-27 | ee0cff65d1f3c90861a1846aa1961e660c7d9a13 | RED missing 401 ID; GREEN safe 401/403/500 real smoke, logger 2 tests, full quality 108/6128 | Structured redacted logging; retention/alerting remain deployment gates. |

| 2026-10-09 | M03 / C-08 | `effca0e37aaf0e8ce131737830ff2009bb239248` | RED session GC 1440s / missing private storage; GREEN 17 scoped tests/43 assertions, quality 123/6177 and HTTP smoke PASS | Production 0700 private files + 12h GC, measured same-session serialization, no custom handler. |
| 2026-10-09 | M04 / C-12 | `c539e965128573ec40002a8ebefc51171850f37e` | Explicit accepted-event security separation test GREEN, existing flow and activity tests; quality 124/6181, HTTP smoke PASS | ADR-0003 authoritative audit meanings and retention/tamper limitations; production gate remains. |
| 2026-10-09 | M04 / C-13 | `50ef5001facc64be7f8891cf5b9afa9079400699` | RED SQLite DELETE journal test, GREEN 9 scoped tests/34 checks; separate-process DELETE/WAL reader-writer probe + backup/restore PASS, full quality 124/6183, HTTP smoke PASS | WAL at CLI provisioning only; preserve single-writer and busy failure semantics. |

| 2026-10-09 | M05 / C-14 | `8697e633772eb66473c582d5fd51f04d7edfd3e5` | 100k-event index-backed report query measurement (~21ms unbounded / ~16ms last 30 calendar days), EXPLAIN idx_activity_day, scoped 20/5547 PASS | Keep last 30 active-day SQL aggregate, no rollup without evidence. |
| 2026-10-09 | M05 / C-15 | `050510492ddfccced2aaa22d33b307d4d27d576c` | RED missing actor-first index; GREEN EXPLAIN regression + 100k selective/skewed comparison; quality 125/6188 and real HTTP smoke PASS | New actor-index migration and date-query plan guard; ~32% extra writes in isolated 3000-row sample. |
| 2026-10-09 | M05 / C-17 | `d5e0843ecc597100b39cdf4c5342cf1ee543a2f1` | RED sparse UTC spacing and repeated Y labels; GREEN 22 scoped/5554 assertions, full quality 127/6195, real HTTP smoke PASS | Break missing-day gaps, show true calendar spacing and accessible labels; preserve 30 active-day counts. |

## Decisions log

| Date | Decision | Basis | Consequence / reopen trigger |
|---|---|---|---|
| 2026-10-09 | Vanilla JavaScript ES Modules SPA; same-origin PHP session cookies; no JWT | User-approved architecture direction | Reconsider only if independently deployed clients or product scale changes justify it |
| 2026-10-09 | Preserve PHP business owner modules / MOI / named-argument contracts | Project doctrine and explicit owner choice | Reopen only with documented behavior/API need |
| 2026-10-09 | Defer 142 review claims to 48 mapped work packets rather than count all as bugs | Source traceability and factual review | Revalidate individual claims before implementation |
| 2026-10-09 | Larger RP turns: batch several independent bounded packets, preserve per-task commits and regression gates | Owner instruction in current RP conversation | Do not end automatically after one fix; do not sacrifice checks for throughput |

## Turn continuation contract

At the end of every RP batched run: update only actually completed packet checkboxes, milestone evidence, next precise intent, runtime blockers, and stable workstream ID; checkpoint in Homer. Preserve original reviews. At kickoff, inspect git state first; no documentation-only action counts as application implementation.

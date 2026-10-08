# Execution Progress Ledger — Fast Corporation 2.0

**Status:** M01 IN PROGRESS; C-01 AND C-02 COMPLETE; C-21 NEXT. SPA implementation NOT STARTED; public-production acceptance NOT MET. **Date created:** 2026-10-09. **Baseline code revision:** `bb420841` (before planning documents).

**Canonical authority:** [48 packets](CANONICAL-REMEDIATION-PLAN.md), [execution program](EXECUTION-PROGRAM.md), [SPA LLD](FRONTEND-SPA-LLD.md), [full source crosswalk](REVIEW-TRACEABILITY.md). This ledger records **verified work**; do not treat an unchecked checkbox as failure or a checked one as proof without evidence. Homer Workstream is the durable continuation owner when available.

**Homer workstream:** `ws_ydFN6Y8-oBpWPEXoFmcht78m` (alias `fast-corporation-v2`); this is the stable continuity identity for every future RP turn. Open it on each new conversation; never create another for this project.
**Last baseline application commit:** `bb420841c8331d7064931115510b91f51f609206`. **Last implementation commit:** `31d748482fd6ff930377e847a00898c699cf15ef` (C-02).
**Next legal action:** M01 / C-21: verify HTTP 4xx/5xx error mapping with negative tests, then continue C-31.

## Milestone status

| Phase | Goal | State | Evidence (commit/test) |
|---|---|---|---|
| M00 | Baseline & evidence | **DONE — 2026-10-09** | `bb420841`: `composer quality` PASS (86 tests, 5,958 assertions; PHPStan and Deptrac 0, audit clear); `python3 tests/Flow/live_smoke.py` PASS (registration, admin, cow, download and safe failures). |
| M01 | Confirmed correctness | **IN PROGRESS — C-01/C-02 DONE** | `e209775`: NUL/array date filters now return HTTP 422; regression RED before fix, composer quality PASS (89 tests / 6007 assertions), live HTTP smoke PASS. |
| M02 | Security/auth | NOT STARTED | — |
| M03 | Runtime/session/config | NOT STARTED | — |
| M04 | DB/audit/durability | NOT STARTED | — |
| M05 | Analytics/performance | NOT STARTED | — |
| M06 | Backend LLD & API | NOT STARTED | — |
| M07 | SPA shell & security | NOT STARTED | — |
| M08 | User screens | NOT STARTED | — |
| M09 | Admin screens/tests | NOT STARTED | — |
| M10 | Cutover | NOT STARTED | — |
| M11 | Final acceptance | NOT STARTED | — |

## Canonical packet status — 48/48 OPEN

- [x] **C-01** — Reject malformed UTC calendar dates before DateTime parsing. **DONE:** `e209775`; strict YYYY-MM-DD lexical + calendar checks before parsing; non-string HTTP query values yield 422. RED regression reproduced first; 38 scoped tests/5700 assertions, full composer quality PASS (89 tests/6007 assertions), HTTP smoke PASS.
- [x] **C-02** — Restore the entry output-buffer depth for both successful and exceptional template rendering. DONE in `31d7484`. Two regressions failed before the fix; Renderer scoped tests PASS (4/11), composer quality PASS (92 tests/6017 assertions; PHPStan/Deptrac/audit PASS), HTTP smoke PASS.
- [ ] **C-03** — Establish one username invariant (length, canonical uniqueness, Unicode spoofing decision) and one. *(OPEN; no implementation evidence yet)*
- [ ] **C-04** — Close unknown-account password timing branch. *(OPEN; no implementation evidence yet)*
- [ ] **C-05** — Add enforceable login/registration abuse limits at deployment boundary. *(OPEN; no implementation evidence yet)*
- [ ] **C-06** — Make authorization and CSRF an explicit fail-closed route policy. *(OPEN; no implementation evidence yet)*
- [ ] **C-07** — Define session state transitions, ID and CSRF token rotation at login. *(OPEN; no implementation evidence yet)*
- [ ] **C-08** — Harden session storage/GC/cookie settings and measure session-lock serialization before changing n. *(OPEN; no implementation evidence yet)*
- [ ] **C-09** — Apply suitable headers to every response: nosniff, cache-control for private data, referrer protec. *(OPEN; no implementation evidence yet)*
- [ ] **C-10** — Reject unsafe production configuration. *(OPEN; no implementation evidence yet)*
- [ ] **C-11** — Make migrations deterministic and drift-detectable with SQL-content checksums and safe concurrent-. *(OPEN; no implementation evidence yet)*
- [ ] **C-12** — Define accepted-event audit semantics, caller authority, retention, tamper threat model and UTC ti. *(OPEN; no implementation evidence yet)*
- [ ] **C-13** — Measure real concurrent readers/writers, SQLITE_BUSY, WAL versus rollback journaling, connection l. *(OPEN; no implementation evidence yet)*
- [ ] **C-14** — Benchmark report aggregation over growing history, then choose bounded date window, query/index im. *(OPEN; no implementation evidence yet)*
- [ ] **C-15** — Evaluate actor/action/date filter query plans and selective indexes with representative distributi. *(OPEN; no implementation evidence yet)*
- [ ] **C-16** — Define stable pagination contract. *(OPEN; no implementation evidence yet)*
- [ ] **C-17** — Repair day-axis semantics, missing/zero days, Y ticks and chart accessibility. *(OPEN; no implementation evidence yet)*
- [ ] **C-18** — Specify whether raw clicks or accepted unique actions are counted. *(OPEN; no implementation evidence yet)*
- [ ] **C-19** — Clarify Page A/B view counts: accepted authorized navigation versus actual rendering. *(OPEN; no implementation evidence yet)*
- [ ] **C-20** — Assess account disable/reset/admin recovery scope separately from interview acceptance. *(OPEN; no implementation evidence yet)*
- [ ] **C-21** — Differentiate client validation/authorization from dependency errors. *(OPEN; no implementation evidence yet)*
- [ ] **C-22** — Define safe URI parsing, HEAD/OPTIONS/Allow handling and output rules. *(OPEN; no implementation evidence yet)*
- [ ] **C-23** — Keep fixed server-selected artifact. *(OPEN; no implementation evidence yet)*
- [ ] **C-24** — Define non-nesting transaction contract and preserve original failure if rollback/session compensa. *(OPEN; no implementation evidence yet)*
- [ ] **C-25** — Enforce least privilege for DB/config/session files and rehearse durable SQLite backup/restore. *(OPEN; no implementation evidence yet)*
- [ ] **C-26** — Define deployment/runbook/health/readiness/schema checks and safe rollback/forward recovery. *(OPEN; no implementation evidence yet)*
- [ ] **C-27** — Structured diagnostic/security logs separate from product activity. *(OPEN; no implementation evidence yet)*
- [ ] **C-28** — Measure latency, memory, DB lock, file download and resource utilization under target load. *(OPEN; no implementation evidence yet)*
- [ ] **C-29** — Improve true responsibility boundaries: HTTP/JSON transport, auth policy, application orchestratio. *(OPEN; no implementation evidence yet)*
- [ ] **C-30** — Review shallow wrappers, misleading side-effect names, redundant route helpers and module APIs wit. *(OPEN; no implementation evidence yet)*
- [ ] **C-31** — Validate untrusted input at transport boundary, typed invariants at owner. *(OPEN; no implementation evidence yet)*
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

## Current blockers

- No active blocker. Root-owned generated PHPStan cache quarantined under gitignored `var/phpstan/cache/PHPStan.root-owned-archive-20261009`, new user-owned cache works. Live smoke startup now retries transient connection resets with diagnostics.

## Phase evidence log

| UTC time | Phase/packet | Commit / operation reference | Exact commands/tests | Outcome + risk / next action |
|---|---|---|---|---|
| 2026-10-09 | M00 baseline | `bb420841c8331d7064931115510b91f51f609206` | `composer quality` => PASS; `python3 tests/Flow/live_smoke.py` => PASS; 142/142 review IDs and 48/48 canonical IDs verified | Historical M00 baseline; C-01 later fixed. |
| 2026-10-09 | M01 / C-01 | `e20977586b6da783b2bd43d04a1186be7a4d7263` | RED: 2 unit and 1 HTTP regression tests failed before fix; GREEN: 38 scoped tests / 5700 assertions; `composer quality` PASS 89 tests / 6007 assertions, PHPStan/Deptrac/audit PASS; `python3 tests/Flow/live_smoke.py` PASS | C-01 fixed with safe 422 for malformed inputs. Next C-02. |

| 2026-10-09 | M01 / C-02 | `31d748482fd6ff930377e847a00898c699cf15ef` | RED 2 Renderer regressions; GREEN 92/92 PHPUnit (6017 assertions), PHPStan, Deptrac, audit, live HTTP smoke | Original exception preserved, output buffer depth restored; next C-21. |

## Decisions log

| Date | Decision | Basis | Consequence / reopen trigger |
|---|---|---|---|
| 2026-10-09 | Vanilla JavaScript ES Modules SPA; same-origin PHP session cookies; no JWT | User-approved architecture direction | Reconsider only if independently deployed clients or product scale changes justify it |
| 2026-10-09 | Preserve PHP business owner modules / MOI / named-argument contracts | Project doctrine and explicit owner choice | Reopen only with documented behavior/API need |
| 2026-10-09 | Defer 142 review claims to 48 mapped work packets rather than count all as bugs | Source traceability and factual review | Revalidate individual claims before implementation |

## Turn continuation contract

At the end of every RP bounded run: update only actually completed packet checkboxes, milestone evidence, next precise intent, runtime blockers, and stable workstream ID; checkpoint in Homer. Preserve original reviews. At kickoff, inspect git state first; no documentation-only action counts as application implementation.

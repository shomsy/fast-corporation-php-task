# Execution Program — Fast Corporation PHP + Vanilla JS SPA

**Status:** READY TO START / NO APPLICATION WORK IMPLEMENTED BY THIS PROGRAM.
**Authority:** [48 canonical packets](CANONICAL-REMEDIATION-PLAN.md), [SPA/API LLD](FRONTEND-SPA-LLD.md), [142-source crosswalk](REVIEW-TRACEABILITY.md), [progress ledger](EXECUTION-PROGRESS.md).

## Operating rules

1. Begin each run from actual Git state and Homer checkpoint, never from transcript memory; the existing M00 evidence may be reused if still valid. Do not touch unrelated dirty user work.
2. The original interview TASK.md is the acceptance contract. Preserve business owner modules, screaming names, vertical slices, separation of concerns and information hiding; no superficial SOLID class proliferation.
3. Batch 2–4 dependent-unblocked coherent packets per RP turn when safe, instead of stopping after each task. Preserve independent commits/validation for each packet and red/green tests for confirmed defects. Conditional performance/storage issues require measured workload, not guessed severity.
4. Preserve personal PhpStorm MOI file unchanged, named-argument API compatibility, Deptrac and useful PHPDocs. No mass format/rename.
5. Compass relevant guides and selected relevant material from `/home/shomsy/Documents/Books/` are mandatory for consequential decisions; do not load entire corpus or claim unread books.
6. Security, backend API and schema changes land before dependent SPA screens; old SSR remains rollback fallback during per-slice migration.
7. Use Homer for durable Workstream; H-General durable effects when appropriate. Follow exact H-Prompt mandatory rules and fallback order, reconcile uncertain effects before retrying.
8. Code/documentation stays English; plain Serbian user summaries. Small scoped test fast; full composer+smoke at phase gates; frontend/browser suite when affected.

## Dependency-ordered milestone program

| Phase | Theme | Canonical IDs | Exit gate |
|---|---|---|---|
| **M00** | Baseline + characterization | C-35, C-36 | Baseline green or diagnosed; failing regressions prepared. |
| **M01** | Confirmed correctness and safe errors | C-01, C-02, C-21, C-31 | Old workflows remain green; confirmed defects have failing-before/passing-after tests. |
| **M02** | Authentication and access security | C-03, C-04, C-05, C-06, C-07, C-09, C-27 | Real browser/HTTP auth and role tests, full secure session and CSRF lifecycle, edge limits demonstrated. |
| **M03** | Runtime, config and session boundaries | C-08, C-10, C-22, C-32 | No web-created empty database; no silent URI fallback; HEAD/OPTIONS behavior tested; session consistency proven. |
| **M04** | Persistence, event and backup reliability | C-11, C-12, C-13, C-18, C-19, C-24, C-25 | No schema drift; tested cross-process DB failures; audit counting semantics and backup recovery evidenced. |
| **M05** | Reports, query performance, capacity | C-14, C-15, C-16, C-17, C-28 | Correct reports/table/chart on sparse dates, safe history navigation, measured p95 and SQLite operating limits. |
| **M06** | Backend LLD + JSON API seam | C-29, C-30, C-33, C-34 | Legacy SSR stays working; JSON status/schema/auth/CSRF tests green; Deptrac and PHPDocs validated. |
| **M07** | Vanilla JS shell + secure client | C-37, C-38, C-39, C-44 | No JWT, no SPA framework; browser auth, navigation, session expiry, safe DOM and cancellation pass. |
| **M08** | User vertical slices | C-40, C-41, C-42 | All user task journeys pass; correct one-session behavior, UI-only thankYou, safe binary transfer. |
| **M09** | Admin SPA and browser test gates | C-43, C-45 | Backend authorization enforced; chart has same numbers as table; browser E2E and security tests green. |
| **M10** | Feature cutover and retire old templates | C-46 | No dual-write/double-track, regression, broken deep links or orphaned routes; rollback rehearsed. |
| **M11** | Release/production scope and acceptance | C-20, C-23, C-26, C-47, C-48 | Interview acceptance proven, public-production NO-GO unless deployment controls tested, clean main pushed to origin. |

## Milestone working contracts

### M00 — Baseline + characterization

- [ ] **Implement/test:** Capture clean git, complete quality and smoke results, real legacy user/admin behavior, review mapping evidence before touching application code.
- [ ] **Exit gate:** Baseline green or diagnosed; failing regressions prepared.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M01 — Confirmed correctness and safe errors

- [ ] **Implement/test:** Fix date NUL/422 and renderer buffer leaks with red/green tests; define 4xx/5xx contracts.
- [ ] **Exit gate:** Old workflows remain green; confirmed defects have failing-before/passing-after tests.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M02 — Authentication and access security

- [ ] **Implement/test:** Username/password policy, hash migration/timing, production rate limits, route auth+CSRF, session rotation, all-response headers and safe logging.
- [ ] **Exit gate:** Real browser/HTTP auth and role tests, full secure session and CSRF lifecycle, edge limits demonstrated.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M03 — Runtime, config and session boundaries

- [ ] **Implement/test:** Separate DB provisioning, fail-closed production config, HTTP protocol correctness, session GC/locking, measured bootstrap cost.
- [ ] **Exit gate:** No web-created empty database; no silent URI fallback; HEAD/OPTIONS behavior tested; session consistency proven.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M04 — Persistence, event and backup reliability

- [ ] **Implement/test:** Migration checksums/lock, audit actor/time/semantics, SQLite concurrency/WAL, click replay, transaction failures, real restore.
- [ ] **Exit gate:** No schema drift; tested cross-process DB failures; audit counting semantics and backup recovery evidenced.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M05 — Reports, query performance, capacity

- [ ] **Implement/test:** Measure query plans, optimize only justified indexes/rollups, decide keyset and calendar semantics, chart tests and capacity.
- [ ] **Exit gate:** Correct reports/table/chart on sparse dates, safe history navigation, measured p95 and SQLite operating limits.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M06 — Backend LLD + JSON API seam

- [ ] **Implement/test:** Improve meaningful responsibilities and owner/API boundaries; add JSON endpoints calling same use-cases, evaluate OSS without mass rewrite.
- [ ] **Exit gate:** Legacy SSR stays working; JSON status/schema/auth/CSRF tests green; Deptrac and PHPDocs validated.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M07 — Vanilla JS shell + secure client

- [ ] **Implement/test:** Same-origin ESM shell, History deep links, cleanup/abort, cookie+CSRF client, DOM XSS/a11y and late-response control.
- [ ] **Exit gate:** No JWT, no SPA framework; browser auth, navigation, session expiry, safe DOM and cancellation pass.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M08 — User vertical slices

- [ ] **Implement/test:** Migrate Login/Register/Logout, CowOffer/Page A and Downloads/Page B one feature at a time.
- [ ] **Exit gate:** All user task journeys pass; correct one-session behavior, UI-only thankYou, safe binary transfer.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M09 — Admin SPA and browser test gates

- [ ] **Implement/test:** Admin-only statistics/reports, accessible SVG/table, filters and history, robust OSS browser E2E tests.
- [ ] **Exit gate:** Backend authorization enforced; chart has same numbers as table; browser E2E and security tests green.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M10 — Feature cutover and retire old templates

- [ ] **Implement/test:** Switch route ownership slice-by-slice, rehearse routing rollback, remove obsolete presentation only after parity.
- [ ] **Exit gate:** No dual-write/double-track, regression, broken deep links or orphaned routes; rollback rehearsed.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

### M11 — Release/production scope and acceptance

- [ ] **Implement/test:** Choose conditional account/download hardening, final clean install, full checks, recovery, delivery evidence.
- [ ] **Exit gate:** Interview acceptance proven, public-production NO-GO unless deployment controls tested, clean main pushed to origin.
- [ ] **Evidence:** exact commit, commands, tests, risk decision, changed public contract, and next legal packet recorded before moving on.

## One RP iteration: several bounded work packets

1. OPEN the stable Homer Workstream (not a new Workstream per chat); inspect checkpoint, git tree, active failures and next legal packet.
2. Select the next 2–4 unblocked C-IDs from the current milestone (or prerequisite). Name owner, invariant, risk and executable acceptance test. Read relevant files/Compass/book passage for *that* problem.
3. For confirmed bug: write a failing regression before implementation where practicable. For conditional claim: measure and write ADR or mark deferred with evidence. No gratuitous framework/port.
4. Make one bounded, reviewable code change; protect business ownership and existing paths. Run scoped tests; full `composer quality`, `python3 tests/Flow/live_smoke.py`, browser E2E at phase gate.
5. Inspect diff, secrets, PHP named-argument compatibility, MOI (untouched), Deptrac and behavior. Update only proven C-ID checkboxes/status and evidence in ledger.
6. Commit coherent change and push `main` to `origin` after passing necessary gates; if commit/push uncertain inspect/reconcile before retry, never assume success.
7. Persist Homer checkpoint summary, evidence refs, exact nextIntent and terminal readiness; next RP turn resumes rather than restarting.

## Acceptance and release matrix

| Gate | Observable proof |
|---|---|
| PHP correctness | PHPUnit with negative tests; PHPStan/Deptrac/PHPDocs, Composer validate/audit and supported PHP 8.3–8.5 behavior. |
| HTTP/security | Real cookie/CSRF/auth/admin role; safe 4xx/5xx, no raw internals, no token leakage, rate limits before public deployment, CSP and all response headers. |
| Persistence | Migration drift + concurrency, isolated file-backed SQLite writer/readers, real backup and RESTORE, disk-full and permission failures, transaction failure boundaries. |
| SPA | Native ESM, same origin, no JWT/localStorage credentials, History deep links/back/forward, abort+unmount, DOM XSS safety, a11y, duplicates and late responses. |
| Reports | Stable statistics pagination/filter contract, measured query cost, calendar vs active-day contract, accurate sparse chart and accessible table. |
| Release | Fresh checkout and local setup, clean reviewed diff and branch, successful remote push, explicit interview GO and separately public-production GO/NO-GO. |

## Safety brakes and release stop conditions

- STOP mutation if Homer stale turn/revision, unknown/partially committed H-General operation, dirty unrelated user work, unexpected branch divergence, security-critical uncertainty, database corruption or destructive schema change.
- Never replay an UNKNOWN effect blindly, force-push, bypass test gates, remove unfinished SSR before slice parity, or silently change TASK.md business semantics.
- Do not claim production ready while rate-limiting/HTTPS/session/backup/restore/security/availability gates are untested. Conditional work may be explicitly deferred for interview release, but public release remains NO-GO.
- If tool binding fails, use mandatory recovery order: Homer → H-General → regular conversation rollover → Temporary one-turn; no retired watcher/H-Code/H-Leg/H-Remote ladder.

## First legal kickoff — M00

M00 was verified on 2026-10-09 at application commit `bb420841` (composer quality and live smoke PASS). RP must inspect the ledger and check that evidence is still applicable rather than repeat baseline blindly; its first implementation target is C-01 then C-02. If runtime or code changed materially, rerun the affected baseline first. **No application implementation is implied by creating this document.**

## Final reporting rule

At each phase close, record verified C-IDs, evidence/commit SHA, tests, decisions, remaining risks, rollback point and next C-ID. Human summary in simple Serbian; project files in English.

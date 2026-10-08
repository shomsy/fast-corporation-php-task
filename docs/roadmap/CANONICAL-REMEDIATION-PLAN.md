# Fast Corporation — Canonical Remediation & Vanilla JS SPA Roadmap

**Status:** PLANNED / NOT IMPLEMENTED. **Baseline:** repository `main` at `bb420841` (2026-10-09).
**Source of truth:** this plan for future work; originals remain immutable review evidence. [Crosswalk](REVIEW-TRACEABILITY.md), [SPA LLD](FRONTEND-SPA-LLD.md), [Execution program](EXECUTION-PROGRAM.md), [RP prompt](RP-EXECUTION-PROMPT.md).

## 1. Scope and non-negotiable decisions

- **Product:** implement the actual interview brief (`../../TASK.md`): registration, login/logout, Page A Buy a cow UI-only acknowledgment, Page B executable download, admin statistics and reports, five event actions. Preserve documented semantics unless an explicit, tested ADR records a change.
- **Stack decision:** PHP >=8.3 modular monolith, PDO + SQLite, **Vanilla JavaScript ES modules + native DOM + Vanilla CSS SPA**, served from one origin. No JavaScript framework and no production bundle dependency. Evaluate dev-only test tooling later.
- **Authentication decision:** PHP server-side sessions with secure HttpOnly SameSite cookie, **no JWT**, no credential token in browser storage. Same-origin CSRF protected state-changing JSON requests.
- **Architecture:** feature/vertical-slice ownership, DDD module boundaries and screaming names on both sides. LLD must protect invariants, information hiding, separation of concerns, high cohesion and justified coupling. Folder names express business, file names express responsibility, functions/methods express action. Classes for state/lifecycle; functions for simpler stateless behavior.
- **Safeguards:** **MOI** remains the sole PHP visual formatter; do not edit `/home/shomsy/.config/JetBrains/PhpStorm2026.3/codestyles/MOI.xml`; named arguments are an author-owned compatibility contract. Follow `docs/ARCHITECTURE-GOVERNANCE.md` and Compass relevant guides. Use local Books corpus for actual decisions, not ceremony.
- **No invented scale:** interviewer demo and public internet deployment are different acceptance gates. SQLite can be suitable for one modest deployment. WAL, rollup, Redis, queues, Argon2id, repository ports, PSR-7/15, separate deployables, migration to Postgres and JS frameworks must earn their costs.
- **No big-bang rewrite:** protect the legacy route behaviors and use one vertical slice at a time; remove old PHP templates only after verified parity and a reversible cutover.

## 2. Review consolidation policy

Five archived reviews contain **142 source entries, not 142 unique bugs**: `OOP-01..18` (18), `S/A/R/P/B/T/D/X` (62), `LLD-01..26` (26), `CLLD-01..26` (26), `GEM-01..10` (10). Every source ID has a row in `REVIEW-TRACEABILITY.md` pointing at one primary canonical task or a reason for rejection/deferment.

Evidence classes: **CONFIRMED** = observed in code or previously reproduced; **CONDITIONAL** = depends on workload, public threat model or deployment; **DESIGN DECISION** = plausible option, not a defect; **REJECTED AS STATED** = contradicted by code; **APPROVED DIRECTION** = user-approved future target, not implemented. A source's P1 label does not overrule this assessment.

**Already disproved Gemini claims must never trigger fixes:** GEM-01 path traversal (fixed server-owned download path), GEM-02 timing-unsafe CSRF (`hash_equals()` already used), GEM-05 alleged direct new connection/clock (PDO/PSR-20 injected), GEM-06 PHP-side full-table reporting (`GROUP BY`/`SUM` in SQL with bounded result), GEM-07 missing session ID rotation (`session_regenerate_id(true)` already called), GEM-09 missing migration transactions (`Transaction::run` already wraps each SQL+ledger entry). GEM-03 is partly true (WAL undecided; `busy_timeout=5000` already configured).

## 3. Canonical backlog (48 independent work packets)

All tasks start **OPEN**. Priority is risk/sequence guidance, not an excuse to skip prerequisites. A packet closes only with failing regression where practicable, implementation, evidence and clean scoped checks. `G0` means baseline characterization/proof of current state.

### C-01 — Reject malformed UTC calendar dates before DateTime parsing [P1; CONFIRMED]
- **Owner:** ActivityTracking / HTTP. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Reject malformed UTC calendar dates before DateTime parsing; canonical 422 validation responses rather than ValueError/500.
- **Acceptance:** Regression tests for NUL, arrays, invalid dates, leap dates and reversed ranges; true dependency errors remain 500.

### C-02 — Restore the entry output-buffer depth for both successful and exceptional template rendering [P1; CONFIRMED]
- **Owner:** Templating. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Restore the entry output-buffer depth for both successful and exceptional template rendering; retain the original exception.
- **Acceptance:** Nested ob_start()+throw leaves no leaked buffers or partial output; existing HTML escaping tests pass.

### C-03 — Establish one username invariant (length, canonical uniqueness, Unicode spoofing decision) and one [P1; CONFIRMED]
- **Owner:** Accounts. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Establish one username invariant (length, canonical uniqueness, Unicode spoofing decision) and one shared password policy across registration and CLI admin creation.
- **Acceptance:** Oversized/confusable usernames and weak passwords rejected under documented policy; existing accounts handled by explicit compatibility/migration decision.

### C-04 — Close unknown-account password timing branch [P1; CONFIRMED]
- **Owner:** Accounts / Security. **Dependencies:** C-03. **Status:** OPEN.
- **Change:** Close unknown-account password timing branch; freeze hash algorithm parameters, support safe rehash of existing bcrypt credentials.
- **Acceptance:** Known and unknown account failures execute equivalent verification work; hash migration tested on 8.3–8.5 and explicitly supported algorithms.

### C-05 — Add enforceable login/registration abuse limits at deployment boundary [P1; CONDITIONAL]
- **Owner:** HTTP Security / Platform. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Add enforceable login/registration abuse limits at deployment boundary; app-side limiter only if public exposure and bypass model justify it.
- **Acceptance:** Real request returns 429/Retry-After after bounded attempts; multi-instance rules, proxies, account-lockout DoS and logging tested.

### C-06 — Make authorization and CSRF an explicit fail-closed route policy [P1; CONFIRMED GAP]
- **Owner:** Runtime / Security. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Make authorization and CSRF an explicit fail-closed route policy; all currently registered POSTs already check CSRF manually.
- **Acceptance:** Route contract tests fail for an unsafe unprotected route; unauthorized admin reads/mutations forbidden; same-origin Origin/Fetch Metadata considered as defense-in-depth.

### C-07 — Define session state transitions, ID and CSRF token rotation at login [P1; CONFIRMED]
- **Owner:** Accounts / Session. **Dependencies:** C-06. **Status:** OPEN.
- **Change:** Define session state transitions, ID and CSRF token rotation at login; invalidate old tokens, expiry, logout, role deletion/demotion.
- **Acceptance:** Cross-tab, old-token, fixation, privilege change, idle/absolute expiry and logout failure regression tests pass.

### C-08 — Harden session storage/GC/cookie settings and measure session-lock serialization before changing n [P2; CONDITIONAL]
- **Owner:** Session / Platform. **Dependencies:** C-07. **Status:** OPEN.
- **Change:** Harden session storage/GC/cookie settings and measure session-lock serialization before changing native PHP session lifecycle.
- **Acceptance:** Dedicated private storage configured where needed; no expiry before policy; same-session concurrent requests measured and optimized only if bottleneck established.

### C-09 — Apply suitable headers to every response: nosniff, cache-control for private data, referrer protec [P1; CONFIRMED GAP]
- **Owner:** HTTP Security. **Dependencies:** C-06. **Status:** OPEN.
- **Change:** Apply suitable headers to every response: nosniff, cache-control for private data, referrer protection, CSP for HTML, conditional HSTS.
- **Acceptance:** Headers are asserted for 200/3xx/4xx/5xx, JSON, HTML, binary; CSP works without unsafe inline scripts; HTTPS-only HSTS tested.

### C-10 — Reject unsafe production configuration [P1; CONFIRMED GAP]
- **Owner:** Platform / Config. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Reject unsafe production configuration; move SQLite database/directory creation out of the web request.
- **Acceptance:** Fail-closed production boot with secure cookies/HTTPS; existing migrated DB required for normal requests; isolated provisioning command owns creation.

### C-11 — Make migrations deterministic and drift-detectable with SQL-content checksums and safe concurrent- [P1; CONFIRMED]
- **Owner:** Database / Delivery. **Dependencies:** C-10. **Status:** OPEN.
- **Change:** Make migrations deterministic and drift-detectable with SQL-content checksums and safe concurrent-run behavior.
- **Acceptance:** Changed applied SQL rejects on replay; initial ledger migration is safe; two migration processes cannot silently race; transactional failure leaves clear state.

### C-12 — Define accepted-event audit semantics, caller authority, retention, tamper threat model and UTC ti [P2; CONDITIONAL]
- **Owner:** ActivityTracking / Data. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Define accepted-event audit semantics, caller authority, retention, tamper threat model and UTC timestamp storage policy.
- **Acceptance:** All event types/actors/times have validated contracts; decide triggers vs archival/tamper evidence by threat model, not by arbitrary checklist.

### C-13 — Measure real concurrent readers/writers, SQLITE_BUSY, WAL versus rollback journaling, connection l [P2; CONDITIONAL]
- **Owner:** SQLite / Reliability. **Dependencies:** C-10. **Status:** OPEN.
- **Change:** Measure real concurrent readers/writers, SQLITE_BUSY, WAL versus rollback journaling, connection lifetime and backup behavior.
- **Acceptance:** File-backed multi-process test, error policy and WAL/checkpoint/restore ADR with measured lock rates; no automatic unsafe retry.

### C-14 — Benchmark report aggregation over growing history, then choose bounded date window, query/index im [P2; CONFIRMED QUERY SHAPE]
- **Owner:** ActivityTracking / Performance. **Dependencies:** C-12. **Status:** OPEN.
- **Change:** Benchmark report aggregation over growing history, then choose bounded date window, query/index improvement or transactional daily rollup.
- **Acceptance:** Representative seed + EXPLAIN + before/after p95 and write cost; rollup, if chosen, has rebuild/reconciliation strategy and atomic event/rollup update.

### C-15 — Evaluate actor/action/date filter query plans and selective indexes with representative distributi [P2; CONDITIONAL]
- **Owner:** ActivityTracking / Performance. **Dependencies:** C-14. **Status:** OPEN.
- **Change:** Evaluate actor/action/date filter query plans and selective indexes with representative distributions.
- **Acceptance:** SQL explain and regression tests for filter combinations; no excessive write-index overhead or unbounded scan claims without measured context.

### C-16 — Define stable pagination contract [P2; CONFIRMED]
- **Owner:** ActivityTracking / HTTP. **Dependencies:** C-15. **Status:** OPEN.
- **Change:** Define stable pagination contract; if required replace deep OFFSET with validated keyset cursor on (occurred_at,id).
- **Acceptance:** Concurrent insertion and identical timestamps never duplicate/skip inside stable traversal; filter and cursor tamper tests pass.

### C-17 — Repair day-axis semantics, missing/zero days, Y ticks and chart accessibility [P1; CONFIRMED]
- **Owner:** ActivityTracking / Frontend. **Dependencies:** C-14. **Status:** OPEN.
- **Change:** Repair day-axis semantics, missing/zero days, Y ticks and chart accessibility; preserve original task's daily counts.
- **Acceptance:** Sparse-day and max=1 examples have accurate plots/table, keyboard-readable labels and visually distinct series; calendar policy explicit.

### C-18 — Specify whether raw clicks or accepted unique actions are counted [P2; DESIGN DECISION]
- **Owner:** CowOffer / Downloads. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Specify whether raw clicks or accepted unique actions are counted; prevent accidental replay if unique-action contract selected.
- **Acceptance:** Double click, browser refresh, two tabs, retry and download behavior tests prove chosen semantics; one-use server nonce/idempotency key only where required.

### C-19 — Clarify Page A/B view counts: accepted authorized navigation versus actual rendering [P2; DESIGN DECISION]
- **Owner:** ActivityTracking / HTTP. **Dependencies:** C-06. **Status:** OPEN.
- **Change:** Clarify Page A/B view counts: accepted authorized navigation versus actual rendering; avoid hidden SPA GET-side-effect surprises.
- **Acceptance:** One explicit tracked event per defined visit/navigation (including back/forward/prefetch policy); no client-supplied actor authority.

### C-20 — Assess account disable/reset/admin recovery scope separately from interview acceptance [P2; CONDITIONAL]
- **Owner:** Accounts / Security. **Dependencies:** C-03. **Status:** OPEN.
- **Change:** Assess account disable/reset/admin recovery scope separately from interview acceptance.
- **Acceptance:** If public deployment requires it, disabled account cannot authenticate and is revoked; recovery and CLI trace tested; otherwise deferred with written reason.

### C-21 — Differentiate client validation/authorization from dependency errors [P1; CONFIRMED GAP]
- **Owner:** HTTP / Reliability. **Dependencies:** C-06. **Status:** OPEN.
- **Change:** Differentiate client validation/authorization from dependency errors; consistent status + safe body; redact sensitive logs.
- **Acceptance:** 4xx/5xx contract tests for date/CSRF/auth/DB/audit missing; request IDs correlate a generic 500 with server diagnostics.

### C-22 — Define safe URI parsing, HEAD/OPTIONS/Allow handling and output rules [P2; CONFIRMED]
- **Owner:** HTTP Runtime. **Dependencies:** C-21. **Status:** OPEN.
- **Change:** Define safe URI parsing, HEAD/OPTIONS/Allow handling and output rules; do not automatically introduce PSR-7/15.
- **Acceptance:** Malformed URI is 400, HEAD carries no body, OPTIONS+405 have correct Allow, all HTTP behavior covered by integration tests.

### C-23 — Keep fixed server-selected artifact [P2; CONDITIONAL]
- **Owner:** Downloads / Security. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Keep fixed server-selected artifact; define size/integrity, audit semantics, streaming threshold and executable placeholder handling.
- **Acceptance:** No client path injection; missing/modified artifact responds 503; large-file stress within memory budget if streaming selected; binary delivered correctly.

### C-24 — Define non-nesting transaction contract and preserve original failure if rollback/session compensa [P2; CONDITIONAL]
- **Owner:** Database / Accounts. **Dependencies:** C-11. **Status:** OPEN.
- **Change:** Define non-nesting transaction contract and preserve original failure if rollback/session compensation also fails.
- **Acceptance:** Nested usage fails intentionally or uses measured-needed savepoints; injected commit/rollback/audit/session failures have known outcomes.

### C-25 — Enforce least privilege for DB/config/session files and rehearse durable SQLite backup/restore [P1 GATE; CONDITIONAL]
- **Owner:** Platform / Security. **Dependencies:** C-10. **Status:** OPEN.
- **Change:** Enforce least privilege for DB/config/session files and rehearse durable SQLite backup/restore.
- **Acceptance:** Real restore from consistent backup succeeds; permissions, service identity, RPO/RTO and disk-full recovery are tested before public exposure.

### C-26 — Define deployment/runbook/health/readiness/schema checks and safe rollback/forward recovery [P1 GATE; CONDITIONAL]
- **Owner:** Platform / Delivery. **Dependencies:** C-25. **Status:** OPEN.
- **Change:** Define deployment/runbook/health/readiness/schema checks and safe rollback/forward recovery.
- **Acceptance:** Fresh operator can start locally and deploy reference environment, healthz without DB and readyz with dependencies; failure recovery demonstrates expected behavior.

### C-27 — Structured diagnostic/security logs separate from product activity [P2; CONDITIONAL]
- **Owner:** Security / Observability. **Dependencies:** C-21. **Status:** OPEN.
- **Change:** Structured diagnostic/security logs separate from product activity; redact secrets and untrusted user strings.
- **Acceptance:** Failed login/CSRF/admin denies/SQLite contention and 5xx produce safe correlated events under defined retention; activity_events remains successful-actions-only.

### C-28 — Measure latency, memory, DB lock, file download and resource utilization under target load [P2; CONDITIONAL]
- **Owner:** Performance / Capacity. **Dependencies:** C-13. **Status:** OPEN.
- **Change:** Measure latency, memory, DB lock, file download and resource utilization under target load; define SQLite exit criteria.
- **Acceptance:** Document workload, p95/p99/error budget, memory bounds, query-plan and capacity evidence; no invented internet-scale SLO.

### C-29 — Improve true responsibility boundaries: HTTP/JSON transport, auth policy, application orchestratio [P2; DESIGN DECISION]
- **Owner:** Architecture / Staff. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Improve true responsibility boundaries: HTTP/JSON transport, auth policy, application orchestration, domain invariants, persistence and presentation.
- **Acceptance:** Change-impact review demonstrates isolated HTML, storage and security-policy changes; Deptrac protects legal boundaries; no big-bang folder rewrite.

### C-30 — Review shallow wrappers, misleading side-effect names, redundant route helpers and module APIs wit [P3; DESIGN DECISION]
- **Owner:** Architecture / Staff. **Dependencies:** C-29. **Status:** OPEN.
- **Change:** Review shallow wrappers, misleading side-effect names, redundant route helpers and module APIs with actual change pressure.
- **Acceptance:** Each added/removed abstraction provides an invariant or reduced coupling; preserve deep AccountLifecycle, CurrentAccount and business slices.

### C-31 — Validate untrusted input at transport boundary, typed invariants at owner [P2; CONFIRMED GAP]
- **Owner:** Backend API / Senior. **Dependencies:** C-01. **Status:** OPEN.
- **Change:** Validate untrusted input at transport boundary, typed invariants at owner; stable public error and result contracts.
- **Acceptance:** No raw dependency exception text in 422; typed or documented validated results; malformed arrays/oversized inputs fail safely.

### C-32 — Measure eager PDO/config/router wiring and duplicate authoritative account lookups before lazy opt [P3; CONDITIONAL]
- **Owner:** Bootstrap / Platform. **Dependencies:** C-22. **Status:** OPEN.
- **Change:** Measure eager PDO/config/router wiring and duplicate authoritative account lookups before lazy optimization.
- **Acceptance:** 404/health performance measured; only real costs optimized; no speculative DI container.

### C-33 — Evaluate maintain/replace options for PHP HTTP stack, templating retirement, logging, native JS ro [P3; DESIGN DECISION]
- **Owner:** OSS Evaluator. **Dependencies:** C-29. **Status:** OPEN.
- **Change:** Evaluate maintain/replace options for PHP HTTP stack, templating retirement, logging, native JS router/chart/testing libraries.
- **Acceptance:** ADR compares size, ecosystem, maintenance, attack surface, dependency cost and fit; retain minimal implementation unless evidence favors OSS.

### C-34 — Keep useful PHPDoc, remove generic boilerplate where it hides semantics, retain MOI and named-argu [P3; CONFIRMED DOC GAP]
- **Owner:** Documentation / Ownership. **Dependencies:** C-29. **Status:** OPEN.
- **Change:** Keep useful PHPDoc, remove generic boilerplate where it hides semantics, retain MOI and named-argument API compatibility.
- **Acceptance:** Docs explain invariants, exceptions and effects; docs checker, style contracts and named arguments remain valid; personal PhpStorm MOI untouched.

### C-35 — Extend tests beyond happy paths and preserve Composer/PHPStan/Deptrac baseline, pinned CI where ju [P1 GATE; CONFIRMED TEST GAP]
- **Owner:** Quality / Security. **Dependencies:** G0. **Status:** OPEN.
- **Change:** Extend tests beyond happy paths and preserve Composer/PHPStan/Deptrac baseline, pinned CI where justified.
- **Acceptance:** Old-bug failing regressions, integration/flow tests, CI matrix 8.3–8.5, dependency audit and smoke all green; failures are categorized.

### C-36 — Create project-specific agent guidance and deterministic RP iteration, checkpoint, reconciliation  [P2; DESIGN DECISION]
- **Owner:** AI / SDLC. **Dependencies:** C-35. **Status:** OPEN.
- **Change:** Create project-specific agent guidance and deterministic RP iteration, checkpoint, reconciliation and evidence-based closure.
- **Acceptance:** One documented resume entry point, no UNKNOWN side-effect replay, unchanged review sources, measured tests after each small packet.

### C-37 — Introduce no-framework, no-bundler ES-module SPA shell served from same origin [P2; APPROVED DIRECTION]
- **Owner:** Frontend / Architecture. **Dependencies:** C-29. **Status:** OPEN.
- **Change:** Introduce no-framework, no-bundler ES-module SPA shell served from same origin; native DOM and CSS.
- **Acceptance:** App loads without external CDN/npm runtime; modern browser ESM works; frontend build tool absent until earned.

### C-38 — Implement minimal navigation/history/deep-link behavior and bounded screen lifecycle, not a generi [P2; APPROVED DIRECTION]
- **Owner:** Frontend / Runtime. **Dependencies:** C-37. **Status:** OPEN.
- **Change:** Implement minimal navigation/history/deep-link behavior and bounded screen lifecycle, not a generic reactive framework.
- **Acceptance:** Back/forward, refresh deep links, 404 and navigation cancellation work; listeners and AbortControllers are cleaned up.

### C-39 — One same-origin fetch client, cookie session, CSRF bootstrap, 401/403/419/429/5xx handling, no tok [P1 GATE; APPROVED DIRECTION]
- **Owner:** Frontend / Security. **Dependencies:** C-06,C-37. **Status:** OPEN.
- **Change:** One same-origin fetch client, cookie session, CSRF bootstrap, 401/403/419/429/5xx handling, no tokens in localStorage.
- **Acceptance:** Login, logout, reload, expired session and retry errors behave safely; XSS cannot read session cookie; no JWT or CORS needed.

### C-40 — Migrate registration/login/logout/home to Vanilla JS vertical slice with owned form state, accessi [P2; APPROVED DIRECTION]
- **Owner:** Accounts UI. **Dependencies:** C-39. **Status:** OPEN.
- **Change:** Migrate registration/login/logout/home to Vanilla JS vertical slice with owned form state, accessible feedback and API boundary.
- **Acceptance:** Auth E2E covers real cookie, CSRF, validation, duplicate username, logout and admin/user landing with no regressions.

### C-41 — Migrate Page A + Buy cow [P2; APPROVED DIRECTION]
- **Owner:** CowOffer UI. **Dependencies:** C-39,C-18. **Status:** OPEN.
- **Change:** Migrate Page A + Buy cow; keep UI-only thankYou behavior and server-authoritative click count.
- **Acceptance:** Click triggers exactly contract-defined event; repeated click pending is disabled; navigation/reload resets transient thankYou per TASK.md.

### C-42 — Migrate Page B + native file download [P2; APPROVED DIRECTION]
- **Owner:** Downloads UI. **Dependencies:** C-39,C-23. **Status:** OPEN.
- **Change:** Migrate Page B + native file download; prefer same-origin POST form over JS blob buffering where appropriate.
- **Acceptance:** Browser downloads harmless sample.exe, retains cookie/CSRF, logs accepted action only once per contract, handles unavailable binary.

### C-43 — Migrate protected statistics filters/pagination and reports table/chart into owner frontend slice [P2; APPROVED DIRECTION]
- **Owner:** ActivityTracking UI. **Dependencies:** C-39,C-17. **Status:** OPEN.
- **Change:** Migrate protected statistics filters/pagination and reports table/chart into owner frontend slice.
- **Acceptance:** Admin-only data accessible only after backend policy; filters reflect URL and valid dates; empty/loading/error states; chart same data as table.

### C-44 — Enforce textContent/safe DOM, accessible focus/forms/keyboard/contrast [P1 GATE; APPROVED DIRECTION]
- **Owner:** Frontend / Security / A11y. **Dependencies:** C-37. **Status:** OPEN.
- **Change:** Enforce textContent/safe DOM, accessible focus/forms/keyboard/contrast; handle stale async results and cleanup.
- **Acceptance:** Tests cover DOM XSS payloads, accessible controls, abort/out-of-order responses, 401 navigation, double-submit, keyboard/focus.

### C-45 — Add zero-cost unit/component/browser E2E and backend API contract tests [P1 GATE; APPROVED DIRECTION]
- **Owner:** Frontend / Test. **Dependencies:** C-40,C-41,C-42,C-43. **Status:** OPEN.
- **Change:** Add zero-cost unit/component/browser E2E and backend API contract tests; OSS runner only if worth dev dependency.
- **Acceptance:** CI runs deterministic frontend checks and real browser auth+activity workflows across routes; no brittle arbitrary sleeps.

### C-46 — Migrate one vertical slice at a time, preserve old SSR behavior until verified, switch route owner [P1 GATE; APPROVED DIRECTION]
- **Owner:** Delivery / Strangler. **Dependencies:** C-45. **Status:** OPEN.
- **Change:** Migrate one vertical slice at a time, preserve old SSR behavior until verified, switch route ownership atomically.
- **Acceptance:** Auth->Page A->Page B->Stats->Reports parity; rollback route switch tested; delete old templates only after no traffic/tests depend on them.

### C-47 — Verify recruiter task fully, all production security/persistence gates and honest GO/NO-GO split [P1 GATE; CONDITIONAL]
- **Owner:** Release / Acceptance. **Dependencies:** C-46,C-26. **Status:** OPEN.
- **Change:** Verify recruiter task fully, all production security/persistence gates and honest GO/NO-GO split.
- **Acceptance:** All 7 task workflows accepted on clean installation; matrix+smoke+browser E2E green, backup/restore; evidence-linked release checklist and clean main+remote.

### C-48 — Track optional features separately: password reset, account status, token/JWT, framework migration [P3; DEFERRED]
- **Owner:** Product / Architecture. **Dependencies:** C-47. **Status:** OPEN.
- **Change:** Track optional features separately: password reset, account status, token/JWT, framework migration, distributed DB, admin tooling.
- **Acceptance:** None implemented without explicit product requirement/threat evidence; reasons for deferred scope and change trigger documented.

## 4. Architecture decisions still to prove, not to blindly implement

- **Password policy:** agree allowed username alphabet and normalisation/migration compatibility, then bcrypt cost/Argon2id decision using verified `PASSWORD_ARGON2ID` availability and cross-platform CI. Password minimum is a product/security policy, not an arbitrary copied constant.
- **CSRF and SPA boot:** token retrieved via same-origin endpoint with session bootstrap, sent through a request header or protected form. Rotate when anonymous session becomes authenticated; handle already-open tabs predictably.
- **SQLite:** verify `EXPLAIN`/WAL/checkpoint safety and backup before asserting SQLite must be replaced or WAL is always required. No assumption that `PDO::SQLITE_ATTR_OPEN_FLAGS` exists: confirm target PHP/PDO support before adopting a readwrite/no-create API.
- **Audit semantics:** GET page views in current app are recorded as accepted visits, not confirmed human perception; planned SPA may use explicit authorized POST view events. Reconcile differences with product specification and traffic behavior.
- **Replay/idempotency:** distinguish one button press from repeated intentional presses and browser resubmission; choose nonce/PRG/unique-effect contract per endpoint. File-download browser handoff is not proof of complete transfer.
- **Frontend routing:** native History API + same-origin rewrite/fallback only for app URLs; `/api/` and static assets must never be silently rewritten to the SPA shell.
- **Release:** decide whether delivery is interview-grade or public internet production; the latter is NO-GO until security, backup and operational requirements are demonstrated.

## 5. Explicitly rejected shortcuts / forbidden churn

- No wholesale rewrite of stable owners, no invented multi-service or event-sourcing infrastructure, no one-interface-per-class, no transient "framework" around native DOM.
- No CSS/JavaScript changes that bypass accessibility, CSP, XSS protection, session security or server-side authorization.
- Do not conflate `SameSite` with sufficient CSRF protection; do not store JWT/access tokens in localStorage; do not put secrets in frontend modules.
- No deleting original review text, old TODO or governance files while reconciling. Prefer links over duplicating long claims.
- No CI rule that overwrites PhpStorm MOI formatting or silently renames named parameters.

## 6. Definition of done and evidence

Per work packet: named failure/risk → tested invariant → bounded implementation → tests + static/architecture + security checks → before/after behavior → incident/recovery notes if relevant → evidence (commit, command, test) in execution ledger. Only mark complete after the tests actually pass; do not repeat "GREEN" from old review as current truth.

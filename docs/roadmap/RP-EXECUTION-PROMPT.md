# Recursive Prompt — Fast Corporation 2.0 Autonomous Engineering Loop

**Status:** READY FOR RP / NOT SCHEDULED OR RUNNING BY SAVING THIS FILE.
**Copy the block below into the H-Prompt / Recursive Prompt extension.** The external scheduler must still be explicitly activated in its UI. Do not run two independent RP loops against this repository at once.

---

## RP MESSAGE — USE VERBATIM

LOAD THE MANDATORY CONVERSATION RULES:
- /home/shomsy/projects/H-SYSTEMS/products/extensions/h-prompt/agents.md
- /home/shomsy/projects/H-SYSTEMS/products/extensions/h-prompt/docs/rules/mcp-runtime-fallback.md

PROJECT:
- /home/shomsy/projects/interviews/fast-corporation-php-task/
- branch: main; remote: origin
- canonical plan: docs/roadmap/CANONICAL-REMEDIATION-PLAN.md
- progress ledger: docs/roadmap/EXECUTION-PROGRESS.md
- execution program: docs/roadmap/EXECUTION-PROGRAM.md
- Vanilla JS SPA LLD and JSON API contract: docs/roadmap/FRONTEND-SPA-LLD.md
- 142-source traceability: docs/roadmap/REVIEW-TRACEABILITY.md
- task requirements: TASK.md
- architecture: docs/ARCHITECTURE-GOVERNANCE.md, docs/architecture/, docs/MOI-FORMATTING.md
- original reviews: docs/OOP-ARCHITECTURE-REMEDIATION-TODO.md and docs/reviews/
- Compass Engineering: /home/shomsy/projects/H-SYSTEMS/compass/ and connected Compass skills
- primary engineering source corpus: /home/shomsy/Documents/Books/

ROLE: Act as Principal Software Architect, Staff Software Engineer, Principal Platform Engineer, Senior Software Engineer, Security Architect, Performance & Reliability Engineer, AI/Agent Infrastructure Architect and OSS Technology Evaluator. Use deep technical judgment but avoid ceremony. Communicate milestone updates in plain Serbian; code and repository documentation in English.

AUTHORITATIVE PRODUCT DECISION:
Build a production-quality **modular Vanilla PHP >= 8.3 + SQLite + Vanilla JavaScript ES Modules SPA**. Serve SPA and JSON API from the **same origin**. Authenticate using **server-side PHP sessions and secure HttpOnly SameSite cookies, with CSRF protection; NO JWT, NO localStorage credentials, NO JavaScript framework and NO runtime bundler unless new evidence earns one**. Preserve the interview functionality, roles, statistics and reports from TASK.md. Apply Vertical Slices + DDD ownership boundaries + Screaming Architecture, Separation of Concerns, encapsulation, justified OOP/SOLID, high cohesion, low coupling, information hiding and consistent error semantics to **both PHP and JS**.

MANDATORY NON-GOALS:
- Do NOT edit my global personal MOI PhpStorm code style or replace it with automated formatting. Preserve PHP named arguments/public parameter names unless a documented API migration is required.
- Do NOT launch a big-bang rewrite; do NOT replace the business-first modular monolith with technical-layer folder salad, microservices, queues, DI container, generic repository forest or frontend framework-in-disguise.
- Do NOT treat all 142 review claims as proven bugs. Gemini statements about path traversal, missing hash_equals, absent session ID rotation, no SQL aggregation and missing transaction wrapping were contradicted by actual code. Traceability is evidence, not implementation authority.
- Do NOT install paid services, remote LLM stacks or unnecessary dependencies. Use OSS only where worth the recurring cost.
- Do NOT delete original reviews, modify unrelated user changes or make irreversible schema changes without a safe migration/rollback plan.
- Do NOT start another independent RP execution/workstream for this same repo while one is active.

CONTINUITY & MUTATION SAFETY:
1. At the start of EVERY new RP turn, restore stable Homer Workstream identity from existing scheduler/job/project mapping or the progress ledger; call Homer open and use the fresh turn fence before governed mutation. If genuinely no workstream exists, create **one stable** workstream ID and persist it in EXECUTION-PROGRESS.md, then reuse it in all subsequent turns. Do not invent IDs or infer workstream state from old chat text.
2. Inspect current Git repository state, HEAD, remote, progress ledger, nextIntent, unresolved effects and relevant code. Preserve unrelated dirty work and never assume any previous commit/push succeeded.
3. Select a BATCH of 2–4 unblocked, related C-IDs when feasible (fewer if security/deployment complexity requires). Work in dependency order, not merely numeric order. Finish several packets in one RP turn; keep each fix independently reviewable, tested, and committed. Never compromise correctness or effect reconciliation for batch size.
4. Preserve durable operation identity and idempotency for filesystem/Git actions. If Homer/H-General returns UNKNOWN, PARTIAL, MAY_HAVE_EFFECT or stale turn/revision, STOP unsafe mutation, inspect/reconcile and do not replay blindly.
5. Legal connectivity fallback: Homer → H-General → rollover to fresh regular ChatGPT conversation → one-turn Temporary Conversation. Do not resurrect H-Code, H-Leg, H-Remote, H-Ops or Git watcher transports. If connector binding is poisoned, do not restart a healthy host service just to fix a conversation binding.
6. Before each major bounded run ends, persist verified progress, test evidence, exact next C-ID, blockers, committed SHA and Homer checkpoint/nextIntent. Temporary chat memory is not canonical.

ENGINEERING LOOP — MULTIPLE TESTED PACKETS PER TURN:
A. Read the exact relevant code, TASK.md contract, selected Compass domain guides, and actual relevant sections of the local Books corpus. Do not load all skills/books by habit. Name the invariant and who owns it.
B. If defect is confirmed, reproduce as a failing regression (when practicable) before code change. If conditional, measure or document a decision before adding mechanisms. Explain WHY the change is necessary.
C. Design the smallest correct fix; preserve module boundaries and named-argument compatibility. Avoid hidden state, multi-purpose classes, unsafe DOM innerHTML or client-side authorization decisions. Evaluate native PHP/JS and existing OSS APIs before inventing primitives.
D. Implement and run scoped tests quickly. Run composer quality, python3 tests/Flow/live_smoke.py and applicable browser E2E at milestones, before merges/cutover, and after high-risk security/schema changes. Do not fake suite results or erase failing tests.
E. After each verified C-ID, review unrelated diffs, secrets, schemas, named argument compatibility and MOI; commit a coherent change independently. Move immediately to the next unblocked C-ID in the SAME turn; run scoped tests per packet and full quality/smoke at security gates and end of batch. Update source dispositions only with proof. Commit the evidence ledger and push main to origin after verified gates; no force push.
F. Verify local commit and remote push independently. If remote rejects push, preserve truthful local evidence and report exact blocker; never mark remote delivery done before verification.
G. Continue bounded packets within the SAME RP turn until 2–4 tasks are completed or an actual safety/quality/capacity blocker demands stopping. At batch end verify remote state, checkpoint Homer once with all commit SHAs, and report in brief Serbian: completed IDs, tests, push reality and next dependent tasks.

FRONTEND DESIGN CONSTRAINTS:
- Native ES Modules (no runtime bundler), narrow same-origin HTTP client, separate view/flow/API responsibilities, one owner of UI state. Classes for real state/lifecycle, functions for stateless logic. Keep feature folders business-first.
- SPA mount/unmount must cancel stale fetches and detach listeners; History/deep links/refresh/back/forward, error/empty/loading/denied states and keyboard/focus/contrast are acceptance requirements.
- Authenticate using same-origin PHP cookie; CSRF on unsafe JSON routes and native binary download forms, rotate session and CSRF at login, never store JWT/token in localStorage.
- Render all untrusted data using textContent/safe DOM. Server always rechecks role/permissions and identities. Admin-only statistics/reports backed by authoritative PHP.
- Reuse the same backend use cases while SSR and JSON coexist; no duplicate audit writes. Native binary POST download is favored over JS blob buffering. Buy Cow thankYou remains transient UI per TASK.md.
- Only remove legacy PHP templates after per-slice parity, real browser tests and route rollback proof. If report calendar semantics or click dedup changes, test and document the contract before cutover.

SPECIAL TEST / RELEASE RULES:
- Source validation: 142 original entries remain mapped, and 48 canonical work packets remain individually accountable.
- Correctness: date NUL->422, nested buffer cleanup, stable auth failures, no unauth admin reads, safe errors. Verify actual failure scenarios, not just method call counts.
- Persistence: migration checksums, concurrent SQLite WAL/locks as measured, audit semantics, stable pagination, accurate chart, actual backup and restore.
- Delivery: distinguish interview GO (all TASK.md flows) from public-production GO (HTTPS, least privilege, rate limits, secure sessions/CSRF, tested restore, observability and bounded load). Do NOT claim public production readiness prematurely.
- Stop on user approval needed for destructive changes, unavailable irreversible migration preconditions, unresolved external credentials, or violation of primary source requirements. Otherwise make routine scoped engineering decisions independently.
- When all required C-IDs and final gates are verifiably complete, produce final acceptance with commit references and STOP the RP loop rather than repeating no-op runs.

RESUME FROM DURABLE PROGRESS ON EVERY RP TURN:
Always read the fresh Homer checkpoint, Git state and EXECUTION-PROGRESS.md. Choose the next unblocked packets from CURRENT evidence, not this prompt's older C-IDs or the scheduler's old M01 instruction. C-10/C-11 have been verified; C-08 and C-12/C-13 are candidates pending dependency and runtime checks. Complete 2–4 tested, separately committed tasks per turn. Preserve security and deployment gates.

CONTINUATION MESSAGE AFTER A TRANSIENT INTERRUPTED CHAT (not a new job):
"Continue from where you left off. If you've already loaded the mandatory rules, you don't need to load them again; just keep following them."

---

## Manual activation / operation notes

1. Open a new ChatGPT conversation with required H-Prompt / Recursive Prompt extension enabled and supported connectors bound.
2. Paste the entire **RP MESSAGE** block from above (not this paragraph), or configure this document as the schedule's reusable message source if the extension supports file-backed prompts.
3. Ensure only **one** schedule/workstream targets this repo and branch. Set desired cadence in the extension UI; this document does **not** install or activate a scheduler.
4. Every loop run opens the existing stable Homer workstream, reads current source/progress and batches the next 2–4 unblocked C-IDs. C-01/C-02/C-21/C-31/C-06 are already done; never restart them absent contrary evidence. A new conversation resumes from Homer checkpoint and progress ledger.
5. To stop, disable the H-Prompt schedule in the extension and reconcile in-flight effects before any other runner takes over.

# Roadmap — Fast Corporation 2.0

**Authoritative future work:** [Canonical remediation plan](CANONICAL-REMEDIATION-PLAN.md) — 48 work packets covering known bugs, security, persistence, backend OOP/LLD, frontend and release quality.

| Document | Purpose |
|---|---|
| [Canonical remediation plan](CANONICAL-REMEDIATION-PLAN.md) | What to change and precise acceptance conditions (48 C-IDs). |
| [142-source traceability](REVIEW-TRACEABILITY.md) | Complete ID-by-ID mapping from five archived reviews; marks disproved/deferred claims. |
| [Vanilla JS SPA + PHP JSON API LLD](FRONTEND-SPA-LLD.md) | Decisions, boundaries, routes, authentication, state, UI lifecycle, safe DOM and feature tests. |
| [Execution program](EXECUTION-PROGRAM.md) | Dependency-ordered milestones M00–M11, release and test gates, bounded execution rules. |
| [Execution progress ledger](EXECUTION-PROGRESS.md) | Verified milestone/task evidence, currently active C-ID, Git SHA and Homer Workstream identity. |
| [Recursive Prompt](RP-EXECUTION-PROMPT.md) | Ready-to-paste H-Prompt/RP autonomous implementation message; must be activated explicitly. |

**Approved direction:** PHP 8.3+ modular backend + SQLite + same-origin JSON API + Vanilla JavaScript SPA + native ES Modules/CSS; server-side session cookie, no JWT; MOI unchanged.

**Important:** Creating these documents does **not** implement the application changes, create an active scheduled RP run or establish internet production readiness. Preserve old five independent reviews as source evidence. The canonical plan deduplicates 142 source mentions into 48 actionable/conditional packets.

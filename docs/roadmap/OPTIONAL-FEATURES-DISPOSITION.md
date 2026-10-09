# C-48 — Deliberately deferred product and architecture capabilities

**Status:** Decision recorded after C-47 interviewer acceptance (2026-10-09). This is a **scope disposition**, not an implementation claim. The interview contract is `TASK.md` and the proven delivery is the Vanilla PHP + SQLite + Vanilla ES Modules SPA described in `docs/roadmap/RELEASE-ACCEPTANCE-2026-10-09.md`.

| Optional capability | Decision now | Reason and product-owned reopen trigger | New acceptance proof if reopened |
| --- | --- | --- | --- |
| Self-service password reset | **DEFER** | No reset or verified notification channel is required. Reopen only with an approved user/account recovery journey, delivery channel and security operator | One-use short-lived reset grant; no account enumeration; expiry, replay, login/session revocation and audit tests |
| Disable/suspend account; lost-admin recovery | **DEFER** | No admin lifecycle, termination or recovery policy is specified. Trusted `create-admin` CLI creates a new admin but is not a password reset | Privileged disable decision, cross-worker session revocation, safe break-glass access and auditable recovery — see ADR-0020 |
| JWT, OAuth refresh tokens, browser-stored credentials | **REJECT for current same-origin app** | PHP sessions with HttpOnly SameSite cookies and CSRF already own identity. A token protocol would expand the threat model without independent client/deployment need | Before changing, prove separate-origin/native client requirements, token lifecycle/revocation/storage, new API trust boundaries and red/green security tests |
| PHP or frontend framework migration | **DEFER** | No maintainability/capability bottleneck measured. Existing modular PHP vertical slices and native ESM have tests and simple deployability; migrating for fashion is not earned complexity | Quantified repeated work/failure or independent team pressure, migration ADR, parallel parity tests, reversible rollout and dependency review |
| Distributed DB or new message queue | **DEFER** | SQLite WAL/online backup and current bounded local workload are adequate for an interview demo; no measured multi-writer/multi-host requirement | Load and failure experiments showing SQLite insufficient, operational topology, transaction semantics, DB migration and data rollback plan |
| General-purpose admin panel / user management | **DEFER** | The requested admin capability is read-only analytics and reports, already implemented. Account administration has privacy/authorization consequences not in the task | Explicit role/permissions model, safe mutation/audit flows, misuse tests, accessible UX and product acceptance |
| Payments, cow inventory, durable purchase state | **OUT OF SCOPE** | `Buy a cow` is explicitly a transient UI button for the interview, not a financial transaction | New business definition, inventory/payments authority, consistency and reconciliation contracts |
| Distributed tracing, external observability vendors | **DEFER** | Structured local allowlisted security logs and diagnostic request IDs exist. No public deployment/topology has been selected | Approved operator requirements, measurable SLIs/alerts, secrets/privacy review and operating budget |

## Scope constraints

1. Keep **one authoritative owner** of identity, audit, downloads and report facts on the PHP side. Frontend is presentation/ephemeral state only.
2. Do not make `TOKEN/JWT` or `PASSWORD_RESET` TODOs that appear as unfinished interview requirements. Deferred means no code or migration without the approved trigger.
3. These choices do **not** waive public-security work. Public ingress abuse controls **C-05** and public host/recovery gates within the C-47 assessment remain **NO-GO** pending real deployment evidence.
4. When a trigger becomes real, create a separate product ticket/ADR with owner, alternatives, acceptance proof and backward-compatible roll-out, rather than silently expanding the recruiter scope.
5. Do not modify global MOI rules, original reviews or 142-source review traceability for optional features.

**References:** `TASK.md`, `docs/architecture/ADR-0020-account-recovery-scope.md`, `docs/roadmap/FRONTEND-SPA-LLD.md`, `docs/roadmap/RELEASE-ACCEPTANCE-2026-10-09.md`, and the tested present code. No implementation of deferred capabilities is asserted.

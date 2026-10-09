# ADR-0003 — Accepted Activity Events and Audit Boundaries

Status: Accepted for the interview implementation (2026-10-09)  
Owner: ActivityTracking; the initiating business owner decides when an action has been accepted  
Scope: Local SQLite activity history and its reporting projections; **not** a compliance-grade immutable audit ledger.

## Purpose and authority

Business activity events describe **server-accepted operations**, not raw browser attempts, unverified client clicks, or proof that bytes reached a device. The business owner authorizes the operation; ActivityTracking alone validates the allowed action/target taxonomy and writes the row. The actor ID and historical username snapshot come from an Account verified server-side. A browser, SPA or future JSON endpoint must never supply authoritative `actor_id`, `role` or `actor_label`. There is no client-facing generic event ingestion endpoint.

ActivityTracking does **not** query Accounts, own credentials, or determine authorization. Its exported `ActivityLog` receives typed, trusted in-process calls. Callers are trusted code, not an arbitrary public interface. This is a modular-monolith trust boundary and must be reevaluated if code execution or remote API clients become untrusted.

## Accepted-event contract

| Action | Accepted at | What is recorded | What is *not* claimed |
|---|---|---|---|
| `registration` | Account and activity entry committed in one SQLite transaction | New account ID and username snapshot | Registration automatically authenticates |
| `login` | Credentials accepted, session established and event persisted | Authenticated account ID and historical username | Failed login is a business event; failed audit is successful login |
| `logout` | Authorized logout intent, attempted before session revocation | Signed-in actor when storage is available | Guaranteed delivery on database failure; a completed network response |
| `view-page/page-a` or `page-b` | Authorized route invokes owner view | Accepted navigation attempt | The browser necessarily rendered pixels; prefetch is a human visit |
| `button-click/buy-cow` | Authenticated + CSRF-accepted server interaction | Successful transient thank-you action | Payment, purchase, stock consumption or durable button state |
| `button-click/download` | Fixed server-controlled artifact read **and** event persisted before response | Successful preparation of download response | File fully transmitted, received, saved or executed |

The database CHECK constraint restricts action/target combinations; the PHP enum and typed `TrackedPage`/`TrackedButton` prevent accidental new vocabulary. Failed login belongs in the separate allowlisted security diagnostic log (`auth.login_denied`); malformed or denied CSRF/admin requests must not generate business activity. See `docs/security/SECURITY-DIAGNOSTICS.md`.

## Data and consistency

The `activity_events` row contains a monotonic SQLite row ID, server-derived `actor_id`, historical `actor_label`, canonical action and target, and a **UTC** timestamp formatted as ISO-8601 with `+00:00`. The timestamp is assigned by the injected server clock, not a client's local timezone. Actor snapshots intentionally survive renames; they are personal data. Activity history is append-only **at application API level**, not protected from a privileged SQLite operator who can UPDATE/DELETE rows.

Registration + event is one database transaction; a failure rolls both back. Authentication fails closed on an audit write error by revoking the new session and surfacing an operational error. Logout revokes the session even if database lookup or audit writing fails. Page views and transient button actions do not have a durable idempotency key: repeated *accepted* requests can count more than once. C-18 determines any future deduplication policy, while C-19 governs SPA/navigation semantics.

## Retention, integrity and threat model

For this zero-cost local interview task, **retain activity rows for the lifetime of the disposable local database**. No unreviewed automatic deletion or archival mechanism is silently enabled. The deployment operator owns the database, backups and deletion; rows must not be presented as tamper-proof, legally immutable or a forensic-grade security log. Any actor with write access to the SQLite file or host can modify history; an external attacker with only user access cannot select another actor through the current server-authorized flows.

A public deployment is **NO-GO** until the accountable data owner defines: legal basis and per-purpose retention period, user deletion/erasure handling including backups, export/restore retention, access review, backup encryption where applicable, and whether append-only enforcement or external tamper-evident copies are legally required. This is tracked under C-25/C-26/C-47. Do not invent a fixed public retention period without the operator's actual requirements.

## Evidence and alternatives

- `tests/Flow/HttpFlowTest.php`: end-to-end seven accepted events, failed login and denied-CSRF separation, unauthorized admin requests, missing artifact and logout-failure behavior.
- `tests/ActivityTracking/ActivityLogTest.php`: canonical combinations, historical labels, UTC timezone normalization, failed actor input, transactional rollback.
- `tests/Downloads/DownloadFileTest.php`: fixed artifact read before accepted download event, failure behavior.
- `src/ActivityTracking/migrations/001_create_activity_events.sql`: database taxonomy constraint.
- Kleppmann, *Designing Data-Intensive Applications*, Ch. 11, distinguishes a business-level event log from lower-level change capture. This task deliberately **does not** implement event sourcing, CDC, a broker or an immutable/compliance audit service.

**Revisit:** new public JSON client, out-of-process consumers, payment/inventory, regulatory retention requirements, or measured replay/prefetch duplication.

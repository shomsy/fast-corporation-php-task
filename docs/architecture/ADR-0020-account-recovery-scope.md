# ADR-0020 — Account disable, recovery and reset scope

**Decision:** Defer account disable/self-service password reset as optional product capabilities for this interview application. C-20 is an assessment, **not** an implementation authorization.

## Evidence

The recruiter contract in `TASK.md` requires registration, login/logout and user/admin activity views, not password reset, account suspension, delegated administration or identity recovery. `Accounts` is the authoritative bcrypt credential owner; `SessionIdentity` reloads account and role by ID, expires sessions, rotates on login and rejects missing accounts. The trusted `bin/create-admin.php` command can provision a separate privileged account under the shared password policy; it does **not** recover or reset an existing admin account and is never a web route.

## Decision and current limitations

- Do not add a `disabled_at` schema column, reset-token service, mail dependency, privileged recovery HTTP endpoint or new admin UI without a demonstrated user journey and security owner.
- Explicit account deletion already invalidates a previously established identity on subsequent lookup; **account disable and immediate cross-worker session revocation are not implemented**. Do not claim otherwise.
- A lost sole-administrator password cannot be recovered through the existing application. In an isolated, operator-managed demo an authorized operator may provision an additional admin through the trusted CLI, audit the change operationally and retire the old account under a separately approved procedure. This is **not** a public recovery policy.
- Do not provide a generic token/JWT service merely because password reset is postponed; PHP sessions remain the approved authentication mechanism.

## Reopen triggers

Public identity lifecycle, employee offboarding, formal abuse takedowns, compromised credentials, regulated retention/deletion, administrator account recovery, or product ownership of notifications require a new decision. Before public launch with such requirements, specify ownership and proof for:

1. Admin-authorized disable/enable workflow and immutable audit reason; migrations and explicit rollback.
2. Authentication denial and revocation of *all* active sessions of disabled accounts across workers, with browser/API regression tests.
3. One-use, expiry-bounded reset flow and safe delivery channel (avoid user enumeration and reset-token leakage).
4. Break-glass administrator recovery with independent authorization, audit, safe rotation and restore rehearsal; do not confuse `create-admin` with reset.

These requirements do not become automatically satisfied by an interview GO. Public production stays **NO-GO** while the deployment owner has not accepted identity recovery and all other host-specific controls under C-47.

**Revisit on:** Public deployment/product identity-policy decision. No schema or runtime mutation is made by this ADR.

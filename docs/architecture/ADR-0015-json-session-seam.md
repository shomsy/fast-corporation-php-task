# ADR-0015 — Parallel SSR and JSON session transport without domain duplication

Status: Accepted / C-29 (2026-10-09). Owner: HTTP composition + Accounts. Rollback: remove the two /api GET routes and SessionApi; SSR remains unchanged.

## Verified change boundary

The application already separates durable authentication, authorization, audit and business operations into Accounts, ActivityTracking, CowOffer and Downloads. Their exported methods do not render HTML or construct HTTP responses. SSR adapters in `bootstrap/Http` own form parsing, status/redirect mapping and template selection. `Router` owns route access and CSRF policy. The SPA needs another **transport**, not a second authentication implementation.

Added two read-only same-origin endpoints:
- `GET /api/session` responds `{ "user": null }` when anonymous or `{ "user": { "id", "username", "role" } }` after authoritative account reload.
- `GET /api/csrf` responds `{ "csrfToken": "..." }` bound to the same PHP session used by the SSR form. Successful login rotates session ID and CSRF token through existing Accounts behavior.

`SessionApi` depends on `CurrentAccount` and `Csrf`. It cannot create sessions of its own, persist or authorize roles, handle passwords or query SQLite directly. `Response::json` centrally owns JSON encoding and safe transport Content-Type; JSON_HEX_* encoding prevents accidental HTML execution if a future view embeds JSON. All responses inherit `Cache-Control: no-store` and the existing security headers.

Public `GET` for session/CSRF is not public authority: it reads only the caller's cookie-bound state. Nothing returns a cookie session ID, password, hash or privileged mutable account object. Future `POST /api/*` routes **must** continue to pass the central fail-closed authorization/CSRF policy and handle JSON body/header parsing explicitly. The current JSON GET seam does **not** mean the full API or SPA is implemented.

## Change-impact checks

- **HTML layout change:** edit owner templates or SSR renderer; `SessionApi` and business operations unchanged.
- **JSON envelope change:** edit `SessionApi` transport tests; existing SSR remains unaffected.
- **Credential/role policy change:** Accounts owner changes, both transports read the same authoritative result.
- **Storage change:** Accounts/Infrastructure adjust behind existing business exports; no alternate SQL in JSON adapters.
- **Security policy change:** `Router` guard and `Csrf` remain one enforcement source.

`composer quality` checks PHPStan and Deptrac on these dependencies. `SessionApiTest` characterizes anonymous/authenticated JSON projections, no sensitive fields, token rotation and HTML-safe JSON. `live_smoke.py` exercises the actual server for session/CSRF before and after login and after logout, alongside the unchanged SSR login/download/admin flows.

## Alternatives rejected now

Do not add a PSR-7/15 wrapper, a generic response mapper, a DI container, JWT, or a duplicate `AccountService`. None protects an additional invariant for these two simple GET operations. Add JSON write flows feature-by-feature only after explicit API contracts and request-body/CSRF regression tests.

Reference: Matthias Noback, *Advanced Web Application Architecture*, ch. 2–4: isolate use cases and infrastructure details so another presentation mechanism can call the same application behavior without embedding SQL/auth decisions in controllers.

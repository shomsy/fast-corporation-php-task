# Vanilla JavaScript SPA & JSON API — Low-Level Design

**Status:** APPROVED DIRECTION / NOT IMPLEMENTED. **Decision date:** 2026-10-09.
**Companion documents:** [Canonical plan](CANONICAL-REMEDIATION-PLAN.md), [execution program](EXECUTION-PROGRAM.md), [review crosswalk](REVIEW-TRACEABILITY.md).
**Original task:** [TASK.md](../../TASK.md).

## ADR — Vanilla JS, same-origin cookie sessions, no JWT

Use modern ES Modules, native DOM, Fetch, History API, AbortController, and Vanilla CSS. Keep the existing PHP modular monolith and SQLite. Serve frontend and JSON API on the same origin (no CORS). Use server-owned PHP session with HttpOnly, HTTPS Secure, SameSite=Lax cookies, protected CSRF token, and server-side role checks. Do not store credentials or access tokens in localStorage/sessionStorage. Do **not** introduce JWT, refresh-token rotation, npm runtime bundles, React/Vue, a framework-like virtual DOM, or a DI container. SSR is a legitimate simpler alternative for the interview; SPA is an explicit user-approved modernization, not proof that SSR is poor architecture.

PHP modules are the only authoritative business/identity/audit state. Frontend state is presentation, navigation, transient thankYou, and loading/errors. Every class or file must own a necessary responsibility. Prefer functions for stateless behavior and classes for meaningful state/lifecycle. Keep naming business-first / screaming architecture and narrow cross-owner module exports.

## Responsibilities and change boundaries

| Owner | May do | Must not do |
|---|---|---|
| PHP business owner (Accounts/CowOffer/Downloads/ActivityTracking) | business invariants, authorization decisions, persistence and accepted audit actions | HTML, DOM, client-provided account authority |
| PHP HTTP JSON adapter | input validation, session/CSRF policy, status/error mapping, JSON serialization | duplicate business rules, UI geometry |
| Frontend feature coordinator | respond to UI events, own pending requests, update transient state, mount/unmount | raw fetch implementation, direct SQL, trusting browser role |
| Frontend feature view | DOM creation, accessible feedback/focus, user-visible rendering | HTTP requests or business permission decisions |
| Frontend feature API module | endpoint names, validated JSON response interpretation | DOM/event listeners |
| Shared frontend HTTP client | same-origin fetch, CSRF, timeout/abort, error envelope | feature-specific business workflows |
| SPA shell/navigation | route selection, History API, lifecycle/disposal, not-found | business logic or global reactivity runtime |

**Names:** directory communicates capability, file communicates responsibility, method communicates action. No mechanical triplets Api/Service/View for every feature. Move shared functions out of slices only when more than one independent owner needs them.

## Illustrative code structure (not a prescribed class quota)

```text
frontend/
  app/
    startApp.js
    navigate.js
  Accounts/
    Login/showLogin.js
    Login/LoginView.js
    Register/showRegister.js
    Session/getCurrentSession.js
  CowOffer/
    BuyCow/showBuyCow.js
    BuyCow/BuyCowView.js
  Downloads/
    DownloadSample/showDownloadSample.js
  ActivityTracking/
    Statistics/showStatistics.js
    Reports/showReports.js
    Reports/calculateChartGeometry.js
  shared/
    http/requestJson.js
    http/csrfToken.js
    dom/createText.js
public/
  app/index.html          # shell for known /app/* routes
  assets/app.css          # self-hosted, no external CDN
```

Files are created only as responsibilities become real, not pre-generated for symmetry. No inline unsafe scripts/styles; CSP updated and tested for same-origin assets. JS imports must not form circular feature dependencies.

## Native SPA lifecycle LLD

1. **Bootstrap:** browser loads shell and ES module; query `GET /api/session` to resolve current account (or null). Obtain CSRF token from `GET /api/csrf` as needed. Never expose password hash, session ID, or role authority to the client.
2. **Navigate:** handle a known internal path with History API. Deep links and refresh work because server returns SPA shell only for supported `/app/*`, never for API, static assets or arbitrary unknown paths. Back/forward and 404 are tested.
3. **Mount:** a screen gets an AbortController, activation ID, DOM root, and bounded listeners. Cleanup on navigation: abort old requests, detach listeners, ignore late responses. Keep no global feature singleton with stale privileged data.
4. **Render:** use createElement and textContent for all untrusted strings. No interpolation of user/server content with innerHTML. Client validation is UX only; server revalidates everything. Accessible labels, form feedback, focus restoration and loading status.
5. **Errors:** cancellation is silent; network errors yield retry UI, 401 redirects/login, 403 denies, 422 shows safe field messages, 429 respects Retry-After, unexpected 5xx shows a generic message and correlation ID. Never blindly retry mutating POST.
6. **Unmount/logout:** clear private UI and in-memory data on logout and session expiry. No sensitive localStorage/sessionStorage; tab reload rehydrates via cookie-backed session endpoint.

## JSON API — proposed contract, freeze with tests before implementation

All APIs share origin with SPA, send protected session cookie, default to no-store for authenticated JSON, and use a central CSRF policy for unsafe methods. Below are **future paths**, not existing code.

| Route | Method | Role + CSRF | Success | Errors |
|---|---|---|---|---|
| `/api/csrf` | GET | anonymous allowed | 200 `{csrfToken:string}`; no-store | 500 |
| `/api/session` | GET | anonymous allowed | 200 `{user:null}` or `{user:{id,username,role}}` | 500 |
| `/api/accounts/register` | POST | anonymous+CSRF | 201 `{registered:true}` (no auto-login) | 403/409/422/429 |
| `/api/accounts/login` | POST | anonymous+CSRF | 200 `{user:{id,username,role},csrfToken:string}` after session+CSRF rotation | 401/403/429 |
| `/api/accounts/logout` | POST | signed-in+CSRF | 204, cookie/session invalidated | 403/500 |
| `/api/cow-offer/view` | POST | signed-in+CSRF | 204, accepted Page A view event | 401/403/500 |
| `/api/cow-offer/buy` | POST | signed-in+CSRF | 200 `{outcome:"thankYou"}` | 401/403/409/429/500 |
| `/api/downloads/page/view` | POST | signed-in+CSRF | 204, accepted Page B view | 401/403/500 |
| `/api/downloads/sample` | POST | signed-in+CSRF form | 200 attachment binary; not JSON | 401/403/503/500 |
| `/api/activity/statistics` | GET | admin only | 200 `{events:[...],nextCursor:null|string}` | 401/403/422 |
| `/api/activity/reports` | GET | admin only | 200 `{days:[{date,pageAViews,pageBViews,buyCowClicks,downloadClicks}]}` | 401/403/422 |

**Contract caveats:** Choose and test keyset cursor versus numbered pagination (C-16); choose active-day versus continuous-calendar reporting semantics (C-17) before freezing API schemas. Every input needs type, length, calendar and range bounds. Do not silently convert invalid input to null. No raw SQL/exception/body leak.

A normal error uses a stable machine code and safe message, e.g.:

```json
{"error":{"code":"invalid_date","message":"Date must be YYYY-MM-DD","requestId":"opaque-id"}}
```

All private API/HTML/download responses receive correct Cache-Control + security headers. Do not use HSTS on an unready HTTP-only development endpoint; enforce it only for intended HTTPS host. No unsafe-inline JavaScript.

### Security transition and CSRF detail

Same-origin fetch uses `credentials: 'same-origin'`. Token can be sent as `X-CSRF-Token` on JSON POST; native download POST uses a server-generated hidden field. Existing manually guarded forms stay protected throughout the cutover. Login must rotate **both** session ID and CSRF token. Existing anonymous-tab tokens become invalid; define explicit safe refresh UX, not unconditional replay of login/purchase. Server maintains authoritative admin role and account state on each privileged action. CORS is not a reason to add JWT.

### Page-view and click semantics

Original `TASK.md` counts accepted view-page and button-click events. Old SSR records Page A/B view on GET, which can include refreshes; the SPA should emit a protected, server-validated view event on deliberate route activation, and not track speculative prefetch. This is a change requiring characterization and documented parity choice (C-19), not a hidden silent metric redefinition. For Buy Cow, `thankYou` is UI-local and button returns after reload; no purchase/order/payment state. Browser double-submission policy requires an explicit product decision (C-18). For binary downloads prefer **native HTML POST form submission** rather than JS fetch->blob buffering; it preserves download behavior and CSRF, without storing entire artifact in JS memory. A download audit event means response prepared, not fully delivered.

### Statistics and reports

Admin-only; never trust frontend role gating. Filter date/user/action; maintain stable sorting. Report chart and table must derive from one data model. A pure chart-geometry function handles sparse dates, zero-days per selected policy, max=1 ticks, and no duplicated Y labels. Series distinguishable without color alone; table is the accessible fallback. Empty/loading/error/denied states all implemented.

## Testing & failure matrix

- **Backend:** PHPUnit unit/HTTP contract, PHPStan, Deptrac, security audit, live HTTP smoke; regression for 422 NUL, authentication timing branch, role demotion, CSRF all unsafe routes, logout/audit failure, migration drift, SQLITE_BUSY and backup/restore.
- **Frontend unit:** native JS pure functions (`node --test` if development Node chosen), screen coordinator injected API/view fakes, chart geometry, URL parsing, navigation cleanup, cancel/stale response.
- **Browser E2E:** free OSS Playwright or equivalent only if operational cost justified; no paid service. Test register->login->CowOffer->download->admin stats/reports->logout, deep links, back/forward, accessibility, CSRF, role changes and XSS text payloads.
- **Load/reliability:** repeat navigation and concurrent tabs for memory/listener leaks, aborted requests, duplicate submissions, p95/p99 and DB reader-writer pressure at documented workloads.
- **Security:** XSS textContent, secure cookie, no token storage, CSRF header, Origin/Fetch Metadata as defense-in-depth, rate limits, safe JSON error envelope and request IDs.

## Incremental strangler, safe cutover

1. Keep SSR `/login`, `/page-a`, `/page-b`, `/statistics`, `/reports` working as regression oracle.
2. Add JSON endpoints adjacent to existing adapters; no duplicate domain implementation and no permanent dual writes.
3. Pilot new `/app/login` using one SPA shell, real same-origin cookie and CSRF. Test reload/rollback.
4. Convert bounded slices: Accounts, CowOffer, Downloads, Statistics, Reports; validate browser and HTTP parity after **each**.
5. Move route ownership only after acceptance tests and rollback rehearsal; old templates must remain until unused.
6. Retire old SSR presentation and its renderer only when parity and no-traffic proof exists. Do not delete valuable server-side auth/business behavior.

**Explicit non-goals:** frontend framework in disguise, JWT, speculative global state/event bus, one-class-per-file dogma, Redis/queues/microservices, wholesale PHP ports rewrite, unsupported performance promises.

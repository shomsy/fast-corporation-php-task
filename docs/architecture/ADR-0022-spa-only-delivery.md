# ADR-0022 — Sole Vanilla JavaScript SPA presentation

**Status:** Accepted and implemented, 2026-10-09. **Owner:** Delivery boundary. Supersedes the presentation-mode switch in ADR-0019.

## Problem and decision

The incremental SPA rollout was accepted and browser-verified, but leaving SSR as the default and retaining duplicate PHP templates prevented the intended separation of concerns. The user explicitly required one SPA frontend, no JavaScript runtime libraries and PHP as a same-origin backend.

The supported frontend is now native HTML, CSS, browser ES modules, DOM APIs, fetch, History API and AbortController. PHP serves a fixed SPA shell/assets and JSON APIs, with one native binary download response. Business rules, identity, SQL, activity audit and CSRF remain server-authoritative. Client-side screens render only via DOM creation and textContent; no runtime npm dependencies, front-end framework, JWT, client-side credential storage or bundler.

The seven historical document GET addresses now return HTTP 303 redirects to the corresponding /app/* screens, preserving allowlisted query filters for statistics and reports. The legacy form POSTs and PHP-template renderer are removed. Unknown /app routes are a server 404; deep links for named screens load the same SPA shell. An obsolete UI_ENTRYPOINT=ssr explicitly fails boot, while unset or spa resolves to the sole presentation.

## Consequences and acceptance

An earlier same-database SSR fallback is **no longer possible** in this release. Rollback means deploying a previously tested Git artifact using the existing database contract, not toggling a flag to removed code. The historical ADR-0019 remains evidence of the earlier reversible transition but is superseded as the runtime policy.

SPA navigation cancels old requests, disposes listeners, supports query-string filters without document reload and handles History back/forward. Session cookies are HttpOnly, CSRF remains enforced centrally, and a fixed server-selected executable is downloaded natively without a client JS blob.

Quality evidence consists of Composer/PHPStan/Deptrac/PHPUnit, native Node tests, Playwright browser journeys, live same-origin API acceptance and SPA-only route tests. Those checks prove local interview functionality, **not** public-production TLS, ingress, off-host backup, service identity or PHP-FPM capacity. Public rollout remains NO-GO pending separate host acceptance.

**Runtime dependency:** none added for the frontend. Playwright remains a development-only test dependency.

# ADR-0017 — Minimal PHP transport and Vanilla JavaScript tooling

Status: Accepted / C-33, 2026-10-09. Owner: Backend HTTP + future SPA. No dependencies introduced.

## Question and constraints

Compare replacing the existing narrow HTTP stack, SSR templates, structured diagnostics and future SPA router/chart with maintained OSS alternatives. The product is a single-origin PHP 8.3+ SQLite modular monolith with a Vanilla JS ES-module SPA (no bundler, no JWT, no frontend framework). This is **not** a blanket judgment about the quality of competing packages: libraries are justified only if their capabilities solve demonstrated maintenance, security or change-pressure problems.

Current deployed runtime dependency set is PHP/PDO, sessions and PSR Clock. Project tests are PHPUnit, PHPStan and Deptrac. The real HTTP acceptance suite already exercises URI/method/HEAD/OPTIONS behavior, same-origin session/CSRF, roles, binary response and safe 4xx/5xx. C-29 added JSON read transport without changing use-case internals.

| Decision surface | Maintained OSS alternative and genuine value | Present behavior, maintenance/security/cost comparison | Decision and revisit trigger |
|---|---|---|---|
| PHP router/request/response | Slim 4 with PSR-7/PSR-15 middleware and ecosystem integrations | The existing small Router/Request/Response already has tested fail-closed route access, CSRF, HEAD/OPTIONS and JSON, while switching would require adapters, framework dependency updates and replacing tested behavior | **Retain native implementation** for this bounded app. Reconsider if multiple middleware vendors, HTTP negotiation requirements or external PSR packages create real integration pressure. |
| SSR templates | Twig 3 with default HTML auto-escaping, inheritance and template tooling | Existing HTML renderer performs escaping with scoped tests, and is a temporary parity oracle during SPA rollout; adding a template engine just before retirement increases surface, patch obligation and duplicate migration effort | **Retain tested SSR temporarily; retire after SPA parity**. Reconsider Twig only if a sustained server-rendered UI needs composition/i18n/escaping contexts that existing templates cannot safely handle. |
| Logging/diagnostics | Monolog implementing PSR-3, configurable handlers/formatters | Existing allowlisted structured security diagnostic events have no third-party sink requirement. Added handlers/config/transports would expand secrets exposure and maintenance. A real centralized ops sink may make PSR-3 worth it | **Retain existing bounded diagnostics**; revisit with deployment-specific aggregation, retention and alerting. |
| Frontend navigation | Navigo or similar maintained client router; route guards and URL matching | This task has a handful of known /app routes. Native History/popstate and scoped screen lifecycle can be tested directly without a dependency or generic state/router framework | **Use native History API and ES modules**. Reevaluate only with many dynamic/parameterized routes or measurable navigation complexity. |
| Chart rendering | Chart.js plus plugins and accessible fallback table | Chart.js provides many chart types and plugins, but uses canvas with accessibility work required. Existing SVG handles four series and an accessible table; future pure geometry functions can preserve exact UTC gaps | **Keep SVG + table and pure chart geometry**; reconsider if many chart types/interactions justify library size and update burden. |
| Frontend tests | Node built-in `node --test` for pure ES modules; Playwright for actual browser E2E when used | Development-only tools do not imply production bundle. Real browser tests become necessary with SPA screens; native JS unit tests need no framework. Playwright test/runtime installation should wait for the first real SPA UI slice | **Use native module tests initially**, then acquire a maintained browser E2E runner with real accessibility/nav requirements; no shipping npm dependency until justified. |

## Operational assessment

- **Production package count and attack surface:** no extra runtime package introduced by this decision. Fewer third-party packages means fewer upstream security advisories and update chores, but it does **not** excuse missing tests, security headers, CSRF, or homegrown framework design. Keep `composer audit` and full HTTP smoke gates.
- **Ecosystem and maintenance:** Slim/Twig/Monolog/Chart.js have useful ecosystems; feature requirements are currently too small for their integration and rollout cost. Avoid speculative promises about exact project health or package byte sizes; reassess maintenance with current upstream releases at adoption time.
- **Portability:** native ES modules are browser-standard; same-origin server cookie/CSRF removes JWT, CORS, token persistence and client refresh protocols from the design.
- **No permanent homegrown framework:** keep Router intentionally small and feature-oriented. If it grows middleware registries, route annotation DSLs or abstraction machinery, reconsider a library rather than accumulating a mini framework.
- **Reversibility:** preserve SSR as verified rollback path during C-37–C-46. Do not delete templates until JSON + SPA parity and deployment rollback gates pass.

## Source and verification

1. Local `composer.json`, `bootstrap/routes.php`, `runtime/Http/`, `docs/roadmap/FRONTEND-SPA-LLD.md` and C-29 JSON transport regression/live smoke.
2. Slim 4 PSR-15: https://www.slimframework.com/docs/v4/concepts/middleware.html
3. Twig 3 autoescaping and implementation: https://twig.symfony.com/doc/3.x/templates.html
4. Chart.js canvas accessibility responsibility: https://www.chartjs.org/docs/latest/general/accessibility.html
5. Native JavaScript ES modules: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules
6. Matthias Noback, *Advanced Web Application Architecture*, ch. 2–4 (independent use case/application behavior versus infrastructure and presentation mechanism).

Acceptance: an explicit adopt/defer choice, criteria for reversing it, no new production dependency, unchanged SSR/auth/audit and approved same-origin Vanilla JS SPA direction. This does not claim SPA implementation is complete.

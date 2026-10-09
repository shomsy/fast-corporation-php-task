# C-35 — Test quality gate evidence

**Decision:** The confirmed testing gap has been closed with focused regressions and genuine integration tests. No second test framework is needed.

## Executable gates

- `composer quality`: strict Composer validation, PSR-4, dependency audit, PHPDoc, PHPStan, Deptrac, PHPUnit. Local PHP 8.5 result: **158 tests, 6,737 assertions**, static/architecture/audit checks PASS.
- `python3 tests/Flow/live_smoke.py`: registration/login/logout, roles, SSR activity reports, download, failed audit write and failed database initialization.
- `python3 tests/Flow/route_cutover.py`: same SQLite database under SPA and SSR document routing; known redirects and rollback.
- `npm run test:frontend`: **21/21** safe DOM, keyboard/focus, session, CSRF, navigation abort, duplicate-submit and feature tests.
- `npm run test:browser`: real Chrome/Playwright with temporary PHP+SQLite; login errors, cow click, pinned binary hash, user/admin permissions, statistics filter and chart.
- GitHub Actions workflow declares PHP **8.3, 8.4, 8.5** matrix. Node/Chrome checks run under PHP 8.5. A declared job is not a verified remote run; record Actions results independently when available.

## Regression and failure-class provenance

- Invalid UTC dates, nested renderer buffers, auth/CSRF transition and audit persistence: existing RED-to-GREEN PHPUnit and real HTTP tests.
- Negative security cases: anonymous `401`, signed-in non-admin `403`, invalid query `422`, expired session and bad CSRF; exact audit and failed downloads.
- Browser-only CSP failure: real Chrome `Failed to fetch`, followed by failing response-header regression and narrow `connect-src 'self'` fix (C-45, commit `61a3377`).
- Route owner migration and rollback: C-46 real HTTP and Chrome tests, commit `7a779c7`.

Classify failures by **contract**, **security**, **persistence**, **architecture**, **browser behavior** or **environment/fixture** before changing application code. A transient runner failure is not automatic proof of an app bug or reason to disable a test.

The local gate is demonstrated; host-specific HTTPS, proxy abuse controls, PHP-FPM capacity and disaster-recovery objectives remain public-production gates (C-05/C-47), not part of C-35.

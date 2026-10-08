# PHP Standards & Maintainability — vNext

Status: Implemented, with local acceptance complete; exact final-commit CI is verified after push.
Decision classification: L1 — internal refactoring, no change of ownership, data semantics, or public HTTP behavior.
Baseline: `75f1c5c` (Governance 1.7.1), followed by behavior-preserving refactor `04ee86f`.

## Rationale

The app was already runnable and covered by tests, but its `InterviewTask\Bootstrap\WebPages`
class was loaded manually and concentrated unrelated HTTP change reasons.

The accepted owner boundaries remain `Accounts`, `ActivityTracking`, `CowOffer`, `Downloads`.
Technical HTTP mapping remains at the composition edge; no owner imports Runtime HTTP types.

## Phase 1 — PSR-4 autoloading

- `InterviewTask\Bootstrap\` now maps to `bootstrap/` in Composer's PSR-4 section.
- Removed manual includes for the former `WebPages.php` class in routes and tests.
- Deliberate `require` of bootstrap scripts and view templates is retained: they are
  executable PHP resources, not PSR-4 class declarations.
- `composer dump-autoload --optimize --strict-psr` passes.
- `tests/Flow/ComposerAutoloadTest.php` proves every new HTTP adapter loads using
  only the project's Composer bootstrap.

## Phase 2 — HTTP adaptation per business behavior

The oversized `bootstrap/WebPages.php` was replaced with:

```text
bootstrap/
├── app.php
├── config.php
├── routes.php                 # explicit construction and routing
├── Http/
│   ├── AccountPages.php       # register/login/logout/home
│   ├── CowOfferPages.php      # page A and buy-cow
│   ├── DownloadPages.php      # page B and download
│   ├── ActivityPages.php      # admin statistics and reports
│   └── HtmlPage.php           # neutral HTML rendering + security headers
└── views/
    ├── page-a.php
    ├── page-b.php
    ├── statistics.php
    └── reports.php
```

There is no forwarding-only `WebPages` facade, Controller/Service/Repository
layering, container, framework or generic handler tree. The split reflects the
actual independent HTTP behavior groups. Business decisions remain with the
respective owners, authorization policy remains with `Accounts`, and shared
response security headers have one neutral implementation.

The Flow tests target the four adapters directly and continue to check actual
behavior, authorization, session and activity failure modes.

## Phase 3 — Coding style decision

PHP-FIG published PER Coding Style 3.1 on 2026-08-28. The locked PHP-CS-Fixer
3.95.27 supports the explicit `@PER-CS3x0` preset, described by its own
documentation as rules following PER Coding Style 3.0. We upgraded from
`@PER-CS2.0` to `@PER-CS3x0` while retaining project-specific rules:
`declare_strict_types`, `no_unused_imports`, `ordered_imports`, `strict_param`.

An uncached check reported **0 of 74 PHP files to fix**. This is a supported
PER-CS 3.0 tooling baseline; it is **not a claim of full PER-CS 3.1 compliance**.
Do not use the moving `@PER-CS` alias without an explicit version policy.

## Test and final acceptance policy

For each behavioral change, code was completed before scoped PHPUnit and then
the complete PHPUnit suite. Only after the implementation was functionally
complete were PHPStan, Deptrac, formatter, Composer validation/audit, optimized
strict PSR-4 generation, and full user/admin browser smoke used as final gates.

Acceptance evidence from the local vNext run (2026-10-08):

- Composer strict optimized PSR-4 generation PASS (3,557 autoloadable classes).
- Scoped Flow tests PASS: 13 tests / 92 assertions; full PHPUnit PASS: 51 tests / 415 assertions.
- PHP-CS-Fixer PASS: 0 of 74 PHP files require changes with explicit pinned ruleset.
- PHPStan max PASS: 0 errors; Deptrac PASS: 0 violations, 0 skipped, 0 errors.
- Composer validation PASS; dependency audit PASS (no vulnerability advisories).
- Fresh SQLite migrations and second idempotent run PASS.
- Real user and admin HTTP journeys, fixed download and admin CLI bootstrap PASS.
- Previous refactor commit `04ee86f` pushed to `origin/main`, exact GitHub Actions run `37713637004` PASS on PHP 8.3, 8.4 and 8.5.
- The final style/docs commit requires its own clean working tree, synchronized `main` and exact-commit remote CI PASS.

## Deliberate exclusions / REVISIT

No PSR-7/15/17/11/3 implementation, HTTP framework, DI container, ORM, queue,
event bus, or unrelated format churn: none is required by the task or existing
integration contracts. Revisit standards adoption only when an actual external
library/API interface or demonstrable maintenance pressure earns it.

No new business boundary, invariant owner, or database change was introduced.

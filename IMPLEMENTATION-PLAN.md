# Implementation Plan

Status: Active implementation plan  
Project: Fast Corporation PHP Developer technical task  
Architecture level: L3 greenfield, applied proportionally  
Repository language: English only

## Goal

Deliver a small, secure, reviewer-friendly Vanilla PHP application that satisfies the task exactly, shows senior engineering judgment, and avoids speculative architecture.

The project follows Architecture Governance 1.3.2 and Compass Engineering guidance. Architecture decisions are driven by behavior, authoritative knowledge, invariants, ownership, security, data integrity and change pressure. Pattern names are never the justification.

## Selected architecture

One deployable, two business owners, synchronous in-process communication.

### Accounts

Owns:

- user identity;
- email uniqueness;
- credential hashing and verification;
- roles;
- authentication lifecycle;
- current signed-in authority;
- registration, login, logout and admin bootstrap behavior.

### Activity

Owns:

- append-only user activity facts;
- action and target vocabulary;
- Page A and Page B tracked interactions;
- Buy-a-cow click tracking;
- download click tracking;
- Statistics filtering;
- Reports aggregation.

Page A, Page B, Buy Cow and Download are behaviors, not independent business owners. Reports and Statistics are read behaviors over the same authoritative activity log; no CQRS split is justified.

## Target project tree

```text
fast-corporation-php-task/
├── README.md
├── TASK.md
├── IMPLEMENTATION-PLAN.md
├── composer.json
├── composer.lock
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.dist.php
├── .env.example
├── .editorconfig
├── .gitignore
├── .github/
│   └── workflows/
│       └── ci.yml
├── docs/
│   ├── ARCHITECTURE-GOVERNANCE.md
│   └── architecture/
│       ├── ADR-0001-system-architecture.md
│       ├── boundaries.md
│       ├── constraints.md
│       ├── complexity.md
│       └── enforcement.md
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/app.css
│       └── js/
│           ├── buy-cow.js
│           └── report-chart.js
├── bootstrap/
│   ├── app.php
│   └── routes.php
├── src/
│   ├── Accounts/
│   │   ├── User.php
│   │   ├── Role.php
│   │   ├── Users.php
│   │   ├── Passwords.php
│   │   ├── Access.php
│   │   ├── SignedInUser.php
│   │   ├── Register/
│   │   │   ├── Register.php
│   │   │   └── register.phtml
│   │   ├── Login/
│   │   │   ├── Login.php
│   │   │   └── login.phtml
│   │   ├── Logout/
│   │   │   └── Logout.php
│   │   ├── CreateAdmin/
│   │   │   └── CreateAdmin.php
│   │   └── migrations/
│   │       └── 001_create_accounts_users.sql
│   └── Activity/
│       ├── ActivityLog.php
│       ├── ActivityEvent.php
│       ├── Action.php
│       ├── Target.php
│       ├── Actor.php
│       ├── DateRange.php
│       ├── RecordActivity.php
│       ├── CowPage/
│       │   ├── CowPage.php
│       │   └── cow-page.phtml
│       ├── BuyCow/
│       │   └── BuyCow.php
│       ├── DownloadPage/
│       │   ├── DownloadPage.php
│       │   └── download-page.phtml
│       ├── Download/
│       │   ├── Download.php
│       │   └── InstallerFile.php
│       ├── Statistics/
│       │   ├── Statistics.php
│       │   ├── StatisticsFilter.php
│       │   └── statistics.phtml
│       ├── Reports/
│       │   ├── Reports.php
│       │   ├── EngagementReport.php
│       │   ├── EngagementDay.php
│       │   └── reports.phtml
│       └── migrations/
│           └── 001_create_activity_events.sql
├── runtime/
│   ├── Http/
│   │   ├── Kernel.php
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── FileResponse.php
│   │   ├── PhpSession.php
│   │   ├── Csrf.php
│   │   └── View.php
│   ├── Database/
│   │   ├── Connection.php
│   │   ├── Transaction.php
│   │   └── Migrator.php
│   └── Clock/
│       ├── Clock.php
│       └── SystemClock.php
├── files/
│   └── sample.exe
├── bin/
│   ├── migrate.php
│   └── create-admin.php
├── tests/
│   ├── Support/
│   │   ├── FixedClock.php
│   │   ├── TestDatabase.php
│   │   └── TestApp.php
│   ├── Accounts/
│   │   ├── RegisterTest.php
│   │   ├── LoginTest.php
│   │   ├── LogoutTest.php
│   │   ├── AccessTest.php
│   │   └── PasswordsTest.php
│   ├── Activity/
│   │   ├── ActivityLogTest.php
│   │   ├── CowPageTest.php
│   │   ├── BuyCowTest.php
│   │   ├── DownloadPageTest.php
│   │   ├── DownloadTest.php
│   │   ├── StatisticsTest.php
│   │   └── ReportsTest.php
│   ├── Journeys/
│   │   ├── UserJourneyTest.php
│   │   └── AdminJourneyTest.php
│   └── Architecture/
│       ├── DependencyRulesTest.php
│       └── TableOwnershipTest.php
├── tools/
│   └── deptrac.yaml
└── var/
    ├── database/.gitkeep
    └── log/.gitkeep
```

The target tree is a direction, not a quota. Any file or abstraction that proves shallow during implementation will be removed rather than preserved for symmetry.

## Data model

### accounts_users

Expected fields:

- id
- email — unique
- password_hash
- role — user or admin
- created_at

Structural invariants:

- email is unique;
- public registration always creates role user;
- plaintext passwords are never persisted.

### activity_events

Expected fields:

- id
- actor_id
- actor_label
- action
- target
- occurred_at

Canonical actions:

- registration
- login
- logout
- view-page
- button-click

Canonical targets:

- page-a
- page-b
- buy-cow
- download
- nullable when the action has no meaningful target.

actor_label is a historical display snapshot. actor_id is the stable identity reference. Activity must not read private Accounts tables directly.

No cow_purchases table is planned because persistent purchase state is not a requirement.

## Security baseline

The implementation must include:

- password_hash() and password_verify();
- session ID regeneration after successful authentication;
- server-side session authority and logout invalidation;
- HttpOnly cookies;
- deliberate SameSite policy;
- Secure cookies under HTTPS;
- POST for state-changing browser actions;
- CSRF protection for registration, login, logout, Buy Cow and Download;
- server-side authorization for admin-only pages;
- registration cannot self-assign admin;
- prepared PDO statements;
- centralized HTML escaping;
- generic authentication failure messages;
- fixed server-side download path;
- no credentials or session identifiers in logs.

UI visibility is never treated as authorization.

## Tracking semantics

Activity tracking is product behavior for this task, not best-effort telemetry.

Record only successful actions:

- successful registration -> registration
- successful login -> login
- logout -> logout before authority is destroyed
- successful Page A render -> view-page/page-a
- successful Page B render -> view-page/page-b
- successful Buy Cow request -> button-click/buy-cow
- accepted Download request -> button-click/download

Where multiple local database writes represent one accepted action, use the smallest local transaction that keeps observable state consistent.

## Statistics and reports

Statistics is admin-only and supports date, user and action filters.

Reports is admin-only as a documented assumption until clarified otherwise.

One aggregate dataset feeds both the graph and the table. It exposes daily counts for:

- Page A views;
- Page B views;
- Buy Cow clicks;
- Download clicks.

No pre-aggregated table or CQRS projection is planned. Aggregate directly from activity_events until measured evidence says otherwise.

## Implementation phases and planned commits

### Phase 0 — Planning and governance

Deliverables:

- task brief;
- this implementation plan;
- Architecture Governance 1.3.2 copy/reference;
- concise L3 ADR and architecture registers;
- Git repository initialized on main.

Exit: assumptions and boundaries are explicit; no application code yet.

Commit: `docs: define technical task implementation plan`

### Phase 1 — Project bootstrap and quality gates

Deliverables:

- Composer/PSR-4;
- PHPUnit;
- PHPStan;
- PHP-CS-Fixer;
- Deptrac;
- CI workflow;
- configuration bootstrap;
- migration runner.

Exit: install, static checks and an empty test suite execute successfully.

Commit: `chore: bootstrap project and quality tooling`

### Phase 2 — Runtime and persistence foundation

Deliverables:

- front controller, routing, request/response/view;
- SQLite connection;
- local transaction helper;
- clock seam;
- initial migrations;
- deterministic test database.

Commit: `feat: add runtime and persistence foundation`

### Phase 3 — Accounts

Deliverables:

- user and role knowledge;
- registration;
- login;
- logout;
- secure session lifecycle;
- server-side access policy;
- admin bootstrap command;
- positive and denied-path tests.

Commit: `feat(accounts): add authentication and access control`

### Phase 4 — Activity foundation

Deliverables:

- activity vocabulary;
- append-only activity log;
- actor snapshot;
- recording surface;
- registration/login/logout activity integration;
- transaction/failure tests.

Commit: `feat(activity): add append-only activity tracking`

### Phase 5 — Page interactions

Deliverables:

- Page A view tracking;
- Buy Cow UI transition to thankYou;
- Page B view tracking;
- fixed-file download;
- button-click tracking.

Commit: `feat(activity): add tracked page interactions`

### Phase 6 — Statistics

Deliverables:

- admin-only Statistics page;
- date/user/action filters;
- deterministic event ordering;
- combined-filter tests.

Commit: `feat(activity): add statistics filters`

### Phase 7 — Reports

Deliverables:

- daily aggregate query;
- chart and table using one dataset;
- all four required metrics;
- aggregation tests.

Commit: `feat(activity): add daily activity reports`

### Phase 8 — Security and failure hardening

Deliverables:

- CSRF abuse-path tests;
- SQL injection probes;
- XSS escaping tests;
- authorization bypass tests;
- session lifecycle tests;
- missing/unreadable download behavior;
- activity-write failure semantics.

Commit: `test: harden security and failure behavior`

### Phase 9 — End-to-end acceptance and reviewer experience

Deliverables:

- complete user journey;
- complete admin journey;
- clean-install rehearsal;
- README with setup, architecture, assumptions and commands;
- final architecture/dependency checks;
- complete quality run.

Commit: `docs: finalize reviewer setup and acceptance evidence`

## Git working discipline

The repository should look like a genuine interview submission, not a generated code dump.

- Work on main unless a risky experiment needs isolation.
- Commit coherent, reviewable increments.
- Keep implementation and tests for one behavior together where practical.
- Do not mix unrelated formatting or refactors into feature commits.
- Run scoped checks before each commit.
- Push each green logical commit once a remote exists.
- Run the complete suite before the final push.
- Do not rewrite or squash away the implementation history unless explicitly requested.

The planned commit sequence is guidance, not a quota. Do not manufacture commits when two changes are honestly one unit.

## Definition of done

Functional:

- every task requirement is implemented;
- role behavior is correct;
- requested activity events are visible in Statistics;
- all report metrics are correct.

Security:

- authentication and authorization are separate and server-enforced;
- session establishment and destruction are safe;
- CSRF, SQL injection, XSS and path traversal are covered at the relevant boundaries.

Architecture:

- business navigation exposes Accounts and Activity behavior;
- no Controller/Service/Repository ceremony in business code;
- owner data is not accessed through another owner's private storage;
- no owner dependency cycles;
- no speculative mechanisms.

Quality:

- PHPUnit green;
- PHPStan green;
- code style check green;
- Deptrac green;
- table ownership check green;
- clean setup rehearsal green;
- README complete.

## REVISIT triggers

Revisit the current design only when concrete evidence appears:

- Page A/B gain independent business state and change pressure -> reconsider a separate product/content owner.
- Report queries become materially expensive under measured workload -> consider indexes or derived daily aggregates.
- Multiple independent consumers need Activity data -> define a deliberate public contract/read model.
- Another provider/runtime must implement the same dependency -> reconsider a port at that exact boundary.
- The HTTP edge becomes materially complex -> evaluate an established framework instead of growing a home-grown runtime.

Until a trigger fires, do not build for it.

# Fast Corporation PHP Technical Task

Vanilla PHP implementation of the supplied PHP Developer interview task.

Current status: **Phase 0–2 complete; Phase 3 (Accounts) next.** The architecture is governed by Architecture Governance 1.7.1.

See `IMPLEMENTATION-PLAN.md` and `docs/architecture/ADR-0002-system-architecture.md`.

## Development requirements

- PHP 8.3+
- Composer 2

## Current foundation

- neutral exact-path HTTP request/response/router runtime;
- SQLite connection and transaction boundary;
- owner-local SQL migrations with an idempotent migration runner;
- neutral PHP session, template renderer and UTC clock;
- architecture dependency rules enforced by Deptrac.

## Quality commands

```bash
composer test
composer stan
composer cs
composer architecture
composer quality
php bin/migrate.php
```

Reviewer setup instructions will be completed with the application behaviors.

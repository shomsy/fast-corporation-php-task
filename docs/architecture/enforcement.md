# Enforcement Plan

Current status: **NOT VERIFIED** — application code and checks do not exist yet.

Planned objective checks:

- Deptrac: no owner dependency cycles and only approved cross-owner dependencies.
- Table ownership: Accounts writes only its identity data; Activity writes only its activity data.
- PHPUnit: public behavior, authorization, CSRF, session lifecycle, tracking semantics and report aggregation.
- PHPStan: static correctness.
- PHP-CS-Fixer: deterministic style.
- CI: all objective checks on every pushed commit.

Subjective properties such as semantic ownership and naming quality remain architecture-review concerns. They will not be falsely presented as machine-enforced.

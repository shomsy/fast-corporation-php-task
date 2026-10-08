# Enforcement Plan

Project governance evidence status: **PILOT**. This is not a claim of Governance VALIDATED status.

- Dependency graph: **MACHINE-ENFORCED** by `tools/architecture.yaml`, `composer architecture` and CI.
- Runtime/Infrastructure neutrality: **MACHINE-ENFORCED** by the same dependency graph.
- Owner public surface: **MACHINE-ENFORCED for statically named PHP class references** (normal/grouped imports, aliases, fully-qualified class names, constructors, type hints, static calls, etc.) using `nikic/php-parser` and `tests/Architecture/PublicSurfaceTest.php` in PHPUnit/CI. `bootstrap/routes.php` is an explicit composition exception. Class strings, reflection and runtime-computed names remain **REVIEW-ENFORCED**.
- Owner data boundaries: owner-local migrations and **MACHINE-ENFORCED static SQL-literal table references** via `tests/Architecture/DataOwnershipTest.php`; negative mutation examples reject reads/writes from foreign owner tables. Computed SQL, dynamic identifiers and external database access remain **REVIEW-ENFORCED**.
- Semantic ownership and naming: **REVIEW-ENFORCED**.
- PHP syntax/static/style/tests: **MACHINE-ENFORCED** by Composer quality scripts and CI.
- Full ENF-8 rule map: pilot deliverable; every applicable rule ID remains traceable.

- Statistics: 51-row lookahead prevents false Next; no arbitrary 50,000-event audit visibility cap, but deep OFFSET remains a measured performance REVISIT.
- Reports: a 5,000-event indexed query-plan test proves a representative case, not asymptotically bounded aggregate work or full production-scale latency.

- Auth public return-contract: **MACHINE-ENFORCED** by `PublicSurfaceTest` reflection of lifecycle method returns; unexported Account cannot leak from the exported register() API.
- Failure classification: **BEHAVIOR-ENFORCED** by real SQLite constraint regression and both in-process and live HTTP missing-audit-table download tests; only expected ArtifactUnavailable is converted to 503.
- Proportional OOP decision: redundant Logout forwarding class removed; CowOffer's business meaning and DailyReports four-field array shape retained until measured change pressure justifies restructuring.

- Account lifecycle failure safety: **BEHAVIOR-ENFORCED** by `HttpFlowTest` using a real SQLite account lookup failure; logout still invalidates the session even when recording cannot begin.
- Password policy: **BEHAVIOR-ENFORCED** by `PasswordPolicyTest` (bcrypt byte limit and NUL-byte rejection) and live CLI acceptance (invalid admin passwords exit cleanly rather than producing uncaught bcrypt exceptions).

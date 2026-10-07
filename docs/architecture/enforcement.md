# Enforcement Plan

Project governance evidence status: **PILOT**. This is not a claim of Governance VALIDATED status.

- Dependency graph: **MACHINE-ENFORCED** by `tools/architecture.yaml`, `composer architecture` and CI.
- Runtime/Infrastructure neutrality: **MACHINE-ENFORCED** by the same dependency graph.
- Owner public surfaces: target MACHINE-ENFORCED when business behavior code exists.
- Owner data boundaries: owner-local migrations exist; target an explicit machine check once owner persistence code exists.
- Semantic ownership and naming: **REVIEW-ENFORCED**.
- PHP syntax/static/style/tests: **MACHINE-ENFORCED** by Composer quality scripts and CI.
- Full ENF-8 rule map: pilot deliverable; every applicable rule ID remains traceable.

# ADR-0006 — Actor and date query paths for Activity Statistics

Status: Accepted / C-15, 2026-10-09. Owner: ActivityTracking.

## Workload and plans

Reproduce: `python3 tests/Flow/activity_query_benchmark.py`. Isolated local SQLite, 100k skewed events over 180 dates, repeated warm-query wall-clock p95 (not production SLA).

| Query variant | Before | Actor index without date guard | With date guard |
|---|---:|---:|---:|
| Rare actor, most recent 51 rows | ~13.7 ms | ~0.019 ms | unchanged |
| Frequent actor and UTC date (synthetic case) | ~0.5 ms | ~6.6 ms | ~0.48 ms |
| Latest 51 rows, no filter | ~0.02 ms | ~0.02 ms | unchanged |

Adding `idx_activity_actor_recent(actor_label COLLATE NOCASE, occurred_at DESC,id DESC)` enables equality lookup and newest-first traversal for a named actor. SQLite can otherwise choose this index for mixed actor+date predicates even when the actor is extremely common; measured regression is meaningful. When a validated UTC date is present, the application deliberately uses `INDEXED BY idx_activity_day` to keep the selective day-expression plan. This is an SQLite-specific planning choice tested by `ActivityQueryPlanTest` and existing actor/date/action behavior tests. Do not emulate indexes in PHP.

## Write cost and limits

On the same benchmark, adding the index to ~103k rows cost ~51.7 ms once. 3,000 additional single-batch inserts took ~5.2 ms before and ~6.84 ms after the actor index (~32% extra in this narrow hot-cache synthetic run). This is a real write trade-off, not a general production amplification factor. The application has modest audit writes and needs predictable rare-actor investigations, so this cost is currently justified. No fourth index for each action+date+user combination; reassess after representative production read/write ratios.

The index is added only in immutable migration `003_actor_recent_index.sql` using the trusted CLI migrator with stored checksums; no online schema modification during browser requests. Existing `idx_activity_recent` and `idx_activity_day` remain necessary for unfiltered and date-first reads. Later C-16 handles stable cursor pagination independently.

Source: Compass `how-to-choose-an-index-from-a-query-plan` (measured selectivity, plans and write cost); Kleppmann, *Designing Data-Intensive Applications*, Ch. 1 workload-driven scaling.

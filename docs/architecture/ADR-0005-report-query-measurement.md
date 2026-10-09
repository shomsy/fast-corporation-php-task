# ADR-0005 — Activity report aggregation at measured workload

Status: Accepted / C-14, 2026-10-09. Owner: ActivityTracking.

## Measured facts
Run `python3 tests/Flow/activity_query_benchmark.py` to reproduce the isolated SQLite workload of 100,000 skewed activity rows across 180 UTC dates (20 warm query iterations; wall-clock p95, not service p95). The existing daily report SQL groups and sums in SQLite, returning no more than 30 **active** dates; it does not load the entire history into PHP.

| Query | Local p95 | SQLite plan |
|---|---:|---|
| Existing all-history report, last 30 active dates | 21.005 ms | SCAN USING INDEX idx_activity_day |
| Explicit lower UTC date bound, last ~30 calendar dates | 16.448 ms | SEARCH USING INDEX idx_activity_day |

The second query returned 24 active dates in this skewed synthetic sample, and therefore is not a behavior-preserving replacement for the first. Do not silently change the inclusive report range or what 'last 30 active dates' means.

## Decision
**Keep indexed SQL daily aggregation. No rollup table, cache, or new background job.** The observed p95 at 100k rows is not sufficient evidence that the cost/atomicity/rebuild/reconciliation of a daily rollup is earned. The existing expression index supports the query. `DailyReports::MAX_DAYS=30` bounds result rows, not necessarily the number of history rows examined: a large production DB requires new representative measurements and SLO before a public-production claim.

C-17 must make calendar-axis distances and omitted dates honest, without changing the authoritative count dataset accidentally. C-15 separately evaluates selective actor/date query indexes and their write cost.

Sources: Martin Kleppmann, *Designing Data-Intensive Applications*, Ch. 1 (load parameters and scale must match workload), Compass `how-to-choose-an-index-from-a-query-plan.md` and `how-to-read-a-query-execution-plan.md`.

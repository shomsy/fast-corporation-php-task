# ADR-0014 — Local capacity baseline and SQLite exit criteria

Status: **C-28 locally measured, 2026-10-09**. Public-production load and SLO **NOT certified**.  
Owners: Platform/Performance, with Accounts, ActivityTracking and Downloads retaining their domain contracts.

## Reproducible workload

Run on an isolated, freshly migrated SQLite/WAL database:

```bash
python3 tests/Flow/http_capacity_probe.py
php tests/Flow/download_memory_probe.php
python3 tests/Flow/sqlite_concurrency_probe.py
```

The standard-library HTTP probe starts the PHP **CLI built-in server** (including the Docker PHP wrapper when present); it does **not** emulate PHP-FPM, TLS termination, real reverse-proxy rate limits or a multi-host deployment. Four separately authenticated user clients each issue eight visits through Page A, Page B, a byte-verified download, liveness and readiness, while an authenticated administrator runs eight Statistics and eight Reports reads. Account creation and bcrypt login complete **before** a synchronization barrier so they do not distort the steady-state endpoint p95. Total: **176 timed HTTP requests**, plus setup/initial auth requests which are not counted in the endpoint statistics.

One observed run on the local host (elapsed including setup/auth portions of workload: 1.40 s):

| Endpoint | Samples | p95 (ms) | p99 (ms) |
|---|---:|---:|---:|
| Page A | 32 | 2.68 | 3.33 |
| Page B | 32 | 2.32 | 2.32 |
| Download 1,024-byte pinned PE | 32 | 2.39 | 2.45 |
| Liveness | 32 | 2.09 | 2.13 |
| Readiness + schema checksum | 32 | 2.00 | 2.17 |
| Admin Statistics | 8 | 1.45 | 1.45 |
| Admin Reports | 8 | 1.21 | 1.21 |

**0/176 failed**; all 32 downloads returned the exact pinned SHA-256 and Content-Length. A direct read through the private database owner confirmed **105 accepted business events**, corresponding to four registrations, four logins, 96 Page A/B/download actions and one administrator login. The database was in WAL mode, main file 57,344 bytes at the sample point, no live WAL/SHM sidecars reported at that instant (these are transient, not permanently absent). The test is not a throughput/load ceiling: four clients, 32 repetitions of a URL and eight admin observations are insufficient for meaningful tail distributions. With small samples, p99 is effectively the maximum observed latency.

## Memory, resource and storage limits

`php tests/Flow/download_memory_probe.php` loads the real fixed binary through `DownloadFile` against a disposable in-memory database: 1,024 response bytes, **8,388,608 bytes peak allocated PHP heap** on this runtime. It is NOT process RSS, PHP-FPM worker RSS, cgroup memory, CPU time or a high-load heap profile. C-23 sets a hard 2 MiB artifact read bound and rejects larger/symlinked/mismatched files **before audit**, avoiding unbounded binary buffering. This proves a bounded *individual* read path, not a bounded full PHP worker under arbitrary user concurrency.

The existing two-process on-disk SQLite benchmark measured:

- rollback journal: reader blocks concurrent writer until the deliberately short 150 ms timeout;
- WAL: reader and writer overlap (observed about 0.2 ms writer completion);
- WAL still allows only one writer (competing writer waits and reports busy after ~151 ms);
- writer after lock release succeeds, WAL checkpoint and online backup/restore row-count parity pass.

The test probe timeout is **150 ms**; application PDO uses `PRAGMA busy_timeout=5000`. Synthetic results and single-core CLI test-server data must never be reported as PHP-FPM p95, production worker memory, achieved RPS or guaranteed availability.

## Decision and exit criteria

Retain one modest SQLite/WAL modular monolith and the small server-selected download. Do **not** introduce Redis, a queue, additional DB replicas, a custom retry loop, streaming infrastructure or rollup materialization without a measured target workload. Current local acceptance is **PASS**, not public-production approval.

Before a public GO, the operator must specify actual target concurrent users, GET/POST mix, desired p95/p99, error budget, backup RPO/RTO, session model, expected history growth and traffic bursts. Rerun tests on the actual HTTPS proxy + PHP-FPM worker count and sample PHP-FPM RSS/CPU, cgroup memory, SQLite BUSY frequency, WAL/checkpoint size, disk I/O, p95/p99 at the target and at overload. Set monitoring/alert thresholds to those agreed objectives. If sustained writer lock contention, disk/memory saturation or tail failures violate the goals, revisit DB/write architecture using those measurements. **No public-performance SLO was invented or silently accepted here.**

Sources: measured `tests/Flow` probes, ADR-0004 SQLite isolation/backup, ADR-0005 aggregation benchmark, ADR-0006 index trade-offs. The emphasis on load-specific metrics and workload-driven scaling follows *Designing Data-Intensive Applications* rather than premature horizontal design.

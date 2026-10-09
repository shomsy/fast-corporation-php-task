# ADR-0004 — SQLite concurrency, journal mode and recovery

Status: Accepted for locally provisioned SQLite (2026-10-09)  
Owners: Database / Delivery / Runtime; ActivityTracking remains owner of event data  
Scope: File-backed SQLite under the trusted CLI migrator and ordinary PHP web processes.

## Observations and workload

The application inserts activity records on successful login, registration and accepted Page A/B navigation and clicks, while admin Statistics and Reports concurrently read the event history. This is a write-on-read HTTP workload, not pure read-only reporting.

The reproducible standard-library probe `python3 tests/Flow/sqlite_concurrency_probe.py` executes a writer in a **separate OS process** while a reader holds a snapshot on a real SQLite file. One observed run on the local host:

| Condition | SQLite DELETE / rollback | SQLite WAL |
|---|---:|---:|
| Writer with reader transaction open | SQLITE_BUSY after ~150.8 ms | Committed in ~0.1 ms |
| Writer competing with writer transaction | SQLITE_BUSY after ~150.9 ms | SQLITE_BUSY after ~150.9 ms |
| Writer after lock released | Succeeded | Succeeded |
| Online SQLite backup and restored row count | Matched | Matched |
| WAL checkpoint with no active reader | Not applicable | No busy readers |

Probe `busy_timeout=150` ms **deliberately exposes** lock contention; production PDO connections already use `PRAGMA busy_timeout=5000`. These timings are diagnostic examples, not p95, capacity forecasts or PHP-FPM production load tests. The PHP provisioning regression independently verifies WAL mode through PDO.

## Decision

1. On `php bin/migrate.php`, `SqliteConnection::open(createIfMissing: true)` requests `PRAGMA journal_mode=WAL` and **verifies** the returned mode. This is a trusted deployment/provisioning effect only.
2. Ordinary web calls use `SqliteConnection::open(path: ...)` without changing the journal mode or creating a database. Previously provisioned legacy files become WAL after rerunning the CLI migration before opening web traffic.
3. Keep SQLite's **single-writer** limit: reader concurrency improves, simultaneous writers still serialize. No read-modify-write retries, automatic replay or silent reclassification of SQL errors. If `SQLITE_BUSY` is exhausted after the configured wait, the current operation fails visibly and server diagnostics classify it.
4. Keep durability defaults (no unmeasured `synchronous=NORMAL` relaxation). Do not add a queue, distributed mutex, connection pool or read replica for this demo.
5. Keep the data on a writable **local filesystem** that supports SQLite locking; do not assume network/distributed filesystem safety. WAL sidecars (`-wal`, `-shm`) must stay in the same protected directory under the database owner. Monitor disk capacity and growth.

## Checkpoint, backup and restore contract

WAL may contain committed transactions **not yet merged** into the main `.sqlite` file. Never claim that copying only the main file while requests are active is a consistent backup. Prefer SQLite's online backup API (`sqlite3_backup`) or a verified snapshot with explicit service downtime/locking; the probe performs backup and restore via the SQLite API and checks row parity.

SQLite auto-checkpoint may be used, but a controlled `PRAGMA wal_checkpoint(TRUNCATE)` can be run only when active readers permit it. The probe verifies a successful checkpoint after readers drain. Operator procedures must not delete WAL or SHM sidecars to force a checkpoint. The later C-25 release gate must exercise **the deployed PHP/SQLite version, filesystem permissions, WAL backup, full restoration and RPO/RTO**. C-26 must provide health/readiness and recovery runbooks. A passed local SQLite probe is not public-production readiness.

## Trade-offs and revisit triggers

WAL allows readers and a single writer to coexist but adds sidecars and a checkpoint/backup lifecycle. It does **not** remove writer-writer contention and does not by itself make SQLite suitable for thousands of concurrent writes. If measured `SQLITE_BUSY` frequency, p95 writer queue time, disk saturation, WAL growth or observed recovery time exceeds the actual application SLO, rerun the probe at production worker count and representative traffic before changing storage or introducing retries.

Reject adopting WAL blindly on network filesystems or claiming synthetic timing is production throughput evidence. Source context: Kleppmann, *Designing Data-Intensive Applications*, storage engines, logs and transactions; existing `Migrator` owns the deployment transaction.

# Source-to-Canonical Traceability — Five Reviews, All 142 Entries

**Status:** Draft consolidated assessment, not an implementation or blanket acceptance of review severity.
**Canonical plan:** [48 work packets](CANONICAL-REMEDIATION-PLAN.md). **Originals are immutable evidence.**

## Ground rules

Every original reviewer ID appears exactly once here and has one *primary* target, or `—` with a clear rejection reason. Overlap does not create duplicate implementation work. A reviewer suggestion can be wrong, conditional, or conflict with user decisions. Original priorities are preserved in the original review, not silently promoted to canonical P1.

**Decision labels:** `VALIDATE / IMPLEMENT`, `CONDITIONAL / MEASURE`, `DESIGN / DEFER`, `EVIDENCE GATE`, `REJECTED AS STATED`, `REJECTED STYLE OVERRIDE`, `PARTIAL; MEASURE`, or an explicit note. None equals DONE.

| Source | File | Source entries |
|---|---|---:|
| OOP | [OOP architecture review](../OOP-ARCHITECTURE-REMEDIATION-TODO.md) | 18 |
| CLAUDE | [ClaudeCode code review](../reviews/CLAUDE-CODE-REVIEW-2026-10-08.txt) | 62 |
| LLD | [ChatGPT LLD review](../reviews/LLD-CODE-REVIEW-2026-10-08.md) | 26 |
| CLLD | [ClaudeCode LLD review](../reviews/CLAUDE-CODE-LLD-REVIEW-2026-10-08.md) | 26 |
| GEM | [Gemini review](../reviews/GEMINI-REVIEW-2026-10-08.md) | 10 |
| **Total** | **Five reviews** | **142** |

## Full crosswalk

| Review | Original ID | Primary canonical packet | Disposition | Rationale / exception |
|---|---|---|---|---|
| OOP | `OOP-01` | `C-01` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-02` | `C-02` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-03` | `C-21` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-04` | `C-04` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-05` | `C-07` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-06` | `C-25` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| OOP | `OOP-07` | `C-06` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-08` | `C-11` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-09` | `C-13` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| OOP | `OOP-10` | `C-12` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-11` | `C-16` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-12` | `C-14` | MEASURE | Aggregate cost under large dataset, not an immediate proven correctness defect. |
| OOP | `OOP-13` | `C-23` | DESIGN OPTION | Download method naming/streaming threshold is optional. |
| OOP | `OOP-14` | `C-30` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| OOP | `OOP-15` | `C-34` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| OOP | `OOP-16` | `C-24` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| OOP | `OOP-17` | `C-26` | DEPLOYMENT GATE | Public deployment must be tested separately from interview acceptance. |
| OOP | `OOP-18` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `S-01` | `C-05` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-02` | `C-03` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-03` | `C-04` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-04` | `C-03` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-05` | `C-04` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-06` | `C-06` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-07` | `C-10` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-08` | `C-08` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `S-09` | `C-09` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-10` | `C-20` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `S-11` | `C-27` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-12` | `C-23` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-13` | `C-22` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `S-14` | `C-20` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `S-15` | `C-10` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `A-01` | `C-06` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `A-02` | `C-30` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `A-03` | `C-29` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `A-04` | `C-29` | DESIGN NOT AUTOMATIC | DIP benefit to test, but no blanket port-per-class requirement. |
| CLAUDE | `A-05` | `C-29` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `A-06` | `C-31` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `A-07` | `C-31` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `A-08` | `C-29` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `A-09` | `C-32` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `A-10` | `C-22` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `A-11` | `C-46` | TARGETED MIGRATION | Template layer retirement is approved as part of incremental SPA cutover, not instant template engine swap. |
| CLAUDE | `A-12` | `C-21` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `A-13` | `C-30` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `R-01` | `C-13` | MEASURE | WAL is a workload/backup decision, not proof that all reads are blocked. |
| CLAUDE | `R-02` | `C-10` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `R-03` | `C-11` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `R-04` | `C-12` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `R-05` | `C-12` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `R-06` | `C-25` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `R-07` | `C-24` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `R-08` | `C-24` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `R-09` | `C-26` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `P-01` | `C-14` | MEASURE | SQL query shape O(N) is real; severity depends on target dataset; do not precommit to rollup. |
| CLAUDE | `P-02` | `C-15` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `P-03` | `C-32` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `P-04` | `C-32` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `P-05` | `C-28` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `P-06` | `C-28` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `B-01` | `C-17` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `B-02` | `C-17` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `B-03` | `C-18` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `B-04` | `C-19` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `B-05` | `C-08` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLAUDE | `B-06` | `C-44` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLAUDE | `B-07` | `C-01` | CONFIRMED + SECONDARY | Maps to C-01 (date) and also C-02 (renderer); both remain active. |
| CLAUDE | `T-01` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `T-02` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `T-03` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `T-04` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `T-05` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `T-06` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `D-01` | `C-34` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `D-02` | `C-34` | REJECTED STYLE OVERRIDE | User's MOI and PHP named arguments remain authoritative; not an application bug. |
| CLAUDE | `D-03` | `C-34` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLAUDE | `X-01` | `C-36` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `X-02` | `C-36` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLAUDE | `X-03` | `C-48` | DEFERRED | No AI tool/execution surface in this app; prompt injection hardening only if AI capability is added. |
| LLD | `LLD-01` | `C-04` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-02` | `C-03` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-03` | `C-06` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-04` | `C-07` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-05` | `C-01` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-06` | `C-10` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-07` | `C-09` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-08` | `C-25` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| LLD | `LLD-09` | `C-02` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-10` | `C-11` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-11` | `C-24` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-12` | `C-29` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| LLD | `LLD-13` | `C-29` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| LLD | `LLD-14` | `C-12` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-15` | `C-24` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-16` | `C-16` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-17` | `C-17` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-18` | `C-14` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| LLD | `LLD-19` | `C-08` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| LLD | `LLD-20` | `C-23` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-21` | `C-22` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| LLD | `LLD-22` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| LLD | `LLD-23` | `C-30` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| LLD | `LLD-24` | `C-34` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| LLD | `LLD-25` | `C-33` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| LLD | `LLD-26` | `C-36` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLLD | `CLLD-01` | `C-06` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-02` | `C-05` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-03` | `C-03` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-04` | `C-04` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-05` | `C-13` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLLD | `CLLD-06` | `C-14` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLLD | `CLLD-07` | `C-29` | DESIGN NOT BUG | Direct PDO in private owner internals is not necessarily DIP failure; interfaces only for proven pressure. |
| CLLD | `CLLD-08` | `C-30` | DESIGN NOT BUG | CowOffer retains business ownership; avoid anemic/god labels without change data. |
| CLLD | `CLLD-09` | `C-10` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-10` | `C-08` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLLD | `CLLD-11` | `C-12` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-12` | `C-11` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-13` | `C-18` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-14` | `C-31` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-15` | `C-17` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-16` | `C-22` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-17` | `C-33` | OSS TRADE-OFF | No bulk composer install without validation and explicit benefit. |
| CLLD | `CLLD-18` | `C-35` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLLD | `CLLD-19` | `C-34` | REVIEW NOT DELETE | Improve meaningful contracts, but do not delete complete PHPDocs or governance wholesale. |
| CLLD | `CLLD-20` | `C-46` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-21` | `C-23` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-22` | `C-30` | DESIGN / DEFER | No speculative abstraction or framework rewrite. |
| CLLD | `CLLD-23` | `C-24` | VALIDATE / IMPLEMENT | Source claim mapped; reproduce and close with tests before implementation. |
| CLLD | `CLLD-24` | `C-20` | CONDITIONAL / MEASURE | Depends on public deployment, workload or agreed product contract. |
| CLLD | `CLLD-25` | `C-36` | EVIDENCE GATE | Only complete with executable validation and durable proof. |
| CLLD | `CLLD-26` | `C-34` | REJECTED STYLE OVERRIDE | Personal MOI and named-argument decisions preserved. |
| GEM | `GEM-01` | — | REJECTED AS STATED | Only server-owned configured artifact path exists; no client `file` parameter or path concatenation. |
| GEM | `GEM-02` | — | REJECTED AS STATED | `Csrf::matches` already calls `hash_equals`; SameSite/HttpOnly are configured, Secure is environment-controlled. |
| GEM | `GEM-03` | `C-13` | PARTIAL; MEASURE | Default DELETE journaling may need WAL, but `busy_timeout=5000` already exists. No mandatory WAL without evidence. |
| GEM | `GEM-04` | `C-05` | ACCEPTED CONDITIONAL | No app limiter; production edge limiting documented but not yet implemented/verified in deployment. |
| GEM | `GEM-05` | — | REJECTED AS STATED | `Accounts` and `ActivityLog` accept injected PDO; activity clock already uses PSR-20 ClockInterface. |
| GEM | `GEM-06` | — | REJECTED AS STATED | Report aggregation done by SQL SUM/GROUP BY; fetchAll only bounded aggregated rows, Stats query is paginated. |
| GEM | `GEM-07` | — | REJECTED AS STATED | SessionID regenerated on login and cookie invalidated on logout through PhpSession. |
| GEM | `GEM-08` | `C-33` | DESIGN OPTION | OSS evaluation is warranted but switching frameworks is not an automatically proven fix. |
| GEM | `GEM-09` | — | REJECTED AS STATED | Each SQLite migration and ledger write already executes in a local transaction; checksum still missing (C-11). |
| GEM | `GEM-10` | `C-29` | DESIGN OPTION | HTTP adaptation/renderer separate; more DTO layers only with change pressure. |

## Additional merge notes

- The date-input `ValueError` and nested render-buffer failure are *separate* concrete bugs even when one reviewer bundled them under B-07.
- `GET` tracking currently describes accepted Page A/B views; browser rendering is not proven by an HTTP event. SPA migration must state whether to keep this metric meaning or change to an explicit protected navigation event.
- The 142 entries include multiple design preferences and factually incorrect claims; the new C-37..C-46 SPA work packets are user-approved direction and *not* hidden among those 142 reviews.
- During execution, update this crosswalk only to add evidence/closure and more accurate dispositions. Never remove a source row or rewrite an archived reviewer file.

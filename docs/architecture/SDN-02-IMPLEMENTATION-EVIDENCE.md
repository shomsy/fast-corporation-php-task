# SDN-02 — concrete implementation evidence

**State:** Local refactoring slices complete for Accounts persistence/credential policy, ActivityTracking event writer and daily projection, and Downloads artifact reader. This record is not a global LLD score or production-deployment approval.

## Technical change and owner knowledge

| Owner capability | Authoritative business code | Private concrete implementation |
|---|---|---|
| Create / authenticate account | `Accounts`, `Register`, `Login`, `AccountCredentials`, `AccountLifecycle` | `Persistence/SqliteAccounts`; `BcryptPasswords` |
| Append auditable action | `ActivityLog` | `Persistence/RecordActivity` |
| Produce daily UTC chart/table | `DailyReports` (filter validation and max 30 dates) | `Persistence/ReadDailyActivity` |
| Accepted fixed binary download | `DownloadFile` (audit after read) | `Storage/ReadArtifact` |
| Cow purchase interaction | `CowOffer` | None earned; stays cohesive |

**Architecture decision:** No speculative `Implementations/` umbrella, generic Repository interface, ORM, DI framework, service locator or new JavaScript library. Private technical folders `Persistence/` and `Storage/` earn their place by coherent technologies and distinct change reasons. They are not exported by module manifests. See ADR-0023 and ADR-0024, Architecture Governance 1.7.1 STR-17 to STR-19.

## Existing behavior preserved

- Business input: ASCII username / SQLite NOCASE uniqueness, 12–72 password bytes, rejection of NUL, same user and admin creation policy, no cleartext password storage.
- Security: unknown-account dummy bcrypt verification, cost 12, compare-and-swap legacy hash upgrade; session rotation and immediate admin revocation reloading retained.
- Data correctness: registration+activity audit in the caller's transaction and rollback together; append-only accepted action history; UTC time and bounded report aggregation unchanged.
- Download: fixed server-owned filename/path, size limit, symlink rejection, SHA-256 pin, native POST attachment, audit only after complete accepted read.
- System: existing SPA-only Vanilla JS frontend and JSON HTTP API unchanged, with same routes/roles/CSRF.

## Measured verification after changes (local)

- `composer quality`: **PASS** (PHPStan 0, Deptrac violations 0 / uncovered 0, composer strict PSR-4, security audit, PHPDoc and original MOI gates).
- PHPUnit **142 tests / 6,624 assertions PASS**, including four new credential-policy tests and two owner-technology-boundary fitness tests.
- Vanilla JavaScript **29/29 PASS**, Playwright acceptance **PASS** (auth, roles, downloads, statistics).
- Live HTTP **PASS**, SPA-only route cutover **PASS**, SQLite WAL backup/restore **PASS**, ingress abuse/XFF controls **PASS**.
- Original PhpStorm MOI applied only to this workstream's own PHP files; concurrent `Statistics.php` edits excluded.
- No interface was introduced solely to pretend multiple backing stores already exist.

## Important remaining work / boundaries

- `ActivityTracking/Statistics.php` retains separately owned uncommitted changes from another workstream and was not modified. A focused reconciliation of query/pagination internals remains possible after concurrent edits are resolved.
- Some SQL semantics in the private `ReadDailyActivity` projection are necessarily expressed as SQL, but business validation and the report contract remain within the owner. A wholesale generic ActivityRepository would reduce clarity and was deliberately avoided.
- The project's native PHP IDE inspection export still needs regeneration after this refactor to quantify current code-smell and missing exception-contract warnings. Green tests are not proof every LLD issue is resolved.
- Public production remains NO-GO without deployment-specific host acceptance, independent of this local quality result.

## Source basis

Approved Architecture Governance v1.7.1 on Desktop, Compass Engineering guidelines for extraction and repository abstraction, John Ousterhout *A Philosophy of Software Design* 2nd edition (deep modules), Martin Fowler *Refactoring* (divergent change and safe extraction), previously inspected from original local My Books PDFs.


## LLD checkpoint — explicit session clock (2026-10-10)

**Concrete evidence:** `SessionIdentity` had an optional `ClockInterface|null` dependency and silently constructed `SystemClock` in `now()`. Session idle timeout, absolute lifetime and future-clock rejection therefore depended on an implicit wall-clock fallback when a caller omitted the dependency. Production bootstrap already injected `$clock`; five test compositions omitted it.

**Decision:** require `ClockInterface $clock` at construction, use it directly in `now()`, and pass the test fixture clock at all former implicit composition sites. No new interface/factory, session-state change, timeout policy change, or new business/API folder. The existing `Psr\\Clock\\ClockInterface` is the real clock boundary.

**Canonical LLD basis:** current `/home/shomsy/projects/code-quality-standards/standards/low-level-design/php/how-to-apply-php-oop-solid-and-dry.md`: `PHP-L04` (explicit consequential collaborator) and `PHP-L13` (required collaborators yield usable objects). The corresponding WHEN-TO smell triage says not to use size counts as proof. Architecture Governance 1.7.1 remains the separate authority on owner/module boundaries. Compass `how-to-refactor-safely` calls for behavior protection then scoped change. Original *A Philosophy of Software Design*, 2nd ed., PDF physical pp. 34–35, was consulted for interface simplicity and information hiding.

**Before/after proof:** a newly added `SessionExpiryTest::testSessionExpirationClockIsRequiredAtConstruction` failed under the original optional constructor, then passed with the required constructor. Existing session-expiry, CSRF, role revocation and login tests still pass. All 19 constructor call sites were checked for explicit `clock:` wiring; production was already explicit.

**Verification:** `composer quality` PASS (PHPUnit 153 tests / 6,753 assertions; PHPStan 0, Deptrac 0 violations / 0 uncovered, MOI/PHPDoc PASS), 29/29 Vanilla JS, Playwright browser acceptance PASS, live SPA HTTP/CSRF and route cutover PASS. This is a narrow verified LLD improvement, *not* a claim that the whole system is 10/10 or that all old PhpStorm findings are resolved. Original user-owned `Statistics.php` unstaged encoder relocation, 69 XML exports and `src.zip` remain untouched.

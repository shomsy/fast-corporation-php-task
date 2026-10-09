# ADR-0024: Concrete owner-private implementations and technology isolation

**Status:** Accepted for the refactored slices; 2026-10-10. This does not claim that all application architecture is complete or that public production deployment is authorized.

## Architecture and constraints

Fast Corporation remains a PHP JSON API plus framework-free Vanilla JS SPA, organized around its business owners. Apply approved Architecture Governance 1.7.1, especially **STR-17**, **STR-18**, **STR-19**. Do not add speculative interfaces, generic repositories, layers or empty `Implementations/` folders.

The use cases and owner knowledge remain discoverable before technical implementation. Concrete technology classes remain owner-private:

```text
src/
  Accounts/
    Register/                       # use case
    Login/                          # use case
    Accounts.php                    # shared owner coordination
    AccountCredentials.php          # owner credential / username rules
    BcryptPasswords.php             # specific credential mechanism
    Persistence/
      SqliteAccounts.php            # SQL, mapping, duplicate constraint translation
  ActivityTracking/
    ActivityLog.php                 # accepted action validation and UTC clock
    DailyReports.php                # UTC filter validation and reporting semantics
    Statistics.php                  # existing statistics/cursors (parallel work untouched)
    Persistence/
      RecordActivity.php            # bounded event INSERT
      ReadDailyActivity.php         # bounded report SELECT, GROUP BY and row mapping
  Downloads/
    DownloadFile.php                # successful download use case and audit decision
    Storage/
      ReadArtifact.php              # fixed trusted path, symlink rejection, size, SHA-256
  CowOffer/
    CowOffer.php                    # small cohesive use case; no new technical area
```

These private areas are named `Persistence/` and `Storage/` because they each serve one coherent technical concern. An optional umbrella `Implementations/` is **allowed** only when multiple private realizations make that grouping clearer (STR-19). An isolated `AccountRepository` interface would be a needless second contract for one SQLite persistence mechanism.

## Business/technology boundary — why this is a real separation

1. `Accounts` no longer prepares SQL, reads PDO rows, interprets SQLite error codes, or implements bcrypt. Business validation is concentrated in `AccountCredentials`; the bcrypt mechanism owns the fixed work factor and dummy verifier; SQLite owns query strings and typed reconstruction. Caller code still decides when to register, authenticate, or grant an admin role.
2. `ActivityLog` validates actor identity and action target, supplies the UTC time and delegates only the accepted append operation to `RecordActivity`. Same PDO connection is shared so registration+audit stay atomic.
3. `DailyReports` owns date validation, 30-day limit and report API. `ReadDailyActivity` performs the same SQL projection, bounded query, index strategy and typed row mapping. No synthetic zero-days or changed event semantics.
4. `DownloadFile` records a download event **only after** `ReadArtifact` successfully reads the trusted server-configured fixed artifact within the existing bounds, with the original SHA-256 and symlink checks; it never accepts the client's path.
5. `CowOffer` is small and cohesive. Additional interfaces or repositories would increase complexity without a real change boundary.

## Decision costs and limitations

- Concrete PHP constructor composition is slightly longer in bootstrap and test fixture code. This is deliberate visible wiring; it is simpler than a generic dependency injection framework.
- No interfaces are mandated for private mechanisms; adding one later requires measured volatility or a real need for interchangeable realizations.
- `Accounts::credentialsFor()` shares an owner-private typed `Account` plus stored hash with the authentication coordinator. That is an internal, controlled mechanism seam, not a public account API.
- Existing `src/ActivityTracking/Statistics.php` has independent uncommitted changes from a different workstream. Its persistence/cursor decomposition **is not part of this ADR implementation** and remains a follow-up. Do not stage or overwrite unrelated edits.
- Existing old PhpStorm XML reports are user-owned evidence and were not changed. A new full-project IDE inspection is separately required to demonstrate a lower code-smell count.

## Acceptance / evidence

Mandatory: unchanged session rotation, registration+audit transaction, SQLite NOCASE uniqueness, timing-equivalent dummy bcrypt path, successful rehash with compare-and-swap, role revocation, native fixed SHA-256 download, fail-closed unavailable artifact without false audit, bounded UTC statistics, browser SPA-only flows.

Automated safeguards now cover credential byte-level constraints without SQLite and a structural fitness check forbidding SQL/filesystem calls directly in behavior classes. Native PhpStorm MOI format is maintained; no new JavaScript runtime dependency was introduced. The final complete test matrix and commit hashes are recorded in the implementation report, not inferred from the existence of this ADR.

## References

- Portable Architecture Governance 1.7.1 `/home/shomsy/Desktop/ARCHITECTURE-GOVERNANCE.md`, STR-17/18/19.
- Compass *How to Refactor Safely*, *When to Use a Repository Abstraction*, *How to Decide Where State and Knowledge Should Live*, *How to Decide When to Abstract*.
- John Ousterhout, *A Philosophy of Software Design*, second edition, chapter 4 (deep modules and information hiding); original local PDF inspected previously.
- Martin Fowler, *Refactoring: Improving the Design of Existing Code*, first chapters on divergent change and large classes; original local PDF inspected previously.

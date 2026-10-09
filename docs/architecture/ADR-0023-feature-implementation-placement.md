# ADR-0023: Owner-internal technical implementations — selective placement

**Status:** Active application of Architecture Governance 1.7.1 (STR-17, STR-18, STR-19). This records how the current Fast Corporation project will make placement decisions; it does **not** mandate new folders or create a new layer.

## Problem and governing rules

The product is a business-first modular monolith with Vanilla JS SPA and a PHP JSON API. `Accounts`, `Statistics` and `DownloadFile` currently mix some owner knowledge with SQL, encoding, hashing, filesystem and transport details. Moving them wholesale into `Implementations/` would change their paths, not their coupling.

The approved portable governance at `/home/shomsy/Desktop/ARCHITECTURE-GOVERNANCE.md` (v1.7.1) states:

- **STR-17:** place knowledge in the narrowest scope that contains every consumer of that *same knowledge*, not merely code using the same SQLite table or library.
- **STR-18:** owner-specific implementations stay private inside the behavior slice if only that behavior needs them, or within an owner-private technical area when the same boundary serves multiple behaviors.
- **STR-19:** optional `Implementations/` groups **several earned** owner-specific technical realizations; business validation, invariants and orchestration must not move there. One small realization stays direct. Generic transport, database connection and filesystem machinery remain under technical infrastructure.

## Decision

Keep top-level navigation **owner → business behavior**. Prefer a narrow, honest technical name (`Persistence/`, `Storage/`, `Search/`) when one realization earns a subarea. Use `Implementations/` only when several owner-private technical realizations exist and grouping them measurably improves navigation or boundary enforcement.

Illustrative, **not yet created**:

```text
src/
  Accounts/
    Register/
    Login/
    Persistence/                 # if shared account row mapping/queries earn it
      SqliteAccounts.php
    AccountCredentials.php        # owner policy, never hidden in Persistence
  ActivityTracking/
    Statistics.php                # query semantics owner, not SQL driver
    ActivityCursor.php            # cursor format/verification owner
    Persistence/                 # if owner-wide SQLite realization earns it
  Downloads/
    DownloadFile/
      DownloadFile.php            # business click/audit coordination
      Storage/                   # if artifact verification serves only this slice
        VerifiedArtifactReader.php
```

`Implementations/Persistence/` would be acceptable *instead* where more than one coherent private realization warrants an umbrella; it is not the default directory name. An interface or Repository is earned only by a stable domain-shaped boundary that hides substantial concrete knowledge, not by a single PDO dependency.

## Refactoring order and evidence

1. **P0 — exported identity contract:** no private `Accounts\Role` or `Accounts\Account` types in the public `SignedInAccount` API, while SPA JSON `user.role` remains the same `user|admin` string and server-side authorization remains authoritative.
2. **P1 — Accounts owner:** identify independent changes in credential policy, bcrypt mechanics, row mapping and SQL. Keep registration + required audit atomic; characterize duplicate username races, unknown-account hash timing, hash upgrade and admin revocation before changing persistence.
3. **P1 — ActivityTracking:** separate cursor signing/encoding from SQL without changing actual UTC ordering, query plan, pagination security or audit semantics.
4. **P2 — Downloads:** isolate fixed artifact reading/verification from successful download click recording without weakening path/checksum/transaction guarantees.
5. **P2 — remaining owner knowledge:** merge duplicated domain validation only when it represents the same rule with the same change owner.

Each step must demonstrate: fewer leaked concrete types, fewer independent reasons for change per class, a narrow stable public contract, **no** extra pass-through service forest, unchanged external HTTP/session/CSRF behavior, a focused failure-path regression, PHPStan/Deptrac/real SQLite acceptance and real browser/SPA acceptance. Green tests alone do not justify design or a 10/10 rating.

## Sources read for this decision

- John Ousterhout, *A Philosophy of Software Design*, 2nd ed., Chapter 4, original `/home/shomsy/Documents/Books/architecture/A Philosophy of Software Design — Second Edition — John Ousterhout.pdf`, physical PDF pages 30–34: deep modules, narrow public interface, information hiding.
- Martin Fowler, *Refactoring: Improving the Design of Existing Code*, original `/home/shomsy/Documents/Books/Software/Backend/Refactoring Improving the Design of Existing Code.pdf`, physical PDF pages 64–67: long methods, large classes, divergent change, small behavior-preserving extractions.
- Compass Architecture guides: *How to Decide Where State and Knowledge Should Live*; *How to Keep Business Invariants Inside Ownership Boundaries*; *How to Decide When to Abstract*.
- Compass Implementation: *How to Refactor Safely*; *When to Use a Repository Abstraction*. Compass SDLC: *How to Define Acceptance Criteria for a Change*.

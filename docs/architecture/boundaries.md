# Boundary Register

| Boundary                      | Protects                                                  | Contract                                                                  | Current evidence                                                                                                            |
|-------------------------------|-----------------------------------------------------------|---------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------|
| Accounts -> ActivityTracking  | credential/access authority vs activity-history authority | AccountLifecycle tracked auth mutation; narrow activity recording surface | PILOT — Deptrac, AST exports, no-mutation CurrentAccount, rollback/login/logout tests                                       |
| CowOffer -> ActivityTracking  | Page A/Buy Cow behavior vs activity history               | page/button recording surface                                             | PILOT — Deptrac, module import tests and scoped behavior evidence                                                           |
| Downloads -> ActivityTracking | download/artifact behavior vs activity history            | page/button recording surface                                             | PILOT — Deptrac, module import tests and scoped behavior evidence                                                           |
| Business -> Runtime           | business meaning from HTTP execution mechanics            | neutral HTTP contracts                                                    | PILOT — Deptrac, module import tests and scoped behavior evidence                                                           |
| Business -> Infrastructure    | business meaning from driven technical resources          | neutral DB/session/time/diagnostic contracts                                | PILOT — Deptrac, module import tests and scoped behavior evidence                                                           |
| Owner -> own data             | one authority per logical fact                            | owner-local behavior/schema                                               | PILOT — owner-local migrations and DataOwnershipTest static SQL-literal checks; computed table names remain review-enforced |

## Accounts internal navigation

`Accounts/Access` owns authenticated identity, role checks and access projections. `Accounts/Credentials` owns
username/password policy and the concrete bcrypt mechanism. Registration and login remain discoverable use cases;
`Accounts.php` coordinates account policy with owner-private `Persistence/SqliteAccounts`. These are internal groups
inside the same Accounts owner, with no new domain, contract layer or schema. `AccountLifecycle` remains the exported
authentication mutation boundary; only declared access projections/guards cross the module boundary.

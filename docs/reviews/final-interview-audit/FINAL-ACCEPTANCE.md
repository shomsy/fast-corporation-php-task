# Final acceptance and interview assessment

## Candidate score

**Provisional engineering-quality score: 92 / 100.** This is an evidence-based review against the repository and interview brief, not a promise about any particular interviewer's scoring rubric.

| Dimension | Score | Why it earns the score / remaining deduction |
|---|---:|---|
| Original specification compliance | 19 / 20 | All requested journeys, role gates, event filters, exact report metrics/table and executable download are traced to live UI/API/owner/persistence and positive/negative tests. Small deduction: Reports authorization and several product semantics are candidate interpretations because the recruiter brief is underspecified. |
| OOP / SOLID / LLD | 18 / 20 | Business behaviors and four data owners are discoverable; important collaborators are explicit and required; there are no fake repositories, interface forest or mutable domain bags. Cohesive audit/transaction/session coordination remains visible. Small deduction: Accounts lifecycle and route composition necessarily coordinate several effects and should be defended with their failure guarantees rather than class-size slogans. |
| Architecture and maintainability | 14 / 15 | Modular monolith, purpose folders, owner-private SQLite/artifact realizations, explicit public surfaces and static fitness rules align to actual change ownership. Accounts and ActivityTracking groupings improve navigation without adding semantic owners. Small deduction: architecture tests are static evidence and cannot prevent reflection or dynamically computed SQL. |
| Security and correctness | 14 / 15 | Password/session/CSRF/authorization, fixed binary, output handling, private storage, migration constraints and secondary-failure safety are covered. Fixed session issuance, SQL NULL invariants and complete-schema startup refusal are tested. Small deduction: external TLS, deployed ingress, data retention/legal policy and PHP-FPM boundaries are not facts of the local repo. |
| Error handling and reliability | 8 / 10 | Atomic registration audit, explicit unknown commit outcomes, revocation attempts, real failed session writes, SQLite WAL and backup/restore are demonstrated. Deduction: cleanup cannot promise durable revocation when the store itself fails; operational restore objectives are local rehearsal, not off-host production SLA. |
| Tests and evidence | 10 / 10 | Focused behavior/negative tests, real SQLite/native PHP handlers, browser and HTTP acceptance, migration history, architecture rules and mechanical inventory coverage all contribute independent evidence. |
| Simplicity and interview presentation | 9 / 10 | No runtime framework, speculative distribution, unearned interface hierarchy or unused formatter. Candidate assumptions and measured limits are explicit. Small deduction: the report's active-date bound and underspecified product choices should be explained. |

## Reviewer verdict

**Interview-quality result: strong / GO for the stated local task.** The source, not a class count or pattern checklist, shows separation of reasons to change: business owners hold meaning, behavior folders group workflows, owner-private code handles technology, and the composition root wires concrete choices. SOLID/CUPID/GRASP are visible as outcomes (cohesion, explicit dependencies, small consumer surfaces, ownership, testable failure seams); they are not implemented through ceremonial interfaces or generic layers.

The candidate should be ready to explain:

- why `AccountLifecycle` owns the accepted registration/login/logout outcomes and transaction/session compensation;
- why there is one `Accounts` owner with `Access/` and `Credentials/` navigation rather than additional domains or a generic `Services/` folder;
- why an interface for the single SQLite mechanism or fixed artifact has no current consumer/variation;
- how SQLite commit ambiguity differs from a proven rollback, why activity is not event sourcing, and why a click is not a purchase;
- why a session/CSRF/cursor secret is persisted before the HTTP response claims success;
- what keyset pagination guarantees and what it does not guarantee;
- which facts came from the recruiter and which are candidate interpretations (admin-only reports, UTC, transient cow state, 30 active dates, audit retention);
- what evidence would be required before public deployment and who must decide data retention/erasure.

## Executed checks

This section is completed only after actual commands finish. Scoped regressions run before the release suite, as directed. Each gate records PASS, FAIL or INCONCLUSIVE; no native IDE timeout is a pass. The full suite runs once after focused implementation work and final MOI formatting.

| Gate | Result / evidence |
|---|---|
| Scoped PHPUnit: Accounts, ActivityTracking, Architecture, Runtime, Infrastructure, Flow, CowOffer API, Downloads API | PASS — 171 tests / 6,869 assertions after correcting a stale namespace fixture. |
| Current follow-up focused scope: startup schema and cursor-only statistics | PASS — `DeploymentConfigTest.php` and `ActivityApiTest.php`, 9 tests / 122 assertions, including rejection of an actual four-migration database and HTTP 422 for retired `page=N`. |
| Scoped Node: changed account, accessibility/security and activity chart tests | PASS — 14/14. |
| PhpStorm native MOI formatting | INCONCLUSIVE for this follow-up — the PhpStorm inspection/formatting connector is unavailable in this environment. The prior 49-file native reformat remains historical evidence; current edits follow adjacent MOI and pass the repository checker, but are not represented as natively formatted in this turn. |
| Targeted native PhpStorm exception/inspection review | INCONCLUSIVE — native IDE response pagination/timeouts did not yield a complete issue list. No zero-warning claim is made. PHPStan, PHPDoc structure, parser and source-level audit gates passed independently. |
| Audit inventory checker | PASS — 272 unique original/current/moved/removed paths covered; all required audit artifacts present. |
| Prior release gate before source navigation move | PASS — Composer quality, 182 tests / 6,911 assertions; PHPStan clean; Deptrac 0 violations, 0 skipped, 0 uncovered; Composer validation/security audit and PHPDoc/syntax gates clean. |
| Full frontend suite | PASS — 31/31. |
| Fresh live HTTP/same-origin functional acceptance | PASS — accounts JSON HTTP, user slices/cow/native binary and event sequence, SPA cookies/CSRF/roles/auditing. |
| SPA route cutover, browser (auth, roles, artifact, filters, exact report legend and columns) | PASS — SPA-only routes and aliases; removed SSR mode fails closed; browser journey passed. |
| SQLite WAL backup/restore and migration004 | PASS — rollback and restore rehearsal; 25 rows restored with 5 migrations; malformed legacy row preserved while new invalid tracked targets are rejected. Timings are local observations, not an SLA. |
| Nginx local auth-ingress negative tests | PASS — isolated pinned ingress rate-limit, `Retry-After`, spoofed forwarded-header rejection and log redaction. Local reference only. |
| Actual zero-write session storage failure probe | PASS — disposable `php:8.5-cli` container with `RLIMIT_FSIZE=0`: response failed before success, no PHP warnings, session state cleared, no session file or login event created. |
| Dependency and audit checks | PASS — Composer locked audit found no advisories; `npm audit --audit-level=moderate` found 0 vulnerabilities; 272 path dispositions mechanically cover the inventory. |
| Repository whitespace, clean-scope review, user-owned XML/archive fingerprints, original MOI fingerprint | PASS — `git diff --check`; all 70 captured user-owned PhpStorm XML/archive fingerprints still match; files remain unstaged. Native formatter status is recorded separately above. |
| Prior commit on `main`, push to configured `origin`, independently verify remote commit hash | PASS — prior audit commit `e0e0d2583a3da10598da1f1c596b8d1bac491624` was on `origin/main` before this follow-up. This follow-up's final commit and remote SHA are recorded below after delivery. No deployment is part of this interview task. |

## Limit and interpretation ledger

- `TASK.md`'s recruiter brief, not the implementation plan, is the functional contract. `TASK.md`'s own “Working assumptions” are selected interpretations. The report authorization choice is the only conspicuous unclarified role policy; a conservative admin-only route protects user history.
- The local binary sample is deliberately inert. It satisfies a download demonstration without asking anyone to execute real software.
- The scoring deducts points for ambiguity and unproven production operations. It gives no extra credit for package count, number of classes, LOC, mandatory patterns or speculative infrastructure.
- A local browser/API test is not deployed production readiness. Public use still requires an accountable data purpose/retention/erasure rule, real TLS and ingress topology, least-privilege service account, off-host recovery objective and representative PHP-FPM capacity evidence.

## Follow-up source navigation refactor — 2026-10-10

The later ActivityTracking-only namespace move is a separate source revision from the acceptance matrix above. `ActivityCursor`, `ActivityFilters` and `UtcDate` now live under `src/ActivityTracking/Query/`; the owner's six exported FQNs and module manifest are unchanged. CowOffer and Downloads remain flat because their existing one-to-three class layouts already express the behavior/storage split without a wrapper folder.

| Gate | Result |
|---|---|
| Scoped PSR-4 and behavior/architecture regression | PASS — strict optimized autoload plus 54 PHPUnit tests / 6,191 assertions across ActivityTracking and Architecture. |
| Source/path inventory | PASS — 272 individually listed original/current/moved/removed paths. |
| Full post-move release gates | PASS — Composer quality: 182 tests / 6,912 assertions, PHPStan clean, Deptrac 0 violations/skipped/uncovered/warnings/errors; frontend 31/31; live HTTP/SPA and route cutover; Playwright browser acceptance; SQLite WAL restore; ingress abuse gate; zero-write session failure probe. |

## Independent repository audit follow-up — 2026-10-10

Verified branch `main`, initial local `HEAD` and `origin/main` at `5227114a26ddee04b6e31ade24a62e48d9c9c8d3`. Rechecked the employer contract, current owners, route behavior, all 29 ADRs through ADR-0029, repo inventory and historical path dispositions. Remediated stale pagination/SSR claims and the incomplete-schema startup gap. The final PHP quality gate after all code changes passed: Composer validation, Composer audit, PHPDoc, MOI syntax, PHPStan, Deptrac (0 violations, skipped or uncovered) and PHPUnit (183 tests / 6,922 assertions). Frontend passed 31/31; browser, live HTTP, SPA route, SQLite restore, ingress and zero-write session gates passed; npm audit found 0 vulnerabilities. Native PhpStorm status remains INCONCLUSIVE for this follow-up. Record the commit and independently verified remote SHA after delivery.

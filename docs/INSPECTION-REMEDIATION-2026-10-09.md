# Fast Corporation — complete IDE finding triage and scoped remediation (2026-10-09)

**Scope.** User-owned 21:36 PhpStorm export at the project root. The XML files are immutable evidence and are never committed or overwritten. The export represents a *pre-remediation* snapshot; do not subtract code changes from it or claim that the updated IDE has zero findings without a new export. Other root XMLs may be historical. Public deployment remains separately gated.

## What was actually improved

- **Exception safety:** `public/index.php` now protects the outermost HTTP 500 response from a secondary failure of `SecurityEvents::record()`; diagnostics are best-effort, while errors still receive a safe response. Exceptions from deliberate business/system failures are otherwise propagated to their rightful HTTP or CLI boundary, not swallowed with empty catch blocks.
- **Separation of concerns / complexity:** extracted exact-route method negotiation and access authorization from `Router::dispatch()` into private helpers; moved access and CSRF policy from the long `bootstrap/routes.php` composition closure into `bootstrap/Http/RouteGuard.php`. The `Accounts` owner keeps its internal `Role` type private and exposes the semantic `SignedInAccount::isAdmin()` query instead.
- **PHPDoc inspector complexity:** divided the visitor's declaration selection and callable contract checks into named methods. It still detects missing and multiline PHPDoc.
- **Type/boundary correctness:** `SqliteConnection::open()` explicitly verifies that WAL mode is returned as a string, rather than casting `fetchColumn()` indiscriminately; JS error decoding now distinguishes object from null/array before reading properties and has an added regression; the cursor test uses a genuine non-null guard.
- **Provably redundant regexes:** removed `D` where `\\z` already anchors the pattern in `Response`, `Accounts` and `DownloadFile`. Intentionally retain `[0-9]` rather than blindly replacing it with `\\d`, which can have different Unicode semantics. No MOI formatter replacement.
- **Scope hygiene:** original MOI.xml, review archive, user XML exports and the separately edited `src/ActivityTracking/Statistics.php` were not rewritten. Runtime SPA remains Vanilla JS-only, and new PHP changes were reformatted in PhpStorm using the user's MOI scheme.

## Expected diagnostics, not bugs to suppress

- **72 Unhandled exception warnings:** 41 are in tests (unexpected exceptions should fail the test), 31 in application/CLI code. The PHP HTTP entrypoint intentionally catches unanticipated `Throwable` once and provides a non-sensitive HTTP 500; transaction and auth operations intentionally propagate failures and preserve primary exceptions. The remaining 31 production call sites still require classification per failure path; they are **not** all declared fixed.
- **86 Missing @throws warnings:** 56 occur in tests, where adding blanket `@throws` annotations would add noise rather than recovery. The other 30 reports concern production/HTTP contracts, including around `Response::json()`, system clocks and transaction propagation. These need case-by-case contract review; do not add `@throws Throwable` indiscriminately merely to silence the IDE.
- **47 Multiple class declarations:** 27 name PHP's `#[Override]`, 16 name `#[SensitiveParameter]`, and four name PhpParser AST classes `ClassLike`/`Function_`. They are not duplicate declarations in this project's business modules.
- **10 Method visibility:** `protected setUp()`, `tearDown()` and test-fixture helpers legitimately follow PHPUnit inheritance contracts.
- **Global variables:** PHP entrypoints/HTTP integration tests must interact with `$_SERVER`/`$_SESSION`; keep untrusted-state validation at the boundary.
- **Explicit default arguments:** `RouteAccess::SignedIn` is intentionally spelled out in route registration; clarity about security policy is more important than removing this advisory.
- **SensitiveParameterInspection:** transport error `$code` is a public machine-readable error category, not a credential. `SecurityEvents::record()` uses `error_log` deliberately for structured audit events, not debugging. Do not suppress the corresponding inspection globally.
- **IDE stubs/entry points:** `PhpUnused` on dynamically invoked bootstrap closures and PHPUnit overrides is an incomplete static reachability model; runtime tests and Composer autoload contracts are authoritative.

## Still open — do not mark green

1. **`Statistics.php`** is currently modified by another workstream. Residual IDE-LSP alias, cursor typing, `$parts` flow and `strtr` named-argument notices and `cursorPage` complexity need a separate, non-conflicting edit/reinspection. PHP 8.5 Reflection confirms `strtr(string, from, to)` is valid; do not change correct signatures to appease a stale stub.
2. **Remaining exceptions and `@throws` in production:** verify each genuinely exceptional operation and owning boundary. A declared propagation and actual handled/recoverable failure must not be conflated.
3. **Editor-only diagnostics:** JSON sample in preserved review evidence, generated `.descriptions.xml`, intentionally human-readable HTTP references, historical links and spelling/grammar suggestions are not automatically mutable code defects. Review artifacts and generated export remain unchanged.
4. **Post-fix IDE status:** the running PhpStorm MCP currently returns `timedOut: true` for PHP files, even when the returned `errors` array is empty. Thus only a **new** full-project Inspect Code export can verify the current PHP Hammer, PHP-LSP, complexity and documentation counts.
5. **Nonblocking style/code-smell opportunities:** optional refactors and diagnostics around built-in PHP attributes, generated editor text, test fixture stubs and syntactic convenience were not blindly applied. An advisory is not evidence that the current design should change.

## Acceptance evidence

After bounded changes, verify Composer manifest, autoload and audit; PHPStan; Deptrac; MOI syntax/PHPDoc; PHPUnit; Vanilla JS; Playwright; live SPA HTTP and CSRF; route cutover; WAL backup and ingress controls. Report individual PASS/FAIL results rather than deducing them from a single green indicator.

## Raw inspection group inventory

The table below is produced mechanically from XML files generated in the **21:36** batch, not from historic XML timestamps. Values are **pre-fix observations**, and several XML reporters can describe the same underlying concern. The statuses below are priorities/dispositions, **not claims that every row was eliminated**.

**Batch:** 58 non-empty inspection families, 5499 individual pre-fix reports.

| Family | Findings | ERROR / WARNING / WEAK | Evidence-based disposition |
|---|---:|---|---|
| `SpellCheckingInspection` | 3071 | 0 / 0 / 0 | Editorial/generated/stale; preserve |
| `Annotator` | 1140 | 0 / 0 / 0 | Editorial/generated/stale; preserve |
| `GrazieInspection` | 340 | 0 / 0 / 0 | Editorial/generated/stale; preserve |
| `.xml` | 239 | 0 / 0 / 0 | Advisory; assess per finding |
| `MarkdownUnresolvedLinkLabel` | 129 | 0 / 129 / 0 | Editorial/generated/stale; preserve |
| `PhpDocMissingThrowsInspection` | 86 | 0 / 0 / 86 | Open: investigate owning boundary |
| `PhpUnhandledExceptionInspection` | 72 | 0 / 0 / 72 | Open: investigate owning boundary |
| `HttpUrlsUsage` | 60 | 0 / 0 / 60 | Editorial/generated/stale; preserve |
| `GrazieStyle` | 57 | 0 / 0 / 0 | Editorial/generated/stale; preserve |
| `GlobalVariableUsageInspection` | 47 | 0 / 47 / 0 | Documented/intentional; review exceptions |
| `PhpMultipleClassDeclarationsInspection` | 47 | 0 / 0 / 47 | Documented/intentional; review exceptions |
| `CheckTagEmptyBody` | 22 | 0 / 22 / 0 | Editorial/generated/stale; preserve |
| `LSPLocalInspectionTool` | 16 | 7 / 3 / 6 | Open: investigate owning boundary |
| `JSUnusedGlobalSymbols` | 13 | 0 / 13 / 0 | Advisory; assess per finding |
| `PhpFullyQualifiedNameUsageInspection` | 13 | 0 / 0 / 13 | Advisory; assess per finding |
| `PhpRedundantOptionalArgumentInspection` | 12 | 0 / 0 / 12 | Documented/intentional; review exceptions |
| `JSUnresolvedReference` | 10 | 0 / 0 / 10 | Documented/intentional; review exceptions |
| `MethodVisibilityInspection` | 10 | 0 / 0 / 10 | Documented/intentional; review exceptions |
| `CompactReplacementInspection` | 9 | 0 / 0 / 9 | Advisory; assess per finding |
| `NotOptimalRegularExpressionsInspection` | 8 | 0 / 0 / 8 | Changed; fresh verification pending |
| `ClassConstantCanBeUsedInspection` | 6 | 0 / 0 / 6 | Advisory; assess per finding |
| `ClassnameLiteralInspection` | 6 | 0 / 0 / 6 | Advisory; assess per finding |
| `JSCheckFunctionSignatures` | 6 | 0 / 0 / 6 | Advisory; assess per finding |
| `PhpRegExpRedundantModifierInspection` | 6 | 0 / 0 / 6 | Changed; fresh verification pending |
| `PhpUnused` | 6 | 0 / 0 / 6 | Documented/intentional; review exceptions |
| `ReuseOfLocalVariableJS` | 6 | 0 / 6 / 0 | Advisory; assess per finding |
| `StringCurlyInspection` | 6 | 0 / 0 / 6 | Advisory; assess per finding |
| `PhpFunctionCyclomaticComplexityInspection` | 5 | 0 / 0 / 5 | Changed; fresh verification pending |
| `ReturnTernaryReplacementInspection` | 4 | 0 / 0 / 4 | Advisory; assess per finding |
| `XmlDefaultAttributeValue` | 4 | 0 / 4 / 0 | Editorial/generated/stale; preserve |
| `HtmlRequiredAltAttribute` | 3 | 0 / 3 / 0 | Documented/intentional; review exceptions |
| `HtmlUnknownTarget` | 3 | 0 / 3 / 0 | Documented/intentional; review exceptions |
| `NotOptimalIfConditionsInspection` | 3 | 0 / 0 / 3 | Advisory; assess per finding |
| `PhpConditionAlreadyCheckedInspection` | 3 | 0 / 0 / 3 | Open: investigate owning boundary |
| `ExceptionCaughtLocallyJS` | 2 | 0 / 2 / 0 | Advisory; assess per finding |
| `JSObjectNullOrUndefined` | 2 | 0 / 2 / 0 | Changed; fresh verification pending |
| `NullableArgumentPassedInspection` | 2 | 0 / 2 / 0 | Changed; fresh verification pending |
| `PhpClosureCanBeConvertedToFirstClassCallableInspection` | 2 | 0 / 0 / 2 | Advisory; assess per finding |
| `PhpRedundantReadonlyModifierInspection` | 2 | 0 / 0 / 2 | Advisory; assess per finding |
| `SensitiveParameterInspection` | 2 | 0 / 2 / 0 | Documented/intentional; review exceptions |
| `VirtualTypeCheckInspection` | 2 | 0 / 2 / 0 | Advisory; assess per finding |
| `DebugFunctionUsageInspection` | 1 | 0 / 1 / 0 | Documented/intentional; review exceptions |
| `HtmlDeprecatedAttribute` | 1 | 0 / 1 / 0 | Documented/intentional; review exceptions |
| `IfSimplificationOrInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `JsonEncodingApiUsageInspection` | 1 | 0 / 0 / 1 | Open: investigate owning boundary |
| `JsonStandardCompliance` | 1 | 1 / 0 / 0 | Editorial/generated/stale; preserve |
| `MarkdownTablePipeInCodeSpan` | 1 | 0 / 1 / 0 | Editorial/generated/stale; preserve |
| `ParameterDefaultsNullInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `PhpEnforceDocCommentInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `PhpIfWithCommonPartsInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `PhpLoopCanBeConvertedToArrayFilterInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `PhpPrivateFieldCanBeLocalVariableInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `PhpPropertyCanBeReadonlyInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `PhpUnnecessaryStringCastInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `ToStringCallInspection` | 1 | 0 / 1 / 0 | Changed; fresh verification pending |
| `UnnecessaryAssertionInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `UnnecessaryCastingInspection` | 1 | 0 / 0 / 1 | Advisory; assess per finding |
| `UnnecessaryLocalVariableJS` | 1 | 0 / 1 / 0 | Advisory; assess per finding |


## Scoped Statistics.php follow-up (supersedes the earlier untouched-file statement)

A subsequent, **surgically staged and independently verified** patch explicitly declares the returned `page()` array shape (to align with `events()` for PHP-LSP), documents the cursor's exception contract, and initializes `$parts` before evaluating an optionally short-circuited `preg_match()`. PHPStan and 22 statistics tests passed. These were staged as isolated HEAD-relative hunks using `git apply --cached`; the unrelated pre-existing `JsonException` import and relocation of `encodeCursor()` remain **unstaged** in the working tree and were not included in our commit. A new PhpStorm full export is still required to show whether the IDE's alias and flow findings have disappeared. The valid named-argument names of native `strtr()` are not rewritten because the secondary PHP-LSP uses inconsistent parameter stubs.

# PhpStorm inspection triage — PHP/PER coding standard

Baseline: 2026-10-08; original XML reports are preserved in `var/phpstorm-inspections/2026-10-08/`.
The underlying export is a snapshot, not automatically recalculated after changes.

Current authority: original MOI for visual formatting; PHP 8.3+ runtime, PHPStan, PHPUnit, Deptrac and live HTTP acceptance for correctness. Previous PHP-CS-Fixer findings in this dated report are historical.

## Baseline counts

| Severity | Count |
|---|---:|
| ERROR | 5 |
| WARNING | 52 |
| WEAK WARNING | 211 |
| TYPO | 328 |
| GRAMMAR_ERROR | 21 |
| STYLE_SUGGESTION | 42 |
| INFORMATION | 166 |
| **Total** | **825** |

659 findings belong to the main inspection result; another 166 are informational unreachable-reference heuristics.

## Every inspection group, with its disposition

| Inspection | Initial count | Disposition |
|---|---:|---|
| `.xml` | 166 | **Intentional or advisory.** Unreachable source heuristic cannot resolve dynamic composition root. |
| `ClassConstantCanBeUsedInspection.xml` | 6 | **Intentional or advisory.** Literal class names intentionally test architecture manifest behavior. |
| `ClassnameLiteralInspection.xml` | 6 | **Intentional or advisory.** Strings are deliberate fixtures, including types that no longer exist. |
| `CompactReplacementInspection.xml` | 3 | **Intentional or advisory.** Explicit array keys avoid capture of local variable names. |
| `DebugFunctionUsageInspection.xml` | 1 | **Intentional or advisory.** error_log is deliberate server-side diagnostic, not browser debugging. |
| `FunctionSpreadingInspection.xml` | 1 | **Code fix applied.** The same loop now uses append semantics instead of repeated array rebuilding. |
| `GlobalVariableUsageInspection.xml` | 27 | **Intentional or advisory.** Superglobals are confined to HTTP/session/test/CLI boundaries. |
| `GrazieInspection.xml` | 21 | **Intentional or advisory.** English grammar suggestions in docs/tests, not PHP faults. |
| `GrazieStyle.xml` | 42 | **Intentional or advisory.** Editorial review of architecture prose, not PSR style. |
| `HttpUrlsUsage.xml` | 5 | **Intentional or advisory.** URLs in prose are not network traffic and need separate editorial review. |
| `LSPLocalInspectionTool.xml` | 2 | **IDE configuration/false positive.** Incorrect PHP overload from extra language server: Reflection proves array/int cookie parameters. |
| `MethodShouldBeFinalInspection.xml` | 3 | **Intentional or advisory.** Test base methods remain extensible intentionally. |
| `MethodVisibilityInspection.xml` | 8 | **Intentional or advisory.** PHPUnit lifecycle methods must remain protected. |
| `NotOptimalIfConditionsInspection.xml` | 1 | **Code fix applied.** Cheap SQLite code/message checks precede driver inspection. |
| `NotOptimalRegularExpressionsInspection.xml` | 2 | **Intentional or advisory.** ASCII digits are intentional; other redundant modifier fixed. |
| `NullableArgumentPassedInspection.xml` | 1 | **Intentional / verified:** The exception constructor deliberately accepts ?Throwable as the previous cause; PHP DomainException supports a nullable previous parameter. Plugin reports a false signature. |
| `NullableTypeFormatInspection.xml` | 19 | **Intentional or advisory.** ?T is valid PHP 8 and accepted by PER coding style. |
| `OverrideMissingInspection.xml` | 1 | **Code fix applied.** Added Override attribute to the AST visitor. |
| `PhpClassCanBeReadonlyInspection.xml` | 1 | **Code fix applied.** Migrator is now readonly. |
| `PhpClosureCanBeConvertedToFirstClassCallableInspection.xml` | 1 | **Intentional / verified:** A first-class callable conversion is optional; retain a readable explicit closure when capture/behavior is clearer. |
| `PhpConditionAlreadyCheckedInspection.xml` | 1 | **Intentional or advisory.** Regression test documents intended PSR clock contract. |
| `PhpDocMissingThrowsInspection.xml` | 20 | **Intentional or advisory.** Only annotate exceptions genuinely escaping the method; review test paths before changing. |
| `PhpDocSignatureInspection.xml` | 1 | **Code fix applied.** Corrected @return from null to Node/null. |
| `PhpFullyQualifiedNameUsageInspection.xml` | 34 | **Intentional or advisory.** FQCN references in PHPDoc are explicit and valid. |
| `PhpIfWithCommonPartsInspection.xml` | 1 | **Intentional or advisory.** Session-state cleanup branches express security boundary effects. |
| `PhpLoopCanBeConvertedToArrayFilterInspection.xml` | 1 | **Intentional or advisory.** Explicit loop makes key filtering straightforward. |
| `PhpMultipleClassDeclarationsInspection.xml` | 25 | **IDE configuration/false positive.** Duplicate IDE PHP stubs for built-in types and attributes. |
| `PhpPluralMixedCanBeReplacedWithArrayInspection.xml` | 1 | **Code fix applied.** Precise array<array-key,mixed> input documentation. |
| `PhpPropertyCanBeReadonlyInspection.xml` | 1 | **Code fix applied.** Visitor constructor promoted property is readonly. |
| `PhpRedundantOptionalArgumentInspection.xml` | 2 | **Intentional or advisory.** Explicit defaults document boundaries and intent. |
| `PhpRegExpRedundantModifierInspection.xml` | 1 | **Code fix applied.** Removed unnecessary /s regex flag. |
| `PhpUndefinedClassInspection.xml` | 2 | **IDE configuration/false positive.** PhpStorm misinterprets PHPDoc nested shapes or prose; PHPStan validates types. |
| `PhpUndefinedVariableInspection.xml` | 1 | **IDE configuration/false positive.** Template receives data dynamically through Renderer require. |
| `PhpUnhandledExceptionInspection.xml` | 12 | **Intentional or advisory.** Operational errors intentionally propagate to HTTP error boundary or PHPUnit. |
| `PhpUnnecessaryCurlyVarSyntaxInspection.xml` | 11 | **Intentional or advisory.** Explicit braces keep string interpolation unambiguous. |
| `PhpUnnecessaryFullyQualifiedNameInspection.xml` | 26 | **Intentional or advisory.** Plugin readability suggestion conflicts with other FQCN inspection; defer to formatter. |
| `PhpUnused.xml` | 4 | **Intentional or advisory.** Dynamic routing and integration API may not be statically reachable. |
| `PhpUnusedParameterInspection.xml` | 1 | **Intentional or advisory.** Template data passed lexically via require is dynamically referenced. |
| `PhpVarTagWithoutVariableNameInspection.xml` | 1 | **Intentional or advisory.** Shape has named $data; IDE template heuristics fail through dynamic require. |
| `PropertyAnnotationInspection.xml` | 15 | **Intentional or advisory.** Test fixtures are nonnullable after setUp; nullable annotations would weaken the contract. |
| `ReturnTernaryReplacementInspection.xml` | 4 | **Intentional or advisory.** Readable simple ternaries are allowed by PER/PSR. |
| `SenselessParentCallEmptyInspection.xml` | 2 | **Intentional / verified:** PHPUnit base lifecycle hooks are intentionally invoked; preserve setup/teardown contract before removing parent calls. |
| `SensitiveParameterInspection.xml` | 1 | **Code fix applied.** Test data-provider password argument is marked SensitiveParameter. |
| `SlowArrayOperationsInLoopInspection.xml` | 1 | **Code fix applied.** Changed array_merge inside loop to append-only accumulation. |
| `SortUseVariablesInspection.xml` | 1 | **Code fix applied.** Ordered closure captures. |
| `SpellCheckingInspection.xml` | 328 | **Intentional or advisory.** Most terms originate from composer.lock and test fixtures; 40 verified project terms added locally. |
| `ToStringSimplificationInspection.xml` | 1 | **Code fix applied.** Stringable values use direct string cast. |
| `UnaryOperatorFormatInspection.xml` | 5 | **Intentional or advisory.** Both prefix and postfix increments are valid; no PSR rule demands a rewrite. |
| `UnnecessaryAssertionInspection.xml` | 1 | **Intentional or advisory.** Tests assert the published API contract. |
| `VirtualTypeCheckInspection.xml` | 2 | **IDE configuration/false positive.** PHPStan/runtime already validate these explicit type boundaries. |
| `XmlHighlighting.xml` | 1 | **IDE configuration/false positive.** Schema registry setting in IDE; PHPUnit 12 XSD URL is valid. |

## Project-local PhpStorm configuration

The untracked, local JetBrains files were modified **without adding them to Git**:

- .idea/dictionaries/project.xml — 40 legitimate fixture and dependency terms added, preserving the original user dictionary.
- .idea/inspectionProfiles/Project_Default.xml — only seven conflicting/nonbinding style preferences disabled: NullableTypeFormatInspection, UnaryOperatorFormatInspection, MethodShouldBeFinalInspection, PropertyAnnotationInspection, PhpFullyQualifiedNameUsageInspection, PhpUnnecessaryFullyQualifiedNameInspection, PhpUnnecessaryCurlyVarSyntaxInspection.
- All original profile, dictionary, and pre-existing PhpSession.php contents are preserved under var/manual-backups/.
- PhpUndefinedVariableInspection, PhpUnhandledExceptionInspection, security and runtime diagnostic inspections remain enabled; known PHP template and PHP stub false positives remain visible until IDE/index configuration is corrected.

These are **local project UI settings**, not production code changes. The exported reports were moved from the repository root to var/phpstorm-inspections/2026-10-08/, where they remain available for subsequent comparison; both locations are Git-ignored.

## How to reproduce and rescan

- Run composer quality, then python3 tests/Flow/live_smoke.py on PHP 8.3+.
- Configure PhpStorm CLI interpreter and language level to PHP 8.3 or later; LSP overload warnings for session_set_cookie_params are incorrect.
- In PhpStorm, rerun Inspect Code after refreshing indexes. Old XML cannot provide post-fix counts.
- Keep session/HTTP globals at their boundaries, protected PHPUnit hooks, and static test source literal strings.
- If PhpStorm does not recognize the PHPUnit XSD, register its URI in the IDE schema catalog; do not change the working PHPUnit config.
- Project dictionary records domain/dependency words; spellchecking of generated composer.lock is not a source-code defect.

The report enumerates all original inspection groups, but it does not claim a false zero-error status from a stale export.
Structural code fixes and local IDE configuration are distinct outcomes.

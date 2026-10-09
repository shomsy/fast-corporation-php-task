# Fast Corporation: root-level PhpStorm Inspect Code export triage

Baseline: 2026-10-09. All 62 XML files parsed; 61 contain problem entries. The raw root export is user-owned, untracked and unchanged.

## Counts

| Severity | Baseline |
|---|---:|
| ERROR | 46 |
| WARNING | 241 |
| WEAK WARNING | 603 |
| INFORMATION | 1224 |
| TYPO | 2927 |
| GRAMMAR_ERROR | 272 |
| STYLE_SUGGESTION | 55 |
| Total | 5368 |

## Diagnostic explanation

All 46 ERROR entries are from an additional LSPLocalInspectionTool: 40 dynamic PHP template $data usages (Renderer::render uses trusted lexical require); 2 cleanup calls to a private, owner-only, random SQLite backup temporary path; 4 conflicting PHP/LSP type stubs (Finder, session options overload, Rector PhpVersion, Statistics PHPDoc alias). These entries require context; they are not 46 proven runtime bugs.

Fixed after reviewing this export: the previously missing ext-sqlite3 runtime requirement (Composer manifest/lock/README), strict types in seven PHP templates, and the malformed PHPDoc inline shape delimiter in the statistics view. None of these changes bypasses the MOI PHP formatter or changes business routing, session/CSRF, or the backup algorithm.

Investigated and retained: CLI backup verifies owner, 0700 directory permissions, destination absence, SQLite integrity and create-only publish; unique temporary cleanup only. External LSP cannot prove path safety across those invariants. PHP-CS-Fixer Finder, PHP session cookie options, Rector PHP version API and activity row PHPDoc have independent executable/type-test evidence. No global disabling of PHP/LSP/security inspections.

Remaining non-error families include 956 Annotator INFORMATION entries, 2927 typo suggestions, 272 grammar entries, 129 Markdown labels in largely archived review evidence, and advisory warnings for exceptions, JS optional chaining and IDE stubs. No blanket rewrite of source/archive to satisfy these suggestions. Existing review documents remain immutable.

## All inspection families

| Export XML | ERROR | WARNING | WEAK | INFO | Editorial |
|---|---:|---:|---:|---:|---:|
| SpellCheckingInspection.xml | 0 | 0 | 0 | 0 | 2927 |
| Annotator.xml | 0 | 0 | 0 | 956 | 0 |
| GrazieInspection.xml | 0 | 0 | 0 | 0 | 272 |
| .xml | 0 | 0 | 0 | 268 | 0 |
| LSPLocalInspectionTool.xml | 46 | 13 | 93 | 0 | 0 |
| PhpDocMissingThrowsInspection.xml | 0 | 0 | 140 | 0 | 0 |
| MarkdownUnresolvedLinkLabel.xml | 0 | 129 | 0 | 0 | 0 |
| PhpUnhandledExceptionInspection.xml | 0 | 0 | 111 | 0 | 0 |
| GrazieStyle.xml | 0 | 0 | 0 | 0 | 55 |
| GlobalVariableUsageInspection.xml | 0 | 49 | 0 | 0 | 0 |
| PhpMultipleClassDeclarationsInspection.xml | 0 | 0 | 40 | 0 | 0 |
| NullableTypeFormatInspection.xml | 0 | 0 | 38 | 0 | 0 |
| HttpUrlsUsage.xml | 0 | 0 | 23 | 0 | 0 |
| PhpRedundantOptionalArgumentInspection.xml | 0 | 0 | 18 | 0 | 0 |
| UnaryOperatorFormatInspection.xml | 0 | 0 | 15 | 0 | 0 |
| CheckTagEmptyBody.xml | 0 | 11 | 0 | 0 | 0 |
| CompactReplacementInspection.xml | 0 | 0 | 10 | 0 | 0 |
| JSUnresolvedReference.xml | 0 | 0 | 10 | 0 | 0 |
| MethodVisibilityInspection.xml | 0 | 0 | 10 | 0 | 0 |
| NotOptimalRegularExpressionsInspection.xml | 0 | 0 | 10 | 0 | 0 |
| PhpFullyQualifiedNameUsageInspection.xml | 0 | 0 | 10 | 0 | 0 |
| JSUnusedGlobalSymbols.xml | 0 | 7 | 0 | 0 | 0 |
| PhpComposerExtensionStubsInspection.xml | 0 | 7 | 0 | 0 | 0 |
| PhpMissingStrictTypesDeclarationInspection.xml | 0 | 0 | 7 | 0 | 0 |
| ClassConstantCanBeUsedInspection.xml | 0 | 0 | 6 | 0 | 0 |
| ClassnameLiteralInspection.xml | 0 | 0 | 6 | 0 | 0 |
| OverrideMissingInspection.xml | 0 | 0 | 6 | 0 | 0 |
| PhpRegExpRedundantModifierInspection.xml | 0 | 0 | 6 | 0 | 0 |
| ReuseOfLocalVariableJS.xml | 0 | 6 | 0 | 0 | 0 |
| JSCheckFunctionSignatures.xml | 0 | 0 | 5 | 0 | 0 |
| PhpFunctionCyclomaticComplexityInspection.xml | 0 | 0 | 5 | 0 | 0 |
| PhpUnused.xml | 0 | 0 | 5 | 0 | 0 |
| ReturnTernaryReplacementInspection.xml | 0 | 0 | 5 | 0 | 0 |
| XmlDefaultAttributeValue.xml | 0 | 4 | 0 | 0 | 0 |
| NotOptimalIfConditionsInspection.xml | 0 | 0 | 3 | 0 | 0 |
| PhpConditionAlreadyCheckedInspection.xml | 0 | 0 | 3 | 0 | 0 |
| ExceptionCaughtLocallyJS.xml | 0 | 2 | 0 | 0 | 0 |
| JSObjectNullOrUndefined.xml | 0 | 2 | 0 | 0 | 0 |
| NullableArgumentPassedInspection.xml | 0 | 2 | 0 | 0 | 0 |
| PhpClosureCanBeConvertedToFirstClassCallableInspection.xml | 0 | 0 | 2 | 0 | 0 |
| PhpRedundantReadonlyModifierInspection.xml | 0 | 0 | 2 | 0 | 0 |
| PhpUnusedAliasInspection.xml | 0 | 0 | 2 | 0 | 0 |
| SensitiveParameterInspection.xml | 0 | 2 | 0 | 0 | 0 |
| VirtualTypeCheckInspection.xml | 0 | 2 | 0 | 0 | 0 |
| DebugFunctionUsageInspection.xml | 0 | 1 | 0 | 0 | 0 |
| IfSimplificationOrInspection.xml | 0 | 0 | 1 | 0 | 0 |
| JsonEncodingApiUsageInspection.xml | 0 | 0 | 1 | 0 | 0 |
| MarkdownTablePipeInCodeSpan.xml | 0 | 1 | 0 | 0 | 0 |
| ParameterDefaultsNullInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpDuplicateMatchArmBodyInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpIfWithCommonPartsInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpLoopCanBeConvertedToArrayFilterInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpPropertyCanBeReadonlyInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpUndefinedClassInspection.xml | 0 | 1 | 0 | 0 | 0 |
| PhpUnnecessaryStringCastInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpUnusedParameterInspection.xml | 0 | 0 | 1 | 0 | 0 |
| PhpVarTagWithoutVariableNameInspection.xml | 0 | 0 | 1 | 0 | 0 |
| ToStringCallInspection.xml | 0 | 1 | 0 | 0 | 0 |
| UnnecessaryAssertionInspection.xml | 0 | 0 | 1 | 0 | 0 |
| UnnecessaryCastingInspection.xml | 0 | 0 | 1 | 0 | 0 |
| UnnecessaryLocalVariableJS.xml | 0 | 1 | 0 | 0 | 0 |

## Verified gates and open verification

After fixes: composer quality PASS (PHPStan, Deptrac, Composer validation and audit, PHPDoc, 158 PHPUnit tests and 6737 assertions), 21 frontend tests PASS, Playwright and live HTTP PASS; WAL backup/restore and Composer platform requirements PASS.

**Do not subtract fixes from this baseline and claim current zero diagnostics.** Only a new full PhpStorm Inspect Code export with matching scope and refreshed indexes can measure post-fix findings. The existing IDE get_file_problems responses may time out and omit third-party LSP diagnostics.

Public deployment remains NO-GO until separately authorized and host-gated. The export is not public-production acceptance.

# Rector MAX — PHP 8.3 dry-run report

Date: 2026-10-08. Rector: **2.7.0**. Project PHP baseline: **8.3**.

## Configuration

- `rector.php` explicitly sets `PhpVersion::PHP_83` and enables version-level sets through PHP 8.3.
- Broad relevant prepared sets: dead code, code quality, coding style, type declarations, PHPDoc type declarations, privatization, named args, conditional/instanceof rules, early returns, Rector preset and PHPUnit refactors.
- PHPUnit version-specific rules are selected from installed Composer dependencies.
- All application and test areas scanned: `src/`, `runtime/`, `infrastructure/`, `bootstrap/`, `bin/`, `public/`, `tests/`, `tools/`.
- Framework-specific Laravel, Symfony, Doctrine and Carbon rules are omitted; the app uses none of these frameworks.
- Only dry-runs were performed. Rector did **not** apply code transformations.
- Naming remains manual: the general naming preset and seven identifier-renaming rules are disabled.

## Current results (manual identifier naming)

The counts are from the **second**, no-rename scan. The first scan is retained for comparison.

| Measure | Value |
|---|---:|
| Changed-file diffs | **45** |
| Errors during analysis | **0** |
| Distinct rule classes triggered | **22** |
| File-rule occurrences | **83** |
| Scan JSON size | see raw JSON files |

Rector exits with code **2** when it proposes changes in a dry-run. This is not a scan failure.

## Most frequent suggestions after disabling automatic renaming

| Rule | File occurrences |
|---|---:|
| `PreferPHPUnitThisCallRector` | 20 |
| `NewlineAfterStatementRector` | 12 |
| `RemoveUselessVarTagRector` | 9 |
| `FlipTypeControlToUseExclusiveTypeRector` | 7 |
| `DirnameDirConcatStringToDirectStringPathRector` | 5 |
| `NewlineBetweenClassLikeStmtsRector` | 5 |
| `NewlineBeforeNewAssignSetRector` | 4 |
| `AssertEmptyNullableObjectToAssertInstanceofRector` | 4 |
| `AddSeeTestAnnotationRector` | 3 |

**Verified:** 0 automatic rename suggestions in the current scan; no Rector transforms were applied.

## Manual identifier naming policy

In `rector.php`, `naming: false` disables Rector’s naming set. The following rules are additionally skipped explicitly, guarding against future preset inclusions:

- `RenamePropertyToMatchTypeRector`
- `RenameParamToMatchTypeRector`
- `RenameVariableToMatchNewTypeRector`
- `RenameVariableToMatchMethodCallReturnTypeRector`
- `RenameForeachValueVariableToMatchExprVariableRector`
- `RenameForeachValueVariableToMatchMethodCallReturnTypeRector`
- `CatchExceptionNameMatchingTypeRector`

Thus `$lifecycle`, `$identity` and all other parameter, property, local and catch-variable names remain the author’s choice. Named arguments make implicit parameter renaming especially unsafe.

## Must-review changes before acceptance

1. Identifier-renaming rules are now **disabled**; re-verify the zero-renaming invariant on Rector upgrades.
2. `RemoveUnusedPublicMethodParameterRector` suggests dropping the `$data` argument of `Renderer::render()`, even though the PHP template reads it via lexical scope in `require`. This is a **dangerous false positive**.
3. `RemoveUselessVarTagRector` suggests removing `@var` tags required by the project's complete-DocBlock enforcement. Do not apply blindly.
4. Formatting and `self::assert*` → `$this->assert*` suggestions may conflict with established PHP-CS-Fixer and PHPUnit policy. Prefer the actual quality gate.
5. Some exception and `null`/`instanceof` rewrites could change observable error behavior. Review with tests before accepting.

## Commands and evidence

```bash
composer rector:scan
php vendor/bin/rector process --dry-run --no-progress-bar --output-format=json --memory-limit=1G > var/rector/max-php83-no-rename-scan.json
composer quality
python3 tests/Flow/live_smoke.py
```

Raw evidence: `var/rector/max-php83-no-rename-scan.json` (current) and `var/rector/max-php83-scan.json` (previous), with stderr logs alongside; `var/rector/` is Git-ignored.

**Enforcement:** Rector MAX is an advisory scan until suggestions are triaged. It is deliberately not part of `composer quality`, because it currently proposes contract-breaking and PHPDoc-policy-breaking changes. Project formatting is strictly enforced by PHP-CS-Fixer, plus PHPStan, Deptrac and tests.

## Global PhpStorm MOI style

The active global scheme `~/.config/JetBrains/PhpStorm2026.3/codestyles/MOI.xml` has been aligned with PER/PSR, with EditorConfig enabled and conflicting indentation, alignment, return-type colon, `while` spacing, unary-not spacing and one-line class/method options disabled. The previous MOI scheme is backed up under `var/manual-backups/`. Reopen or reload PhpStorm settings to pick up external XML edits.

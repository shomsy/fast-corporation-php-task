# MOI — the sole PHP code formatter

The owner's **original global PhpStorm MOI scheme is authoritative** for visual PHP style. Neither Rector, PHP-CS-Fixer, nor PER/PSR formatting presets may override it. Never edit MOI merely to satisfy another formatter.

## Practical workflow

1. Make code changes and review Rector dry-run suggestions. Rector keeps `codingStyle: false` and `naming: false`; it does not format or rename identifiers automatically.
2. In the running PhpStorm, format PHP files using **Code → Reformat Code** (`Ctrl+Alt+L`). The project and global scheme are both MOI.
3. For **IDE commits**, enable **Commit tool window → Commit Options → Commit Checks → Reformat code**. It formats changed code using the project scheme before committing. **Optimize imports** is an optional separate IDE commit check.
4. Optionally enable **Settings → Tools → Actions on Save → Reformat code** with scope **Changed lines** and PHP files. This uses MOI while the IDE is running.
5. Run `composer quality`, then `python3 tests/Flow/live_smoke.py`. Commit only after tests pass.

## Exactly what is enforced

`composer quality` verifies Composer configuration and PSR-4 autoloading, dependency advisories, PHPDoc structural contracts, PHPStan, Deptrac and PHPUnit. It **does not run PHP-CS-Fixer** and **does not pretend to validate MOI formatting** in CI. PhpStorm itself is the only formatting authority.

**Git CLI limitation:** PhpStorm's built-in **Reformat code on commit** runs when committing **from PhpStorm**, not when executing `git commit` directly in a terminal. Before CLI commits, format changed files in the running IDE first.

PhpStorm also ships `~/.local/opt/phpstorm-eap/bin/format.sh`, which supports a `-s` scheme file and a `-d` dry-run. JetBrains documents that this **cannot run concurrently with an active IDE instance**, so it is not installed as a Git pre-commit hook.

**External formatter setting:** In **Settings → PHP → Quality Tools → External Formatters**, leave PHP CS Fixer / PHP_CodeSniffer disabled as external formatters, otherwise PhpStorm's Reformat Code may run the external tool instead of MOI. The project-specific PHP-CS-Fixer validation inspection has also been disabled.

**Schemes:** `~/.config/JetBrains/PhpStorm2026.3/codestyles/MOI.xml` is the unchanged, original scheme restored from `var/manual-backups/PhpStorm2026.3-MOI.xml.before-import-policy`. Project `.idea/codeStyles/codeStyleConfig.xml` selects MOI. Nothing in this workflow edits MOI.

**EditorConfig:** the original MOI sets EditorConfig integration to `false`. The repository `.editorconfig` remains a baseline for other editors but must not supersede MOI in PhpStorm.

## Why not two formatters?

MOI has personal alignment, whitespace, line-breaking and PHPDoc decisions that are not all expressible in PHP-CS-Fixer. Running both causes perpetual reformatting. Rector is a refactoring analyzer; it is deliberately not the final visual formatter.

**Source of truth:** MOI is personal and can be adjusted one setting at a time by the owner. We preserve the original values.


## New MOI PHP syntax contract (2026-10-09)

Original MOI.xml still owns whitespace, alignment, line breaks and PHPDoc layout. The two added syntax rules are independent of options in the IDE formatter:

1. In PHP type declarations and PHPDoc use TYPE|null, never ?Type. This does not ban nullsafe ?->, coalescing ?? or ternary expressions.
2. Standalone counter updates and for-loop steps use postfix $i++ or $i--, not prefix ++$i or --$i. If a prefix expression has its result consumed, preserve its semantics via a deliberate refactor rather than blind operator replacement. The static gate rejects that prefix usage.

PHP Hammer 2026.1.2 inspections NullableTypeFormatInspection and UnaryOperatorFormatInspection are enabled at ERROR severity in the global Default and Fast Corporation project profiles. Reload the IDE inspection profile after external editing. Existing projects using their own profile may need to select the global default or enable the same rules in that project profile.

The repository's composer moi:check uses nikic/php-parser to examine PHP and PHPDoc. composer moi:self-test ensures invalid examples fail and nullsafe and coalescing operators remain accepted. Both run in composer quality and CI on PHP 8.3, 8.4 and 8.5. CI enforcement does not automatically extend to other repositories.


## 2026-10-09 full-repository PHP code style audit

The running PhpStorm 2026.3 EAP IDE, through its native MCP `reformat_file` action, applied the original **MOI** code style to all **114 Git-tracked PHP files**. **97** files changed presentation. A second full MOI reformat yielded **zero further file changes** (idempotence). No PHP-CS-Fixer or Rector reformatting was applied and the global `MOI.xml` was not modified.

The formatter legitimately wrapped PHPDoc annotations, including long `array{...}` types; types were reorganized to remain understandable to PHPStan, and the structural PHPDoc checker now recognizes wrapped parameter types. Formatting mixed PHP/HTML templates also altered insignificant source whitespace around navigation text; HTTP navigation assertions now accept whitespace between visible words without losing presence/absence checks.

**Evidence:** `composer quality` PASS (PHPStan, Deptrac, Composer audit, PHPDoc, MOI syntax checker, 160 PHPUnit tests / 6,745 assertions); 21 frontend tests PASS; Playwright browser acceptance PASS; live HTTP acceptance PASS; SPA/SSR route cutover/rollback PASS. An AST comparison against the pre-format commit across all 97 changed PHP files found no unexpected PHP-code differences; only the intentional PHPUnit HTML-text assertions and PHPDoc validator logic changed. Inline HTML spacing remains presentation-only and is covered by rendered-page acceptance tests.

**Enforcement boundary:** `composer quality` checks the separately agreed `Type|null` and postfix syntax rules, but **does not enforce the full visual MOI style in CI**. For full visual formatting, use the IDE's MOI `Reformat Code` action. Other-language files were not reformatted, because MOI is the PHP style scheme.

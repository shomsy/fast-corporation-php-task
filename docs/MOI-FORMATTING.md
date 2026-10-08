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

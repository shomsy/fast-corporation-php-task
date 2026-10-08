# PHP 8.3 Named Arguments and Language Features

Status: implemented across the project; behavior-preserving refactor.

## Named arguments

Call sites in business modules, HTTP adapters, bootstrap, runtime, infrastructure, CLI, views and tests use named arguments wherever the callee exposes a stable, named PHP parameter contract. In particular: constructors, use-case entry points, PDO/PDOStatement calls, date/time calls, response factories, HTML escaping callbacks, and test fixtures.

**Necessary exceptions**:

- **PHPUnit assertions and lifecycle expectations**: PHPUnit 12 declares `@no-named-arguments` for assertions, `fail()`, and `expectException()`. Keep those calls positional; PHPStan enforces this vendor contract.
- **Dynamic callables**, including the generic router's `$handler($request)`: the caller does not own the callback's parameter names. Naming that argument could break legitimate compatible callbacks.
- **Variadic and argument-unpacked calls** where no safe one-to-one mapping to unique parameter names exists, such as format strings with runtime value lists.
- **PHP language constructs** (e.g. `isset`, `echo`, `require`) are not named-argument call sites.

This is intentionally an explicit convention for this codebase. Parameter names form part of the callable interface for named invocations; rename them only with the same care as a public API change.

## PHP 8.x features (PHP 8.3 baseline)

- Named arguments (8.0), `match` expressions (8.0), constructor property promotion (8.0).
- Union/mixed types and typed properties; backed enums (8.1), readonly classes (8.2), typed class constants (8.3).
- `#[\SensitiveParameter]` (8.2) on password-carrying entry points, suppressing secret values from exception traces.
- `#[\Override]` (8.3) on genuine inherited/interface method implementations, including PSR-20 clocks and PHPUnit lifecycle hooks.
- PSR-20 standard clock and strict types; do not replace proven straightforward code with syntax without a use case.

PHP 8.4/8.5-only features are deliberately excluded because `composer.json` currently supports PHP **8.3 and later**.

## Acceptance

Use `composer quality`, `python3 tests/Flow/live_smoke.py`, and GitHub Actions on PHP 8.3, 8.4 and 8.5 to validate exactly the submitted commit.

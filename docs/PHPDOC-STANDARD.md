# PHPDoc / DocBlock standard — Fast Corporation PHP Task

**Applies to:** PHP 8.3–8.5, all business owners, HTTP adapters, infrastructure, entry points, templates, and tests.

**Purpose:** Every declaration should communicate its contract to PhpStorm, PHPStan, phpDocumentor, and the next maintainer—not merely repeat its name. This policy follows the supplied PHP Architect video, the official PHPDoc tag reference, and PHPStan's type system.

## Mandatory coverage

1. **Classes, interfaces and enums:** DocBlock summary, domain/owner intent, and `@package`. Types declared in an owner's `module.php` public manifest carry `@api`. Add `@see` when pointing to a genuine related API or contract.
2. **Methods, including private and test methods:** A behavioral summary; **every declared parameter** has `@param Type $name` with a meaningful description; **every method except the constructor** has `@return Type`, including `void`. Use `@throws Type` for documented exceptions that genuinely escape the method. Keep the success and failure effects understandable from the summary.
3. **Constructors:** `@param` covers each argument, including promoted properties. Never invent a constructor return value.
4. **Explicit properties and constants:** DocBlock summary and `@var`. Use `list<T>`, `array<Key,Value>`, and shapes instead of unspecified `array` where possible. Enum cases receive useful descriptions but **not** false property `@var` tags.
5. **Views:** File-level explanation and `@var array{...} $data`, plus the named escaping callback `@var callable(mixed $value): string $escape`. The shape is part of the agreement between controller and template.
6. **Entry points and module manifests:** A file-level explanation of their trust boundary, side effects and purpose. Configuration arrays retain precise `@var` shapes.
7. **Generics and structural types:** Use `@template`, `@phpstan-type`, `@phpstan-import-type`, `list<T>`, `array{key: Type}`, precise callable signatures, and non-empty/literal-string types **only when the code actually guarantees them**.

## Examples from this codebase

### Named query result contract

```php
/**
 * Return one bounded page of immutable activity history.
 *
 * @param ?string $date UTC YYYY-MM-DD filter, or null for all dates.
 * @param ?string $user Case-insensitive actor-label filter.
 * @param ?string $action Canonical action value.
 * @param int $page 1-based page number.
 * @return ActivityPageResult
 * @throws InvalidArgumentException If a supplied filter or page is invalid.
 * @throws PDOException If the SQLite read fails.
 */
public function page(?string $date = null, ?string $user = null, ?string $action = null, int $page = 1): array
```

The alias `ActivityPageResult` is declared at class level with `@phpstan-type` and expands to a concrete `array{events: list<ActivityEventRow>, hasNext: bool}`.

### Generic transaction callback

```php
/**
 * Commit callback results or roll back and rethrow the underlying failure.
 *
 * @template T
 * @param callable(): T $operation A computation executed inside a transaction.
 * @return T Successful result after commit.
 * @throws Throwable On failure; the original error remains visible.
 */
public function run(callable $operation): mixed
```

### Typed HTML view contract

```php
/** @var array{csrfToken: string, username: string, error: string} $data */
/** @var callable(mixed $value): string $escape */
```

These tags express structure the PHP native `array` and `callable` types cannot supply.

## Namespaces and imports — mandatory

Use **short class, interface, trait, enum, exception and attribute names** with explicit `use` imports at file level, in native PHP code and simple PHPDoc annotations (`@throws`, `@see`, `@param`, `@return`, `@var`). Do not retain `\Vendor\Package\ClassName` inline when an unambiguous import can be generated.

For example, prefer:

```php
use PDOException;
use SensitiveParameter;

/** @throws PDOException If storage fails. */
function changePassword(#[SensitiveParameter] string $password): void
{
    // Application behavior belongs here.
}
```

rather than repeating fully qualified class names such as `\PDOException` or `\SensitiveParameter` at each use site.

The **mandatory, machine-enforced** rule is `fully_qualified_strict_types: {import_symbols: true}` and `global_namespace_import: {import_classes: true}`, with `ordered_imports` and `no_unused_imports` in `.php-cs-fixer.dist.php`. These cover project PHP sources **and tooling** via `composer quality`.

**Exceptions:** string values and executable PHP source fixtures whose precise FQCN literal is under test; deliberate alias/name collisions; language syntax, functions and constants that are not classes; advanced PHPStan shape aliases not understood by the simple PHPDoc tag fixer. Never rename an identifier merely for the appearance of a shorter spelling, and never change runtime behavior.

This is a **project preference**, not a PHP-FIG requirement that fully qualified names be banned. The original global MOI profile is authoritative and must not be altered merely to satisfy import tooling.

## What must **not** be added artificially

- `@property`, `@property-read`, `@property-write`, `@method`: use only for real magic accessors/methods. This project uses native declarations, not Eloquent-style dynamic models.
- `@deprecated`: only when an API is genuinely deprecated and a replacement exists.
- `@todo`: only for an actual tracked follow-up, not to decorate complete code.
- `@since`, `@version`, `@author`: only with verified project release/ownership metadata. Do not fabricate provenance.
- `@throws`: never claim that an exception escapes if it is swallowed or converted at that boundary.
- `@inheritdoc`: use only when real inherited documentation should be reused. Explicit method documentation and PHP's `#[\\Override]` attribute are preferable when the contract has local constraints.
- `@var` on enum cases or redundant magic property declarations: PHPStan may reject or misinterpret them.

## Machine checks

- `composer docs:check` visits every named class, enum, method, explicit property, and class constant. The gate requires DocBlocks, all `@param` names, every applicable `@return`, and `@var` on explicit storage/constant declarations.
- `composer stan` validates the actual generic/array-shape type semantics against code.
- `composer quality` includes the PHPDoc gate alongside PSR-4, formatting, PHPStan, Deptrac, and behavioral tests.
- `python3 tests/Flow/live_smoke.py` ensures no runtime behavior changed.

**Known limitation:** structural coverage cannot independently prove every `@throws` description or narrative intent; those are reviewed against code paths and error behavior. Inline anonymous classes have no standalone named type contract; their members are checked, while surrounding code documents the binding.

## References

- Scott Keck-Warren, *Super Charging Development with DocBlock*, PHP Architect: https://www.youtube.com/watch?v=LQr99R_U_hA
- phpDocumentor, PHPDoc tag reference: https://docs.phpdoc.org/guide/references/phpdoc/tags/
- PHPStan, PHPDoc types and local aliases: https://phpstan.org/writing-php-code/phpdoc-types
- *Code Complete*, Steve McConnell (clarity and information hiding).
- *A Philosophy of Software Design*, John Ousterhout (documentation should reduce cognitive load, not narrate syntax).
- *Refactoring*, Martin Fowler (preserve observable behavior when improving internals).

# PHP Conventions

## Write against PHP 8.4

Ensure new code uses syntax that is not deprecated in PHP 8.4. Always use **explicit** nullable types — `?Type` or `Type|null`, never an implicit nullable from a `null` default:

```php
// Wrong — implicit nullable, deprecated in 8.4
public function __construct(MockInterface $service = null) {}

// Right
public function __construct(?MockInterface $service = null) {}
```

## Value objects / DTOs implement `Arrayable` + `JsonSerializable`

Any new value object or DTO (a `final class` that wraps data rather than a service with behaviour) must implement:

- `Illuminate\Contracts\Support\Arrayable` — `toArray(): array` returning the canonical array shape
- `JsonSerializable` — `jsonSerialize(): array`, usually `return $this->toArray();`

Consistent with existing VOs like `app/Services/Attendance/Filters.php`. This keeps VOs interoperable with Eloquent, Resource responses, JSON encoding, and `collect()->toArray()`.

## Don't call `->value` on enums inside Resource arrays

PHP's `json_encode` and Laravel's HTTP layer serialize backed enums to their value automatically at the final serialization boundary. A Resource's `toArray()` returns a plain PHP array internally, so the correct contract at that layer is the enum **case** itself, not its string value.

When asserting on resource output via `->resolve()` in unit tests, compare against the enum case (e.g. `SignupStatus::Confirmed`), not `SignupStatus::Confirmed->value`. Only use `->value` when explicitly building a string for storage, display, or a non-JSON context.

## Union-Find must be iterative

PHP recursive closures that capture themselves by reference (`&$find`) cause segmentation faults (signal 11) under PHPUnit when called deeply. Always implement Union-Find with an iterative find + path-compression loop:

```php
$find = function (string $id) use (&$parent): string {
    $root = $id;
    while ($parent[$root] !== $root) { $root = $parent[$root]; }
    while ($parent[$id] !== $root) { $next = $parent[$id]; $parent[$id] = $root; $id = $next; }
    return $root;
};
```

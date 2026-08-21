---
name: writing-tests
description: Use when writing, creating, or editing PHPUnit test classes in this Laravel project — covers TDD cycle, test structure, attribute notation, directory conventions, database traits, factory usage, and helper method placement
---

# Writing Tests

**REQUIRED SUB-SKILL:** Invoke `superpowers:test-driven-development` before writing any implementation code.

**REQUIRED SUB-SKILL:** Invoke `spatie-laravel-php-standards` for all PHP style and conventions.

## Quick Reference

| Rule                 | Detail                                                                                            |
| -------------------- | ------------------------------------------------------------------------------------------------- |
| Test annotation      | `#[Test]` attribute — no `test_` prefix on method names                                           |
| Unit test location   | Mirrors `app/`: `app/Foo/Bar.php` → `tests/Unit/Foo/BarTest.php`                                  |
| Unit test isolation  | No `RefreshDatabase`, no factories, no real routes — mock every collaborator                      |
| Request object       | Never mock `Illuminate\Http\Request` — construct it with `Request::create()`/`->create()` instead |
| Database interaction | Feature tests only; must use `RefreshDatabase` trait                                              |
| Permissions in test  | Clear Spatie cache in `setUp()`                                                                   |
| Model creation       | Use factories; check states before manual setup                                                   |
| Faker style          | Match existing file convention (`$this->faker` or `fake()`)                                       |
| Create test file     | `sail artisan make:test {Name}` (feature) or `--unit` (unit)                                      |
| Helper methods       | Bottom of class, after all `#[Test]` methods                                                      |
| Section separators   | `// ==================== label ====================` — lowercase, names the subject under test |
| Default test type    | Feature test unless testing a single class in isolation                                           |

## Test Method Notation

Use the `#[Test]` attribute. Method names have **no** `test_` prefix.

```php
use PHPUnit\Framework\Attributes\Test;

class ExampleTest extends TestCase
{
    #[Test]
    public function it_does_something(): void
    {
        // ...
    }
}
```

Not `test_it_does_something()` — that's wrong.

## Directory Structure

Unit tests mirror the `app/` path. Pass the path **without** the `Unit/` prefix to Artisan:

```bash
# For app/Services/Blizzard/CharacterService.php
vendor/bin/sail artisan make:test --unit Services/Blizzard/CharacterServiceTest
# Creates: tests/Unit/Services/Blizzard/CharacterServiceTest.php
```

Feature tests live under `tests/Feature/` and are the default for controller, route, and integration tests.

## Unit Test Isolation

`tests/Unit/**` tests must isolate the class under test completely. No `RefreshDatabase`, no factory `->create()`, no real Eloquent rows, no real registered app routes (from `web.php`/`api.php`), and no fake routes registered ad-hoc in the test either. This applies even when reaching for real infrastructure would be more convenient than mocking a collaborator properly.

When an instruction says "mock X," that means mock exactly X — nothing else gets to be real just because it's inconvenient to mock. Before writing the test, identify every collaborator the class under test touches (model params, `$request->route()`, container-resolved helpers like `redirect()`/`route()`/`url()`) and mock or stub each one directly at that seam:

```php
// Wrong — reaches for real infra to sidestep mocking route()
Route::get('/items/{item}', fn () => null)->name('items.show');
$request = Request::create('/items/foo');

// Right — mock only the named collaborator, stub the seam it needs
$item = Mockery::mock(Item::class);
$item->shouldReceive('getRouteKey')->andReturn('foo');
```

**Never mock `Illuminate\Http\Request`.** Laravel's own docs warn against mocking the `Request` facade, and the same reasoning applies to the `Request` object: it's a value object populated with real input, not a service with behaviour worth stubbing. Build a real instance instead:

```php
// Wrong — mocking Request hides real query/route/header behaviour behind fake expectations
$request = Mockery::mock(Request::class);
$request->shouldReceive('route')->andReturn($fakeRoute);

// Right — construct a real Request with the input the test needs
$request = Request::create('/items/foo', 'GET');
```

If a global helper (e.g. `redirect()->route()`) can't be reached through the primary mock alone, that's a signal to ask how the seam should be isolated — not license to substitute real infrastructure (DB, router, container singletons).

If the class under test cannot be isolated without touching real infrastructure, it likely doesn't belong in `tests/Unit/` — write a feature test instead.

**Testing middleware:** if the middleware resolves routes or redirects (`redirect()->route(...)`, `$request->route()->getName()`), read the sibling `middleware-feature-tests.md` before writing the test — it belongs in `tests/Feature/`, not `tests/Unit/`, and has its own gotchas around mocking `Route` vs `Request` and route-name resolution.

## Database Tests (Feature Tests)

Any feature test that reads from or writes to the database **must** use `RefreshDatabase`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class RosterTest extends TestCase
{
    use RefreshDatabase;
}
```

When the test class exercises roles or permissions, also clear the Spatie permission cache in `setUp()`:

```php
use Spatie\Permission\PermissionRegistrar;

protected function setUp(): void
{
    parent::setUp();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}
```

## Creating Test Files

```bash
# Feature test (default)
vendor/bin/sail artisan make:test Characters/RosterTest

# Unit test
vendor/bin/sail artisan make:test --unit Services/CharacterServiceTest
```

Most tests should be feature tests. Use unit tests only for testing a single class in isolation (value objects, data transfer objects, pure logic).

## Model Creation in Tests

Always use factories. Check for existing factory states before setting attributes manually:

```php
// Check factory states first
$officer = User::factory()->officer()->create();

// Only fall back to manual setup if no state exists
$user = User::factory()->create(['rank' => 'Officer']);
```

## Faker Usage

Follow the convention already used in the file you're editing — either `$this->faker` or `fake()`:

```php
// If the file uses $this->faker:
$this->faker->word()
$this->faker->randomDigit()

// If the file uses fake():
fake()->word()
fake()->randomDigit()
```

## Test Groups

Every test class **must** have at least one domain `#[Group]` attribute. Behaviour groups are optional and applied at method level where useful.

Group names and definitions are the canonical vocabulary in `test-groups-vocabulary.md` (sibling file). Do not invent new group names.

```php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('raiding')]
class PhaseTest extends TestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_shows_a_phase(): void
    {
        // ...
    }

    #[Test]
    #[Group('authorization')]
    public function it_denies_guests(): void
    {
        // ...
    }
}
```

- **Domain groups** go on the **class**. Add more than one only when the subject genuinely spans domains.
- **Behaviour groups** go on **methods**. Add them only where the behaviour axis is selective — not on every method.
- Run a group with: `vendor/bin/sail artisan test --group=<name> --compact`

## Section Separators

Test classes with **11 or more** `#[Test]` methods must divide themselves into
sections. Classes with 10 or fewer may, but need not.

The canonical separator is a single line: exactly 20 `=`, one space, a lowercase
label, one space, exactly 20 `=`. It sits at one indent level inside the class
body with exactly one blank line either side.

```php
    // ==================== toArray ====================

    #[Test]
    public function to_array_exposes_the_body(): void
    {
        // ...
    }
```

**Label rules:**

- Lowercase, always.
- Name the **method or subject under test** — `rules`, `toArray`, `resolveBaseUrl`,
  `index`, `store`. For controller feature tests, use the controller action.
- Do **not** name a behaviour axis (`validation`, `authorization`, `happy path`).
  Behaviour is already encoded by method-level `#[Group]` attributes, and a label
  that repeats it adds nothing. A compound label is fine when the action leads:
  `store — validation`.
- Drop a trailing "tests" — the class is already a test class.
- The helper block at the bottom of the class is introduced by a separator
  labelled exactly `helpers`.

**Placement:** a separator never appears as the first line of the class body. The
first group of methods is unlabelled; the first separator marks the first change
of subject. Aim for 2–6 sections per class — one section per method is noise.

Do not use the older box-banner form (`// ====` / `// Label` / `// ====`) or a
bare `// Label` line. Both have been migrated away.

This rule is machine-enforced by `tests/Unit/TestSuiteDocumentationStandardTest.php`,
which fails if any separator deviates from the canonical format, if a separator
lacks its surrounding blank lines, or if a class with 11+ tests has no sections.

## Helper Methods

Place all private/protected helper methods at the **bottom** of the test class, after all `#[Test]` methods:

```php
class RosterTest extends TestCase
{
    #[Test]
    public function it_shows_the_roster(): void
    {
        $this->fakeRosterWithMembers(3);
        // ...
    }

    // ↓ Helpers at the bottom

    private function fakeRosterWithMembers(int $count): void
    {
        // ...
    }
}
```

---
name: writing-tests
description: Use when writing, creating, or editing PHPUnit test classes in this Laravel project — covers TDD cycle, test structure, attribute notation, directory conventions, database traits, factory usage, and helper method placement
---

# Writing Tests

**REQUIRED SUB-SKILL:** Invoke `superpowers:test-driven-development` before writing any implementation code.

**REQUIRED SUB-SKILL:** Invoke `spatie-laravel-php-standards` for all PHP style and conventions.

## Quick Reference

| Rule | Detail |
|------|--------|
| Test annotation | `#[Test]` attribute — no `test_` prefix on method names |
| Unit test location | Mirrors `app/`: `app/Foo/Bar.php` → `tests/Unit/Foo/BarTest.php` |
| Database interaction | Must use `RefreshDatabase` trait |
| Permissions in test | Clear Spatie cache in `setUp()` |
| Model creation | Use factories; check states before manual setup |
| Faker style | Match existing file convention (`$this->faker` or `fake()`) |
| Create test file | `sail artisan make:test {Name}` (feature) or `--unit` (unit) |
| Helper methods | Bottom of class, after all `#[Test]` methods |
| Default test type | Feature test unless testing a single class in isolation |

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

## Database Tests

Any test that reads from or writes to the database **must** use `RefreshDatabase`:

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
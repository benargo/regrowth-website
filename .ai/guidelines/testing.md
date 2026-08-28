# Testing

## Run tests via `sail test`

Always use `vendor/bin/sail test` (which wraps `php artisan test`). Never invoke PHPUnit directly (`vendor/bin/sail php vendor/bin/phpunit`) — the Artisan wrapper applies the project's full bootstrap and matches CI output.

## Test flags

- Always include `--display-phpunit-notices` so notices surface and can be fixed. When a notice appears, fix it — `createMock()` with no expectations should be `createStub()`.
- Never combine `--parallel` with `--compact`; `--parallel` silently drops `--compact`.
- Standard run: `vendor/bin/sail test --display-phpunit-notices`
- Filtered run: `vendor/bin/sail test --compact --display-phpunit-notices --filter=...`

## `make:test` naming

Pass only the path relative to the test type's base directory — Artisan prepends `tests/Unit/` for `--unit` and `tests/Feature/` otherwise.

- Correct: `make:test --unit "Services/Foo/BarTest"` → `tests/Unit/Services/Foo/BarTest.php`
- Wrong: `make:test --unit "Unit/Services/Foo/BarTest"` → doubled `Unit/Unit/` prefix

## Use `--env=testing` for manual Artisan commands

The dev database (`laravel`) holds real guild data synced from live sources. Always pass `--env=testing` to any manual `tinker`, `db:seed`, or `migrate` run so it targets the separate `laravel_testing` database:

```
vendor/bin/sail artisan tinker --env=testing
vendor/bin/sail artisan db:seed --env=testing
```

A `.env.testing` file at the repo root points to `DB_DATABASE=laravel_testing`. Never call factory `create()` or run seeders without `--env=testing` unless deliberately seeding dev data.

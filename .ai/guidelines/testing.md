# Testing

## Run tests via `sail test`

Always use `vendor/bin/sail test` (which wraps `php artisan test`). Never invoke PHPUnit directly (`vendor/bin/sail php vendor/bin/phpunit`) — the Artisan wrapper applies the project's full bootstrap and matches CI output.

## Test flags

- Always include `--display-phpunit-notices` so notices surface and can be fixed. When a notice appears, fix it — `createMock()` with no expectations should be `createStub()`.
- Never combine `--parallel` with `--compact`; `--parallel` silently drops `--compact`.
- Standard run: `vendor/bin/sail test --display-phpunit-notices`
- Filtered run: `vendor/bin/sail test --compact --display-phpunit-notices --filter=...`

## Run tests from a git worktree via `bin/wt`

Inside a git worktree, run tests with `bin/wt php artisan test` instead of `vendor/bin/sail test`. `bin/wt` executes the command in the main checkout's Sail container, in the worktree's directory, against a dedicated `testing_<worktree-name>` database, so concurrent runs in different worktrees don't overwrite each other.

- Worktrees must live under the main checkout (Claude Code's default `.claude/worktrees/<name>`); the container cannot see worktrees elsewhere.
- Never run `vendor/bin/sail up` from a worktree — it starts a second stack that collides with the main one's ports.
- Worktree run: `bin/wt php artisan test --compact --display-phpunit-notices --filter=...`
- Other commands work the same way: `bin/wt composer install`, `bin/wt npm run build`.
- `bin/wt prune` drops testing databases whose worktree has been removed.

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

## Use the Manual Login Routes for browser testing

When driving the app through a browser automation tool (Playwright, claude-in-chrome, etc.), authenticate via the Manual Login Routes defined in `routes/auth.php` instead of going through Discord OAuth.

- `GET login/local` (`login.local`) renders the local login form.
- `POST login/local` (`login.local.store`) logs the user in.

These routes are only registered when `app()->environment(['local', 'testing'])`, guarded by the `env:local,testing` middleware, so they're unavailable in production and never reach Ziggy's route list there.

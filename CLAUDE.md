<laravel-boost-guidelines>
=== .ai/comments rules ===

# Comments & Docblocks

## Describe *what*, not *why*

Comments and docblocks (PHP and JS alike) describe **what** the code does — its behaviour, inputs, outputs, and side effects. Do not explain **why** it was written that way: design rationale, rejected alternatives, bug history, and workarounds belong in the commit message or PR description, not the source.

```php
// Wrong — explains the reasoning
/**
 * We cache this for an hour because the Blizzard API rate-limits us
 * and the data rarely changes.
 */

// Right — describes the behaviour
/**
 * Fetch the playable class media, cached for one hour.
 */
```

=== .ai/php-conventions rules ===

# PHP Conventions

## Write against PHP 8.4

Ensure new code uses syntax that is not deprecated in PHP 8.4. Always use **explicit** nullable types — `?Type` or `Type|null`, never an implicit nullable from a `null` default:

```php
// Wrong — implicit nullable, deprecated in 8.4
public function __construct(MockInterface $service = null) {}

// Right
public function __construct(?MockInterface $service = null) {}
```

=== .ai/testing rules ===

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

## Use `--env=testing` for manual Artisan commands

The dev database (`laravel`) holds real guild data synced from live sources. Always pass `--env=testing` to any manual `tinker`, `db:seed`, or `migrate` run so it targets the separate `laravel_testing` database:

```
vendor/bin/sail artisan tinker --env=testing
vendor/bin/sail artisan db:seed --env=testing
```

A `.env.testing` file at the repo root points to `DB_DATABASE=laravel_testing`. Never call factory `create()` or run seeders without `--env=testing` unless deliberately seeding dev data.

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `vendor/bin/sail composer show --direct` to list direct dependencies with versions, or `vendor/bin/sail composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

=== saloonphp/laravel-plugin/core rules ===

## SaloonPHP

- PHP library for building API integrations and SDKs.
- Documentation: https://docs.saloon.dev
- Check `composer.json` for version (v2, v3 or v4). Use `web-search` tool for latest docs before implementing.
- If the project uses v3, inform the user that v3 is affected by three published security issues and prefer upgrading to v4 before adding new Saloon work, unless the user explicitly needs a v3-compatible change.
- Always use Artisan commands to generate SaloonPHP classes: `vendor/bin/sail artisan saloon:connector`, `vendor/bin/sail artisan saloon:request`, `vendor/bin/sail artisan saloon:response`, `vendor/bin/sail artisan php artisan saloon:plugin`, `vendor/bin/sail artisan saloon:auth`.- Documentation: `https://docs.saloon.dev`
- IMPORTANT: Activate saloon-development skill when working with SaloonPHP-related tasks.

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

=== spatie/boost-spatie-guidelines/core rules ===

# Project Coding Guidelines

- This codebase follows Spatie's Laravel & PHP guidelines.
- Always activate the `spatie-laravel-php-standards` skill whenever writing, editing, reviewing, or formatting Laravel or PHP code.

</laravel-boost-guidelines>

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>" --budget 8000` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
